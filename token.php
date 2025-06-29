<?php
require_once 'vendor/autoload.php'; // Certifique-se de incluir o autoload do Composer

use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\Key;

// Configura o fuso horário global para garantir que todos usem o mesmo fuso
date_default_timezone_set('America/Sao_Paulo');

// Caminho para o arquivo de chave fora do diretório público
$keyPath = __DIR__ . '/../jwt_key.txt'; // Ajuste o caminho conforme necessário

// Carrega a chave do arquivo externo
if (!file_exists($keyPath)) {
    die('Erro: Arquivo de chave JWT não encontrado.');
}

$key = trim(file_get_contents($keyPath)); // Remove espaços ou quebras de linha extras

$project = 'jbl-faturamento';

function criaToken($userId, $nivel, $nome, $regime, $estado) {
    global $key;
    global $project;

    try {
        $issuedAt = time();
        $expirationTime = $issuedAt + 3600; // O token expira em 1 hora
        $createdAt = new DateTime('now'); // Usará o fuso horário configurado globalmente

        $payload = [
            'sub' => $userId,
            'nivel' => $nivel,
            'exp' => $expirationTime,
            'createdAt' => $createdAt->format('Y-m-d H:i:s'), // Data de criação no formato desejado
            'project' => $project,
            'nome' => $nome,
            'regime' => $regime,
            'estado' => $estado,
        ];

        // Gera o token com a chave carregada
        $token = JWT::encode($payload, $key, 'HS256');

        // Retorna uma mensagem de sucesso junto com o token
        return [
            'status' => 'sucesso',
            'token' => $token
        ];

    } catch (Exception $e) {
        // Se ocorrer algum erro, retorna o status de erro e a mensagem
        return [
            'status' => 'erro',
            'mensagem' => 'Erro ao gerar o token: ' . $e->getMessage()
        ];
    }
}

function verificaToken($token) {
    global $key; // Chama a chave secreta definida fora da função

    try {
        // Decodifica o token recebido usando o objeto Key
        $decoded = JWT::decode($token, new Key($key, 'HS256'));

        // Verifica se a data de expiração do token é válida
        $currentTime = time();
        if ($decoded->exp < $currentTime) {
            return [
                'status' => 'erro',
                'mensagem' => 'Token expirado.'
            ];
        }

        // Pega o ID do usuário e o nível do usuário a partir do token decodificado
        $userId = $decoded->sub;
        $nivel = $decoded->nivel;
        $nome = $decoded->nome;
        $regime = $decoded->regime;
        $estado = $decoded->estado;

        // Cria um novo token com base nas informações do token antigo
        $novoToken = criaToken($userId, $nivel, $nome, $regime, $estado);

        return [
            'status' => 'sucesso',
            'userId' => $userId,
            'nivel' => $nivel,
            'nome' => $nome,
            'regime' => $regime,
            'estado' => $estado,
            'token' => $novoToken['token']
        ];

    } catch (ExpiredException $e) {
        return [
            'status' => 'erro',
            'mensagem' => 'Token expirado.'
        ];
    } catch (Exception $e) {
        return [
            'status' => 'erro',
            'mensagem' => 'Erro ao verificar o token: ' . $e->getMessage()
        ];
    }
}
function enviarMensagemWablas($token, $phoneNumber, $message, $deviceType = 'default') {
    // URL da API do Wablas
    $url = 'https://api.wablas.com/api/send-message';
    
    // Preparar os dados da requisição
    $data = [
        'phone' => $phoneNumber,
        'message' => $message,
        'token' => $token,
        'device_type' => $deviceType
    ];
    
    // Inicializar cURL
    $ch = curl_init();
    
    // Configurar as opções do cURL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    // Executar a requisição e obter a resposta
    $response = curl_exec($ch);
    
    // Verificar por erros
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'error' => $error_msg];
    }
    
    // Fechar a conexão cURL
    curl_close($ch);
    
    // Decodificar a resposta JSON
    $responseData = json_decode($response, true);
    
    return $responseData;
}

function formatar_telefone($telefone_br) {
    $telefone_digits = preg_replace('/\D/', '', $telefone_br);
    if (strpos($telefone_digits, '0') === 0) {
        $telefone_digits = substr($telefone_digits, 1);
    }
    if (strlen($telefone_digits) == 11) {
        return "55" . $telefone_digits;
    }
    return $telefone_digits;
}

function enviar_mensagem($numero, $token, $secret_key, $produtos, $valor_total, $pedido_id, $url, $nome_user, $userId) {
    // Chama a função formata_texto() primeiro
    $mensagem = formata_texto($produtos, $valor_total, $pedido_id, $nome_user, $userId);
    
    // Verifica se a mensagem foi formatada corretamente
    if (empty($mensagem)) {
        return [
            'status' => 'erro',
            'mensagem' => 'Mensagem não formatada.'
        ];
    }

    if (empty($numero) || empty($token) || empty($url)) {
        error_log("Parâmetros inválidos para enviar_mensagem");
        return false;
    }

    // Codifica a mensagem para URL
    $mensagemCodificada = urlencode(trim($mensagem));
    
    // Monta a URL com os parâmetros GET
    $urlCompleta = $url . "?token=$token.$secret_key&phone=" . trim($numero) . "&message=$mensagemCodificada";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlCompleta);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FAILONERROR, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        error_log("Erro cURL: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    if ($httpCode === 200) {
        return [
            'status' => 'sucesso',
            'mensagem' => 'Mensagem enviada com sucesso.',
            'body' => $response
        ];
    }
    return [
        'status' => $httpCode,
        'body' => $response
    ];
}

function formata_texto($produtos, $valor_total, $pedido_id, $nome_user, $userId) {
    // Cabeçalho da mensagem
    $mensagem = "📦 *Pedido #$pedido_id* 📦\n";
    $mensagem .= "Cliente: *$nome_user*\n";
    $mensagem .= "Id cliente: *$userId*\n\n";
    $mensagem .= "📋 *Itens do Pedido:*\n";
    
    // Adiciona cada produto à mensagem
    foreach ($produtos as $produto) {
        $nome = $produto['nome_prod'];
        $quantidade = $produto['quantidade'];
        $codigo = $produto['codigo'];
        $cor = $produto['cor'];
        $valor_unit = number_format(floatval($produto['valor_unitario']), 2, ',', '.');
        $total_item = number_format(floatval($produto['total_item']), 2, ',', '.');
        
        $mensagem .= "   Nome: $nome\n";
        $mensagem .= "   Código: $codigo\n";
        $mensagem .= "   Cor: $cor\n";
        $mensagem .= "   Quantidade: $quantidade\n";
        $mensagem .= "   Valor Unitário: R$ $valor_unit\n";
        $mensagem .= "   Total do Item: R$ $total_item\n\n";
    }
    
    // Adiciona o total do pedido
    $valor_total_formatado = number_format(floatval($valor_total), 2, ',', '.');
    $mensagem .= "💵 *Total do Pedido: R$ $valor_total_formatado*";
    
    return $mensagem;
}

?>
