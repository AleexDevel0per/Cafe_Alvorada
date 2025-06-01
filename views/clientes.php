<?php

include("../auth/protect.php");
include("../config/conexao.php"); 

if (isset($_POST['salvar_cliente'])) {
    $nome = $mysqli->real_escape_string($_POST['nome']);
    $email = $mysqli->real_escape_string($_POST['email']);
    $telefone = $mysqli->real_escape_string($_POST['telefone']);
    $cpf = $mysqli->real_escape_string($_POST['cpf']);

    if (!empty($nome)) {
        $sql_inserir = "INSERT INTO clientes (nome, email, telefone, cpf) VALUES (?, ?, ?, ?)";
        $stmt = $mysqli->prepare($sql_inserir);
        if ($stmt === false) {
            die('Erro na preparação da query: ' . $mysqli->error);
        }
        $stmt->bind_param("ssss", $nome, $email, $telefone, $cpf);

        if ($stmt->execute()) {
            header("Location: clientes.php?cadastro_sucesso=true");
            exit(); 
        } else {
            echo "<script>alert('Erro ao cadastrar cliente: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('O nome do cliente é obrigatório!');</script>";
    }
}

$sql_clientes = "
    SELECT
        c.cliente_id,
        c.nome,
        c.email,
        c.telefone,
        c.cpf,
        MAX(p.data_pedido) AS ultima_compra,
        COUNT(DISTINCT p.pedido_id) AS qtd_pedidos
    FROM
        clientes c
    LEFT JOIN
        pedidos p ON c.cliente_id = p.cliente_id
    GROUP BY
        c.cliente_id, c.nome, c.email, c.telefone, c.cpf
    ORDER BY
        c.nome ASC;
";
$result_clientes = $mysqli->query($sql_clientes);
$total_clientes = 0;
if ($result_clientes) {
    $total_clientes = $result_clientes->num_rows;
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alvorada - Clientes</title>
    <link rel="stylesheet" href="../public/css/clientes.css">
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

        <section class="container">
            <h2>Gestão de clientes</h2>
            <p>Nesta área são relacionados os clientes e a quantidade de pedidos / última compra</p>
            <div class="badge">
                <span><?php echo $total_clientes; ?> Clientes</span>
            </div>
            
            <button class="btn-novo-cliente" onclick="abrirModalCliente()">
                Novo Cliente
            </button>

            <div id="popupCliente" class="popup-cliente-modal">
                <div class="popup-content">
                    <form action="" method="POST">
                        <div class="header-modal">
                            <div>
                                <span class="modal-close-btn" onclick="fecharModalCliente()">←</span>
                                <span>Cadastro de cliente</span>
                            </div>
                            <span>Cadaste o cliente</span>
                        </div>

                        <input type="text" name="nome" placeholder="Digite o nome" required class="modal-input">
                        <input type="email" name="email" placeholder="Digite o E-mail" class="modal-input">
                        <input type="text" name="telefone" placeholder="Digite o telefone" class="modal-input">
                        <input type="text" name="cpf" placeholder="Digite o CPF" class="modal-input">

                        <div class="modal-buttons">
                            <button type="submit" name="salvar_cliente" class="btn-salvar">
                                Salvar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <section>
            <div class="tabela">
                <div class="tabela-header">
                    <div>Nome</div>
                    <div>CPF</div>
                    <div>E-mail</div>
                    <div>Telefone</div>
                    <div>Última Compra</div>
                    <div>Qtd Pedidos</div>
                </div>

                <?php
                if ($result_clientes && $result_clientes->num_rows > 0) {
                    while ($cliente = $result_clientes->fetch_assoc()) {
                        ?>
                        <div class="tabela-row">
                            <div><?php echo htmlspecialchars($cliente['nome']); ?></div>
                            <div><?php echo htmlspecialchars($cliente['cpf']); ?></div>
                            <div><?php echo htmlspecialchars($cliente['email']); ?></div>
                            <div><?php echo htmlspecialchars($cliente['telefone']); ?></div>
                            <div><?php echo htmlspecialchars($cliente['ultima_compra'] ? date('d/m/Y', strtotime($cliente['ultima_compra'])) : 'N/A'); ?></div>
                            <div><?php echo htmlspecialchars($cliente['qtd_pedidos']); ?></div>
                        </div>
                        <?php
                    }
                } else {
                    echo "<div class='tabela-row'><div style='grid-column: 1 / span 6; text-align: center; padding: 20px;'>Nenhum cliente encontrado.</div></div>";
                }
                ?>
            </div>
        </section>
    </main>

        <script>
        // Função para abrir o modal e travar o scroll do body
        function abrirModalCliente() {
            document.getElementById('popupCliente').style.display = 'flex';
            document.body.classList.add('no-scroll'); // Adiciona a classe ao body
        }

        // Função para fechar o modal e reativar o scroll do body
        function fecharModalCliente() {
            document.getElementById('popupCliente').style.display = 'none';
            document.body.classList.remove('no-scroll'); // Remove a classe do body
        }
    </script>
</body>

</html>

<?php
$mysqli->close();
?>