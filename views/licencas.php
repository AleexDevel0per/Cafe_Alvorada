<?php

// Inclui o script de proteção de autenticação
include("../auth/protect.php");
// Inclui o arquivo de conexão com o banco de dados
include("../config/conexao.php");

// --- Lógica para buscar e filtrar as licenças ---
$where_clauses = []; // Array para armazenar as condições WHERE
$params = []; // Array para armazenar os parâmetros do prepared statement
$param_types = ''; // String para os tipos de parâmetros

// Verifica se o filtro de licença foi enviado e não está vazio
if (isset($_GET['filtro_licenca']) && $_GET['filtro_licenca'] !== '') {
    $where_clauses[] = "nome_licenca = ?";
    $params[] = $_GET['filtro_licenca'];
    $param_types .= 's';
}

// Verifica se o filtro de CNPJ foi enviado e não está vazio
if (isset($_GET['filtro_cnpj']) && $_GET['filtro_cnpj'] !== '') {
    $where_clauses[] = "cnpj = ?";
    $params[] = $_GET['filtro_cnpj'];
    $param_types .= 's';
}

// Constrói a query SQL base
// ATENÇÃO: Adicionei 'caminho_documento' na sua SELECT para que o download funcione!
$sql_licencas = "SELECT licenca_id, nome_licenca, cnpj, orgao_responsavel, data_validade, prazo_expiracao, caminho_documento FROM licencas";

// Adiciona as cláusulas WHERE se houver filtros
if (!empty($where_clauses)) {
    $sql_licencas .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql_licencas .= " ORDER BY data_validade ASC"; // Ordena os resultados

// Prepara a declaração (Prepared Statement) para a consulta principal
$stmt_licencas = $mysqli->prepare($sql_licencas);

if ($stmt_licencas === false) {
    die('Falha na preparação da consulta de licenças: ' . $mysqli->error);
}

// Se houver parâmetros de filtro, faz o bind
if (!empty($params)) {
    // A função bind_param requer que os parâmetros sejam passados por referência
    // Para arrays dinâmicos, é preciso usar call_user_func_array ou o operador ... (splat operator)
    // O operador ... é mais moderno e limpo
    $stmt_licencas->bind_param($param_types, ...$params);
}

// Executa a declaração
$stmt_licencas->execute();
// Obtém o resultado da consulta preparada
$result_licencas = $stmt_licencas->get_result();

// Verifica se a consulta retornou resultados para a contagem total
$total_licencas = 0;
if ($result_licencas) {
    $total_licencas = $result_licencas->num_rows;
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alvorada - Licenças</title>
    <link rel="stylesheet" href="../public/css/licencas.css">
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
        <div class="greeting">
            <span>Olá,</span>
            <b><?php echo htmlspecialchars($_SESSION['nome_usuario']); ?></b>
        </div>

        <section class="section-card">
            <h2>Gestão de Licenças</h2>
            <p>Nesta área são relacionados as licenças e alvarás de funcionamento</p>
            <div class="tag">
                <span><?php echo $total_licencas; ?> Licenças</span>
            </div>
            <div class="btn-cadastrar-licenca">
                <a href="cadastro_licenca.php">Cadastrar Nova Licença</a>
            </div>
        </section>

        <section>
            <div class="section-card">
                <form action="licencas.php" method="GET">
                    <div class="filter-header">
                        <div class="filter">
                            <div class="filter-content-wrapper">
                                <label for="filtro_licenca">Licença</label>
                                <select name="filtro_licenca" id="filtro_licenca" class="filter-select">
                                    <option value="">Todas</option>
                                    <?php
                                    // Consulta para popular o dropdown de Licença
                                    $sql_distinct_licencas = "SELECT DISTINCT nome_licenca FROM licencas ORDER BY nome_licenca ASC";
                                    $result_distinct_licencas = $mysqli->query($sql_distinct_licencas);
                                    if ($result_distinct_licencas) {
                                        while ($row = $result_distinct_licencas->fetch_assoc()) {
                                            $selected = (isset($_GET['filtro_licenca']) && $_GET['filtro_licenca'] == $row['nome_licenca']) ? 'selected' : '';
                                            echo "<option value=\"" . htmlspecialchars($row['nome_licenca']) . "\" {$selected}>" . htmlspecialchars($row['nome_licenca']) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="dropdown-icon">
                                <div></div>
                            </div>
                        </div>
                        <div class="filter">
                            <div class="filter-content-wrapper">
                                <label for="filtro_cnpj">CNPJ</label>
                                <select name="filtro_cnpj" id="filtro_cnpj" class="filter-select">
                                    <option value="">Todos</option>
                                    <?php
                                    // Consulta para popular o dropdown de CNPJ
                                    $sql_distinct_cnpjs = "SELECT DISTINCT cnpj FROM licencas ORDER BY cnpj ASC";
                                    $result_distinct_cnpjs = $mysqli->query($sql_distinct_cnpjs);
                                    if ($result_distinct_cnpjs) {
                                        while ($row = $result_distinct_cnpjs->fetch_assoc()) {
                                            $selected = (isset($_GET['filtro_cnpj']) && $_GET['filtro_cnpj'] == $row['cnpj']) ? 'selected' : '';
                                            echo "<option value=\"" . htmlspecialchars($row['cnpj']) . "\" {$selected}>" . htmlspecialchars($row['cnpj']) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="dropdown-icon">
                                <div></div>
                            </div>
                        </div>
                        <button type="submit" class="btn-filtrar">Filtrar</button>
                    </div>
                </form>
                <div class="table-header">
                    <div>Licença</div>
                    <div>Validade</div>
                    <div>Órgão</div>
                    <div>CNPJ</div>
                    <div>Notificação</div>
                    <div>Ações</div>
                </div>

                <?php
                // Verifica se a consulta principal de licenças retornou resultados
                if ($result_licencas && $result_licencas->num_rows > 0) {
                    while ($licenca = $result_licencas->fetch_assoc()) {
                        // Lógica de cálculo de status da notificação
                        $data_validade = new DateTime($licenca['data_validade']);
                        $hoje = new DateTime();
                        $intervalo = $hoje->diff($data_validade);
                        $dias_restantes = $intervalo->days;
                        $notificacao_status = 'N/A';
                        $notificacao_class = '';

                        $prazo_expiracao = (int)$licenca['prazo_expiracao'];

                        if ($data_validade < $hoje) {
                            $notificacao_status = 'Vencida';
                            $notificacao_class = 'vencida';
                        } elseif ($dias_restantes <= $prazo_expiracao && $data_validade > $hoje) {
                            $notificacao_status = "Faltam {$dias_restantes} dias";
                            $notificacao_class = 'proximo-vencimento';
                        } else {
                            $notificacao_status = 'Ativa';
                            $notificacao_class = 'ativa';
                        }
                        ?>
                        <div class="table-row">
                            <div><?php echo htmlspecialchars($licenca['nome_licenca']); ?></div>
                            <div><?php echo htmlspecialchars(date('d/m/Y', strtotime($licenca['data_validade']))); ?></div>
                            <div><?php echo htmlspecialchars($licenca['orgao_responsavel']); ?></div>
                            <div><?php echo htmlspecialchars($licenca['cnpj']); ?></div>
                            <div class="notificacao-badge <?php echo $notificacao_class; ?>">
                                <span><?php echo htmlspecialchars($notificacao_status); ?></span>
                            </div>
                            <div>
                                <?php if (!empty($licenca['caminho_documento'])) { ?>
                                    <a href="download_licenca.php?id=<?php echo htmlspecialchars($licenca['licenca_id']); ?>" title="Baixar Licença">
                                        <img src="../public/icons/download.png" alt="Baixar">
                                    </a>
                                <?php } else { ?>
                                    <span>N/A</span> <?php } ?>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo "<div class='table-row no-data'><div style='grid-column: 1 / span 6; text-align: center; padding: 20px;'>Nenhuma licença encontrada.</div></div>";
                }
                ?>
            </div>
        </section>
    </main>
</body>

</html>

<?php
// Fecha a conexão com o banco de dados ao final da página para liberar recursos.
$mysqli->close();
?>