<?php
// Inclui o arquivo de proteção de autenticação
// É CRUCIAL QUE session_start() ESTEJA DENTRO DE protect.php OU AQUI NO INÍCIO!
// Se não estiver, adicione session_start(); logo abaixo desta linha.
include(__DIR__ . '/../auth/protect.php');
// Inclui o arquivo de conexão com o banco de dados
include(__DIR__ . '/../config/conexao.php');

// Inicializa a mensagem de feedback
$mensagem = "";

// --- Lógica para NOVO PEDIDO (CREATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['salvar_pedido'])) {
    // Validar e escapar as entradas do usuário
    $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
    $mesa = isset($_POST['mesa']) ? $mysqli->real_escape_string($_POST['mesa']) : null;
    $produtos_selecionados = isset($_POST['produtos']) ? $_POST['produtos'] : []; // Array de produtos
    $quantidades_selecionadas = isset($_POST['quantidades']) ? $_POST['quantidades'] : []; // Array de quantidades

    // Validação inicial
    if ($cliente_id <= 0) {
        $_SESSION['mensagem_erro'] = "Erro: Por favor, selecione um cliente.";
    } elseif (empty($produtos_selecionados) || empty($quantidades_selecionadas)) {
        $_SESSION['mensagem_erro'] = "Erro: Adicione pelo menos um produto ao pedido.";
    } else {
        $mysqli->begin_transaction();
        try {
            $valor_total_pedido = 0;
            $itens_processados = []; // Para armazenar informações dos itens antes de inserir

            // 1. Validar e coletar informações de todos os produtos selecionados
            foreach ($produtos_selecionados as $index => $produto_id) {
                $produto_id = intval($produto_id);
                $quantidade = intval($quantidades_selecionadas[$index]);

                if ($produto_id <= 0 || $quantidade <= 0) {
                    throw new Exception("Erro: Produto ou quantidade inválidos para o item " . ($index + 1) . ".");
                }

                $stmt_produto = $mysqli->prepare("SELECT nome, valor, quantidade AS estoque_disponivel FROM estoque WHERE produto_id = ?");
                if (!$stmt_produto) {
                    throw new Exception("Erro ao preparar consulta de produto: " . $mysqli->error);
                }
                $stmt_produto->bind_param("i", $produto_id);
                $stmt_produto->execute();
                $result_produto = $stmt_produto->get_result();
                $produto_info = $result_produto->fetch_assoc();
                $stmt_produto->close();

                if (!$produto_info) {
                    throw new Exception("Produto não encontrado no estoque para o item " . ($index + 1) . ".");
                }

                if ($produto_info['estoque_disponivel'] < $quantidade) {
                    throw new Exception("Estoque insuficiente para o produto '" . htmlspecialchars($produto_info['nome']) . "'. Disponível: " . $produto_info['estoque_disponivel'] . " und.");
                }

                $itens_processados[] = [
                    'produto_id' => $produto_id,
                    'quantidade' => $quantidade,
                    'produto_nome' => $produto_info['nome'],
                    'valor_unitario' => $produto_info['valor'],
                    'valor_total_item' => $produto_info['valor'] * $quantidade
                ];
                $valor_total_pedido += ($produto_info['valor'] * $quantidade);
            }

            // 2. Inserir o pedido na tabela 'pedidos'
            $data_pedido = date("Y-m-d"); // Data atual
            $stmt_pedido = $mysqli->prepare("INSERT INTO pedidos (cliente_id, data_pedido, valor, mesa, status) VALUES (?, ?, ?, ?, 'aberto')");
            if (!$stmt_pedido) {
                throw new Exception("Erro ao preparar inserção de pedido: " . $mysqli->error);
            }
            $stmt_pedido->bind_param("isds", $cliente_id, $data_pedido, $valor_total_pedido, $mesa);
            $stmt_pedido->execute();
            $pedido_id = $mysqli->insert_id; // Pega o ID do pedido recém-criado
            $stmt_pedido->close();

            // 3. Inserir os itens do pedido e dar baixa no estoque
            $stmt_item_pedido = $mysqli->prepare("INSERT INTO item_pedido (produto_id, pedido_id, quantidade, produto_nome) VALUES (?, ?, ?, ?)");
            $stmt_update_estoque = $mysqli->prepare("UPDATE estoque SET quantidade = quantidade - ? WHERE produto_id = ?");

            if (!$stmt_item_pedido || !$stmt_update_estoque) {
                throw new Exception("Erro ao preparar inserção de item_pedido ou atualização de estoque: " . $mysqli->error);
            }

            foreach ($itens_processados as $item) {
                // Inserir item
                $stmt_item_pedido->bind_param("iiis", $item['produto_id'], $pedido_id, $item['quantidade'], $item['produto_nome']);
                $stmt_item_pedido->execute();

                // Dar baixa no estoque
                $stmt_update_estoque->bind_param("ii", $item['quantidade'], $item['produto_id']);
                $stmt_update_estoque->execute();
            }

            $stmt_item_pedido->close();
            $stmt_update_estoque->close();

            $mysqli->commit();
            $_SESSION['mensagem_sucesso'] = "Pedido criado com sucesso! (ID: " . $pedido_id . ")";
            header("Location: pedidos.php");
            exit();

        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['mensagem_erro'] = "Erro ao criar pedido: " . $e->getMessage();
            header("Location: pedidos.php");
            exit();
        }
    }
    header("Location: pedidos.php");
    exit();
}

// --- Lógica para EXCLUSÃO (DELETE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['excluir_pedido'])) {
    $pedido_id_excluir = isset($_POST['pedido_id']) ? intval($_POST['pedido_id']) : 0;

    if ($pedido_id_excluir > 0) {
        $mysqli->begin_transaction();
        try {
            // Reverter estoque dos itens do pedido ANTES de excluir
            $stmt_get_items = $mysqli->prepare("SELECT produto_id, quantidade FROM item_pedido WHERE pedido_id = ?");
            if (!$stmt_get_items) {
                throw new Exception("Erro ao preparar busca de itens para reverter estoque: " . $mysqli->error);
            }
            $stmt_get_items->bind_param("i", $pedido_id_excluir);
            $stmt_get_items->execute();
            $result_get_items = $stmt_get_items->get_result();
            $items_to_revert = [];
            while($row = $result_get_items->fetch_assoc()) {
                $items_to_revert[] = $row;
            }
            $stmt_get_items->close();

            // Reverter estoque
            foreach($items_to_revert as $item) {
                $stmt_revert_estoque = $mysqli->prepare("UPDATE estoque SET quantidade = quantidade + ? WHERE produto_id = ?");
                if (!$stmt_revert_estoque) {
                    throw new Exception("Erro ao preparar reversão de estoque: " . $mysqli->error);
                }
                $stmt_revert_estoque->bind_param("ii", $item['quantidade'], $item['produto_id']);
                $stmt_revert_estoque->execute();
                $stmt_revert_estoque->close();
            }

            // Primeiro, excluir os itens relacionados ao pedido
            $stmt_delete_items = $mysqli->prepare("DELETE FROM item_pedido WHERE pedido_id = ?");
            if (!$stmt_delete_items) {
                throw new Exception("Erro ao preparar exclusão de itens: " . $mysqli->error);
            }
            $stmt_delete_items->bind_param("i", $pedido_id_excluir);
            $stmt_delete_items->execute();
            $stmt_delete_items->close();

            // Depois, excluir o pedido em si
            $stmt_delete_pedido = $mysqli->prepare("DELETE FROM pedidos WHERE pedido_id = ?");
            if (!$stmt_delete_pedido) {
                throw new Exception("Erro ao preparar exclusão de pedido: " . $mysqli->error);
            }
            $stmt_delete_pedido->bind_param("i", $pedido_id_excluir);
            $stmt_delete_pedido->execute();
            $stmt_delete_pedido->close();

            $mysqli->commit();
            $_SESSION['mensagem_sucesso'] = "Pedido excluído com sucesso!";
            header("Location: pedidos.php");
            exit();
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['mensagem_erro'] = "Erro ao excluir pedido: " . $e->getMessage();
            header("Location: pedidos.php");
            exit();
        }
    } else {
        $_SESSION['mensagem_erro'] = "Erro: ID do pedido para exclusão inválido.";
        header("Location: pedidos.php");
        exit();
    }
}

// --- Lógica para CONCLUIR PEDIDO (UPDATE status) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['concluir_pedido'])) {
    $pedido_id_concluir = isset($_POST['pedido_id']) ? intval($_POST['pedido_id']) : 0;

    if ($pedido_id_concluir > 0) {
        $stmt_concluir = $mysqli->prepare("UPDATE pedidos SET status = 'concluido' WHERE pedido_id = ?");
        if (!$stmt_concluir) {
            $_SESSION['mensagem_erro'] = "Erro ao preparar conclusão do pedido: " . $mysqli->error;
        } else {
            $stmt_concluir->bind_param("i", $pedido_id_concluir);
            if ($stmt_concluir->execute()) {
                $_SESSION['mensagem_sucesso'] = "Pedido #" . $pedido_id_concluir . " concluído com sucesso!";
            } else {
                $_SESSION['mensagem_erro'] = "Erro ao concluir pedido: " . $stmt_concluir->error;
            }
            $stmt_concluir->close();
        }
    } else {
        $_SESSION['mensagem_erro'] = "Erro: ID do pedido para conclusão inválido.";
    }
    header("Location: pedidos.php");
    exit();
}

// --- Lógica para EDIÇÃO (UPDATE) - MÚLTIPLOS PRODUTOS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['editar_pedido'])) {
    $pedido_id_editar = isset($_POST['edit_pedido_id']) ? intval($_POST['edit_pedido_id']) : 0;
    $novo_cliente_id = isset($_POST['edit_cliente_id']) ? intval($_POST['edit_cliente_id']) : 0;
    $nova_mesa = isset($_POST['edit_mesa']) ? $mysqli->real_escape_string($_POST['edit_mesa']) : null;
    $novos_produtos_ids = isset($_POST['edit_produtos']) ? $_POST['edit_produtos'] : [];
    $novas_quantidades = isset($_POST['edit_quantidades']) ? $_POST['edit_quantidades'] : [];

    if ($pedido_id_editar <= 0 || $novo_cliente_id <= 0 || empty($novos_produtos_ids) || empty($novas_quantidades)) {
        $_SESSION['mensagem_erro'] = "Erro: Dados inválidos para edição do pedido. Verifique cliente, produtos, quantidades e ID do pedido.";
        header("Location: pedidos.php");
        exit();
    } else {
        $mysqli->begin_transaction();
        try {
            // 1. Reverter estoque dos itens ATUAIS do pedido
            $stmt_get_old_items = $mysqli->prepare("SELECT produto_id, quantidade FROM item_pedido WHERE pedido_id = ?");
            if (!$stmt_get_old_items) {
                throw new Exception("Erro ao preparar busca de itens antigos para reversão: " . $mysqli->error);
            }
            $stmt_get_old_items->bind_param("i", $pedido_id_editar);
            $stmt_get_old_items->execute();
            $result_old_items = $stmt_get_old_items->get_result();
            while ($row = $result_old_items->fetch_assoc()) {
                $stmt_revert_estoque = $mysqli->prepare("UPDATE estoque SET quantidade = quantidade + ? WHERE produto_id = ?");
                if (!$stmt_revert_estoque) {
                    throw new Exception("Erro ao preparar reversão de estoque antigo: " . $mysqli->error);
                }
                $stmt_revert_estoque->bind_param("ii", $row['quantidade'], $row['produto_id']);
                $stmt_revert_estoque->execute();
                $stmt_revert_estoque->close();
            }
            $stmt_get_old_items->close();

            // 2. Deletar todos os itens antigos do pedido
            $stmt_delete_old_items = $mysqli->prepare("DELETE FROM item_pedido WHERE pedido_id = ?");
            if (!$stmt_delete_old_items) {
                throw new Exception("Erro ao preparar exclusão de itens antigos: " . $mysqli->error);
            }
            $stmt_delete_old_items->bind_param("i", $pedido_id_editar);
            $stmt_delete_old_items->execute();
            $stmt_delete_old_items->close();

            $novo_valor_total_pedido = 0;
            $novos_itens_processados = [];

            // 3. Validar e coletar informações dos NOVOS produtos selecionados
            foreach ($novos_produtos_ids as $index => $produto_id) {
                $produto_id = intval($produto_id);
                $quantidade = intval($novas_quantidades[$index]);

                if ($produto_id <= 0 || $quantidade <= 0) {
                    throw new Exception("Erro: Produto ou quantidade inválidos para o novo item " . ($index + 1) . ".");
                }

                $stmt_produto_info = $mysqli->prepare("SELECT nome, valor, quantidade AS estoque_disponivel FROM estoque WHERE produto_id = ?");
                if (!$stmt_produto_info) {
                    throw new Exception("Erro ao preparar consulta de novo produto: " . $mysqli->error);
                }
                $stmt_produto_info->bind_param("i", $produto_id);
                $stmt_produto_info->execute();
                $result_produto_info = $stmt_produto_info->get_result();
                $produto_info = $result_produto_info->fetch_assoc();
                $stmt_produto_info->close();

                if (!$produto_info) {
                    throw new Exception("Novo produto para edição não encontrado para o item " . ($index + 1) . ".");
                }

                // A verificação de estoque deve ser feita após a reversão, então $produto_info['estoque_disponivel'] já reflete o estoque atualizado.
                if ($produto_info['estoque_disponivel'] < $quantidade) {
                    throw new Exception("Estoque insuficiente para a nova quantidade do produto '" . htmlspecialchars($produto_info['nome']) . "'. Disponível: " . $produto_info['estoque_disponivel'] . " und.");
                }

                $novos_itens_processados[] = [
                    'produto_id' => $produto_id,
                    'quantidade' => $quantidade,
                    'produto_nome' => $produto_info['nome'],
                    'valor_unitario' => $produto_info['valor'],
                    'valor_total_item' => $produto_info['valor'] * $quantidade
                ];
                $novo_valor_total_pedido += ($produto_info['valor'] * $quantidade);
            }

            // 4. Inserir os NOVOS itens do pedido e dar baixa no estoque
            $stmt_insert_new_item = $mysqli->prepare("INSERT INTO item_pedido (produto_id, pedido_id, quantidade, produto_nome) VALUES (?, ?, ?, ?)");
            $stmt_deduct_new_estoque = $mysqli->prepare("UPDATE estoque SET quantidade = quantidade - ? WHERE produto_id = ?");

            if (!$stmt_insert_new_item || !$stmt_deduct_new_estoque) {
                throw new Exception("Erro ao preparar inserção de novo item ou dedução de estoque: " . $mysqli->error);
            }

            foreach ($novos_itens_processados as $item) {
                // Inserir novo item
                $stmt_insert_new_item->bind_param("iiis", $item['produto_id'], $pedido_id_editar, $item['quantidade'], $item['produto_nome']);
                $stmt_insert_new_item->execute();

                // Dar baixa no estoque
                $stmt_deduct_new_estoque->bind_param("ii", $item['quantidade'], $item['produto_id']);
                $stmt_deduct_new_estoque->execute();
            }

            $stmt_insert_new_item->close();
            $stmt_deduct_new_estoque->close();

            // 5. Atualizar o pedido principal (cliente, mesa, valor total)
            $stmt_update_pedido = $mysqli->prepare("UPDATE pedidos SET cliente_id = ?, valor = ?, mesa = ? WHERE pedido_id = ?");
            if (!$stmt_update_pedido) {
                throw new Exception("Erro ao preparar atualização de pedido principal: " . $mysqli->error);
            }
            $stmt_update_pedido->bind_param("idsi", $novo_cliente_id, $novo_valor_total_pedido, $nova_mesa, $pedido_id_editar);
            $stmt_update_pedido->execute();
            $stmt_update_pedido->close();

            $mysqli->commit();
            $_SESSION['mensagem_sucesso'] = "Pedido atualizado com sucesso!";
            header("Location: pedidos.php");
            exit();

        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['mensagem_erro'] = "Erro ao atualizar pedido: " . $e->getMessage();
            header("Location: pedidos.php");
            exit();
        }
    }
}

// --- Lógica para LEITURA (READ) e FILTRO ---
$pedidos = []; // Array para armazenar os pedidos a serem exibidos

// Parâmetros de filtro
$filtro_nr_pedido = isset($_GET['filtro_nr_pedido']) ? $mysqli->real_escape_string($_GET['filtro_nr_pedido']) : '';
$filtro_nome_cliente = isset($_GET['filtro_nome_cliente']) ? $mysqli->real_escape_string($_GET['filtro_nome_cliente']) : '';
$filtro_data_pedido = isset($_GET['filtro_data_pedido']) ? $mysqli->real_escape_string($_GET['filtro_data_pedido']) : '';
$filtro_mesa = isset($_GET['filtro_mesa']) ? $mysqli->real_escape_string($_GET['filtro_mesa']) : '';

// SQL para buscar pedidos e seus itens (usando JOINs)
// NOTA: Para exibir múltiplos itens por pedido no card, precisamos agrupar e concatenar os itens.
// O JOIN com item_pedido pode duplicar linhas se um pedido tiver múltiplos itens.
// Precisamos um GROUP_CONCAT no item_pedido para que cada pedido apareça como uma única linha.
$sql_select = "
    SELECT
        p.pedido_id,
        p.data_pedido,
        p.valor AS valor_total_pedido,
        p.mesa,
        p.status,
        c.nome AS cliente_nome,
        c.email AS cliente_email,
        c.telefone AS cliente_telefone,
        c.cliente_id,
        GROUP_CONCAT(CONCAT(ip.produto_nome, ' (', ip.quantidade, ' und)') SEPARATOR '<br>') AS itens_pedido_display,
        GROUP_CONCAT(ip.produto_id) AS produto_ids_raw,
        GROUP_CONCAT(ip.quantidade) AS quantidades_raw
    FROM
        pedidos p
    JOIN
        clientes c ON p.cliente_id = c.cliente_id
    LEFT JOIN
        item_pedido ip ON p.pedido_id = ip.pedido_id
    WHERE p.status = 'aberto'
"; // Apenas pedidos com status 'aberto'

$params = [];
$types = "";

// Adicionar condições de filtro se houver
if (!empty($filtro_nr_pedido)) {
    $sql_select .= " AND p.pedido_id LIKE ?";
    $params[] = "%" . $filtro_nr_pedido . "%";
    $types .= "s";
}
if (!empty($filtro_nome_cliente)) {
    $sql_select .= " AND c.nome LIKE ?";
    $params[] = "%" . $filtro_nome_cliente . "%";
    $types .= "s";
}
if (!empty($filtro_data_pedido)) {
    $sql_select .= " AND p.data_pedido = ?";
    $params[] = $filtro_data_pedido;
    $types .= "s";
}
if (!empty($filtro_mesa)) {
    $sql_select .= " AND p.mesa LIKE ?";
    $params[] = "%" . $filtro_mesa . "%";
    $types .= "s";
}

$sql_select .= " GROUP BY p.pedido_id, p.data_pedido, p.valor, p.mesa, p.status, c.nome, c.email, c.telefone, c.cliente_id ORDER BY p.pedido_id DESC";

if ($stmt = $mysqli->prepare($sql_select)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $pedidos[] = $row;
    }
    $stmt->close();
} else {
    $_SESSION['mensagem_erro'] = (isset($_SESSION['mensagem_erro']) ? $_SESSION['mensagem_erro'] . " | " : "") . "Erro ao buscar pedidos (prepare): " . $mysqli->error;
}


// --- Buscar Clientes e Produtos para os Selects dos Formulários (Novo e Edição) ---
$clientes_disponiveis = [];
$sql_clientes = "SELECT cliente_id, nome FROM clientes ORDER BY nome ASC";
if ($result_clientes = $mysqli->query($sql_clientes)) {
    while ($row = $result_clientes->fetch_assoc()) {
        $clientes_disponiveis[] = $row;
    }
    $result_clientes->free();
} else {
    $_SESSION['mensagem_erro'] = (isset($_SESSION['mensagem_erro']) ? $_SESSION['mensagem_erro'] . " | " : "") . "Erro ao buscar clientes para dropdown: " . $mysqli->error;
}

$produtos_disponiveis = [];
$sql_produtos = "SELECT produto_id, nome FROM estoque ORDER BY nome ASC";
if ($result_produtos = $mysqli->query($sql_produtos)) {
    while ($row = $result_produtos->fetch_assoc()) {
        $produtos_disponiveis[] = $row;
    }
    $result_produtos->free();
} else {
    $_SESSION['mensagem_erro'] = (isset($_SESSION['mensagem_erro']) ? $_SESSION['mensagem_erro'] . " | " : "") . "Erro ao buscar produtos para dropdown: " . $mysqli->error;
}

// Fechar a conexão com o banco de dados no final do script
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}

// --- Exibição de Mensagens de Sucesso/Erro e Limpeza da Sessão ---
if (isset($_SESSION['mensagem_sucesso'])) {
    $mensagem = htmlspecialchars($_SESSION['mensagem_sucesso']);
    unset($_SESSION['mensagem_sucesso']); // Limpa a mensagem da sessão
} elseif (isset($_SESSION['mensagem_erro'])) {
    $mensagem = htmlspecialchars($_SESSION['mensagem_erro']);
    unset($_SESSION['mensagem_erro']); // Limpa a mensagem da sessão
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Acompanhamento de Pedidos</title>
    <link rel="stylesheet" href="../public/css/pedidos.css">
    <style>
        /* Ajustes de CSS para os cards e botões */
        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            padding-bottom: 50px;
        }

        .card {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 30px;
            background-color: white;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 220px;
        }

        .card p {
            margin-bottom: 12px;
            line-height: 1.6;
        }

        .card p strong {
            color: #333;
        }

        /* Tags de informação (Mesa e Data) */
        .card .info-tag {
            position: absolute;
            background-color: #f0f0f0;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 0.9em;
            color: #555;
            white-space: nowrap;
            z-index: 1;
        }

        .card .info-tag:first-of-type {
            top: 25px;
            right: 25px;
        }

        .card .info-tag:nth-of-type(2) {
            top: 65px;
            right: 25px;
        }

        /* Cores dos botões */
        .btn-edit {
            padding: 10px 18px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px;
            font-size: 0.95em;
            transition: background-color 0.3s ease;
        }

        .btn-edit:hover {
            background-color: #218838;
        }

        .btn-delete {
            padding: 10px 18px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.95em;
            transition: background-color 0.3s ease;
        }

        .btn-delete:hover {
            background-color: #c82333;
        }

        .btn-finish { /* Novo estilo para botão de concluir */
            padding: 10px 18px;
            background: #8C5B4F; /* Azul para concluir */
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px;
            font-size: 0.95em;
            transition: background-color 0.3s ease;
        }

        .btn-finish:hover {
            background-color: #0056b3;
        }


        /* Estilo para o modal de edição (similar ao de novo pedido) */
        #popupNovoPedido,
        #popupEditarPedido {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        #popupNovoPedido .modal-content,
        #popupEditarPedido .modal-content {
            width: 655px;
            background: #F6F6F6;
            border-radius: 10px;
            padding: 32px 25px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        #popupNovoPedido .modal-header,
        #popupEditarPedido .modal-header {
            background: white;
            border-radius: 8px;
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        #popupNovoPedido .modal-header .title,
        #popupEditarPedido .modal-header .title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        #popupNovoPedido .modal-header .title span,
        #popupEditarPedido .modal-header .title span {
            font-size: 20px;
            cursor: pointer;
        }

        #popupNovoPedido .modal-header h2,
        #popupEditarPedido .modal-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #344054;
            margin: 0;
        }

        #popupNovoPedido .modal-header p,
        #popupEditarPedido .modal-header p {
            font-size: 15px;
            font-weight: 400;
            color: #667085;
            margin: 0;
        }

        #popupNovoPedido form,
        #popupEditarPedido form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        #popupNovoPedido input,
        #popupNovoPedido select,
        #popupEditarPedido input,
        #popupEditarPedido select {
            padding: 12px 16px;
            font-size: 15px;
            border: 1px solid #D0D5DD;
            border-radius: 7px;
            background: white;
            color: #495057;
            outline: none;
        }

        #popupNovoPedido input::placeholder,
        #popupEditarPedido input::placeholder {
            color: #ADB5BD;
        }

        #popupNovoPedido button[type="submit"],
        #popupEditarPedido button[type="submit"] {
            padding: 12px 28px;
            background: #9D6A5E;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        #popupNovoPedido button[type="submit"]:hover,
        #popupEditarPedido button[type="submit"]:hover {
            background-color: #8C5B4F;
        }

        /* Estilos para os campos de produto dinâmicos */
        .product-item {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .product-item select,
        .product-item input[type="number"] {
            flex: 1;
        }

        .product-item button {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .product-item button:hover {
            background-color: #c82333;
        }

        #add-product-btn, #edit-add-product-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
            transition: background-color 0.3s ease;
        }

        #add-product-btn:hover, #edit-add-product-btn:hover {
            background-color: #0056b3;
        }

        /* Scroll da tela */
        body {
            height: 100vh;
            display: flex;
            flex-direction: row;
        }

        .main {
            flex-grow: 1;
            overflow-y: auto;
            padding: 20px;
            box-sizing: border-box;
        }

        /* Filtro bar - Mantenho aqui porque é parte do layout do main */
        .filter-bar {
            background-color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .filter-bar select,
        .filter-bar input[type="date"],
        .filter-bar input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
        }

        .filter-bar button.btn {
            background: #6c757d;
            transition: background-color 0.3s ease;
        }

        .filter-bar button.btn:hover {
            background: #5a6268;
        }
    </style>
</head>

<body>

    <nav>
        <a href="dashboard.php">
            <img src="../public/img/logo.png" alt="Logo Alvorada" />
        </a>

        <div class="nav-item">
            <img src="../public/icons/pedidos.png" alt="Ícone Pedidos" />
            <span>
                <a href="pedidos.php">Pedidos</a>
            </span>
        </div>

        <div class="nav-item">
            <img src="../public/icons/clientes.png" alt="Ícone Clientes" />
            <span>
                <a href="clientes.php">Clientes</a>
            </span>
        </div>

        <div class="nav-item">
            <img src="../public/icons/estoque.png" alt="Ícone Estoque" />
            <span>
                <a href="estoque.php">Estoque</a>
            </span>
        </div>

        <div class="nav-item">
            <img src="../public/icons/licenca.png" alt="Ícone Licenças" />
            <span>
                <a href="licencas.php">Licenças</a>
            </span>
        </div>

        <div class="nav-item logout">
            <span>
                <a href="../auth/logout.php">Sair</a>
            </span>
        </div>

    </nav>

    <div class="main">
        <div class="header">
            <b><?php echo $_SESSION['nome_usuario'] ?? 'Usuário'; ?></b>
            <p class="subtext">Nesta área são relacionados os pedidos em andamento</p>
        </div>

        <?php if (!empty($mensagem)): ?>
            <p style="color: <?php echo (strpos($mensagem, 'Erro:') === 0 ? 'red' : 'green'); ?>; font-weight: bold; text-align: center; margin-bottom: 15px;"><?php echo $mensagem; ?></p>
        <?php endif; ?>

        <form method="GET" action="pedidos.php" class="filter-bar" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="text" name="filtro_nr_pedido" placeholder="Nr. Pedido" value="<?php echo htmlspecialchars($filtro_nr_pedido); ?>" style="flex: 1; min-width: 120px;">
            <select name="filtro_nome_cliente" style="flex: 1; min-width: 150px;">
                <option value="">Nome do cliente</option>
                <?php foreach ($clientes_disponiveis as $cliente): ?>
                    <option value="<?php echo htmlspecialchars($cliente['nome']); ?>"
                        <?php echo ($filtro_nome_cliente == $cliente['nome'] ? 'selected' : ''); ?>>
                        <?php echo htmlspecialchars($cliente['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="filtro_data_pedido" value="<?php echo htmlspecialchars($filtro_data_pedido); ?>" style="flex: 1; min-width: 150px;">
            <input type="text" name="filtro_mesa" placeholder="Filtrar por Mesa" value="<?php echo htmlspecialchars($filtro_mesa); ?>" style="flex: 1; min-width: 120px;">
            <button type="submit" class="btn" style="padding: 10px 20px; background: #9e7563; color: white; border: none; border-radius: 6px; cursor: pointer;">Filtrar</button>
            <button type="button" onclick="document.getElementById('popupNovoPedido').style.display='flex'"
                style="padding: 10px 20px; background: #9e7563; color: white; border: none; border-radius: 6px; cursor: pointer;">
                Novo Pedido
            </button>
        </form>

        <div id="popupNovoPedido">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="title">
                        <span onclick="document.getElementById('popupNovoPedido').style.display='none'">←</span>
                        <h2>Novo pedido</h2>
                    </div>
                    <p>Realize um novo pedido</p>
                </div>

                <form method="POST" action="pedidos.php">
                    <label for="cliente_id" style="font-size: 14px; color: #667085;">Selecione o Cliente:</label>
                    <select name="cliente_id" id="cliente_id" required>
                        <option value="" disabled selected>-- Selecione um cliente --</option>
                        <?php foreach ($clientes_disponiveis as $cliente): ?>
                            <option value="<?php echo htmlspecialchars($cliente['cliente_id']); ?>">
                                <?php echo htmlspecialchars($cliente['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label style="font-size: 14px; color: #667085;">Itens do Pedido:</label>
                    <div id="product-items-container">
                        <div class="product-item">
                            <select name="produtos[]" required>
                                <option value="" disabled selected>-- Selecione um produto --</option>
                                <?php foreach ($produtos_disponiveis as $produto): ?>
                                    <option value="<?php echo htmlspecialchars($produto['produto_id']); ?>">
                                        <?php echo htmlspecialchars($produto['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="quantidades[]" placeholder="Qtd" min="1" value="1" required style="width: 80px;">
                            <button type="button" class="remove-product-btn">Remover</button>
                        </div>
                    </div>
                    <button type="button" id="add-product-btn">Adicionar Outro Produto</button>

                    <label for="mesa" style="font-size: 14px; color: #667085;">Mesa:</label>
                    <input type="text" name="mesa" id="mesa" placeholder="Mesa (Ex: Mesa 03)">

                    <div style="display: flex; justify-content: flex-end; margin-top: 16px;">
                        <button type="submit" name="salvar_pedido">Salvar Pedido</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="cards">
            <?php if (empty($pedidos)): ?>
                <p style="text-align: center; margin-top: 20px; width: 100%;">Nenhum pedido encontrado. Crie um novo pedido!</p>
            <?php else: ?>
                <?php foreach ($pedidos as $pedido): ?>
                    <div class="card">
                        <p><strong>Pedido Nº <?php echo htmlspecialchars($pedido['pedido_id']); ?></strong></p>
                        <p>
                            <?php echo $pedido['itens_pedido_display'] ?? ''; ?>
                        </p>
                        <p><strong>Total do Pedido:</strong> R$ <?php echo number_format($pedido['valor_total_pedido'] ?? 0, 2, ',', '.'); ?></p>
                        <p><strong>Cliente:</strong> <?php echo htmlspecialchars($pedido['cliente_nome'] ?? ''); ?><br>
                            <strong>E-mail:</strong> <?php echo htmlspecialchars($pedido['cliente_email'] ?? ''); ?><br>
                            <strong>Telefone:</strong> <?php echo htmlspecialchars($pedido['cliente_telefone'] ?? ''); ?>
                        </p>
                        <div class="info-tag">Mesa <?php echo htmlspecialchars($pedido['mesa'] ?? ''); ?></div>
                        <div class="info-tag" style="top: 65px;">Data: <?php echo date('d/m/Y', strtotime($pedido['data_pedido'] ?? 'now')); ?></div>

                        <div style="margin-top: 10px;">
                            <form method="POST" action="pedidos.php" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja concluir este pedido? Ele não aparecerá mais na lista.');">
                                <input type="hidden" name="pedido_id" value="<?php echo htmlspecialchars($pedido['pedido_id'] ?? 0); ?>">
                                <button type="submit" name="concluir_pedido" class="btn-finish">Concluir</button>
                            </form>
                            <button class="btn-edit"
                                onclick="openEditModal(
                                        <?php echo htmlspecialchars(json_encode($pedido['pedido_id'] ?? 0)); ?>,
                                        <?php echo htmlspecialchars(json_encode($pedido['cliente_id'] ?? 0)); ?>,
                                        <?php echo htmlspecialchars(json_encode($pedido['mesa'] ?? '')); ?>,
                                        <?php echo htmlspecialchars(json_encode(explode(',', $pedido['produto_ids_raw'] ?? ''))); ?>,
                                        <?php echo htmlspecialchars(json_encode(explode(',', $pedido['quantidades_raw'] ?? ''))); ?>
                                    )">
                                Editar
                            </button>
                            <form method="POST" action="pedidos.php" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir este pedido?');">
                                <input type="hidden" name="pedido_id" value="<?php echo htmlspecialchars($pedido['pedido_id'] ?? 0); ?>">
                                <button type="submit" name="excluir_pedido" class="btn-delete">Excluir</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <div id="popupEditarPedido">
        <div class="modal-content">
            <div class="modal-header">
                <div class="title">
                    <span onclick="document.getElementById('popupEditarPedido').style.display='none'">←</span>
                    <h2>Editar pedido</h2>
                </div>
                <p>Altere os detalhes do pedido</p>
            </div>

            <form method="POST" action="pedidos.php">
                <input type="hidden" name="edit_pedido_id" id="edit_pedido_id">

                <label for="edit_cliente_id">Selecione o Cliente:</label>
                <select name="edit_cliente_id" id="edit_cliente_id" required>
                    <option value="" disabled selected>-- Selecione um cliente --</option>
                    <?php foreach ($clientes_disponiveis as $cliente): ?>
                        <option value="<?php echo htmlspecialchars($cliente['cliente_id']); ?>">
                            <?php echo htmlspecialchars($cliente['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Itens do Pedido:</label>
                <div id="edit-product-items-container">
                    </div>
                <button type="button" id="edit-add-product-btn">Adicionar Outro Produto</button>

                <label for="edit_mesa">Mesa:</label>
                <input type="text" name="edit_mesa" id="edit_mesa" placeholder="Mesa (Ex: Mesa 03)">

                <div style="display: flex; justify-content: flex-end; margin-top: 16px;">
                    <button type="submit" name="editar_pedido">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const produtosDisponiveis = <?php echo json_encode($produtos_disponiveis); ?>;

        // Função para adicionar um novo item de produto no formulário
        function addProductItem(containerId, productList = [], quantityList = []) {
            const container = document.getElementById(containerId);
            const productItemDiv = document.createElement('div');
            productItemDiv.classList.add('product-item');

            const select = document.createElement('select');
            select.name = containerId === 'product-items-container' ? 'produtos[]' : 'edit_produtos[]';
            select.required = true;

            const defaultOption = document.createElement('option');
            defaultOption.value = "";
            defaultOption.disabled = true;
            defaultOption.selected = true;
            defaultOption.textContent = "-- Selecione um produto --";
            select.appendChild(defaultOption);

            produtosDisponiveis.forEach(produto => {
                const option = document.createElement('option');
                option.value = produto.produto_id;
                option.textContent = produto.nome;
                select.appendChild(option);
            });

            const quantityInput = document.createElement('input');
            quantityInput.type = "number";
            quantityInput.name = containerId === 'product-items-container' ? 'quantidades[]' : 'edit_quantidades[]';
            quantityInput.placeholder = "Qtd";
            quantityInput.min = "1";
            quantityInput.value = "1";
            quantityInput.required = true;
            quantityInput.style.width = "80px";

            const removeButton = document.createElement('button');
            removeButton.type = "button";
            removeButton.classList.add('remove-product-btn');
            removeButton.textContent = "Remover";
            removeButton.onclick = function() {
                productItemDiv.remove();
            };

            productItemDiv.appendChild(select);
            productItemDiv.appendChild(quantityInput);
            productItemDiv.appendChild(removeButton);

            container.appendChild(productItemDiv);
        }

        // Adicionar um item inicial no modal de Novo Pedido
        document.addEventListener('DOMContentLoaded', () => {
            const initialProductItem = document.querySelector('#product-items-container .product-item');
            if (initialProductItem) {
                initialProductItem.querySelector('.remove-product-btn').onclick = function() {
                    if (document.querySelectorAll('#product-items-container .product-item').length > 1) {
                        initialProductItem.remove();
                    } else {
                        alert("Um pedido deve ter pelo menos um produto.");
                    }
                };
            }
        });

        // Event listener para o botão "Adicionar Outro Produto" no modal de Novo Pedido
        document.getElementById('add-product-btn').addEventListener('click', () => {
            addProductItem('product-items-container');
        });


        // Função para abrir o modal de edição e preencher os dados
        function openEditModal(pedidoId, clienteId, mesa, produtoIdsRaw, quantidadesRaw) {
            document.getElementById('edit_pedido_id').value = pedidoId;
            document.getElementById('edit_cliente_id').value = clienteId;
            document.getElementById('edit_mesa').value = mesa;

            const editProductItemsContainer = document.getElementById('edit-product-items-container');
            editProductItemsContainer.innerHTML = ''; // Limpa os itens antigos

            // Popula o modal de edição com os produtos existentes
            if (produtoIdsRaw && quantidadesRaw && produtoIdsRaw.length > 0 && quantidadesRaw.length > 0) {
                for (let i = 0; i < produtoIdsRaw.length; i++) {
                    const productItemDiv = document.createElement('div');
                    productItemDiv.classList.add('product-item');

                    const select = document.createElement('select');
                    select.name = 'edit_produtos[]';
                    select.required = true;

                    const defaultOption = document.createElement('option');
                    defaultOption.value = "";
                    defaultOption.disabled = true;
                    defaultOption.textContent = "-- Selecione um produto --";
                    select.appendChild(defaultOption);

                    produtosDisponiveis.forEach(produto => {
                        const option = document.createElement('option');
                        option.value = produto.produto_id;
                        option.textContent = produto.nome;
                        if (parseInt(produto.produto_id) === parseInt(produtoIdsRaw[i])) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });

                    const quantityInput = document.createElement('input');
                    quantityInput.type = "number";
                    quantityInput.name = 'edit_quantidades[]';
                    quantityInput.placeholder = "Qtd";
                    quantityInput.min = "1";
                    quantityInput.value = parseInt(quantidadesRaw[i]);
                    quantityInput.required = true;
                    quantityInput.style.width = "80px";

                    const removeButton = document.createElement('button');
                    removeButton.type = "button";
                    removeButton.classList.add('remove-product-btn');
                    removeButton.textContent = "Remover";
                    removeButton.onclick = function() {
                        if (document.querySelectorAll('#edit-product-items-container .product-item').length > 1) {
                            productItemDiv.remove();
                        } else {
                            alert("Um pedido deve ter pelo menos um produto.");
                        }
                    };

                    productItemDiv.appendChild(select);
                    productItemDiv.appendChild(quantityInput);
                    productItemDiv.appendChild(removeButton);

                    editProductItemsContainer.appendChild(productItemDiv);
                }
            } else {
                // Se não houver itens pré-existentes, adicione um item vazio
                addProductItem('edit-product-items-container');
            }

            document.getElementById('popupEditarPedido').style.display = 'flex';
        }

        // Event listener para o botão "Adicionar Outro Produto" no modal de Edição
        document.getElementById('edit-add-product-btn').addEventListener('click', () => {
            addProductItem('edit-product-items-container');
        });

        // Lógica para garantir que pelo menos um item permaneça ao remover
        document.addEventListener('click', (event) => {
            if (event.target.classList.contains('remove-product-btn')) {
                const container = event.target.closest('#product-items-container') || event.target.closest('#edit-product-items-container');
                if (container) {
                    if (container.querySelectorAll('.product-item').length > 1) {
                        event.target.closest('.product-item').remove();
                    } else {
                        alert("Um pedido deve ter pelo menos um produto.");
                    }
                }
            }
        });
    </script>

</body>

</html>