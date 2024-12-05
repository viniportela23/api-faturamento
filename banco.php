<?php

// Inclui o arquivo de conexão com o banco de dados
require_once 'conexao.php'; // Certifique-se de que o caminho esteja correto

function verificaUsuario($usuario, $senha) {
    $conn = conectarBanco(); // Chama a função de conexão
    if ($conn) {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE usuario = :usuario");
        $stmt->bindParam(':usuario', $usuario);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            // Verifica se a senha está correta
            if (password_verify($senha, $user['senha'])) {
                return [
                    'mensagem' => 'success',
                    'id' => $user['id'] // Retorna o ID do usuário se as credenciais forem válidas
                ];
            } else {
                return [
                    'mensagem' => 'error.',
                    'id' => null // ID não é aplicável se a senha estiver incorreta
                ];
            }
        }
    }
    return [
        'mensagem' => 'Usuário não encontrado.',
        'id' => null // ID não é aplicável se o usuário não for encontrado
    ];
}
?>
