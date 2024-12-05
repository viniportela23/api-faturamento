<?php
require_once 'vendor/autoload.php'; // Certifique-se de incluir o autoload do Composer

use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;

// Caminho para o arquivo de chave fora do diretório público
$keyPath = __DIR__ . '/../jwt_key.txt'; // Ajuste o caminho conforme necessário

// Carrega a chave do arquivo externo
if (!file_exists($keyPath)) {
    die('Erro: Arquivo de chave JWT não encontrado.');
}

$key = trim(file_get_contents($keyPath)); // Remove espaços ou quebras de linha extras

$project = 'teste';

function criaToken($userId) {
    global $key;
    global $project;

    $issuedAt = time();
    $expirationTime = $issuedAt + 3600; // O token expira em 1 hora
    $createdAt = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));

    $payload = [
        'sub' => $userId,
        'iat' => $issuedAt,
        'exp' => $expirationTime,
        'createdAt' => $createdAt->format('Y-m-d H:i:s'), // Data de criação no formato desejado
        'project' => $project,
    ];

    // Gera o token com a chave carregada
    $token = JWT::encode($payload, $key, 'HS256');

    // Retorna uma mensagem de sucesso junto com o token
    return [
        'mensagem' => 'success',
        'token' => $token
    ];
}

function decodificaToken($token) {
    global $key; // Chama a chave secreta definida fora da função

    try {
        return JWT::decode($token, $key, ['HS256']);
    } catch (ExpiredException $e) {
        echo 'Token expirado';
    } catch (Exception $e) {
        echo 'Erro ao decodificar o token: ' . $e->getMessage();
    }
}
?>
