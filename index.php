<?php
// main.php
date_default_timezone_set('America/Sao_Paulo'); // Define o fuso horário para São Paulo

// Função para registrar as requisições e respostas
function logRequestAndResponse($logFile = 'logs/requisicoes.log') {
    // Cria o diretório de logs, se não existir
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0777, true);
    }

    // Captura as informações da requisição
    $logData = [
        'Data/Hora' => date('Y-m-d H:i:s'),
        'Método' => $_SERVER['REQUEST_METHOD'],
        'URL' => $_SERVER['REQUEST_URI'],
        'Headers' => getallheaders(),
        'GET' => $_GET,
        'POST' => $_POST,
        'FILES' => $_FILES,
        'RAW_BODY' => file_get_contents('php://input'),
        'IP' => $_SERVER['REMOTE_ADDR'],
    ];

    // Formata os dados da requisição como JSON
    $logJson = json_encode(['Requisicao' => $logData], JSON_PRETTY_PRINT);

    // Salva os dados da requisição no log
    file_put_contents($logFile, $logJson . PHP_EOL, FILE_APPEND);
}

// Função para registrar a resposta
function logResponse($logFile = 'logs/requisicoes.log') {
    // Captura o conteúdo da saída (resposta do script)
    $responseContent = ob_get_contents();

    // Salva a resposta no log
    $logData = [
        'Resposta' => $responseContent,
    ];

    $logJson = json_encode($logData, JSON_PRETTY_PRINT);

    // Adiciona ao arquivo de log
    file_put_contents($logFile, $logJson . PHP_EOL . str_repeat('=', 80) . PHP_EOL, FILE_APPEND);
}

// Ativa o buffer de saída para capturar a resposta do script
ob_start();

// Registra a requisição
logRequestAndResponse();

/*

//remover quando tiver em producao
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


*/

$allowedOrigin = "https://centraljbl.com.br";
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$type = $_GET['tipo'] ?? ''; // Obtém o tipo da requisição

if (in_array($type, ['webhook']) && (!isset($_POST['tipo']) || $_POST['tipo'] === null)) {
    // Permite requisições de qualquer origem quando for do tipo webhook
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
} else {// colocar === quando tiver em produção
    if ($origin = $allowedOrigin) {
        header("Access-Control-Allow-Origin: $allowedOrigin");
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
    } else {
        // Bloqueia requisições de origens não permitidas
        http_response_code(403); // Alterado para 403 (acesso proibido) em vez de 404 (não encontrado)
        logResponse(); // Registra o log antes de sair
        exit;
    }
}


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
            $token = criaToken($respostaBanco['id'], $respostaBanco['nivel'], $respostaBanco['nome'], $respostaBanco['regime'], $respostaBanco['estado']);

            if ($token['status'] === 'sucesso') {
                http_response_code(200);

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

            $respostaBanco = consultaPedidos($token['userId']);

            if ($respostaBanco['status'] === 'sucesso' || $respostaBanco['status'] === 'nenhum') {
                if ($respostaBanco['status'] === 'sucesso') {
                    http_response_code(200);
                    // Envia a resPOSTa final para controle_login com o token
                    echo json_encode([
                        'status' => 'sucesso', 
                        'dados' => $respostaBanco['dados'],
                        'nome' => $token['nome'],                   
                        'token' => $token['token']]);
                } else {
                    http_response_code(200);
                    echo json_encode([
                        'status' => 'sucesso', 
                        'dados' => $respostaBanco['mensagem'],
                        'nome' => $token['nome'],                   
                        'token' => $token['token']]);
                }
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
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'cadastrar-produto') {
    // Recebe os dados do produto
    $nome = $_POST['nome'] ?? null;
    $codigo = $_POST['codigo'] ?? null;
    $cor = $_POST['cor'] ?? null; // Corrigido para a variável correta
    $quant_minima = $_POST['quant_minima'] ?? null;
    
    // Recebe a imagem enviada
    $imagem = $_FILES['imagem'] ?? null;

    // Preços para cada estado
    $real_rs = $_POST['real_rs'] ?? null;
    $presumido_rs = $_POST['presumido_rs'] ?? null;
    $simples_rs = $_POST['simples_rs'] ?? null;
    $real_sc = $_POST['real_sc'] ?? null;
    $presumido_sc = $_POST['presumido_sc'] ?? null;
    $simples_sc = $_POST['simples_sc'] ?? null;
    $real_pr = $_POST['real_pr'] ?? null;
    $presumido_pr = $_POST['presumido_pr'] ?? null;
    $simples_pr = $_POST['simples_pr'] ?? null;

    // Recebe o token de autenticação
    $token = $_POST['token'] ?? null;

    if ($token) {
        // Verifica o token
        require_once 'token.php';
        $token = verificaToken($token);

        if ($token['status'] === 'sucesso' && $token['nivel'] === 'admin') {
            // Verifica se a imagem foi enviada
            if ($imagem && $imagem['error'] === UPLOAD_ERR_OK) {
                // Gera um nome aleatório para a imagem
                $extensao = pathinfo($imagem['name'], PATHINFO_EXTENSION);
                $novoNomeImagem = uniqid('produto_') . '.' . $extensao;
                
                // Diretório onde as imagens serão armazenadas
                $diretorioDestino = 'uploads/produtos/';
                
                // Verifica se o diretório de destino existe, caso contrário cria
                if (!is_dir($diretorioDestino)) {
                    mkdir($diretorioDestino, 0755, true);
                }

                // Mover a imagem para o diretório desejado
                if (move_uploaded_file($imagem['tmp_name'], $diretorioDestino . $novoNomeImagem)) {
                    // A imagem foi movida com sucesso, agora chama a função de cadastro
                    require_once 'banco.php';
                    $respostaBanco = cadastraProduto($nome, $codigo, $cor, $quant_minima, $novoNomeImagem, $real_rs, $presumido_rs, $simples_rs, $real_sc, $presumido_sc, $simples_sc, $real_pr, $presumido_pr, $simples_pr);

                    if ($respostaBanco['status'] === 'sucesso') {
                        http_response_code(200);
                        echo json_encode([
                            'status' => 'sucesso',
                            'dados' => $respostaBanco['mensagem'],
                            'token' => $token['token']
                        ]);
                    } else {
                        http_response_code(401);
                        echo json_encode(['status' => 'erro', 'mensagem' => $respostaBanco['mensagem']]);
                    }
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao salvar a imagem.']);
                }
            } else {
                http_response_code(422);
                echo json_encode(['status' => 'erro', 'mensagem' => 'Imagem não recebida ou erro ao fazer upload.']);
            }
        } else {
            http_response_code(422);
            echo json_encode(['status' => 'erro', 'mensagem' => $token['mensagem'] ?? 'Erro ao gerar token do usuário.']);
        }
    } else {
        http_response_code(422);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dados incompletos.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'listar-pedidos-admin') {
    $token = $_POST['token'] ?? null;

    if ($token) {
        // Inclui o arquivo de consulta ao banco somente quando necessário
        require_once 'token.php';

        $token = verificaToken($token);

        if ($token['status'] === 'sucesso' && $token['nivel'] === 'admin') {
            require_once 'banco.php';

            $respostaBanco = listaPedidosAdmin();

            if ($respostaBanco['status'] === 'sucesso') {

                http_response_code(200);

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
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'desativa-produto') {
    $id_produto = $_POST['id_produto'] ?? null;
    $token = $_POST['token'] ?? null;

    if ($token) {
        // Inclui o arquivo de consulta ao banco somente quando necessário
        require_once 'token.php';

        $token = verificaToken($token);

        if ($token['status'] === 'sucesso' && $token['nivel'] === 'admin') {
            require_once 'banco.php';

            $respostaBanco = desativaProduto($id_produto);

            if ($respostaBanco['status'] === 'sucesso') {

                http_response_code(200);

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
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'atualizar-produto') {
    // Recebe os dados do produto
    $nome = $_POST['nome'] ?? null;
    $codigo = $_POST['codigo'] ?? null;
    $cor = $_POST['cor'] ?? null;
    $quant_minima = $_POST['quant_minima'] ?? null;

    // Recebe a imagem enviada, se existir
    $imagem = $_FILES['imagem'] ?? null;

    // Preços para cada estado
    $real_rs = $_POST['real_rs'] ?? null;
    $presumido_rs = $_POST['presumido_rs'] ?? null;
    $simples_nacional_rs = $_POST['simples_nacional_rs'] ?? null;
    $real_sc = $_POST['real_sc'] ?? null;
    $presumido_sc = $_POST['presumido_sc'] ?? null;
    $simples_nacional_sc = $_POST['simples_nacional_sc'] ?? null;
    $real_pr = $_POST['real_pr'] ?? null;
    $presumido_pr = $_POST['presumido_pr'] ?? null;
    $simples_nacional_pr = $_POST['simples_nacional_pr'] ?? null;

    $produto_id = $_POST['produto_id'] ?? null;

    // Recebe o token de autenticação
    $token = $_POST['token'] ?? null;

    if ($token) {
        // Verifica o token
        require_once 'token.php';
        $token = verificaToken($token);

        if ($token['status'] === 'sucesso' && $token['nivel'] === 'admin') {
            $novoNomeImagem = null; // Nome da imagem, se enviado

            // Verifica se a imagem foi enviada
            if ($imagem && $imagem['error'] === UPLOAD_ERR_OK) {
                // Gera um nome aleatório para a imagem
                $extensao = pathinfo($imagem['name'], PATHINFO_EXTENSION);
                $novoNomeImagem = uniqid('produto_') . '.' . $extensao;

                // Diretório onde as imagens serão armazenadas
                $diretorioDestino = 'uploads/produtos/';

                // Verifica se o diretório de destino existe, caso contrário cria
                if (!is_dir($diretorioDestino)) {
                    mkdir($diretorioDestino, 0755, true);
                }

                // Mover a imagem para o diretório desejado
                if (!move_uploaded_file($imagem['tmp_name'], $diretorioDestino . $novoNomeImagem)) {
                    http_response_code(500);
                    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao salvar a imagem.']);
                    exit;
                }
            }

            // Chama a função para atualizar o produto
            require_once 'banco.php';
            $respostaBanco = atualizarProduto(
                $nome,
                $codigo,
                $cor,
                $quant_minima,
                $novoNomeImagem, // Pode ser null
                $real_rs,
                $presumido_rs,
                $simples_nacional_rs,
                $real_sc,
                $presumido_sc,
                $simples_nacional_sc,
                $real_pr,
                $presumido_pr,
                $simples_nacional_pr,
                $produto_id
            );

            if ($respostaBanco['status'] === 'sucesso') {
                http_response_code(200);
                echo json_encode([
                    'status' => 'sucesso',
                    'dados' => $respostaBanco['mensagem'],
                    'token' => $token['token']
                ]);
            } else {
                http_response_code(401);
                echo json_encode(['status' => 'erro', 'mensagem' => $respostaBanco['mensagem']]);
            }
        } else {
            http_response_code(422);
            echo json_encode(['status' => 'erro', 'mensagem' => $token['mensagem'] ?? 'Erro ao gerar token do usuário.']);
        }
    } else {
        http_response_code(422);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dados incompletos.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'alterar-status') {
    $pedido_id = $_POST['pedido_id'] ?? null;
    $status = $_POST['status'] ?? null;
    $token = $_POST['token'] ?? null;

    if ($token) {
        // Inclui o arquivo de consulta ao banco somente quando necessário
        require_once 'token.php';

        $token = verificaToken($token);

        if ($token['status'] === 'sucesso' && $token['nivel'] === 'admin') {
            require_once 'banco.php';

            $respostaBanco = alteraStatusPedido($pedido_id, $status);

            if ($respostaBanco['status'] === 'sucesso') {

                http_response_code(200);

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
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'puxa-dados-produto') {
    $produto_id = $_POST['produto_id'] ?? null;
    $token = $_POST['token'] ?? null;

    if ($token) {
        // Inclui o arquivo de consulta ao banco somente quando necessário
        require_once 'token.php';

        $token = verificaToken($token);

        if ($token['status'] === 'sucesso' && $token['nivel'] === 'admin') {
            require_once 'banco.php';

            $respostaBanco = puxaDadoProduto($produto_id);

            if ($respostaBanco['status'] === 'sucesso') {

                http_response_code(200);

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
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'finalizar-pedido') {
    $token = $_POST['token'] ?? null;

    $tokenWablas = '11MRLonB98auTpGI3xEMqcq1GoadpIMPQ8WUjsB4TCqquzPnJc70fMh'; // Substitua pelo seu token do Wablas
    $secret_key = 'i6OJgyEk';
    $url = "https://solo.wablas.com/api/send-message";

    if ($token) {
        // Inclui o arquivo de consulta ao banco somente quando necessário
        require_once 'token.php';

        $token = verificaToken($token);

        if ($token['status'] === 'sucesso' && $token['nivel'] === 'cliente') {
            require_once 'banco.php';

            $respostaBanco = criarPedido($token['userId']);

            if ($respostaBanco['status'] === 'sucesso') {

                $respostaWablas = enviar_mensagem($respostaBanco['numero'], $tokenWablas, $secret_key, $respostaBanco['produtos'], $respostaBanco['valor_total'], $respostaBanco['pedido_id'], $url, $respostaBanco['nome_cliente'], $token['userId']);

                if ($respostaWablas['status'] === 'sucesso') {

                    http_response_code(200);
                    echo json_encode([
                        'status' => 'sucesso',
                        'mensagem' => $respostaBanco['mensagem'],
                        'token' => $token['token']]);
                } else {
                    http_response_code(422);
                    // Erro na validação de usuário
                    echo json_encode(['status' => 'erro', 'mensagem' => $respostaWablas['mensagem']]);

                }
            } else {
                http_response_code(422);
                // Erro na validação de usuário
                echo json_encode(['status' => 'erro', 'mensagem' => $respostaBanco['mensagem']]);

            }
        } else {
            http_response_code(402);
            // Erro na validação de usuário
            echo json_encode(['status' => 'erro', 'mensagem' => $token['mensagem'] ?? 'Erro ao gerrar token do usuario.']);
        }
    } else {
        http_response_code(422);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Dados incompletos.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'atualizar-cliente') {
    $id = $_POST['id'] ?? null;
    $nome = $_POST['nome'] ?? null;
    $email = $_POST['email'] ?? null;
    $estado = $_POST['estado'] ?? null;
    $regime = $_POST['regime'] ?? null;
    $token = $_POST['token'] ?? null;

    if ($token) {
        require_once 'token.php';

        $token = verificaToken($token);

        if ($token['status'] === 'sucesso' && $token['nivel'] === 'admin') {
            require_once 'banco.php';

            $respostaBanco = editaUsuario($id, $nome, $email, $estado, $regime);

            if ($respostaBanco['status'] === 'sucesso') {

                http_response_code(200);

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
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'alterar-senha-cliente') {
    $id = $_POST['id'] ?? null;
    $nova_senha = $_POST['nova_senha'] ?? null;
    $token = $_POST['token'] ?? null;

    if ($token) {
        require_once 'token.php';

        $token = verificaToken($token);

        if ($token['status'] === 'sucesso' && $token['nivel'] === 'admin') {
            require_once 'banco.php';

            $respostaBanco = alteraSenhaUser($id, $nova_senha);

            if ($respostaBanco['status'] === 'sucesso') {

                http_response_code(200);

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
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'altera-numero-bot') {
    $id = $_POST['id'] ?? null;
    $numero = $_POST['numero'] ?? null;
    $token = $_POST['token'] ?? null;

    if ($token) {
        require_once 'token.php';

        $token = verificaToken($token);

        if ($token['status'] === 'sucesso' && $token['nivel'] === 'admin') {
            require_once 'banco.php';

            $respostaBanco = alteraNumeroBot($id, $numero);

            if ($respostaBanco['status'] === 'sucesso') {

                http_response_code(200);

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
} else {
    http_response_code(404);
    /*
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'requisicao invalida.'
    ]);
    */
}
// No final do script, registra a resposta no log
logResponse();

// Envia a resposta ao cliente
ob_end_flush();

?>