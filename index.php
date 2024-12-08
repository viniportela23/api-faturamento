<?php
// main.php

//remover quando tiver em producao
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/*
*/

// Se o IP estiver permitido, continua com a configuração de CORS
header("Access-Control-Allow-Origin: *"); // Permitir apenas o IP do cliente
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, FILES");
header("Access-Control-Allow-Headers: Content-Type");


session_start();
header('Content-Type: application/json'); // Define o cabeçalho JSON

// Verifica se a requisição é POST e se é uma solicitação de login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'login') {
    $email = $_POST['email'] ?? null; // Agora usa $_POST
    $senha = $_POST['senha'] ?? null; 

    if ($email && $senha) {
        // Inclui o arquivo de consulta ao banco somente quando necessário
        require_once 'banco.php';

        // Chama a função de verificação no banco
        $respostaBanco = verificaUsuario($email, $senha);

        if ($respostaBanco['status'] === 'sucesso') {
            require_once 'token.php';
            // Inclui o arquivo de geração do token somente quando necessário
            // $token = criaToken($respostaBanco['id'], $respostaBanco['nivel']);

            $token = criaToken($respostaBanco['id'], $respostaBanco['nivel'], $respostaBanco['nome'], $respostaBanco['regime'], $respostaBanco['estado']);

            if ($token['status'] === 'sucesso') {
                http_response_code(200);
                // Envia a resPOSTa final para controle_login com o token
                echo json_encode([
                    'status' => 'sucesso', 
                    'nivel' => $respostaBanco['nivel'], 
                    'token' => $token['token']]);
            } else {
                http_response_code(500);
                // Erro na validação de usuário
                echo json_encode(['status' => 'erro', 'mensagem' => $token['mensagem'] ?? 'Erro ao gerrar token do usuario.']);
            }
        } else {
           http_response_code(422);
            // Erro na validação de usuário
            echo json_encode(['status' => 'erro', 'mensagem' => $respostaBanco['mensagem']]);
        }
    } else {
       http_response_code(422);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dados incompletos.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'dashboard-cliente') {
    $token = $_POST['token'] ?? null;

    if ($token) {
        // Inclui o arquivo de consulta ao banco somente quando necessário
        require_once 'token.php';

        $token = verificaToken($token);

        if ($token['status'] === 'sucesso' && $token['nivel'] === 'cliente') {
            require_once 'banco.php';

            $respostaBanco = consultaPediodos($token['userId']);

            if ($respostaBanco['status'] === 'sucesso') {
                http_response_code(200);
                // Envia a resPOSTa final para controle_login com o token
                echo json_encode([
                    'status' => 'sucesso', 
                    'dados' => $respostaBanco['dados'],
                    'nome' => $token['nome'],                   
                    'token' => $token['token']]);
            } elseif ($respostaBanco['status'] === 'nemnhum') {
                http_response_code(200);
                // Envia a resPOSTa final para controle_login com o token
                echo json_encode([
                    'status' => 'sucesso', 
                    'dados' => $respostaBanco['mensagem'],
                    'nome' => $token['nome'],                   
                    'token' => $token['token']]);
            } else {
                http_response_code(500);
                // Erro na validação de usuário
                echo json_encode(['status' => 'erro', 'mensagem' => $respostaBanco['mensagem']]);

            }
        } else {
            http_response_code(422);
            // Erro na validação de usuário
            echo json_encode(['status' => 'erro', 'mensagem' => $token['mensagem'] ?? 'Erro ao gerrar token do usuario.']);
        }
    } else {
        http_response_code(422);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dados incompletos.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'lista-produtos') {
    $token = $_POST['token'] ?? null;
    $busca = $_POST['busca'] ?? null;

    if ($token) {
        // Inclui o arquivo de consulta ao banco somente quando necessário
        require_once 'token.php';

        $token = verificaToken($token);

        if ($token['status'] === 'sucesso') {
            require_once 'banco.php';

            $respostaBanco = consultaProdutos($busca, $token['estado'], $token['regime'], $token['nivel']);

            if ($respostaBanco['status'] === 'sucesso') {

                http_response_code(200);
                // Envia a resPOSTa final para controle_login com o token
                echo json_encode([
                    'status' => 'sucesso', 
                    'dados' => $respostaBanco['dados'],
                    'nivel' => $token['nivel'],
                    'token' => $token['token']]);
            } else {
                http_response_code(500);
                // Erro na validação de usuário
                echo json_encode(['status' => 'erro', 'mensagem' => $respostaBanco['mensagem']]);

            }
        } else {
            http_response_code(422);
            // Erro na validação de usuário
            echo json_encode(['status' => 'erro', 'mensagem' => $token['mensagem'] ?? 'Erro ao gerrar token do usuario.']);
        }
    } else {
        http_response_code(422);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dados incompletos.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'carrega-carrinho') {
    $token = $_POST['token'] ?? null;

    if ($token) {
        // Inclui o arquivo de consulta ao banco somente quando necessário
        require_once 'token.php';

        $token = verificaToken($token);

        if ($token['status'] === 'sucesso' && $token['nivel'] === 'cliente') {
            require_once 'banco.php';

            $respostaBanco = listarCarrinho($token['userId']);

            if ($respostaBanco['status'] === 'sucesso') {

                http_response_code(200);
                // Envia a resPOSTa final para controle_login com o token
                echo json_encode([
                    'status' => 'sucesso',
                    'itens' => $respostaBanco['dados'],
                    'token' => $token['token']]);
            } else {
                http_response_code(401);
                // Erro na validação de usuário
                echo json_encode(['status' => 'erro', 'mensagem' => $respostaBanco['mensagem']]);

            }
        } else {
            http_response_code(422);
            // Erro na validação de usuário
            echo json_encode(['status' => 'erro', 'mensagem' => $token['mensagem'] ?? 'Erro ao gerrar token do usuario.']);
        }
    } else {
        http_response_code(422);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dados incompletos.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'alterar-quantidade') {
    $token = $_POST['token'] ?? null;
    $sinal = $_POST['sinal'] ?? null;
    $id_produto = $_POST['id_produto'] ?? null;

    if ($token) {
        // Inclui o arquivo de consulta ao banco somente quando necessário
        require_once 'token.php';

        $token = verificaToken($token);

        if ($token['status'] === 'sucesso' && $token['nivel'] === 'cliente') {
            require_once 'banco.php';

            $respostaBanco = alteraQuantidadeProd($token['userId'], $id_produto, $sinal, $token['estado'], $token['regime']);

            if ($respostaBanco['status'] === 'sucesso') {

                http_response_code(200);
                // Envia a resPOSTa final para controle_login com o token
                echo json_encode([
                    'status' => 'sucesso',
                    'mensagem' => $respostaBanco['mensagem'],
                    'token' => $token['token']]);
            } else {
                http_response_code(500);
                // Erro na validação de usuário
                echo json_encode(['status' => 'erro', 'mensagem' => $respostaBanco['mensagem']]);

            }
        } else {
            http_response_code(422);
            // Erro na validação de usuário
            echo json_encode(['status' => 'erro', 'mensagem' => $token['mensagem'] ?? 'Erro ao gerrar token do usuario.']);
        }
    } else {
        http_response_code(422);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dados incompletos.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'dashboard-admin') {
    $token = $_POST['token'] ?? null;

    if ($token) {
        // Inclui o arquivo de consulta ao banco somente quando necessário
        require_once 'token.php';

        $token = verificaToken($token);

        if ($token['status'] === 'sucesso' && $token['nivel'] === 'admin') {
            require_once 'banco.php';

            $respostaBanco = carregaInfoDashAdmin();

            if ($respostaBanco['status'] === 'sucesso') {

                http_response_code(200);
                // Envia a resPOSTa final para controle_login com o token
                echo json_encode([
                    'status' => 'sucesso',
                    'dados' => $respostaBanco['dados'],
                    'nome' => $token['nome'],
                    'token' => $token['token']]);
            } else {
                http_response_code(500);
                // Erro na validação de usuário
                echo json_encode(['status' => 'erro', 'mensagem' => $respostaBanco['mensagem']]);

            }
        } else {
            http_response_code(422);
            // Erro na validação de usuário
            echo json_encode(['status' => 'erro', 'mensagem' => $token['mensagem'] ?? 'Erro ao gerrar token do usuario.']);
        }
    } else {
        http_response_code(422);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dados incompletos.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'listar-clientes') {
    $token = $_POST['token'] ?? null;

    if ($token) {
        // Inclui o arquivo de consulta ao banco somente quando necessário
        require_once 'token.php';

        $token = verificaToken($token);

        if ($token['status'] === 'sucesso' && $token['nivel'] === 'admin') {
            require_once 'banco.php';

            $respostaBanco = listaUsuarios();

            if ($respostaBanco['status'] === 'sucesso') {

                http_response_code(200);
                // Envia a resPOSTa final para controle_login com o token
                echo json_encode([
                    'status' => 'sucesso',
                    'dados' => $respostaBanco['dados'],
                    'token' => $token['token']]);
            } else {
                http_response_code(500);
                // Erro na validação de usuário
                echo json_encode(['status' => 'erro', 'mensagem' => $respostaBanco['mensagem']]);

            }
        } else {
            http_response_code(422);
            // Erro na validação de usuário
            echo json_encode(['status' => 'erro', 'mensagem' => $token['mensagem'] ?? 'Erro ao gerrar token do usuario.']);
        }
    } else {
        http_response_code(422);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dados incompletos.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'cadastrar-cliente') {
    $nome = $_POST['nome'] ?? null;
    $email = $_POST['email'] ?? null;
    $senha = $_POST['senha'] ?? null;
    $estado = $_POST['estado'] ?? null;
    $regime_fiscal = $_POST['regime_fiscal'] ?? null;
    $tipo_usuario = $_POST['tipo_usuario'] ?? null;
    $token = $_POST['token'] ?? null;

    if ($token) {
        // Inclui o arquivo de consulta ao banco somente quando necessário
        require_once 'token.php';

        $token = verificaToken($token);

        if ($token['status'] === 'sucesso' && $token['nivel'] === 'admin') {
            require_once 'banco.php';

            $respostaBanco = cadastraUsuarios($nome, $email, $senha, $estado, $regime_fiscal, $tipo_usuario);

            if ($respostaBanco['status'] === 'sucesso') {

                http_response_code(200);
                // Envia a resPOSTa final para controle_login com o token
                echo json_encode([
                    'status' => 'sucesso',
                    'dados' => $respostaBanco['mensagem'],
                    'token' => $token['token']]);
            } else {
                http_response_code(500);
                // Erro na validação de usuário
                echo json_encode(['status' => 'erro', 'mensagem' => $respostaBanco['mensagem']]);

            }
        } else {
            http_response_code(422);
            // Erro na validação de usuário
            echo json_encode(['status' => 'erro', 'mensagem' => $token['mensagem'] ?? 'Erro ao gerrar token do usuario.']);
        }
    } else {
        http_response_code(422);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dados incompletos.']);
    }
} else {
    //http_response_code(404);
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'requisicao invalida.'
    ]);
}
?>