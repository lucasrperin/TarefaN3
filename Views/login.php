<?php
session_start();
require '../Config/Database.php'; // Ajuste conforme sua estrutura

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    $sql = "SELECT * FROM TB_USUARIO WHERE Email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $usuario = $resultado->fetch_assoc();

        // Comparação direta da senha (sem hash, pois foi gravada como texto simples)
        if ($senha == $usuario['Senha']) {
            $_SESSION['usuario_id'] = $usuario['Id'];
            $_SESSION['usuario_nome'] = $usuario['Nome'];
            $_SESSION['cargo'] = $usuario['Cargo']; // Armazena o cargo na sessão

            // Verifica o cargo do usuário e redireciona conforme o cargo
            if ($usuario['Cargo'] == 'Admin' || $usuario['Cargo'] == 'Viewer') {
                header("Location: menu.php");
            } elseif ($usuario['Cargo'] == 'User') {
                header("Location: ../Views/menu.php");
            } elseif ($usuario['Cargo'] == 'Conversor') {
                header("Location: ../Views/menu.php");
            } else {
                // Caso o cargo não seja reconhecido, redireciona para uma página padrão ou exibe uma mensagem
                header("Location: ../index.php");
            }
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Arquivo CSS personalizado -->
    <link rel="stylesheet" href="../Public/login.css">
    <link rel="icon" href="..\Public\Image\icone2.png" type="image/png">
</head>
<body>

    <div class="login-container text-center">
        <!-- Logo -->
        <img src="../Public/Image/Screenshot_1.png" alt="Logo" style="max-width: 200px;">
        
        <?php if (isset($erro)) echo "<div class='alert alert-danger'>$erro</div>"; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Senha</label>
                <input type="password" name="senha" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">Entrar</button>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
