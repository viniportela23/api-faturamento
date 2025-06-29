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
                    'mensagem' => 'Usuario nao encontrado. '
                ];
            }
        } catch (PDOException $e) {
            return [
                'status' => 'erro',
                'mensagem' => 'Erro no banco.'
            ];
        }
    }

    return [
        'status' => 'erro',
        'mensagem' => 'Conexão ao banco não disponível.'
    ];
}
function consultaPedidos($userId) {
    global $conn; // Usa a conexão global

    if ($conn) {
        try {
            // Consulta os pedidos e itens relacionados ao usuário
            $stmt = $conn->prepare("
                SELECT
                    pe.id AS pedido_id,
                    pe.data_pedido,
                    pe.status,
                    pe.valor_total,
                    p.nome AS produto_nome,
                    i.quantidade,
                    i.preco_unitario,
                    i.total_item,
                    p.codigo AS produto_codigo
                FROM pedidos pe
                INNER JOIN itens_pedido i ON pe.id = i.pedido_id
                INNER JOIN produtos p ON i.produto_id = p.id
                WHERE pe.cliente_id = :id
                ORDER BY pe.data_pedido DESC
            ");
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            // Busca todos os registros
            $pedidosUsuario = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($pedidosUsuario) {
                // Organizar os dados de pedidos para retornar uma estrutura semelhante ao formato esperado
                $pedidosComItens = [];
                
                foreach ($pedidosUsuario as $pedido) {
                    // Organiza os pedidos por ID
                    if (!isset($pedidosComItens[$pedido['pedido_id']])) {
                        $pedidosComItens[$pedido['pedido_id']] = [
                            'pedido_id' => $pedido['pedido_id'],
                            'data_pedido' => $pedido['data_pedido'],
                            'status' => $pedido['status'],
                            'valor_total' => $pedido['valor_total'],
                            'itens' => []
                        ];
                    }
                    // Adiciona os itens ao pedido
                    $pedidosComItens[$pedido['pedido_id']]['itens'][] = [
                        'produto_nome' => $pedido['produto_nome'],
                        'produto_codigo' => $pedido['produto_codigo'],
                        'quantidade' => $pedido['quantidade'],
                        'preco_unitario' => $pedido['preco_unitario'],
                        'total_item' => $pedido['total_item']
                    ];
                }

                return [
                    'status' => 'sucesso',
                    'dados' => array_values($pedidosComItens) // Retorna os pedidos formatados
                ];
            } else {
                return [
                    'status' => 'nenhum',
                    'mensagem' => 'Nenhum pedido encontrado para o usuário.'
                ];
            }
        } catch (PDOException $e) {
            return [
                'status' => 'erro',
                'mensagem' => 'Erro no banco.'
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
                    SELECT p.id, p.nome, p.codigo, p.cor, p.imagem, p.quantidade_minima, p.ativo
                    FROM produtos p
                    WHERE ($condicoesBusca)
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
                'mensagem' => 'Erro no banco.'
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
            // Consulta para buscar o carrinho ativo do usuário
            $stmtCarrinho = $conn->prepare("
                SELECT id AS carrinho_id, status, data
                FROM carrinho
                WHERE usuario_id = :usuario_id AND status = 'ativo'
            ");
            $stmtCarrinho->bindParam(':usuario_id', $userId, PDO::PARAM_INT);
            $stmtCarrinho->execute();
            $carrinho = $stmtCarrinho->fetch(PDO::FETCH_ASSOC);

            if (!$carrinho) {
                return [
                    'status' => 'erro',
                    'mensagem' => 'Nenhum carrinho ativo encontrado para este usuário.'
                ];
            }

            // Consulta para buscar os itens do carrinho
            $stmtItens = $conn->prepare("
                SELECT 
                    ic.id AS item_carrinho_id,
                    ic.produto_id,
                    p.nome AS nome_produto,
                    p.codigo AS codigo_produto,
                    p.cor AS cor_produto,
                    p.quantidade_minima,
                    p.imagem,
                    ic.quantidade,
                    ic.valor_unitario AS preco_unitario,
                    (ic.quantidade * ic.valor_unitario) AS total_item,
                    ic.data
                FROM item_carrinho ic
                INNER JOIN produtos p ON ic.produto_id = p.id
                WHERE ic.carrinho_id = :carrinho_id
            ");
            $stmtItens->bindParam(':carrinho_id', $carrinho['carrinho_id'], PDO::PARAM_INT);
            $stmtItens->execute();
            $itensCarrinho = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

            if ($itensCarrinho) {
                // Soma o valor total do carrinho com base nos itens
                $valorTotal = 0;
                foreach ($itensCarrinho as $item) {
                    $valorTotal += $item['total_item'];
                }

                return [
                    'status' => 'sucesso',
                    'dados' => [
                        'carrinho_id' => $carrinho['carrinho_id'],
                        'status' => $carrinho['status'],
                        'data' => $carrinho['data'],
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
                'mensagem' => 'Erro no banco.'
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
            // Verifica se existe um carrinho ativo para o usuário
            $stmtCarrinho = $conn->prepare("
                SELECT id 
                FROM carrinho 
                WHERE usuario_id = :usuario_id AND status = 'ativo'
            ");
            $stmtCarrinho->bindParam(':usuario_id', $userId);
            $stmtCarrinho->execute();
            $carrinho = $stmtCarrinho->fetch(PDO::FETCH_ASSOC);

            if (!$carrinho) {
                // Se não existir, cria um novo carrinho ativo
                $stmtNovoCarrinho = $conn->prepare("
                    INSERT INTO carrinho (usuario_id, data, status) 
                    VALUES (:usuario_id, NOW(), 'ativo')
                ");
                $stmtNovoCarrinho->bindParam(':usuario_id', $userId);
                $stmtNovoCarrinho->execute();
                $carrinhoId = $conn->lastInsertId();
            } else {
                $carrinhoId = $carrinho['id'];
            }

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

            // Verifica se o produto já está no carrinho
            $stmtItem = $conn->prepare("
                SELECT quantidade 
                FROM item_carrinho 
                WHERE carrinho_id = :carrinho_id AND produto_id = :produto_id
            ");
            $stmtItem->bindParam(':carrinho_id', $carrinhoId);
            $stmtItem->bindParam(':produto_id', $id_produto);
            $stmtItem->execute();
            $itemCarrinho = $stmtItem->fetch(PDO::FETCH_ASSOC);

            if ($sinal == 'remover') {
                // Remove o item do carrinho
                $stmtDelete = $conn->prepare("
                    DELETE FROM item_carrinho 
                    WHERE carrinho_id = :carrinho_id AND produto_id = :produto_id
                ");
                $stmtDelete->bindParam(':carrinho_id', $carrinhoId);
                $stmtDelete->bindParam(':produto_id', $id_produto);
                $stmtDelete->execute();

                return [
                    'status' => 'sucesso',
                    'mensagem' => 'Item removido do carrinho.'
                ];
            }

            if (!$itemCarrinho) {
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

                    // Adiciona um novo item ao carrinho
                    $stmtInsert = $conn->prepare("
                        INSERT INTO item_carrinho (carrinho_id, produto_id, quantidade, valor_unitario, data) 
                        VALUES (:carrinho_id, :produto_id, :quantidade_minima, :valor_unitario, NOW())
                    ");
                    $stmtInsert->bindParam(':carrinho_id', $carrinhoId);
                    $stmtInsert->bindParam(':produto_id', $id_produto);
                    $stmtInsert->bindParam(':quantidade_minima', $quantidadeMinima);
                    $stmtInsert->bindParam(':valor_unitario', $preco);
                    $stmtInsert->execute();

                    return [
                        'status' => 'sucesso',
                        'mensagem' => 'Produto adicionado com sucesso.'
                    ];
                }
            } else {
                // Atualiza a quantidade do item no carrinho
                $novaQuantidade = $itemCarrinho['quantidade'];

                if ($sinal == 'mais') {
                    $novaQuantidade += $quantidadeMinima;
                } elseif ($sinal == 'menos' && $novaQuantidade > $quantidadeMinima) {
                    $novaQuantidade -= $quantidadeMinima;
                } else {
                    // Remove o item se a quantidade for igual à mínima
                    $stmtDelete = $conn->prepare("
                        DELETE FROM item_carrinho 
                        WHERE carrinho_id = :carrinho_id AND produto_id = :produto_id
                    ");
                    $stmtDelete->bindParam(':carrinho_id', $carrinhoId);
                    $stmtDelete->bindParam(':produto_id', $id_produto);
                    $stmtDelete->execute();

                    return [
                        'status' => 'sucesso',
                        'mensagem' => 'Item removido do carrinho.'
                    ];
                }

                $stmtUpdate = $conn->prepare("
                    UPDATE item_carrinho 
                    SET quantidade = :quantidade, data = NOW() 
                    WHERE carrinho_id = :carrinho_id AND produto_id = :produto_id
                ");
                $stmtUpdate->bindParam(':quantidade', $novaQuantidade);
                $stmtUpdate->bindParam(':carrinho_id', $carrinhoId);
                $stmtUpdate->bindParam(':produto_id', $id_produto);
                $stmtUpdate->execute();

                return [
                    'status' => 'sucesso',
                    'mensagem' => 'Quantidade atualizada com sucesso.'
                ];
            }
        } catch (PDOException $e) {
            return [
                'status' => 'erro',
                'mensagem' => 'Erro no banco.'
            ];
        }
    }

    return [
        'status' => 'erro',
        'mensagem' => 'Conexão ao banco não disponível.'
    ];
}
function carregaInfoDashAdmin() {
    global $conn;

    if (!$conn) {
        return ['status' => 'erro', 'mensagem' => 'Conexão ao banco não disponível.'];
    }

    try {
        // Mês e ano atuais
        $mesAtual = date('Y-m');
        $anoAtual = date('Y');
        $diasDoMes = range(1, date('t'));
        $estados = ['RS', 'SC', 'PR'];

        // Vendas por estado
        $vendasPorEstado = [];
        foreach ($estados as $estado) {
            $dados = [];
            foreach ($diasDoMes as $dia) {
                $dataDia = $mesAtual . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT);
                $stmt = $conn->prepare("
                    SELECT SUM(i.quantidade * p.preco) AS total 
                    FROM pedidos ped
                    INNER JOIN itens_pedido i ON ped.id = i.pedido_id
                    INNER JOIN precos p ON i.produto_id = p.produto_id
                    WHERE DATE(ped.data_pedido) = :dataDia AND p.estado = :estado
                ");
                $stmt->bindParam(':dataDia', $dataDia);
                $stmt->bindParam(':estado', $estado);
                $stmt->execute();
                $dados[] = (float) $stmt->fetchColumn() ?: 0;
            }
            $vendasPorEstado[$estado] = $dados;
        }

        // Indicadores gerais
        $stmtClientes = $conn->query("SELECT COUNT(*) AS total FROM clientes WHERE tipo = 'cliente'");
        $totalClientes = $stmtClientes->fetchColumn();

        // Total de pedidos no mês (quantidade)
        $stmtPedidosMes = $conn->prepare("SELECT COUNT(*) AS total FROM pedidos WHERE DATE_FORMAT(data_pedido, '%Y-%m') = :mesAtual");
        $stmtPedidosMes->bindParam(':mesAtual', $mesAtual);
        $stmtPedidosMes->execute();
        $totalPedidosMes = (int)$stmtPedidosMes->fetchColumn();

        // Valor total das vendas no mês atual
        $stmtVendasMesAtual = $conn->prepare("SELECT SUM(valor_total) AS total FROM pedidos WHERE DATE_FORMAT(data_pedido, '%Y-%m') = :mesAtual");
        $stmtVendasMesAtual->bindParam(':mesAtual', $mesAtual);
        $stmtVendasMesAtual->execute();
        $vendasMesAtual = (float)$stmtVendasMesAtual->fetchColumn() ?: 0;

        // Média de valor por pedido no mês atual
        $mediaPorPedido = ($totalPedidosMes > 0) ? $vendasMesAtual / $totalPedidosMes : 0;

        // Mês anterior
        $mesAnterior = date('Y-m', strtotime($mesAtual . ' -1 month'));
        
        // Valor total das vendas no mês anterior
        $stmtVendasMesAnterior = $conn->prepare("SELECT SUM(valor_total) AS total FROM pedidos WHERE DATE_FORMAT(data_pedido, '%Y-%m') = :mesAnterior");
        $stmtVendasMesAnterior->bindParam(':mesAnterior', $mesAnterior);
        $stmtVendasMesAnterior->execute();
        $vendasMesAnterior = (float)$stmtVendasMesAnterior->fetchColumn() ?: 0;

        // Variação percentual das vendas
        $variacaoPercentual = ($vendasMesAnterior > 0) ? (($vendasMesAtual - $vendasMesAnterior) / $vendasMesAnterior) * 100 : 0;
        $variacaoFormatada = ($variacaoPercentual >= 0 ? '+' : '') . round($variacaoPercentual) . '%';

        // Total de pedidos no ano
        $stmtPedidosAno = $conn->prepare("SELECT COUNT(*) AS total FROM pedidos WHERE DATE_FORMAT(data_pedido, '%Y') = :anoAtual");
        $stmtPedidosAno->bindParam(':anoAtual', $anoAtual);
        $stmtPedidosAno->execute();
        $totalPedidosAno = $stmtPedidosAno->fetchColumn();

        // Vendas totais do mês (alternativa)
        $vendasTotaisMes = $vendasMesAtual;

        // Consolidando os dados para o dashboard
        $dadosDash = [
            'vendas_por_estado' => $vendasPorEstado,
            'indicadores' => [
                'total_clientes' => $totalClientes,
                'total_pedidos_mes' => $totalPedidosMes,
                'vendas_mes_atual' => number_format($vendasMesAtual, 2, ',', '.'),
                'media_por_pedido' => number_format($mediaPorPedido, 2, ',', '.'),
                'vendas_mes_anterior' => number_format($vendasMesAnterior, 2, ',', '.'),
                'variacao_vendas' => $variacaoFormatada,
                'total_pedidos_ano' => $totalPedidosAno,
                'vendas_totais_mes' => number_format($vendasTotaisMes, 2, ',', '.')
            ]
        ];

        return ['status' => 'sucesso', 'dados' => $dadosDash];

    } catch (PDOException $e) {
        return ['status' => 'erro', 'mensagem' => 'Erro no banco: ' . $e->getMessage()];
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
                'mensagem' => 'Erro no banco.'
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
                'mensagem' => 'Erro no banco.'
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
                'mensagem' => 'Erro no banco.'
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
            // Consulta os pedidos com informações gerais
            $stmt = $conn->prepare("
                SELECT 
                    pe.id AS pedido_id, 
                    pe.data_pedido, 
                    pe.status, 
                    pe.valor_total, 
                    c.id AS cliente_id, 
                    c.nome AS cliente_nome, 
                    c.email AS cliente_email
                FROM pedidos pe
                INNER JOIN clientes c ON pe.cliente_id = c.id
                ORDER BY pe.data_pedido DESC
            ");
            $stmt->execute();
            $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$pedidos) {
                return [
                    'status' => 'erro',
                    'mensagem' => 'Nenhum pedido encontrado.'
                ];
            }

            // Consulta os itens dos pedidos
            $stmt = $conn->prepare("
                SELECT 
                    ip.pedido_id, 
                    ip.produto_id, 
                    ip.quantidade, 
                    ip.preco_unitario, 
                    ip.total_item, 
                    p.nome AS produto_nome, 
                    p.codigo AS produto_codigo
                FROM itens_pedido ip
                INNER JOIN produtos p ON ip.produto_id = p.id
                ORDER BY ip.pedido_id
            ");
            $stmt->execute();
            $itensPedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Estruturar os dados com os itens associados a cada pedido
            $dadosPedidos = [];
            foreach ($pedidos as $pedido) {
                $pedidoId = $pedido['pedido_id'];
                $dadosPedidos[$pedidoId] = $pedido;
                $dadosPedidos[$pedidoId]['itens'] = [];
            }

            foreach ($itensPedidos as $item) {
                $pedidoId = $item['pedido_id'];
                if (isset($dadosPedidos[$pedidoId])) {
                    $dadosPedidos[$pedidoId]['itens'][] = $item;
                }
            }

            return [
                'status' => 'sucesso',
                'dados' => array_values($dadosPedidos)
            ];
        } catch (PDOException $e) {
            return [
                'status' => 'erro',
                'mensagem' => 'Erro no banco.'
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
            $stmt = $conn->prepare("SELECT id, ativo FROM produtos WHERE id = :id_produto");
            $stmt->bindParam(':id_produto', $id_produto, PDO::PARAM_STR); // Vincula o parâmetro nomeado
            $stmt->execute();
            $selectDestaivaProduto = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($selectDestaivaProduto) {

                $selectDestaivaProduto['ativo'] = (int)$selectDestaivaProduto['ativo'];

                if ($selectDestaivaProduto['ativo'] === 0) {
                    $statusReal = 1;
                    $resposta = 'Produto ativado com sucesso.';
                } else {
                    $statusReal = 0;
                    $resposta = 'Produto desativado com sucesso.';
                }
                $stmtUpdate = $conn->prepare("
                    UPDATE produtos SET ativo = $statusReal
                    WHERE id = :produto_id
                ");
                $stmtUpdate->bindParam(':produto_id', $id_produto);
                $stmtUpdate->execute();

                // Busca todos os registros
                $destaivaProduto = $stmtUpdate->fetchAll(PDO::FETCH_ASSOC);

                return [
                    'status' => 'sucesso',
                    'mensagem' => $resposta
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
                'mensagem' => 'Erro no banco.'
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
                'mensagem' => 'Erro no banco.'
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
                'mensagem' => 'Erro no banco.'
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
                'mensagem' => 'Erro no banco.'
            ];
        }
    }

    return [
        'status' => 'erro',
        'mensagem' => 'Conexão ao banco não disponível.'
    ];
}
function criarPedido($userId) {
    global $conn; // Usa a conexão global

    if ($conn) {
        try {
            // 1. Encontrar o carrinho ativo do usuário
            $stmt = $conn->prepare("SELECT id FROM carrinho WHERE usuario_id = :userId AND status = 'ativo' LIMIT 1");
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $carrinho = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$carrinho) {
                return [
                    'status' => 'erro',
                    'mensagem' => 'Nenhum carrinho ativo encontrado para o usuário.'
                ];
            }

            $carrinhoId = $carrinho['id'];

            // 2. Buscar itens do carrinho
            $stmt = $conn->prepare("SELECT ic.produto_id, ic.quantidade, ic.valor_unitario, 
                                    (ic.quantidade * ic.valor_unitario) AS total_item
                                    FROM item_carrinho ic
                                    WHERE ic.carrinho_id = :carrinhoId");
            $stmt->bindParam(':carrinhoId', $carrinhoId, PDO::PARAM_INT);
            $stmt->execute();
            $itensCarrinho = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($itensCarrinho)) {
                return [
                    'status' => 'erro',
                    'mensagem' => 'O carrinho está vazio.'
                ];
            }

            // 3. Calcular o valor total do pedido
            $valorTotal = 0;
            foreach ($itensCarrinho as $item) {
                $valorTotal += $item['total_item'];
            }

            // 4. Inserir o pedido na tabela 'pedidos'
            $stmt = $conn->prepare("INSERT INTO pedidos (cliente_id, valor_total, status) 
                                    VALUES (:userId, :valorTotal, 'pendente')");
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':valorTotal', $valorTotal, PDO::PARAM_STR);
            $stmt->execute();
            $pedidoId = $conn->lastInsertId(); // Pega o ID do pedido inserido

            // 5. Inserir os itens na tabela 'itens_pedido'
            foreach ($itensCarrinho as $item) {
                $stmt = $conn->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario, total_item) 
                                        VALUES (:pedidoId, :produtoId, :quantidade, :precoUnitario, :totalItem)");
                $stmt->bindParam(':pedidoId', $pedidoId, PDO::PARAM_INT);
                $stmt->bindParam(':produtoId', $item['produto_id'], PDO::PARAM_INT);
                $stmt->bindParam(':quantidade', $item['quantidade'], PDO::PARAM_INT);
                $stmt->bindParam(':precoUnitario', $item['valor_unitario'], PDO::PARAM_STR);
                $stmt->bindParam(':totalItem', $item['total_item'], PDO::PARAM_STR);
                $stmt->execute();
            }

            // 6. Alterar o status do carrinho para 'finalizado'
            $stmt = $conn->prepare("UPDATE carrinho SET status = 'finalizado' WHERE id = :carrinhoId");
            $stmt->bindParam(':carrinhoId', $carrinhoId, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'status' => 'sucesso',
                'mensagem' => 'Pedido criado com sucesso.',
                'pedido_id' => $pedidoId
            ];
        } catch (PDOException $e) {
            return [
                'status' => 'erro',
                'mensagem' => 'Erro no banco.'
            ];
        }
    }

    return [
        'status' => 'erro',
        'mensagem' => 'Conexão ao banco não disponível.'
    ];
}
function editaUsuario($id, $nome, $email, $estado, $regime) {
    global $conn; // Usa a conexão global

    if ($conn) {
        try {
            $stmt = $conn->prepare("SELECT * FROM clientes WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $verificaUsuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$verificaUsuario) {
                return [
                    'status' => 'erro',
                    'mensagem' => 'Nenhum usuário encontrado.'
                ];
            }

            $stmt = $conn->prepare("UPDATE clientes SET nome = :nome, email = :email, estado = :estado, regime = :regime WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':nome', $nome, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
            $stmt->bindParam(':regime', $regime, PDO::PARAM_STR);
            $stmt->execute();

            return [
                'status' => 'sucesso',
                'mensagem' => 'Usuário atualizado com sucesso.'
            ];
        } catch (PDOException $e) {
            return [
                'status' => 'erro',
                'mensagem' => 'Erro no banco ao alterar usuário.'
            ];
        }
    }

    return [
        'status' => 'erro',
        'mensagem' => 'Conexão ao banco não disponível.'
    ];
}
function alteraSenhaUser($id, $nova_senha) {
    global $conn; // Usa a conexão global
    
    if (strlen($nova_senha) <= 8) {
        return [
            'status' => 'erro',
            'mensagem' => 'A senha deve conter mais de 8 caracteres.'
        ];
    }

    if ($conn) {
        try {
            $stmt = $conn->prepare("SELECT * FROM clientes WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $verificaUsuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$verificaUsuario) {
                return [
                    'status' => 'erro',
                    'mensagem' => 'Nenhum usuário encontrado.'
                ];
            }

            $senhaHash = password_hash($nova_senha, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE clientes SET senha = :nova_senha_cripto WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':nova_senha_cripto', $senhaHash, PDO::PARAM_STR);
            $stmt->execute();

            return [
                'status' => 'sucesso',
                'mensagem' => 'Usuário atualizado com sucesso.'
            ];
        } catch (PDOException $e) {
            return [
                'status' => 'erro',
                'mensagem' => 'Erro no banco ao alterar usuário.'
            ];
        }
    }

    return [
        'status' => 'erro',
        'mensagem' => 'Conexão ao banco não disponível.'
    ];
}


?>
