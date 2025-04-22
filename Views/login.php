<?php
require '../Config/Database.php'; // Ajuste conforme sua estrutura
session_start();

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

            // Redirecionamento conforme o cargo
            if ($usuario['Cargo'] == 'Admin' || $usuario['Cargo'] == 'Viewer') {
                header("Location: menu.php");
            } elseif ($usuario['Cargo'] == 'User' || $usuario['Cargo'] == 'Conversor') {
                header("Location: ../Views/menu.php");
            } elseif ($usuario['Cargo'] == 'Comercial') {
                header("Location: ../Views/indicacao.php");
            } else {
                header("Location: menu.php");
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
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Painel Zucchetti</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="../Public/login.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
    <link rel="icon" href="../Public/Image/LogoTituto.png" type="image/png">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <!-- Logo ajustado para a pasta Public/Image -->
            <img src="../Public/Image/zucchetti_blue.png" class="light-logo" width="150" alt="Zucchetti Logo">
          
           
            <?php if (isset($erro)) echo "<div class='alert alert-danger'>$erro</div>"; ?>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="Seu email" required>
                </div>
                <div class="mb-3">
                    <label for="senha" class="form-label">Senha</label>
                    <input type="password" name="senha" id="senha" class="form-control" placeholder="Sua senha" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Entrar</button>
            </form>
        </div>
    </div>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
