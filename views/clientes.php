<?php

include("../auth/protect.php");
include("../config/conexao.php");

$mensagem_feedback = '';
if (isset($_SESSION['mensagem_sucesso'])) {
    $mensagem_feedback = htmlspecialchars($_SESSION['mensagem_sucesso']);
    unset($_SESSION['mensagem_sucesso']);
} elseif (isset($_SESSION['mensagem_erro'])) {
    $mensagem_feedback = htmlspecialchars($_SESSION['mensagem_erro']);
    unset($_SESSION['mensagem_erro']);
}


if (isset($_POST['salvar_cliente'])) {
    $nome = $mysqli->real_escape_string($_POST['nome']);
    $email = $mysqli->real_escape_string($_POST['email']);
    $telefone = $mysqli->real_escape_string($_POST['telefone']);
    $cpf = $mysqli->real_escape_string($_POST['cpf']);

    if (empty($nome)) {
        $_SESSION['mensagem_erro'] = 'Erro: O nome do cliente é obrigatório!';
    } else {
        $stmt_check_cpf = $mysqli->prepare("SELECT cliente_id FROM clientes WHERE cpf = ?");
        $stmt_check_cpf->bind_param("s", $cpf);
        $stmt_check_cpf->execute();
        $stmt_check_cpf->store_result();

        if ($stmt_check_cpf->num_rows > 0) {
            $_SESSION['mensagem_erro'] = 'Erro: CPF já cadastrado para outro cliente!';
        } else {
            $sql_inserir = "INSERT INTO clientes (nome, email, telefone, cpf) VALUES (?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql_inserir);
            if ($stmt === false) {
                $_SESSION['mensagem_erro'] = 'Erro na preparação da query de cadastro: ' . $mysqli->error;
            } else {
                $stmt->bind_param("ssss", $nome, $email, $telefone, $cpf);

                if ($stmt->execute()) {
                    $_SESSION['mensagem_sucesso'] = 'Cliente cadastrado com sucesso!';
                } else {
                    $_SESSION['mensagem_erro'] = 'Erro ao cadastrar cliente: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
        $stmt_check_cpf->close();
    }
    header("Location: clientes.php");
    exit();
}

if (isset($_POST['editar_cliente'])) {
    $cliente_id = intval($_POST['cliente_id_edicao']);
    $nome = $mysqli->real_escape_string($_POST['nome_edicao']);
    $email = $mysqli->real_escape_string($_POST['email_edicao']);
    $telefone = $mysqli->real_escape_string($_POST['telefone_edicao']);
    $cpf = $mysqli->real_escape_string($_POST['cpf_edicao']);

    if (empty($nome)) {
        $_SESSION['mensagem_erro'] = 'Erro: O nome do cliente é obrigatório para edição!';
    } else {
        $stmt_check_cpf = $mysqli->prepare("SELECT cliente_id FROM clientes WHERE cpf = ? AND cliente_id != ?");
        $stmt_check_cpf->bind_param("si", $cpf, $cliente_id);
        $stmt_check_cpf->execute();
        $stmt_check_cpf->store_result();

        if ($stmt_check_cpf->num_rows > 0) {
            $_SESSION['mensagem_erro'] = 'Erro: CPF já cadastrado para outro cliente!';
        } else {
            $sql_atualizar = "UPDATE clientes SET nome = ?, email = ?, telefone = ?, cpf = ? WHERE cliente_id = ?";
            $stmt = $mysqli->prepare($sql_atualizar);
            if ($stmt === false) {
                $_SESSION['mensagem_erro'] = 'Erro na preparação da query de edição: ' . $mysqli->error;
            } else {
                $stmt->bind_param("ssssi", $nome, $email, $telefone, $cpf, $cliente_id);

                if ($stmt->execute()) {
                    $_SESSION['mensagem_sucesso'] = 'Cliente atualizado com sucesso!';
                } else {
                    $_SESSION['mensagem_erro'] = 'Erro ao atualizar cliente: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
        $stmt_check_cpf->close();
    }
    header("Location: clientes.php");
    exit();
}

if (isset($_GET['excluir_cliente'])) {
    $cliente_id = intval($_GET['excluir_cliente']);

    $stmt_check_pedidos = $mysqli->prepare("SELECT COUNT(*) FROM pedidos WHERE cliente_id = ? AND status = 'aberto'");
    $stmt_check_pedidos->bind_param("i", $cliente_id);
    $stmt_check_pedidos->execute();
    $stmt_check_pedidos->bind_result($num_pedidos_abertos);
    $stmt_check_pedidos->fetch();
    $stmt_check_pedidos->close();

    if ($num_pedidos_abertos > 0) {
        $_SESSION['mensagem_erro'] = 'Erro: Não é possível excluir o cliente pois ele possui ' . $num_pedidos_abertos . ' pedido(s) EM ANDAMENTO associado(s). Conclua ou exclua os pedidos em andamento primeiro!';
    } else {
        $sql_excluir = "DELETE FROM clientes WHERE cliente_id = ?";
        $stmt = $mysqli->prepare($sql_excluir);
        if ($stmt === false) {
            $_SESSION['mensagem_erro'] = 'Erro na preparação da query de exclusão: ' . $mysqli->error;
        } else {
            $stmt->bind_param("i", $cliente_id);

            if ($stmt->execute()) {
                $_SESSION['mensagem_sucesso'] = 'Cliente excluído com sucesso!';
            } else {
                $_SESSION['mensagem_erro'] = 'Erro ao excluir cliente: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
    header("Location: clientes.php");
    exit();
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

            <?php if (!empty($mensagem_feedback)): ?>
                <p style="color: <?php echo (strpos($mensagem_feedback, 'Erro:') === 0 ? 'red' : 'green'); ?>; font-weight: bold; text-align: center; margin-top: 15px;"><?php echo $mensagem_feedback; ?></p>
            <?php endif; ?>

            <div id="popupCliente" class="popup-cliente-modal">
                <div class="popup-content">
                    <form action="" method="POST">
                        <div class="header-modal">
                            <div>
                                <span class="modal-close-btn" onclick="fecharModalCliente()">←</span>
                                <span>Cadastro de cliente</span>
                            </div>
                            <span>Cadastre o cliente</span>
                        </div>

                        <input type="text" name="nome" placeholder="Digite o nome" required class="modal-input">
                        <input type="email" name="email" placeholder="Digite o E-mail" class="modal-input">
                        <input type="text" name="telefone" placeholder="Digite o telefone" class="modal-input" onkeyup="formatarTelefone(this)">
                        <input type="text" name="cpf" placeholder="Digite o CPF" required class="modal-input" onkeyup="formatarCpf(this)">

                        <div class="modal-buttons">
                            <button type="submit" name="salvar_cliente" class="btn-salvar">
                                Salvar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="popupEdicaoCliente" class="popup-cliente-modal">
                <div class="popup-content">
                    <form action="" method="POST">
                        <div class="header-modal">
                            <div>
                                <span class="modal-close-btn" onclick="fecharModalEdicaoCliente()">←</span>
                                <span>Edição de cliente</span>
                            </div>
                            <span>Edite os dados do cliente</span>
                        </div>
                        <input type="hidden" name="cliente_id_edicao" id="cliente_id_edicao">

                        <input type="text" name="nome_edicao" id="nome_edicao" placeholder="Digite o nome" required class="modal-input">
                        <input type="email" name="email_edicao" id="email_edicao" placeholder="Digite o E-mail" class="modal-input">
                        <input type="text" name="telefone_edicao" id="telefone_edicao" placeholder="Digite o telefone" class="modal-input" onkeyup="formatarTelefone(this)">
                        <input type="text" name="cpf_edicao" id="cpf_edicao" placeholder="Digite o CPF" required class="modal-input" onkeyup="formatarCpf(this)">

                        <div class="modal-buttons">
                            <button type="submit" name="editar_cliente" class="btn-salvar">
                                Salvar Edição
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="popupConfirmacaoExclusao" class="popup-cliente-modal">
                <div class="popup-content small-popup">
                    <div class="header-modal">
                        <div>
                            <span class="modal-close-btn" onclick="fecharModalConfirmacaoExclusao()">←</span>
                            <span>Confirmar Exclusão</span>
                        </div>
                        <span>Tem certeza que deseja excluir o cliente?</span>
                    </div>
                    <p id="mensagemConfirmacaoExclusao" style="text-align: center; margin: 20px 0; font-weight: bold;"></p>
                    <div class="modal-buttons">
                        <button class="btn-cancelar" onclick="fecharModalConfirmacaoExclusao()">Cancelar</button>
                        <a href="#" id="linkExcluirCliente" class="btn-excluir-confirmar">Excluir</a>
                    </div>
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
                    <div>Ações</div>
                </div>

                <?php
                if ($result_clientes && $result_clientes->num_rows > 0) {
                    $result_clientes->data_seek(0);
                    while ($cliente = $result_clientes->fetch_assoc()) {
                        ?>
                        <div class="tabela-row">
                            <div><?php echo htmlspecialchars($cliente['nome']); ?></div>
                            <div><?php echo htmlspecialchars($cliente['cpf']); ?></div>
                            <div><?php echo htmlspecialchars($cliente['email']); ?></div>
                            <div><?php echo htmlspecialchars($cliente['telefone']); ?></div>
                            <div><?php echo htmlspecialchars($cliente['ultima_compra'] ? date('d/m/Y', strtotime($cliente['ultima_compra'])) : 'N/A'); ?></div>
                            <div><?php echo htmlspecialchars($cliente['qtd_pedidos']); ?></div>
                            <div class="tabela-actions">
                                <button class="btn-editar"
                                        onclick="abrirModalEdicaoCliente(
                                            <?php echo htmlspecialchars($cliente['cliente_id']); ?>,
                                            '<?php echo htmlspecialchars($cliente['nome']); ?>',
                                            '<?php echo htmlspecialchars($cliente['email']); ?>',
                                            '<?php echo htmlspecialchars($cliente['telefone']); ?>',
                                            '<?php echo htmlspecialchars($cliente['cpf']); ?>'
                                        )">
                                    <i class="fa-solid fa-pencil"></i>
                                </button>
                                <button class="btn-excluir" onclick="confirmarExclusao(<?php echo htmlspecialchars($cliente['cliente_id']); ?>, '<?php echo htmlspecialchars($cliente['nome']); ?>')">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo "<div class='tabela-row'><div style='grid-column: 1 / span 7; text-align: center; padding: 20px;'>Nenhum cliente encontrado.</div></div>";
                }
                ?>
            </div>
        </section>
    </main>

    <script>
        function formatarCpf(input) {
            let cpf = input.value.replace(/\D/g, '');
            if (cpf.length > 11) {
                cpf = cpf.substring(0, 11);
            }
            if (cpf.length > 9) {
                cpf = cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            } else if (cpf.length > 6) {
                cpf = cpf.replace(/(\d{3})(\d{3})(\d{3})/, '$1.$2.$3');
            } else if (cpf.length > 3) {
                cpf = cpf.replace(/(\d{3})(\d{3})/, '$1.$2');
            }
            input.value = cpf;
        }

        function formatarTelefone(input) {
            let telefone = input.value.replace(/\D/g, '');
            if (telefone.length > 11) {
                telefone = telefone.substring(0, 11);
            }
            if (telefone.length > 10) {
                telefone = telefone.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
            } else if (telefone.length > 6) {
                telefone = telefone.replace(/^(\d{2})(\d{4})(\d{4}).*/, '($1) $2-$3');
            } else if (telefone.length > 2) {
                telefone = telefone.replace(/^(\d{2})(\d+)/, '($1) $2');
            }
            input.value = telefone;
        }

        function abrirModalCliente() {
            document.getElementById('popupCliente').style.display = 'flex';
            document.body.classList.add('no-scroll');
        }

        function fecharModalCliente() {
            document.getElementById('popupCliente').style.display = 'none';
            document.body.classList.remove('no-scroll');
        }

        function abrirModalEdicaoCliente(id, nome, email, telefone, cpf) {
            document.getElementById('popupEdicaoCliente').style.display = 'flex';
            document.body.classList.add('no-scroll');

            document.getElementById('cliente_id_edicao').value = id;
            document.getElementById('nome_edicao').value = nome;
            document.getElementById('email_edicao').value = email;
            document.getElementById('telefone_edicao').value = telefone;
            document.getElementById('cpf_edicao').value = cpf;
        }

        function fecharModalEdicaoCliente() {
            document.getElementById('popupEdicaoCliente').style.display = 'none';
            document.body.classList.remove('no-scroll');
        }

        function confirmarExclusao(clienteId, clienteNome) {
            document.getElementById('mensagemConfirmacaoExclusao').textContent = `Você realmente deseja excluir o cliente ${clienteNome}?`;
            document.getElementById('linkExcluirCliente').href = `clientes.php?excluir_cliente=${clienteId}`;
            document.getElementById('popupConfirmacaoExclusao').style.display = 'flex';
            document.body.classList.add('no-scroll');
        }

        function fecharModalConfirmacaoExclusao() {
            document.getElementById('popupConfirmacaoExclusao').style.display = 'none';
            document.body.classList.remove('no-scroll');
        }

    </script>
</body>

</html>

<?php
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}
?>