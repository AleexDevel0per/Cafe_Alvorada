<?php
include('../config/conexao.php');

$errorMessage = '';

if(isset($_POST['user']) || isset($_POST['senha'])) {

    if(strlen($_POST['user']) == 0) {
        $errorMessage = "Preencha seu usuário";
    } else if(strlen($_POST['password']) == 0) {
        $errorMessage = "Preencha sua senha";
    } else {
        $user = $mysqli->real_escape_string($_POST['user']);
        $senha = $mysqli->real_escape_string($_POST['password']);

        $sql_code = "SELECT * FROM usuarios WHERE nome_usuario = '$user' AND senha = '$senha'";
        $sql_query = $mysqli->query($sql_code) or die("Falha na execução do código SQL: " . $mysqli->error);

        $quantidade = $sql_query->num_rows;

        if($quantidade == 1) {
            $usuario = $sql_query->fetch_assoc();

            if(!isset($_SESSION)) {
                session_start();
            }

            $_SESSION['usuario_id'] = $usuario['usuario_id'];
            $_SESSION['nome_usuario'] = $usuario['nome_usuario'];

            header('Location: ../views/dashboard.php');
            exit();
        } else {
            $errorMessage = "Falha ao logar! Usuário ou senha incorretos";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="../public/css/login.css">
</head>

<body>
    <div class="container">
        <form class="form" method="POST">
            <div class="card">
                <div class="card-top">
                    <div class="logo-container">
                        <img class="logo" src="../public/img/logo.png" alt="">
                        <p class="logo-text">ALVORADA</p>
                    </div>
                    <h2 class="title">Bem vindo!</h2>
                    <p class="paragrafo">Digite seu e-mail e senha para login.</p>
                </div>

                <div class="container-card">
                    <div class="card-group">
                        <label>Seu usuário</label>
                        <input type="text" name="user" placeholder="Informe seu usuário" required>
                    </div>

                    <div class="card-group">
                        <label>Sua senha</label>
                        <input type="password" name="password" placeholder="Informe sua senha" required>
                    </div>

                    <div class="card-group">
                        <label class="checkbox-container">
                            <input type="checkbox" name="remember">
                            <span class="checkmark"></span>
                            Lembre-me
                        </label>
                    </div>

                    <div class="card-group">
                        <button type="submit">Acessar minha conta</button>
                    </div>
                </div>
            </div>
        </form>

        <div class="catchphrase-content">
            <p class="catchphrase">Aqueça seu <span>CORAÇÃO</span> com o sabor de cafés artesanais e um ambiente
                acolhedor.</p>
        </div>
    </div>

    <?php if (!empty($errorMessage)): ?>
    <script>
        alert("<?php echo $errorMessage; ?>");
    </script>
    <?php endif; ?>

</body>

</html>