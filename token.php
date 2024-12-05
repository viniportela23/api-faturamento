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

function criaToken($userId, $nivel, $nome) {
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

        // Cria um novo token com base nas informações do token antigo
        $novoToken = criaToken($userId, $nivel, $nome);

        return [
            'status' => 'sucesso',
            'userId' => $userId,
            'nivel' => $nivel,
            'nome' => $nome,
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
?>
