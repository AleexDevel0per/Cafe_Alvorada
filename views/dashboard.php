<?php
include("../auth/protect.php");
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alvorada</title>
    <link rel="stylesheet" href="../public/css/dashboard.css">
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

        <div class="welcome">
            <span>Olá,</span>
            <b><?php echo $_SESSION['nome_usuario']; ?></b>
            <p>
                
            </p>
        </div>

        <section class="intro-section">
            <h2>Bem-vindo ao ERP Alvorada</h2>
            <p>Nesta àrea estão relacionados os módulos para gestão do seu negócio</p>
            <div class="badge">
                <span>4 Módulos</span>
            </div>
        </section>

        <section class="modules-section">
            <a href="pedidos.php">
                <div class="modules-container">
                    <div class="module-card">
                        <img src="../public/icons/Pedidosbig.png" alt="Ícone Pedidos">
                        <div>Pedidos</div>
                    </div>
            </a>
            <a href="clientes.php">
                <div class="module-card">
                    <img src="../public/icons/Cliente-img.png" alt="Ícone Clientes">
                    <div>Clientes</div>
                </div>
            </a>
            <a href="estoque.php">
                <div class="module-card">
                    <img src="../public/icons/Estoque-img.png" alt="Ícone Estoque">
                    <div>Estoque</div>
                </div>
            </a>
            <a href="licencas.php">
                <div class="module-card">
                    <img src="../public/icons/Licenca-img.png" alt="Ícone Licenca">
                    <div>Licenças</div>
                </div>
            </a>
            </div>
        </section>
    </main>

</body>

</html>