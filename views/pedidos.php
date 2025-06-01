<?php
// Inclui o arquivo de proteção de autenticação
// É CRUCIAL QUE session_start() ESTEJA DENTRO DE protect.php OU AQUI NO INÍCIO!
// Se não estiver, adicione session_start(); logo abaixo desta linha.
include(__DIR__ . '/../auth/protect.php');
// Inclui o arquivo de conexão com o banco de dados
include(__DIR__ . '/../config/conexao.php');

// Variável para armazenar mensagens de feedback (agora vindo principalmente da sessão)
$mensagem = "";

// --- Lógica para NOVO PEDIDO (CREATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['salvar_pedido'])) {
    // Validar e escapar as entradas do usuário
    $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
    $produto_id = isset($_POST['produto_id']) ? intval($_POST['produto_id']) : 0;
    $quantidade = isset($_POST['quantidade']) ? intval($_POST['quantidade']) : 0;
    $mesa = isset($_POST['mesa']) ? $mysqli->real_escape_string($_POST['mesa']) : null; // Pode ser NULL

    // Validação detalhada de entrada
    if ($cliente_id <= 0) {
        $_SESSION['mensagem_erro'] = "Erro: Por favor, selecione um cliente.";
    } elseif ($produto_id <= 0) {
        $_SESSION['mensagem_erro'] = "Erro: Por favor, selecione um produto.";
    } elseif ($quantidade <= 0) {
        $_SESSION['mensagem_erro'] = "Erro: Por favor, insira uma quantidade válida (maior que zero).";
    } else {
        // Se todas as validações iniciais passaram, inicie a transação e processe o pedido
        $mysqli->begin_transaction();

        try {
            // 1. Obter o valor do produto e o nome do produto do estoque
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
                throw new Exception("Produto não encontrado no estoque.");
            }

            $produto_nome = $produto_info['nome'];
            $valor_unitario = $produto_info['valor'];
            $estoque_disponivel = $produto_info['estoque_disponivel'];
            $valor_total_item = $valor_unitario * $quantidade;

            // 2. VERIFICAR ESTOQUE SUFICIENTE ANTES DE CONTINUAR (NOVO)
            if ($estoque_disponivel < $quantidade) {
                throw new Exception("Estoque insuficiente para o produto '" . htmlspecialchars($produto_nome) . "'. Disponível: " . $estoque_disponivel . " und.");
            }

            // 3. Inserir o pedido na tabela 'pedidos'
            $data_pedido = date("Y-m-d"); // Data atual

            $stmt_pedido = $mysqli->prepare("INSERT INTO pedidos (cliente_id, data_pedido, valor, mesa) VALUES (?, ?, ?, ?)");
            if (!$stmt_pedido) {
                throw new Exception("Erro ao preparar inserção de pedido: " . $mysqli->error);
            }
            $stmt_pedido->bind_param("isds", $cliente_id, $data_pedido, $valor_total_item, $mesa);
            $stmt_pedido->execute();
            $pedido_id = $mysqli->insert_id; // Pega o ID do pedido recém-criado
            $stmt_pedido->close();

            // 4. Inserir o item do pedido na tabela 'item_pedido'
            $stmt_item_pedido = $mysqli->prepare("INSERT INTO item_pedido (produto_id, pedido_id, quantidade, produto_nome) VALUES (?, ?, ?, ?)");
            if (!$stmt_item_pedido) {
                throw new Exception("Erro ao preparar inserção de item_pedido: " . $mysqli->error);
            }
            $stmt_item_pedido->bind_param("iiis", $produto_id, $pedido_id, $quantidade, $produto_nome);
            $stmt_item_pedido->execute();
            $stmt_item_pedido->close();

            // 5. DAR BAIXA NO ESTOQUE (NOVO)
            $stmt_update_estoque = $mysqli->prepare("UPDATE estoque SET quantidade = quantidade - ? WHERE produto_id = ?");
            if (!$stmt_update_estoque) {
                throw new Exception("Erro ao preparar atualização de estoque: " . $mysqli->error);
            }
            $stmt_update_estoque->bind_param("ii", $quantidade, $produto_id);
            $stmt_update_estoque->execute();
            $stmt_update_estoque->close();

            $mysqli->commit();
            $_SESSION['mensagem_sucesso'] = "Pedido criado com sucesso! (ID: " . $pedido_id . ")"; // Armazena na sessão
            header("Location: pedidos.php"); // Redireciona SEM parâmetros GET
            exit();

        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['mensagem_erro'] = "Erro ao criar pedido: " . $e->getMessage(); // Armazena erro na sessão
            header("Location: pedidos.php"); // Redireciona mesmo com erro
            exit();
        }
    }
    // Se houve um erro de validação inicial, a mensagem já foi setada na sessão
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
            $_SESSION['mensagem_sucesso'] = "Pedido excluído com sucesso!"; // Armazena na sessão
            header("Location: pedidos.php"); // Redireciona SEM parâmetros GET
            exit();
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['mensagem_erro'] = "Erro ao excluir pedido: " . $e->getMessage(); // Armazena erro na sessão
            header("Location: pedidos.php");
            exit();
        }
    } else {
        $_SESSION['mensagem_erro'] = "Erro: ID do pedido para exclusão inválido."; // Armazena erro na sessão
        header("Location: pedidos.php");
        exit();
    }
}

// --- Lógica para EDIÇÃO (UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['editar_pedido'])) {
    $pedido_id_editar = isset($_POST['edit_pedido_id']) ? intval($_POST['edit_pedido_id']) : 0;
    $novo_cliente_id = isset($_POST['edit_cliente_id']) ? intval($_POST['edit_cliente_id']) : 0;
    $nova_mesa = isset($_POST['edit_mesa']) ? $mysqli->real_escape_string($_POST['edit_mesa']) : null;
    $novo_produto_id = isset($_POST['edit_produto_id']) ? intval($_POST['edit_produto_id']) : 0;
    $nova_quantidade = isset($_POST['edit_quantidade']) ? intval($_POST['edit_quantidade']) : 0;

    if ($pedido_id_editar <= 0 || $novo_cliente_id <= 0 || $novo_produto_id <= 0 || $nova_quantidade <= 0) {
        $_SESSION['mensagem_erro'] = "Erro: Dados inválidos para edição do pedido. Verifique cliente, produto, quantidade e ID do pedido.";
        header("Location: pedidos.php");
        exit();
    } else {
        $mysqli->begin_transaction();
        try {
            // Antes de atualizar: Reverter a baixa do estoque do item antigo e dar nova baixa
            $stmt_get_old_item = $mysqli->prepare("SELECT produto_id, quantidade FROM item_pedido WHERE pedido_id = ?");
            if (!$stmt_get_old_item) {
                throw new Exception("Erro ao preparar busca de item antigo: " . $mysqli->error);
            }
            $stmt_get_old_item->bind_param("i", $pedido_id_editar);
            $stmt_get_old_item->execute();
            $result_old_item = $stmt_get_old_item->get_result();
            $old_item_info = $result_old_item->fetch_assoc();
            $stmt_get_old_item->close();

            if ($old_item_info) {
                // Reverter estoque antigo
                $stmt_revert_old_estoque = $mysqli->prepare("UPDATE estoque SET quantidade = quantidade + ? WHERE produto_id = ?");
                if (!$stmt_revert_old_estoque) {
                    throw new Exception("Erro ao preparar reversão de estoque antigo: " . $mysqli->error);
                }
                $stmt_revert_old_estoque->bind_param("ii", $old_item_info['quantidade'], $old_item_info['produto_id']);
                $stmt_revert_old_estoque->execute();
                $stmt_revert_old_estoque->close();
            }

            // 1. Obter o valor do produto para recalcular o total e verificar novo estoque
            $stmt_produto_edit = $mysqli->prepare("SELECT nome, valor, quantidade AS estoque_disponivel FROM estoque WHERE produto_id = ?");
            if (!$stmt_produto_edit) {
                throw new Exception("Erro ao preparar consulta de produto para edição: " . $mysqli->error);
            }
            $stmt_produto_edit->bind_param("i", $novo_produto_id);
            $stmt_produto_edit->execute();
            $result_produto_edit = $stmt_produto_edit->get_result();
            $produto_info_edit = $result_produto_edit->fetch_assoc();
            $stmt_produto_edit->close();

            if (!$produto_info_edit) {
                throw new Exception("Novo produto para edição não encontrado.");
            }
            $novo_valor_total_item = $produto_info_edit['valor'] * $nova_quantidade;
            $novo_produto_nome = $produto_info_edit['nome'];
            $novo_estoque_disponivel = $produto_info_edit['estoque_disponivel'];

            // Verificar se o novo estoque é suficiente após a reversão do item antigo
            // Se o produto não mudou (old_item_info['produto_id'] == novo_produto_id), precisamos considerar a quantidade antiga + o que já foi revertido.
            // Para simplificar, estamos fazendo a reversão completa e depois a nova baixa.
            // Se o novo produto é o mesmo do antigo, a quantidade que estava no estoque (original) já foi incrementada.
            // A verificação deve ser: o estoque atual (com a reversão já aplicada) é suficiente para a nova_quantidade?
            // A quantidade disponível em $novo_estoque_disponivel JÁ reflete a reversão se $old_item_info['produto_id'] == $novo_produto_id.
            if ($novo_estoque_disponivel < $nova_quantidade) {
                 throw new Exception("Estoque insuficiente para a nova quantidade do produto '" . htmlspecialchars($novo_produto_nome) . "'. Disponível: " . $novo_estoque_disponivel . " und.");
            }

            // 2. Atualizar o pedido na tabela 'pedidos'
            $stmt_update_pedido = $mysqli->prepare("UPDATE pedidos SET cliente_id = ?, valor = ?, mesa = ? WHERE pedido_id = ?");
            if (!$stmt_update_pedido) {
                throw new Exception("Erro ao preparar atualização de pedido: " . $mysqli->error);
            }
            $stmt_update_pedido->bind_param("idsi", $novo_cliente_id, $novo_valor_total_item, $nova_mesa, $pedido_id_editar);
            $stmt_update_pedido->execute();
            $stmt_update_pedido->close();

            // 3. Atualizar o item do pedido na tabela 'item_pedido'
            // Assumimos que cada pedido terá apenas 1 item_pedido para simplificar a edição por agora.
            $stmt_update_item = $mysqli->prepare("UPDATE item_pedido SET produto_id = ?, quantidade = ?, produto_nome = ? WHERE pedido_id = ?");
            if (!$stmt_update_item) {
                throw new Exception("Erro ao preparar atualização de item_pedido: " . $mysqli->error);
            }
            $stmt_update_item->bind_param("iisi", $novo_produto_id, $nova_quantidade, $novo_produto_nome, $pedido_id_editar);
            $stmt_update_item->execute();
            $stmt_update_item->close();

            // 4. DAR NOVA BAIXA NO ESTOQUE (NOVO)
            $stmt_new_estoque_deduction = $mysqli->prepare("UPDATE estoque SET quantidade = quantidade - ? WHERE produto_id = ?");
            if (!$stmt_new_estoque_deduction) {
                throw new Exception("Erro ao preparar nova dedução de estoque: " . $mysqli->error);
            }
            $stmt_new_estoque_deduction->bind_param("ii", $nova_quantidade, $novo_produto_id);
            $stmt_new_estoque_deduction->execute();
            $stmt_new_estoque_deduction->close();


            $mysqli->commit();
            $_SESSION['mensagem_sucesso'] = "Pedido atualizado com sucesso!"; // Armazena na sessão
            header("Location: pedidos.php"); // Redireciona SEM parâmetros GET
            exit();

        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['mensagem_erro'] = "Erro ao atualizar pedido: " . $e->getMessage(); // Armazena erro na sessão
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
$filtro_mesa = isset($_GET['filtro_mesa']) ? $mysqli->real_escape_string($_GET['filtro_mesa']) : ''; // Novo filtro para mesa

// SQL para buscar pedidos e seus itens (usando JOINs)
$sql_select = "
    SELECT
        p.pedido_id,
        p.data_pedido,
        p.valor AS valor_total_pedido,
        p.mesa,
        c.nome AS cliente_nome,
        c.email AS cliente_email,
        c.telefone AS cliente_telefone,
        c.cliente_id,
        ip.produto_id,
        ip.quantidade AS item_quantidade,
        GROUP_CONCAT(CONCAT(ip.produto_nome, ' (', ip.quantidade, ' und)') SEPARATOR '<br>') AS itens_pedido
    FROM
        pedidos p
    JOIN
        clientes c ON p.cliente_id = c.cliente_id
    LEFT JOIN
        item_pedido ip ON p.pedido_id = ip.pedido_id
    WHERE 1=1
";

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
if (!empty($filtro_mesa)) { // Adicionado filtro para mesa
    $sql_select .= " AND p.mesa LIKE ?";
    $params[] = "%" . $filtro_mesa . "%";
    $types .= "s";
}

$sql_select .= " GROUP BY p.pedido_id, p.data_pedido, p.valor, p.mesa, c.nome, c.email, c.telefone, c.cliente_id, ip.produto_id, ip.quantidade ORDER BY p.pedido_id DESC";

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
    // Isso indicaria um erro na preparação do SQL (ex: sintaxe errada)
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
// Apenas feche se a conexão foi estabelecida com sucesso
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
        /* Força 3 colunas de largura igual, sem "auto-fit" */
        grid-template-columns: repeat(3, 1fr);
        gap: 30px; /* Aumenta ainda mais o espaço entre os cards */
        padding-bottom: 50px; /* Espaço para o scroll */
        /* Opcional: Centralizar o grid se a largura total for menor que a tela */
        /* max-width: 1000px; /* Ajuste este valor conforme a largura desejada para 3 cards */ */
        /* margin: 0 auto; */
    }

    .card {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
        padding: 30px; /* Aumenta o padding interno para mais espaço */
        background-color: white;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 220px; /* Garante uma altura mínima para todos os cards */
    }

    .card p {
        margin-bottom: 12px; /* Mais espaço entre os parágrafos de texto */
        line-height: 1.6; /* Melhora a legibilidade com mais espaço na linha */
    }

    .card p strong {
        color: #333;
    }

    /* Tags de informação (Mesa e Data) */
    .card .info-tag {
        position: absolute;
        background-color: #f0f0f0; /* Usei o cinza original para manter a consistência */
        padding: 8px 15px; /* Mais padding para o tag */
        border-radius: 5px;
        font-size: 0.9em;
        color: #555; /* Usei o cinza original para manter a consistência */
        white-space: nowrap;
        z-index: 1;
    }
    
    .card .info-tag:first-of-type { /* Primeiro info-tag (Mesa) */
        top: 25px; /* Ajustado para mais espaçamento */
        right: 25px;
    }

    .card .info-tag:nth-of-type(2) { /* Segundo info-tag (Data) */
        top: 65px; /* Posição ajustada para ficar bem abaixo da primeira tag */
        right: 25px;
    }

    /* Cores dos botões */
    .btn-edit {
        padding: 10px 18px; /* Mais padding para os botões */
        background: #28a745; /* Verde mais vibrante */
        color: white;
        border: none;
        border-radius: 5px; /* Borda levemente mais arredondada */
        cursor: pointer;
        margin-right: 10px; /* Mais espaço entre os botões */
        font-size: 0.95em; /* Levemente maior */
        transition: background-color 0.3s ease; /* Transição suave no hover */
    }

    .btn-edit:hover {
        background-color: #218838; /* Escurece no hover */
    }

    .btn-delete {
        padding: 10px 18px;
        background: #dc3545; /* Vermelho mais forte */
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.95em;
        transition: background-color 0.3s ease;
    }

    .btn-delete:hover {
        background-color: #c82333; /* Escurece no hover */
    }

    /* Estilo para o modal de edição (similar ao de novo pedido) */
    #popupEditarPedido {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0,0,0,0.5); /* Fundo mais escuro */
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    #popupEditarPedido .modal-content {
        width: 655px;
        background: #F6F6F6;
        border-radius: 10px; /* Bordas mais arredondadas */
        padding: 32px 25px; /* Mais padding */
        box-shadow: 0 6px 20px rgba(0,0,0,0.2); /* Sombra mais pronunciada */
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 18px; /* Mais espaço entre os elementos do modal */
    }
    #popupEditarPedido .modal-header {
        background: white;
        border-radius: 8px; /* Bordas mais arredondadas */
        padding: 18px; /* Mais padding */
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    #popupEditarPedido .modal-header .title {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    #popupEditarPedido .modal-header .title span {
        font-size: 20px; /* Ícone de voltar maior */
        cursor: pointer;
    }
    #popupEditarPedido .modal-header h2 {
        font-size: 20px; /* Título maior */
        font-weight: 600;
        color: #344054;
        margin: 0;
    }
    #popupEditarPedido .modal-header p {
        font-size: 15px; /* Subtítulo maior */
        font-weight: 400;
        color: #667085;
        margin: 0;
    }
    #popupEditarPedido form {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    #popupEditarPedido input, #popupEditarPedido select {
        padding: 12px 16px; /* Mais padding nos inputs */
        font-size: 15px; /* Fonte maior nos inputs */
        border: 1px solid #D0D5DD;
        border-radius: 7px;
        background: white;
        color: #495057;
        outline: none;
    }
    #popupEditarPedido input::placeholder {
        color: #ADB5BD;
    }
    #popupEditarPedido button[type="submit"] {
        padding: 12px 28px; /* Mais padding */
        background: #9D6A5E;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    #popupEditarPedido button[type="submit"]:hover {
        background-color: #8C5B4F;
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
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .filter-bar select,
    .filter-bar input[type="date"],
    .filter-bar input[type="text"] {
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 5px;
    }
    .filter-bar button.btn {
        background: #6c757d; /* Um cinza mais neutro para o filtrar */
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
            <button type="button" onclick="document.getElementById('popupPedido').style.display='flex'"
                style="padding: 10px 20px; background: #9e7563; color: white; border: none; border-radius: 6px; cursor: pointer;">
                Novo Pedido
            </button>
        </form>

        <div id="popupPedido"
            style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.4); justify-content: center; align-items: center; z-index: 999;">
            <div
                style="width: 655px; background: #F6F6F6; border-radius: 8px; padding: 32px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); position: relative; display: flex; flex-direction: column; gap: 16px;">

                <div
                    style="background: white; border-radius: 6px; padding: 16px; display: flex; flex-direction: column; gap: 4px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span onclick="document.getElementById('popupPedido').style.display='none'"
                            style="font-size: 18px; cursor: pointer;">←</span>
                        <span style="font-size: 18px; font-weight: 600; color: #344054;">Novo pedido</span>
                    </div>
                    <span style="font-size: 14px; font-weight: 400; color: #667085;">Realize um novo pedido</span>
                </div>

                <form method="POST" action="pedidos.php" style="display: flex; flex-direction: column; gap: 16px;">
                    <label for="cliente_id" style="font-size: 14px; color: #667085;">Selecione o Cliente:</label>
                    <select name="cliente_id" id="cliente_id" required
                        style="padding: 10px 14px; font-size: 14px; border: 1px solid #D0D5DD; border-radius: 6px; background: white; color: #667085; outline: none;">
                        <option value="" disabled selected>-- Selecione um cliente --</option>
                        <?php foreach ($clientes_disponiveis as $cliente): ?>
                            <option value="<?php echo htmlspecialchars($cliente['cliente_id']); ?>">
                                <?php echo htmlspecialchars($cliente['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="produto_id" style="font-size: 14px; color: #667085;">Selecione o Produto:</label>
                    <select name="produto_id" id="produto_id" required
                        style="padding: 10px 14px; font-size: 14px; border: 1px solid #D0D5DD; border-radius: 6px; background: white; color: #667085; outline: none;">
                        <option value="" disabled selected>-- Selecione um produto --</option>
                        <?php foreach ($produtos_disponiveis as $produto): ?>
                            <option value="<?php echo htmlspecialchars($produto['produto_id']); ?>">
                                <?php echo htmlspecialchars($produto['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="quantidade" style="font-size: 14px; color: #667085;">Quantidade:</label>
                    <input type="number" name="quantidade" id="quantidade" placeholder="Quantidade" min="1" value="1" required
                        style="padding: 10px 14px; font-size: 14px; border: 1px solid #D0D5DD; border-radius: 6px; background: white; color: #667085; outline: none;">

                    <label for="mesa" style="font-size: 14px; color: #667085;">Mesa:</label>
                    <input type="text" name="mesa" id="mesa" placeholder="Mesa (Ex: Mesa 03)"
                        style="padding: 10px 14px; font-size: 14px; border: 1px solid #D0D5DD; border-radius: 6px; background: white; color: #667085; outline: none;">


                    <div style="display: flex; justify-content: flex-end; margin-top: 16px;">
                        <button type="submit" name="salvar_pedido"
                            style="padding: 10px 24px; background: #9D6A5E; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer;">
                            Salvar
                        </button>
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
                            <?php echo nl2br(htmlspecialchars($pedido['itens_pedido'] ?? '')); ?>
                        </p>
                        <p><strong>Total do Pedido:</strong> R$ <?php echo number_format($pedido['valor_total_pedido'] ?? 0, 2, ',', '.'); ?></p>
                        <p><strong>Cliente:</strong> <?php echo htmlspecialchars($pedido['cliente_nome'] ?? ''); ?><br>
                            <strong>E-mail:</strong> <?php echo htmlspecialchars($pedido['cliente_email'] ?? ''); ?><br>
                            <strong>Telefone:</strong> <?php echo htmlspecialchars($pedido['cliente_telefone'] ?? ''); ?>
                        </p>
                        <div class="info-tag">Mesa <?php echo htmlspecialchars($pedido['mesa'] ?? ''); ?></div>
                        <div class="info-tag" style="top: 50px;">Data: <?php echo date('d/m/Y', strtotime($pedido['data_pedido'] ?? 'now')); ?></div>

                        <div style="margin-top: 10px;">
                            <button class="btn-edit"
                                onclick="openEditModal(
                                    <?php echo htmlspecialchars(json_encode($pedido['pedido_id'] ?? 0)); ?>,
                                    <?php echo htmlspecialchars(json_encode($pedido['cliente_id'] ?? 0)); ?>,
                                    <?php echo htmlspecialchars(json_encode($pedido['mesa'] ?? '')); ?>,
                                    <?php echo htmlspecialchars(json_encode($pedido['produto_id'] ?? 0)); ?>,
                                    <?php echo htmlspecialchars(json_encode($pedido['item_quantidade'] ?? 0)); ?>
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

    <div id="popupEditarPedido" style="display: none;">
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

                <label for="edit_produto_id">Selecione o Produto:</label>
                <select name="edit_produto_id" id="edit_produto_id" required>
                    <option value="" disabled selected>-- Selecione um produto --</option>
                    <?php foreach ($produtos_disponiveis as $produto): ?>
                        <option value="<?php echo htmlspecialchars($produto['produto_id']); ?>">
                            <?php echo htmlspecialchars($produto['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="edit_quantidade">Quantidade:</label>
                <input type="number" name="edit_quantidade" id="edit_quantidade" min="1" required>

                <label for="edit_mesa">Mesa:</label>
                <input type="text" name="edit_mesa" id="edit_mesa" placeholder="Mesa (Ex: Mesa 03)">

                <div style="display: flex; justify-content: flex-end; margin-top: 16px;">
                    <button type="submit" name="editar_pedido">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Função para abrir o modal de edição e preencher os dados
        function openEditModal(pedidoId, clienteId, mesa, produtoId, quantidade) {
            document.getElementById('edit_pedido_id').value = pedidoId;
            document.getElementById('edit_cliente_id').value = clienteId;
            document.getElementById('edit_mesa').value = mesa;
            document.getElementById('edit_produto_id').value = produtoId;
            document.getElementById('edit_quantidade').value = quantidade; // Corrigido de 'quantity' para 'quantidade'
            document.getElementById('popupEditarPedido').style.display = 'flex';
        }
    </script>

</body>

</html>