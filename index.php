<?php
// main.php

//remover quando tiver em producao
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/*

// Se o IP estiver permitido, continua com a configuração de CORS
header("Access-Control-Allow-Origin: *"); // Permitir apenas o IP do cliente
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, FILES");
header("Access-Control-Allow-Headers: Content-Type");


session_start();
header('Content-Type: application/json'); // Define o cabeçalho JSON

// Verifica se a requisição é POST e se é uma solicitação de login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'login') {
    $usuario = $_POST['usuario'] ?? null; // Agora usa $_POST
    $senha = $_POST['senha'] ?? null; // Agora usa $_POST

    if ($usuario && $senha) {
        // Inclui o arquivo de consulta ao banco somente quando necessário
        require_once 'banco.php';

        // Chama a função de verificação no banco
        $resPOSTaBanco = verificaUsuario($senha, $usuario);

        if ($resPOSTaBanco['status'] == 'sucesso') {
            require_once 'token.php';
            // Inclui o arquivo de geração do token somente quando necessário
            // $token = criaToken($respostaBanco['id'], $resPOSTaBanco['nivel']);

            $token = criaToken($respostaBanco['id']);

            if ($token !== null) {

                $veirificaAssinatura = ajustaNivel($resPOSTaBanco['user_id']);

                if ($veirificaAssinatura['status'] == 'sucesso') {
                    http_response_code(200);
                    // Envia a resPOSTa final para controle_login com o token
                    echo json_encode([
                        'status' => 'sucesso', 
                        'token' => $token]);
                } else {
                    http_response_code(500);
                    // Erro na validação de usuário
                    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro verificar dados no banco.']);
                }

            } else {
                http_response_code(500);
                // Erro na validação de usuário
                echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao gerrar token do usuario.']);
            }
        } else {
           http_response_code(422);
            // Erro na validação de usuário
            echo json_encode(['status' => 'erro', 'mensagem' => $resPOSTaBanco['mensagem']]);
        }
    } else {
       http_response_code(422);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dados incompletos.']);
    }
//} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'categoria-banner') {
  //  $token = $_POST['token'] ?? null;

} else {
    //http_response_code(404);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'requisicao invalida.'
    ]);


}
?>