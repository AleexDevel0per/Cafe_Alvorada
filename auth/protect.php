<?php

if(!isset($_SESSION)) {
    session_start();
}

if(!isset($_SESSION["usuario_id"])) {
    die("Você não pode acessar essa página. Faça login primeiro. <p><a href=\"../auth/login.php\">Entrar</a></p>");
}

?>