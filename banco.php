<?php

// Inclui o arquivo de conexão com o banco de dados
require_once 'conexao.php'; // Certifique-se de que o caminho esteja correto
date_default_timezone_set('America/Sao_Paulo');

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
                        'regime' => $user['regime'],
                        'estado' => $user['estado'],
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
function consultaProdutos($busca, $estado, $regime, $nivel) {
    global $conn; // Usa a conexão global

    if ($conn) {
        try {
            // Quebra a busca em palavras
            $palavras = explode(' ', trim($busca ?? ''));

            // Cria uma condição dinâmica para o LIKE com várias palavras
            $condicoes = [];
            foreach ($palavras as $palavra) {
                $condicoes[] = "p.nome LIKE :busca_$palavra";
            }
            $condicoesBusca = implode(' OR ', $condicoes);

            if ($nivel === 'cliente') {
                $query = "
                    SELECT p.id, p.nome, p.codigo, p.cor, p.imagem, pr.preco 
                    FROM produtos p
                    INNER JOIN precos pr ON p.id = pr.produto_id
                    WHERE pr.estado = :estado AND pr.regime = :regime AND ($condicoesBusca) AND p.ativo = 1
                ";
                $stmt = $conn->prepare($query);

                $stmt->bindParam(':estado', $estado);
                $stmt->bindParam(':regime', $regime);

                // Adiciona os parâmetros dinâmicos para cada palavra
                foreach ($palavras as $key => $palavra) {
                    $stmt->bindValue(":busca_$palavra", "%$palavra%");
                }
            } else { // Admin
                $query = "
                    SELECT p.id, p.nome, p.codigo, p.cor, p.imagem 
                    FROM produtos p
                    WHERE ($condicoesBusca) AND p.ativo = 1
                ";
                $stmt = $conn->prepare($query);

                // Adiciona os parâmetros dinâmicos para cada palavra
                foreach ($palavras as $key => $palavra) {
                    $stmt->bindValue(":busca_$palavra", "%$palavra%");
                }
            }

            $stmt->execute();

            // Busca todos os registros
            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($produtos) {
                return [
                    'status' => 'sucesso',
                    'dados' => $produtos
                ];
            } else {
                return [
                    'status' => 'sucesso',
                    'dados' => 'Nenhum produto encontrado.'
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
function listarCarrinho($userId) {
    global $conn; // Usa a conexão global

    if ($conn) {
        try {
            // Consulta para buscar os itens do carrinho do usuário
            $stmt = $conn->prepare("
                SELECT 
                    c.id AS carrinho_id,
                    p.id AS produto_id,
                    p.nome AS nome_produto,
                    p.codigo AS codigo_produto,
                    p.cor AS cor_produto,
                    p.imagem,
                    c.quantidade,
                    c.preco AS preco_unitario,
                    (c.quantidade * c.preco) AS total_item,
                    c.data_adicao
                FROM carrinho c
                INNER JOIN produtos p ON c.produto_id = p.id
                WHERE c.cliente_id = :cliente_id
            ");
            $stmt->bindParam(':cliente_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            // Busca todos os registros do carrinho
            $itensCarrinho = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($itensCarrinho) {
                // Calcula o valor total do carrinho
                $valorTotal = array_reduce($itensCarrinho, function ($total, $item) {
                    return $total + $item['total_item'];
                }, 0);

                return [
                    'status' => 'sucesso',
                    'dados' => [
                        'itens' => $itensCarrinho,
                        'valor_total' => $valorTotal
                    ]
                ];
            } else {
                return [
                    'status' => 'erro',
                    'mensagem' => 'Nenhum item encontrado no carrinho.'
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
function alteraQuantidadeProd($userId, $id_produto, $sinal, $estado, $regime) {
    global $conn; // Usa a conexão global

    if ($conn) {
        try {
            // Consulta para buscar o item do carrinho do usuário
            $stmt = $conn->prepare("
                SELECT quantidade FROM carrinho 
                WHERE cliente_id = :cliente_id AND produto_id = :produto_id
            ");
            $stmt->bindParam(':cliente_id', $userId);
            $stmt->bindParam(':produto_id', $id_produto);
            $stmt->execute();
            $produtoCarrinho = $stmt->fetch(PDO::FETCH_ASSOC);

            // Se o sinal for 'remover', deleta o produto diretamente do carrinho
            if ($sinal == 'remover') {
                $stmtDelete = $conn->prepare("
                    DELETE FROM carrinho 
                    WHERE cliente_id = :cliente_id AND produto_id = :produto_id
                ");
                $stmtDelete->bindParam(':cliente_id', $userId);
                $stmtDelete->bindParam(':produto_id', $id_produto);
                $stmtDelete->execute();

                return [
                    'status' => 'sucesso',
                    'mensagem' => 'Item excluído com sucesso.'
                ];
            }

            // Se o produto não estiver no carrinho, cria um novo item
            if (!$produtoCarrinho) {
                if ($sinal == 'mais') {
                    // Consulta para buscar o preço do produto baseado no estado e regime
                    $stmtPreco = $conn->prepare("
                        SELECT pr.preco
                        FROM produtos p
                        INNER JOIN precos pr ON p.id = pr.produto_id
                        WHERE p.id = :produto_id AND pr.estado = :estado AND pr.regime = :regime
                    ");
                    $stmtPreco->bindParam(':produto_id', $id_produto);
                    $stmtPreco->bindParam(':estado', $estado);
                    $stmtPreco->bindParam(':regime', $regime);
                    $stmtPreco->execute();
                    $preco = $stmtPreco->fetchColumn();

                    if ($preco === false) {
                        return [
                            'status' => 'erro',
                            'mensagem' => 'Preço não encontrado para este produto, estado e regime.'
                        ];
                    }

                    // Insere um novo item no carrinho com quantidade 1 e o preço do produto
                    $stmtInsert = $conn->prepare("
                        INSERT INTO carrinho (cliente_id, produto_id, quantidade, preco, data_adicao) 
                        VALUES (:cliente_id, :produto_id, 1, :preco, NOW())
                    ");
                    $stmtInsert->bindParam(':cliente_id', $userId);
                    $stmtInsert->bindParam(':produto_id', $id_produto);
                    $stmtInsert->bindParam(':preco', $preco);
                    $stmtInsert->execute();

                    return [
                        'status' => 'sucesso',
                        'mensagem' => 'Produto adicionado com sucesso.'
                    ];
                }
            }

            // Caso o produto esteja no carrinho, realiza a alteração de quantidade
            if ($produtoCarrinho) {
                // Calcula a nova quantidade baseada no sinal (+ ou -)
                if ($sinal == 'mais') {
                    $novaQuantidade = $produtoCarrinho['quantidade'] + 1;
                    $mensagem = 'Item adicionado com sucesso.';
                } elseif ($sinal == 'menos') {
                    // Se a quantidade for 1 ou menor, o item será removido
                    if ($produtoCarrinho['quantidade'] <= 1) {
                        // Deletando o item do carrinho
                        $stmtDelete = $conn->prepare("
                            DELETE FROM carrinho 
                            WHERE cliente_id = :cliente_id AND produto_id = :produto_id
                        ");
                        $stmtDelete->bindParam(':cliente_id', $userId);
                        $stmtDelete->bindParam(':produto_id', $id_produto);
                        $stmtDelete->execute();

                        return [
                            'status' => 'sucesso',
                            'mensagem' => 'Item excluído com sucesso.'
                        ];
                    } else {
                        // Subtrai 1 da quantidade
                        $novaQuantidade = $produtoCarrinho['quantidade'] - 1;
                        $mensagem = 'Item subtraído com sucesso.';
                    }
                }

                // Atualiza a quantidade no banco de dados
                $stmtUpdate = $conn->prepare("
                    UPDATE carrinho 
                    SET quantidade = :quantidade, data_adicao = NOW() 
                    WHERE cliente_id = :cliente_id AND produto_id = :produto_id
                ");
                $stmtUpdate->bindParam(':quantidade', $novaQuantidade);
                $stmtUpdate->bindParam(':cliente_id', $userId);
                $stmtUpdate->bindParam(':produto_id', $id_produto);
                $stmtUpdate->execute();

                return [
                    'status' => 'sucesso',
                    'mensagem' => $mensagem
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
function carregaInfoDashAdmin() {
    global $conn; // Usa a conexão global

    if (!$conn) {
        return [
            'status' => 'erro',
            'mensagem' => 'Conexão ao banco não disponível.'
        ];
    }

    try {
        // Mês e ano atuais
        $mesAtual = date('Y-m');
        $anoAtual = date('Y');
        $diasDoMes = range(1, date('t')); // Dias do mês atual
        $estados = ['RS', 'SC', 'PR'];

        // Total de vendas acumuladas por estado ao longo do mês
        $vendasPorEstado = [];
        foreach ($estados as $estado) {
            $dados = [];
            foreach ($diasDoMes as $dia) {
                $dataDia = $mesAtual . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT);
                $stmt = $conn->prepare("
                    SELECT SUM(preco * quantidade) AS total 
                    FROM pedidos 
                    INNER JOIN precos ON pedidos.produto_id = precos.produto_id
                    WHERE DATE(data_pedido) = :dataDia AND precos.estado = :estado
                ");
                $stmt->bindParam(':dataDia', $dataDia);
                $stmt->bindParam(':estado', $estado);
                $stmt->execute();
                $dados[] = (float) $stmt->fetchColumn() ?: 0;
            }
            $vendasPorEstado[$estado] = $dados;
        }

        // Indicadores gerais
        $stmtClientes = $conn->query("
            SELECT COUNT(*) AS total 
            FROM clientes 
            WHERE tipo = 'cliente'
        ");
        $totalClientes = $stmtClientes->fetchColumn();

        $stmtPedidosMes = $conn->prepare("
            SELECT COUNT(*) AS total 
            FROM pedidos 
            WHERE DATE_FORMAT(data_pedido, '%Y-%m') = :mesAtual
        ");
        $stmtPedidosMes->bindParam(':mesAtual', $mesAtual);
        $stmtPedidosMes->execute();
        $totalPedidosMes = $stmtPedidosMes->fetchColumn();

        $stmtPedidosAno = $conn->prepare("
            SELECT COUNT(*) AS total 
            FROM pedidos 
            WHERE DATE_FORMAT(data_pedido, '%Y') = :anoAtual
        ");
        $stmtPedidosAno->bindParam(':anoAtual', $anoAtual);
        $stmtPedidosAno->execute();
        $totalPedidosAno = $stmtPedidosAno->fetchColumn();

        $stmtVendasTotaisMes = $conn->prepare("
            SELECT SUM(preco * quantidade) AS total 
            FROM pedidos 
            INNER JOIN precos ON pedidos.produto_id = precos.produto_id
            WHERE DATE_FORMAT(data_pedido, '%Y-%m') = :mesAtual
        ");
        $stmtVendasTotaisMes->bindParam(':mesAtual', $mesAtual);
        $stmtVendasTotaisMes->execute();
        $vendasTotaisMes = $stmtVendasTotaisMes->fetchColumn() ?: 0;

        // Consolidando os dados para o dashboard
        $dadosDash = [
            'vendas_por_estado' => $vendasPorEstado,
            'indicadores' => [
                'total_clientes' => $totalClientes,
                'total_pedidos_mes' => $totalPedidosMes,
                'total_pedidos_ano' => $totalPedidosAno,
                'vendas_totais_mes' => $vendasTotaisMes
            ]
        ];

        return [
            'status' => 'sucesso',
            'dados' => $dadosDash
        ];
    } catch (PDOException $e) {
        return [
            'status' => 'erro',
            'mensagem' => 'Erro no banco: ' . $e->getMessage()
        ];
    }
}

?>
