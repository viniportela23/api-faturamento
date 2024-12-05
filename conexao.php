<?php
// Caminho para o arquivo de credenciais
$credenciais_file = __DIR__ . '/../credenciais_banco.txt';

// Verifica se o arquivo de credenciais existe
if (!file_exists($credenciais_file)) {
    die('Arquivo de credenciais não encontrado.');
}

// Lê o conteúdo do arquivo de credenciais
$credenciais = parse_ini_file($credenciais_file);

// Função para estabelecer a conexão com o banco de dados
function conectarBanco() {
    global $credenciais; // Permite acesso à variável $credenciais dentro da função

    // Estabelece a conexão com o banco de dados
    try {
        $dsn = "mysql:host=" . $credenciais['host'] . ";dbname=" . $credenciais['database'] . ";charset=utf8";
        $pdo = new PDO($dsn, $credenciais['user'], $credenciais['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Lida com exceções
        return $pdo; // Retorna a instância de conexão
    } catch (PDOException $e) {
        die("Erro ao conectar ao banco de dados: " . $e->getMessage());
    }
}
?>
