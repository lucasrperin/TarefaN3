<?php
session_start();
require '../config/Database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    // Buscar usuário no banco de dados
    $sql = "SELECT * FROM TB_USUARIO WHERE Email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $usuario = $resultado->fetch_assoc();
        
        // Verifica a senha
        if ($senha === $usuario['Senha']) {
            $_SESSION['usuario_id'] = $usuario['Id'];
            $_SESSION['usuario_nome'] = $usuario['Nome'];
            header("Location: ../home.php"); // Redireciona para o painel
            exit();
        } else {
            $erro = "Senha incorreta!";
        }
    } else {
        $erro = "Usuário não encontrado!";
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="Public/login.css">
</head>
<body>
    <h2>Login</h2>
    <?php if (isset($erro)) echo "<p style='color:red;'>$erro</p>"; ?>
    <form method="POST" action="">
        <label>Email:</label>
        <input type="email" name="email" required><br>
        
        <label>Senha:</label>
        <input type="password" name="senha" required><br>
        
        <button type="submit">Entrar</button>
    </form>
</body>
</html>