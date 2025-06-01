<?php
// Inclui o arquivo de proteção (autenticação)
// É CRUCIAL QUE session_start() ESTEJA DENTRO DE protect.php OU AQUI NO INÍCIO!
// Se não estiver, adicione session_start(); logo abaixo desta linha.
include("../auth/protect.php");

// Inclui o arquivo de conexão com o banco de dados
// A variável de conexão com o banco de dados deve ser $mysqli
include("../config/conexao.php");

// --- Lógica para Cadastro de Produto (Modal de Cadastro) ---
if (isset($_POST['cadastrar_produto'])) {
    $nome_produto = $_POST['nome_produto'];
    $quantidade_entrada_cadastro = intval($_POST['quantidade_entrada_cadastro']);
    $unidade_medida = $_POST['unidade_medida'];
    $valor_unitario_cadastro = floatval(str_replace(',', '.', $_POST['valor_unitario_cadastro'])); // Trata vírgula como decimal
    $limite_minimo = intval($_POST['limite_minimo']);

    // Validação básica para garantir que valores não sejam 0 ou vazios
    if (empty($nome_produto) || $quantidade_entrada_cadastro <= 0 || empty($unidade_medida) || $valor_unitario_cadastro <= 0 || $limite_minimo < 0) {
        $_SESSION['mensagem_erro'] = 'Erro: Por favor, preencha todos os campos corretamente para cadastrar o produto.';
    } else {
        $stmt = $mysqli->prepare("INSERT INTO estoque (nome, quantidade, unidade_medida, valor, limite_alerta) VALUES (?, ?, ?, ?, ?)");
        if ($stmt === false) {
            $_SESSION['mensagem_erro'] = 'Erro ao preparar a consulta de cadastro: ' . $mysqli->error;
        } else {
            $stmt->bind_param("sisdi", $nome_produto, $quantidade_entrada_cadastro, $unidade_medida, $valor_unitario_cadastro, $limite_minimo);

            if ($stmt->execute()) {
                $_SESSION['mensagem_sucesso'] = 'Produto cadastrado com sucesso!';
            } else {
                $_SESSION['mensagem_erro'] = 'Erro ao cadastrar produto: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
    header("Location: estoque.php");
    exit();
}

// --- Lógica para Saída de Produto (Modal de Saída) ---
if (isset($_POST['saida_produto'])) {
    $produto_id_saida = intval($_POST['produto_id_saida']);
    $quantidade_saida = intval($_POST['quantidade_saida']);

    if ($produto_id_saida <= 0 || $quantidade_saida <= 0) {
        $_SESSION['mensagem_erro'] = 'Erro: Por favor, selecione um produto e insira uma quantidade válida para a saída.';
    } else {
        $stmt_check = $mysqli->prepare("SELECT quantidade FROM estoque WHERE produto_id = ?");
        if ($stmt_check === false) {
            $_SESSION['mensagem_erro'] = 'Erro ao preparar a verificação de estoque: ' . $mysqli->error;
        } else {
            $stmt_check->bind_param("i", $produto_id_saida);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $produto_atual = $result_check->fetch_assoc();
            $stmt_check->close();

            if ($produto_atual && $produto_atual['quantidade'] >= $quantidade_saida) {
                $stmt = $mysqli->prepare("UPDATE estoque SET quantidade = quantidade - ? WHERE produto_id = ?");
                if ($stmt === false) {
                    $_SESSION['mensagem_erro'] = 'Erro ao preparar a consulta de saída: ' . $mysqli->error;
                } else {
                    $stmt->bind_param("ii", $quantidade_saida, $produto_id_saida);

                    if ($stmt->execute()) {
                        $_SESSION['mensagem_sucesso'] = 'Saída de produto registrada com sucesso!';
                    } else {
                        $_SESSION['mensagem_erro'] = 'Erro ao registrar saída de produto: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            } else {
                $_SESSION['mensagem_erro'] = 'Quantidade em estoque insuficiente para esta saída ou produto não encontrado!';
            }
        }
    }
    header("Location: estoque.php");
    exit();
}

// --- Lógica para Entrada de Produto (Botão + na tabela) ---
if (isset($_POST['entrada_produto'])) {
    $produto_id_entrada = intval($_POST['produto_id_entrada']);
    $quantidade_entrada_individual = intval($_POST['quantidade_entrada_individual']);
    $valor_unitario_entrada = floatval(str_replace(',', '.', $_POST['valor_unitario_entrada'])); // Trata vírgula como decimal

    if ($produto_id_entrada <= 0 || $quantidade_entrada_individual <= 0 || $valor_unitario_entrada <= 0) {
        $_SESSION['mensagem_erro'] = 'Erro: Por favor, insira uma quantidade e valor válidos para a entrada.';
    } else {
        $stmt = $mysqli->prepare("UPDATE estoque SET quantidade = quantidade + ?, valor = ? WHERE produto_id = ?");
        if ($stmt === false) {
            $_SESSION['mensagem_erro'] = 'Erro ao preparar a consulta de entrada: ' . $mysqli->error;
        } else {
            $stmt->bind_param("idi", $quantidade_entrada_individual, $valor_unitario_entrada, $produto_id_entrada);

            if ($stmt->execute()) {
                $_SESSION['mensagem_sucesso'] = 'Entrada de produto registrada com sucesso!';
            } else {
                $_SESSION['mensagem_erro'] = 'Erro ao registrar entrada de produto: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
    header("Location: estoque.php");
    exit();
}

// --- Lógica para Filtrar Produtos ---
$sql_produtos = "SELECT produto_id, nome, quantidade, unidade_medida, valor, limite_alerta FROM estoque";
$parametros = [];
$tipos = "";

if (isset($_GET['filtrar'])) {
    $filtro_produto = $_GET['filtro_produto'] ?? '';
    $filtro_codigo_produto = $_GET['filtro_codigo_produto'] ?? '';

    $condicoes = [];

    if (!empty($filtro_produto)) {
        $condicoes[] = "nome LIKE ?";
        $parametros[] = "%" . $filtro_produto . "%";
        $tipos .= "s";
    }

    if (!empty($filtro_codigo_produto) && is_numeric($filtro_codigo_produto)) {
        $condicoes[] = "produto_id = ?";
        $parametros[] = intval($filtro_codigo_produto); // Garante que é um inteiro
        $tipos .= "i";
    }

    if (count($condicoes) > 0) {
        $sql_produtos .= " WHERE " . implode(" AND ", $condicoes);
    }
}

// Ordernar sempre
$sql_produtos .= " ORDER BY nome ASC";

$stmt_produtos = $mysqli->prepare($sql_produtos);

if ($stmt_produtos === false) {
    // Erro na preparação da consulta de exibição
    $_SESSION['mensagem_erro'] = (isset($_SESSION['mensagem_erro']) ? $_SESSION['mensagem_erro'] . " | " : "") . "Erro ao preparar a consulta de exibição de produtos: " . $mysqli->error;
    $resultado_produtos = false; // Define como falso para indicar que não houve resultados válidos
} else {
    if (!empty($parametros)) {
        // Usa call_user_func_array para bind_param com array de parâmetros
        $stmt_produtos->bind_param($tipos, ...$parametros);
    }
    $stmt_produtos->execute();
    $resultado_produtos = $stmt_produtos->get_result();
}


// --- Lógica para buscar produtos para os dropdowns (especialmente para Saída) ---
$produtos_para_dropdown = [];
$sql_dropdown = "SELECT produto_id, nome FROM estoque ORDER BY nome ASC";
if ($result_dropdown = $mysqli->query($sql_dropdown)) {
    while ($row = $result_dropdown->fetch_assoc()) {
        $produtos_para_dropdown[] = $row;
    }
    $result_dropdown->free();
} else {
    $_SESSION['mensagem_erro'] = (isset($_SESSION['mensagem_erro']) ? $_SESSION['mensagem_erro'] . " | " : "") . "Erro ao buscar produtos para dropdowns: " . $mysqli->error;
}

// --- Verificação e Exibição de Avisos de Baixo Estoque (Geral no Topo) ---
$produtos_com_alerta = [];
// Não fechar $mysqli aqui, pois a conexão será fechada no final.
$sql_alerta = "SELECT nome, quantidade, limite_alerta FROM estoque WHERE quantidade <= limite_alerta AND limite_alerta > 0 ORDER BY nome ASC";
$result_alerta = $mysqli->query($sql_alerta);

if ($result_alerta) { // Verifica se a consulta foi bem-sucedida
    while ($alerta = $result_alerta->fetch_assoc()) {
        $produtos_com_alerta[] = $alerta;
    }
    $result_alerta->free();
} else {
    $_SESSION['mensagem_erro'] = (isset($_SESSION['mensagem_erro']) ? $_SESSION['mensagem_erro'] . " | " : "") . "Erro ao verificar produtos com alerta: " . $mysqli->error;
}


// Fechar a conexão com o banco de dados no final do script
// Apenas feche se a conexão foi estabelecida com sucesso
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}


// --- Exibição de Mensagens de Sucesso/Erro e Limpeza da Sessão ---
$mensagem_feedback = '';
if (isset($_SESSION['mensagem_sucesso'])) {
    $mensagem_feedback = htmlspecialchars($_SESSION['mensagem_sucesso']);
    unset($_SESSION['mensagem_sucesso']);
} elseif (isset($_SESSION['mensagem_erro'])) {
    $mensagem_feedback = htmlspecialchars($_SESSION['mensagem_erro']);
    unset($_SESSION['mensagem_erro']);
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Estoque</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/estoque.css">
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

    <main>
        <div class="header">
            <b><?php echo $_SESSION['nome_usuario'] ?? 'Usuário'; ?></b>
            <h1>← Controle de estoque</h1>
            <p>Nesta área são relacionados os produtos em estoque.</p>
            <div class="produtos-count">
                <?php
                // Recalcular total de produtos após as operações (se necessário)
                $total_exibidos = ($resultado_produtos && $resultado_produtos->num_rows > 0) ? $resultado_produtos->num_rows : 0;
                echo $total_exibidos . " Produtos";
                ?>
            </div>
        </div>

        <?php if (!empty($mensagem_feedback)): ?>
            <p style="color: <?php echo (strpos($mensagem_feedback, 'Erro:') === 0 ? 'red' : 'green'); ?>; font-weight: bold; text-align: center; margin-bottom: 15px;"><?php echo $mensagem_feedback; ?></p>
        <?php endif; ?>

        <?php if (!empty($produtos_com_alerta)): ?>
            <div style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                <strong>ATENÇÃO: Produtos com estoque baixo!</strong><br>
                <?php foreach ($produtos_com_alerta as $alerta): ?>
                    - <?php echo htmlspecialchars($alerta['nome']); ?> (Estoque: <?php echo htmlspecialchars($alerta['quantidade']); ?>, Limite: <?php echo htmlspecialchars($alerta['limite_alerta']); ?>)<br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>


        <div class="filtros">
            <form action="" method="GET" style="display: flex; gap: 10px;">
                <input type="text" name="filtro_produto" placeholder="Nome do Produto" value="<?php echo $_GET['filtro_produto'] ?? ''; ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                <button type="submit" name="filtrar" class="modal-button" style="background-color: #5cb85c;">Filtrar</button>
            </form>

            <button onclick="document.getElementById('cadastroProdutoModal').style.display='flex'" class="modal-button">Cadastro de Produto</button>

            <button onclick="document.getElementById('saidaProdutoModal').style.display='flex'" class="modal-button">Saída de Produto</button>
        </div>

        <div id="cadastroProdutoModal" class="modal-overlay">
            <div class="modal-content">
                <button onclick="document.getElementById('cadastroProdutoModal').style.display='none'" class="modal-close-btn">×</button>
                <div style="margin-bottom: 16px;">
                    <h2 style="margin: 0; font-size: 20px; color: #344054;">Cadastro de produto</h2>
                    <p style="margin-top: 4px; font-size: 14px; color: #667085;">
                        Realize o cadastro de um novo produto que ainda não existe em estoque
                    </p>
                </div>
                <form action="" method="POST">
                    <input type="text" name="nome_produto" placeholder="Digite o nome do produto" required>
                    <input type="number" name="quantidade_entrada_cadastro" placeholder="Digite a quantidade de entrada" required>
                    <input type="text" name="unidade_medida" placeholder="Digite a unidade de medida" required>
                    <input type="number" step="0.01" name="valor_unitario_cadastro" placeholder="Digite o valor unitário do produto" required>
                    <input type="number" name="limite_minimo" placeholder="Limite mínimo em estoque para alerta" required>
                    <div class="modal-footer">
                        <button type="submit" name="cadastrar_produto" class="modal-button">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="saidaProdutoModal" class="modal-overlay">
            <div class="modal-content">
                <button onclick="document.getElementById('saidaProdutoModal').style.display='none'" class="modal-close-btn">×</button>
                <div style="margin-bottom: 16px;">
                    <h2 style="margin: 0; font-size: 20px; color: #344054;">Saída de produto</h2>
                    <p style="margin-top: 4px; font-size: 14px; color: #667085;">
                        Selecione o produto que deseja realizar a saída
                    </p>
                </div>
                <form action="" method="POST">
                    <select name="produto_id_saida" required>
                        <option value="" disabled selected>-- Selecione um produto --</option>
                        <?php foreach ($produtos_para_dropdown as $produto): ?>
                            <option value="<?php echo htmlspecialchars($produto['produto_id']); ?>">
                                <?php echo htmlspecialchars($produto['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="quantidade_saida" placeholder="Digite a quantidade de saída" required>
                    <div class="modal-footer">
                        <button type="submit" name="saida_produto" class="modal-button">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Ações / Produto</th>
                    <th>Quantidade</th>
                    <th>Unidade de medida</th>
                    <th>Valor unitário</th>
                    <th>Valor em estoque</th>
                    <th>Limite</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($resultado_produtos && $resultado_produtos->num_rows > 0) { // Verifica se $resultado_produtos não é falso
                    while ($linha = $resultado_produtos->fetch_assoc()) {
                        $valor_em_estoque = $linha['quantidade'] * $linha['valor'];
                        $alerta_limite = $linha['quantidade'] <= $linha['limite_alerta'] && $linha['limite_alerta'] > 0 ? 'background-color: #ffe0e0;' : ''; // Estilo para alerta (apenas se limite > 0)
                        ?>
                        <tr style="<?php echo $alerta_limite; ?>">
                            <td>
                                <button onclick="openEntradaModal(<?php echo htmlspecialchars($linha['produto_id']); ?>, '<?php echo htmlspecialchars($linha['nome']); ?>')"
                                    class="modal-button" style="padding: 5px 10px; font-weight: bolder;">
                                    +
                                </button>
                                <?php echo htmlspecialchars($linha['nome']); ?>
                                <?php if ($linha['quantidade'] <= $linha['limite_alerta'] && $linha['limite_alerta'] > 0): // Aviso visual na linha ?>
                                    <span style="color: orange; font-weight: bold; margin-left: 5px; font-size: 0.8em;">(Baixo)</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($linha['quantidade']); ?></td>
                            <td><?php echo htmlspecialchars($linha['unidade_medida'] ?? ''); ?></td>
                            <td>R$ <?php echo number_format($linha['valor'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format($valor_em_estoque, 2, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($linha['limite_alerta']); ?></td>
                        </tr>
                        <?php
                    }
                } else {
                    echo "<tr><td colspan='6' style='text-align:center;'>Nenhum produto encontrado.</td></tr>";
                }
                // O stmt_produtos->close() e mysqli->close() já são chamados no final do PHP
                ?>
            </tbody>
        </table>

        <div id="popupEntrada" class="modal-overlay modal-entrada-individual">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <span onclick="document.getElementById('popupEntrada').style.display='none'"
                            style="font-size: 18px; cursor: pointer;">←</span>
                        <span style="font-size: 18px; font-weight: 600; color: #344054;">Entrada de produto</span>
                    </div>
                    <span style="font-size: 14px; font-weight: 400; color: #667085;">Faça o lançamento de entrada do produto
                        selecionado</span>
                </div>

                <form action="" method="POST">
                    <input type="hidden" id="produto_id_entrada_hidden" name="produto_id_entrada">
                    <p id="produto_nome_entrada" style="font-weight: 600; margin-bottom: 10px;"></p>
                    <input type="number" name="quantidade_entrada_individual" placeholder="Digite a quantidade de entrada" required>
                    <input type="number" step="0.01" name="valor_unitario_entrada" placeholder="Digite o valor unitário do produto" required>

                    <div class="modal-footer">
                        <button type="submit" name="entrada_produto" class="modal-button">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

    </main>

    <script>
        function openEntradaModal(productId, productName) {
            document.getElementById('popupEntrada').style.display = 'flex';
            document.getElementById('produto_id_entrada_hidden').value = productId;
            document.getElementById('produto_nome_entrada').innerText = 'Produto: ' + productName;
        }
    </script>
</body>
</html>