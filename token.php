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

?>
