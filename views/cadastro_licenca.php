<?php

include("../auth/protect.php");
include("../config/conexao.php"); // Inclua o arquivo de conexão com o banco de dados

// --- Processamento do Formulário de Cadastro de Licença ---
if (isset($_POST['salvar_licenca'])) {
    $nome_licenca = $mysqli->real_escape_string($_POST['nome_licenca']);
    $orgao_responsavel = $mysqli->real_escape_string($_POST['orgao_responsavel']);
    $cnpj = $mysqli->real_escape_string($_POST['cnpj']); // Supondo que você queira adicionar CNPJ aqui
    $data_validade = $mysqli->real_escape_string($_POST['data_validade']);
    $prazo_expiracao = $mysqli->real_escape_string($_POST['prazo_expiracao']);

    $caminho_documento = null; // Variável para armazenar o caminho do arquivo

    // --- Lógica de Upload de Arquivo ---
    if (isset($_FILES['documento_licenca']) && $_FILES['documento_licenca']['error'] == UPLOAD_ERR_OK) {
        $arquivo_tmp = $_FILES['documento_licenca']['tmp_name'];
        $nome_arquivo = basename($_FILES['documento_licenca']['name']);
        $diretorio_upload = "../uploads/licencas/"; // CRIE ESTA PASTA E GARANTA PERMISSÕES DE ESCRITA!

        // Garante que o diretório de upload exista
        if (!is_dir($diretorio_upload)) {
            mkdir($diretorio_upload, 0777, true); // Cria o diretório se não existir, com permissões
        }

        $caminho_documento = $diretorio_upload . uniqid() . '_' . $nome_arquivo; // Adiciona um ID único para evitar conflitos

        if (move_uploaded_file($arquivo_tmp, $caminho_documento)) {
            // Arquivo movido com sucesso
        } else {
            echo "<script>alert('Erro ao fazer upload do documento.');</script>";
            $caminho_documento = null; // Reseta se houver erro no upload
        }
    }

    // Validação básica
    if (!empty($nome_licenca) && !empty($orgao_responsavel) && !empty($data_validade) && !empty($prazo_expiracao)) {
        // Converte a data para o formato YYYY-MM-DD para o banco de dados
        $data_validade_db = date('Y-m-d', strtotime(str_replace('/', '-', $data_validade)));

        // Consulta SQL para inserir a nova licença
        // Inclui caminho_documento se o upload for parte da sua tabela licencas
        $sql_inserir = "INSERT INTO licencas (nome_licenca, cnpj, orgao_responsavel, data_validade, prazo_expiracao, caminho_documento) VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $mysqli->prepare($sql_inserir);

        if ($stmt === false) {
            die('Erro na preparação da query: ' . $mysqli->error);
        }

        // 'ssssss' se caminho_documento for string. Se não tiver, 'sssss'
        $stmt->bind_param("ssssss", $nome_licenca, $cnpj, $orgao_responsavel, $data_validade_db, $prazo_expiracao, $caminho_documento);

        if ($stmt->execute()) {
            echo "<script>alert('Licença cadastrada com sucesso!'); window.location.href='licencas.php';</script>";
            exit();
        } else {
            echo "<script>alert('Erro ao cadastrar licença: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Por favor, preencha todos os campos obrigatórios.');</script>";
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alvorada - Cadastro de Licença</title>
    <link rel="stylesheet" href="../public/css/cadastro_licenca.css">
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
            <h2>Cadastro de licença</h2>
            <p>Cadastre licenças para que sejam visualizadas</p>
            <div class="module-info">
                <span>4 Módulos</span>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" class="licenca-form">
                <div class="input-group">
                    <label for="nome_licenca" class="input-label">Nome da licença</label>
                    <input type="text" id="nome_licenca" name="nome_licenca" placeholder="NOME DA LICENÇA" class="input-field" required>
                </div>

                <div class="input-group">
                    <label for="cnpj_licenca" class="input-label">CNPJ</label>
                    <input type="text" id="cnpj_licenca" name="cnpj" placeholder="Ex: 00.000.000/0000-00" class="input-field" required>
                </div>

                <div class="input-group">
                    <label for="orgao_responsavel" class="input-label">Órgão responsável pelo licenciamento</label>
                    <input type="text" id="orgao_responsavel" name="orgao_responsavel" placeholder="NOME DO ÓRGÃO" class="input-field" required>
                </div>

                <div class="input-group">
                    <label for="data_validade" class="input-label">Data de validade da licença</label>
                    <input type="date" id="data_validade" name="data_validade" class="input-field" required>
                </div>

                <div class="input-group">
                    <label for="documento_licenca" class="input-label">Anexar documento</label>
                    <input type="file" id="documento_licenca" name="documento_licenca" class="input-field-file">
                    <small>Tipos de arquivo permitidos: PDF, JPG, PNG (Max: 5MB)</small>
                </div>

                <div class="input-group">
                    <div class="input-label">Enviar notificação - prazo em dias antes da expiração</div>
                    <div class="notification-options">
                        <label class="notification-option">
                            <input type="radio" name="prazo_expiracao" value="140" required>
                            <span>140 dias.</span>
                        </label>
                        <label class="notification-option">
                            <input type="radio" name="prazo_expiracao" value="90" required>
                            <span>90 dias.</span>
                        </label>
                        <label class="notification-option">
                            <input type="radio" name="prazo_expiracao" value="60" required>
                            <span>60 dias.</span>
                        </label>
                        <label class="notification-option">
                            <input type="radio" name="prazo_expiracao" value="30" required>
                            <span>30 dias.</span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="salvar_licenca" class="btn-salvar">SALVAR</button>
                    <button type="button" class="btn-cancelar" onclick="window.location.href='licencas.php'">Cancelar</button>
                </div>
            </form>
        </section>
    </main>
</body>

</html>

<?php
// Fecha a conexão com o banco de dados ao final da página para liberar recursos.
$mysqli->close();
?>