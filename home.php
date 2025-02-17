<?php
session_start();

// Verifica se o usuário está logado, se não, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Código para mostrar o conteúdo da página
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Home</title>
</head>
<body>
    <h2>Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</h2>
    <p>Conteúdo protegido da página Home.</p>
    <a href="Views/login.php">Sair</a>
</body>
</html>