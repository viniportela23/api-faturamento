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
                    'status' => 'nemnhum',
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
                    SELECT p.id, p.nome, p.codigo, p.cor, p.imagem, p.quantidade_minima, pr.preco 
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
                    SELECT p.id, p.nome, p.codigo, p.cor, p.imagem, p.quantidade_minima
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
                    p.quantidade_minima,
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
            // Busca o valor de quantidade mínima para o produto
            $stmtMinima = $conn->prepare("
                SELECT quantidade_minima 
                FROM produtos 
                WHERE id = :produto_id
            ");
            $stmtMinima->bindParam(':produto_id', $id_produto);
            $stmtMinima->execute();
            $quantidadeMinima = $stmtMinima->fetchColumn();

            if ($quantidadeMinima === false || $quantidadeMinima <= 0) {
                return [
                    'status' => 'erro',
                    'mensagem' => 'Quantidade mínima inválida para este produto.'
                ];
            }

            // Consulta para buscar o item do carrinho do usuário
            $stmt = $conn->prepare("
                SELECT quantidade 
                FROM carrinho 
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
                    // Busca o preço do produto baseado no estado e regime
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

                    // Insere um novo item no carrinho com a quantidade mínima
                    $stmtInsert = $conn->prepare("
                        INSERT INTO carrinho (cliente_id, produto_id, quantidade, preco, data_adicao) 
                        VALUES (:cliente_id, :produto_id, :quantidade_minima, :preco, NOW())
                    ");
                    $stmtInsert->bindParam(':cliente_id', $userId);
                    $stmtInsert->bindParam(':produto_id', $id_produto);
                    $stmtInsert->bindParam(':quantidade_minima', $quantidadeMinima);
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
                $novaQuantidade = $produtoCarrinho['quantidade'];
                $mensagem = '';

                if ($sinal == 'mais') {
                    $novaQuantidade += $quantidadeMinima;
                    $mensagem = 'Item adicionado com sucesso.';
                } elseif ($sinal == 'menos') {
                    // Subtração respeitando a quantidade mínima
                    if ($novaQuantidade > $quantidadeMinima) {
                        $novaQuantidade -= $quantidadeMinima;
                        $mensagem = 'Item subtraído com sucesso.';
                    } else {
                        // Se a quantidade for igual ou menor que a mínima, remove o item
                        $stmtDelete = $conn->prepare("
                            DELETE FROM carrinho 
                            WHERE cliente_id = :cliente_id AND produto_id = :produto_id
                        ");
                        $stmtDelete->bindParam(':cliente_id', $userId);
                        $stmtDelete->bindParam(':produto_id', $id_produto);
                        $stmtDelete->execute();

                        return [
                            'status' => 'sucesso',
                            'mensagem' => 'Item removido do carrinho.'
                        ];
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
function listaUsuarios() {
    global $conn; // Usa a conexão global

    if ($conn) {
        try {
            // Consulta os pedidos do usuário no banco
            $stmt = $conn->prepare("
                SELECT *
                FROM clientes
                ORDER BY 
                    CASE 
                        WHEN tipo = 'admin' THEN 1 
                        ELSE 2 
                    END;
            ");
            $stmt->execute();

            // Busca todos os registros
            $listaUsuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($listaUsuarios) {
                return [
                    'status' => 'sucesso',
                    'dados' => $listaUsuarios
                ];
            } else {
                return [
                    'status' => 'erro',
                    'mensagem' => 'Nenhum Usuario encontrado.'
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
function cadastraUsuarios($nome, $email, $senha, $estado, $regime_fiscal, $tipo) {
    global $conn; // Usa a conexão global

    if ($conn) {
        try {
            // Verifica se já existe um usuário com o mesmo e-mail
            $stmt = $conn->prepare("SELECT id FROM clientes WHERE email = :email");
            $stmt->bindParam(':email', $email, PDO::PARAM_STR); // Vincula o parâmetro nomeado
            $stmt->execute();
            $usuarioExistente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuarioExistente) {
                // Se o e-mail já existir, retorna um erro
                return [
                    'status' => 'erro',
                    'mensagem' => 'Já existe um usuário com esse e-mail.'
                ];
            }

            // Senha criptografada (recomenda-se usar bcrypt ou algo similar)
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

            // Insere o novo usuário na tabela
            $stmt = $conn->prepare("
                INSERT INTO clientes (nome, email, senha, estado, regime, tipo)
                VALUES (:nome, :email, :senha, :estado, :regime, :tipo)
            ");
            $stmt->bindParam(':nome', $nome, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':senha', $senhaHash, PDO::PARAM_STR);
            $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
            $stmt->bindParam(':regime', $regime_fiscal, PDO::PARAM_STR);
            $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
            $stmt->execute();

            // Retorna o sucesso
            return [
                'status' => 'sucesso',
                'mensagem' => 'Usuário cadastrado com sucesso.'
            ];

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
function cadastraProduto($nome, $codigo, $cor, $quant_minima, $imagem, $real_rs, $presumido_rs, $simples_rs, $real_sc, $presumido_sc, $simples_sc, $real_pr, $presumido_pr, $simples_pr) {
    global $conn; // Usa a conexão global

    if ($conn) {
        try {
            // Insere o produto na tabela 'produtos'
            $stmt = $conn->prepare("INSERT INTO produtos (nome, codigo, cor, quantidade_minima, imagem) VALUES (:nome, :codigo, :cor, :quant_minima, :imagem)");
            $stmt->bindParam(':nome', $nome, PDO::PARAM_STR);
            $stmt->bindParam(':codigo', $codigo, PDO::PARAM_STR);
            $stmt->bindParam(':cor', $cor, PDO::PARAM_STR);
            $stmt->bindParam(':quant_minima', $quant_minima, PDO::PARAM_INT);
            $stmt->bindParam(':imagem', $imagem, PDO::PARAM_STR);
            $stmt->execute();
            $produtoId = $conn->lastInsertId(); // Pega o ID do produto inserido

            // Inserir os preços por estado e regime na tabela 'precos'
            $precos = [
                'RS' => ['Lucro Real' => $real_rs, 'Lucro Presumido' => $presumido_rs, 'Simples Nacional' => $simples_rs],
                'SC' => ['Lucro Real' => $real_sc, 'Lucro Presumido' => $presumido_sc, 'Simples Nacional' => $simples_sc],
                'PR' => ['Lucro Real' => $real_pr, 'Lucro Presumido' => $presumido_pr, 'Simples Nacional' => $simples_pr],
            ];

            foreach ($precos as $estado => $regimes) {
                foreach ($regimes as $regime => $preco) {
                    $stmt = $conn->prepare("INSERT INTO precos (produto_id, estado, regime, preco) VALUES (:produto_id, :estado, :regime, :preco)");
                    $stmt->bindParam(':produto_id', $produtoId, PDO::PARAM_INT);
                    $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
                    $stmt->bindParam(':regime', $regime, PDO::PARAM_STR);
                    $stmt->bindParam(':preco', $preco, PDO::PARAM_STR);
                    $stmt->execute();
                }
            }

            // Retorna o sucesso
            return [
                'status' => 'sucesso',
                'mensagem' => 'Produto cadastrado com sucesso.'
            ];

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
function listaPedidosAdmin() {
    global $conn; // Usa a conexão global

    if ($conn) {
        try {
            // Consulta os pedidos do usuário no banco
            $stmt = $conn->prepare("
                SELECT 
                    pe.id AS pedido_id, 
                    c.nome AS cliente_nome, 
                    c.email AS cliente_email,
                    p.nome AS produto_nome, 
                    p.codigo AS produto_codigo,
                    pe.quantidade, 
                    pe.data_pedido, 
                    pe.status
                FROM pedidos pe
                INNER JOIN clientes c ON pe.cliente_id = c.id
                INNER JOIN produtos p ON pe.produto_id = p.id
                ORDER BY pe.data_pedido DESC
            ");
            $stmt->execute();

            // Busca todos os registros
            $listaPedidosA = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($listaPedidosA) {
                return [
                    'status' => 'sucesso',
                    'dados' => $listaPedidosA
                ];
            } else {
                return [
                    'status' => 'erro',
                    'mensagem' => 'Nenhum pedido encontrado.'
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
function desativaProduto($id_produto) {
    global $conn; // Usa a conexão global

    if ($conn) {
        try {
            $stmt = $conn->prepare("SELECT id FROM produtos WHERE id = :id_produto");
            $stmt->bindParam(':id_produto', $id_produto, PDO::PARAM_STR); // Vincula o parâmetro nomeado
            $stmt->execute();
            $selectDestaivaProduto = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($selectDestaivaProduto) {

                $stmtUpdate = $conn->prepare("
                    UPDATE produtos SET ativo = 0 
                    WHERE id = :produto_id
                ");
                $stmtUpdate->bindParam(':produto_id', $id_produto);
                $stmtUpdate->execute();

                // Busca todos os registros
                $destaivaProduto = $stmtUpdate->fetchAll(PDO::FETCH_ASSOC);

                return [
                    'status' => 'sucesso',
                    'mensagem' => 'Produto desativado com sucesso.'
                ];
            } else {
                return [
                    'status' => 'erro',
                    'mensagem' => 'Este produto nao existe.'
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
function alteraStatusPedido($pedido_id, $status) {
    global $conn; // Usa a conexão global

    if ($conn) {
        try {
            $stmt = $conn->prepare("SELECT id FROM pedidos WHERE id = :pedido_id");
            $stmt->bindParam(':pedido_id', $pedido_id, PDO::PARAM_STR); // Vincula o parâmetro nomeado
            $stmt->execute();
            $selectDestaivaProduto = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($selectDestaivaProduto) {

                $stmtUpdate = $conn->prepare("
                    UPDATE pedidos SET status = :status
                    WHERE id = :pedido_id
                ");
                $stmtUpdate->bindParam(':status', $status);
                $stmtUpdate->bindParam(':pedido_id', $pedido_id);
                $stmtUpdate->execute();

                // Busca todos os registros
                $destaivaProduto = $stmtUpdate->fetchAll(PDO::FETCH_ASSOC);

                return [
                    'status' => 'sucesso',
                    'mensagem' => 'Pedido alterado com sucesso.'
                ];
            } else {
                return [
                    'status' => 'erro',
                    'mensagem' => 'Este Pedido nao existe.'
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
function puxaDadoProduto($produto_id) {
    global $conn; // Usa a conexão global

    if ($conn) {
        try {
            // Consulta os dados do produto
            $stmt = $conn->prepare("SELECT * FROM produtos WHERE id = :produto_id");
            $stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt->execute();
            $produto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                return [
                    'status' => 'erro',
                    'mensagem' => 'Produto não encontrado.'
                ];
            }

            // Consulta os preços do produto
            $stmt = $conn->prepare("SELECT estado, regime, preco FROM precos WHERE produto_id = :produto_id");
            $stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt->execute();
            $precos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Estrutura os dados para retorno
            $dadosProduto = [
                'produto' => $produto,
                'precos' => $precos
            ];

            return [
                'status' => 'sucesso',
                'dados' => $dadosProduto
            ];

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

function atualizarProduto($nome, $codigo, $cor, $quant_minima, $imagem, $real_rs, $presumido_rs, $simples_rs, $real_sc, $presumido_sc, $simples_sc, $real_pr, $presumido_pr, $simples_pr, $produto_id) {
    global $conn; // Usa a conexão global

    if ($conn) {
        try {
            // Inicia a consulta base
            $sql = "UPDATE produtos 
                    SET nome = :nome, codigo = :codigo, cor = :cor, quantidade_minima = :quant_minima";
            
            // Adiciona o campo `imagem` à consulta apenas se ela não for nula
            if ($imagem !== null) {
                $sql .= ", imagem = :imagem";
            }

            // Finaliza a consulta com a cláusula WHERE
            $sql .= " WHERE id = :produto_id";

            // Prepara a consulta
            $stmt = $conn->prepare($sql);

            // Vincula os parâmetros comuns
            $stmt->bindParam(':nome', $nome, PDO::PARAM_STR);
            $stmt->bindParam(':codigo', $codigo, PDO::PARAM_STR);
            $stmt->bindParam(':cor', $cor, PDO::PARAM_STR);
            $stmt->bindParam(':quant_minima', $quant_minima, PDO::PARAM_INT);
            $stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);

            // Vincula o parâmetro `imagem` apenas se ele não for nulo
            if ($imagem !== null) {
                $stmt->bindParam(':imagem', $imagem, PDO::PARAM_STR);
            }

            // Executa a consulta de atualização
            $stmt->execute();

            // Remove os preços existentes relacionados ao produto
            $stmt = $conn->prepare("DELETE FROM precos WHERE produto_id = :produto_id");
            $stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
            $stmt->execute();

            // Insere os novos preços por estado e regime na tabela 'precos'
            $precos = [
                'RS' => ['Lucro Real' => $real_rs, 'Lucro Presumido' => $presumido_rs, 'Simples Nacional' => $simples_rs],
                'SC' => ['Lucro Real' => $real_sc, 'Lucro Presumido' => $presumido_sc, 'Simples Nacional' => $simples_sc],
                'PR' => ['Lucro Real' => $real_pr, 'Lucro Presumido' => $presumido_pr, 'Simples Nacional' => $simples_pr],
            ];

            foreach ($precos as $estado => $regimes) {
                foreach ($regimes as $regime => $preco) {
                    $stmt = $conn->prepare("INSERT INTO precos (produto_id, estado, regime, preco) VALUES (:produto_id, :estado, :regime, :preco)");
                    $stmt->bindParam(':produto_id', $produto_id, PDO::PARAM_INT);
                    $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
                    $stmt->bindParam(':regime', $regime, PDO::PARAM_STR);
                    $stmt->bindParam(':preco', $preco, PDO::PARAM_STR);
                    $stmt->execute();
                }
            }

            // Retorna sucesso
            return [
                'status' => 'sucesso',
                'mensagem' => 'Produto atualizado com sucesso.'
            ];

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
