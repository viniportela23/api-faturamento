<?php

// Inclui o arquivo de conexão com o banco de dados
require_once 'conexao.php'; // Certifique-se de que o caminho esteja correto

function verificaUsuario($email, $senha) {
    global $conn; // Usa a conexão global

    if ($conn) {
        try {
            // Consulta o usuário no banco pelo email
            $stmt = $conn->prepare("SELECT * FROM clientes WHERE email = :email");
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();

            // Busca o registro
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Verifica a senha
                if (password_verify($senha, $user['senha'])) {
                    return [
                        'status' => 'sucesso',
                        'id' => $user['id'],
                        'nome' => $user['nome'],
                        'nivel' => $user['tipo']
                    ];
                } else {
                    return [
                        'status' => 'erro',
                        'mensagem' => 'Senha incorreta.'
                    ];
                }
            } else {
                return [
                    'status' => 'erro',
                    'mensagem' => 'Usuario nao encontrado. ' . $senha
                ];
            }
        } catch (PDOException $e) {
            return [
                'status' => 'erro',
                'mensagem' => 'Erro no banco: ' . $e->getMessage()
            ];
        }
    }

    return [
        'status' => 'erro',
        'mensagem' => 'Conexão ao banco não disponível.'
    ];
}
function consultaPediodos($userId) {
    global $conn; // Usa a conexão global

    if ($conn) {
        try {
            // Consulta os pedidos do usuário no banco
            $stmt = $conn->prepare("
                SELECT p.nome AS produto_nome, pe.quantidade, pe.data_pedido, pe.status
                FROM pedidos pe
                INNER JOIN produtos p ON pe.produto_id = p.id
                WHERE pe.cliente_id = :id
                ORDER BY pe.data_pedido DESC
            ");
            $stmt->bindParam(':id', $userId, PDO::PARAM_STR);
            $stmt->execute();

            // Busca todos os registros
            $pedidosUsuario = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($pedidosUsuario) {
                return [
                    'status' => 'sucesso',
                    'dados' => $pedidosUsuario
                ];
            } else {
                return [
                    'status' => 'erro',
                    'mensagem' => 'Nenhum pedido encontrado para o usuário.'
                ];
            }
        } catch (PDOException $e) {
            return [
                'status' => 'erro',
                'mensagem' => 'Erro no banco: ' . $e->getMessage()
            ];
        }
    }

    return [
        'status' => 'erro',
        'mensagem' => 'Conexão ao banco não disponível.'
    ];
}
?>
