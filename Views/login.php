<?php
session_start();
require '../Config/Database.php';

// —————— 2.1 Auto-login via cookie ——————
if (!isset($_SESSION['usuario_id']) && !empty($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $stmt = $conn->prepare("
      SELECT * 
        FROM TB_USUARIO 
       WHERE remember_token = ? 
         AND remember_expiry > NOW()
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($usuario = $res->fetch_assoc()) {
        // restaura sessão
        $_SESSION['usuario_id']   = $usuario['Id'];
        $_SESSION['usuario_nome'] = $usuario['Nome'];
        $_SESSION['cargo']        = $usuario['Cargo'];
        // redireciona conforme cargo
        if (in_array($usuario['Cargo'], ['Admin','Viewer'])) {
            header("Location: menu.php");
        } elseif (in_array($usuario['Cargo'], ['User','Conversor'])) {
            header("Location: ../Views/menu.php");
        } elseif ($usuario['Cargo'] === 'Comercial') {
            header("Location: ../Views/indicacao.php");
        } else {
            header("Location: menu.php");
        }
        exit();
    }
    // token inválido ou expirado → apaga cookie
    setcookie('remember_me', '', time() - 3600, '/');
}

// —————— 2.2 Login via formulário ——————
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    $stmt = $conn->prepare("SELECT * FROM TB_USUARIO WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    //Extrai senha hash
    $stmt_senha = $conn->prepare("SELECT Senha FROM TB_USUARIO WHERE Email = ?");
    $stmt_senha->bind_param("s", $email);
    $stmt_senha->execute();
    $res_senha = $stmt_senha->get_result();

    if ($usuario = $res->fetch_assoc()) {
        // senha em texto simples
         if ((password_verify($senha, $usuario['Senha'])) || ($senha === $usuario['Senha'])) {
            $_SESSION['usuario_id']   = $usuario['Id'];
            $_SESSION['usuario_nome'] = $usuario['Nome'];
            $_SESSION['cargo']        = $usuario['Cargo'];

            // checkbox “Lembrar-me”?
            if (!empty($_POST['remember'])) {
                // Marcou "Lembrar-me"
                $token  = bin2hex(random_bytes(16));
                $expiry_datetime = new DateTime();
                $expiry_datetime->setTime(23, 59, 59);
                $expiry_timestamp = $expiry_datetime->getTimestamp();

                setcookie('remember_me', $token, $expiry_timestamp, '/');

                $dt = $expiry_datetime->format('Y-m-d H:i:s');
                $upd = $conn->prepare("
                  UPDATE TB_USUARIO 
                    SET remember_token  = ?, 
                        remember_expiry = ? 
                  WHERE Id = ?
                ");
                $upd->bind_param('ssi', $token, $dt, $usuario['Id']);
                $upd->execute();
            } else {
                // NÃO marcou "Lembrar-me" → apaga token e cookie
                setcookie('remember_me', '', time() - 3600, '/');

                $upd = $conn->prepare("
                  UPDATE TB_USUARIO 
                    SET remember_token = NULL, 
                        remember_expiry = NULL 
                  WHERE Id = ?
                ");
                $upd->bind_param('i', $usuario['Id']);
                $upd->execute();
            }

            // redireciona conforme cargo
            if (in_array($usuario['Cargo'], ['Admin','Viewer'])) {
                header("Location: menu.php");
            } elseif (in_array($usuario['Cargo'], ['User','Conversor'])) {
                header("Location: ../Views/menu.php");
            } elseif ($usuario['Cargo'] === 'Comercial') {
                header("Location: ../Views/indicacao.php");
            } else {
                header("Location: menu.php");
            }
            exit();
        }
        $erro = "Senha incorreta!";
    } else {
        $erro = "Usuário não encontrado!";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login - Painel Zucchetti</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet">
  <link rel="stylesheet" href="../Public/login.css">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap"
        rel="stylesheet">
  <link rel="icon" href="../Public/Image/LogoTituto.png" type="image/png">
</head>
<body>
  <div class="login-wrapper">
    <div class="login-card">
      <img src="../Public/Image/zucchetti_blue.png"
           class="light-logo" width="150" alt="Zucchetti Logo">

      <?php if (isset($erro)): ?>
        <div class="alert alert-danger"><?= $erro ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="mb-3">
          <label for="email" class="form-label">Email</label>
          <input type="email" name="email" id="email"
                 class="form-control" placeholder="Seu email" required>
        </div>

        <div class="mb-3">
          <label for="senha" class="form-label">Senha</label>
          <input type="password" name="senha" id="senha"
                 class="form-control" placeholder="Sua senha" required>
        </div>

        <div class="mb-3 form-check">
          <input type="checkbox" name="remember" id="remember"
                 class="form-check-input">
          <label for="remember" class="form-check-label">
            Lembrar-me
          </label>
        </div>

        <button type="submit" class="btn btn-primary w-100">
          Entrar
        </button>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
  </script>
</body>
</html>
