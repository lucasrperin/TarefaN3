<?php
require_once __DIR__ . '/../Config/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<script>console.log('[auth.php] Chamado');</script>";

if (isset($_SESSION['usuario_id'])) {
    echo "<script>console.log('[auth.php] Sessão ativa — validando token');</script>";

    // Se também existir remember_me, valida se ainda é válido
    if (!empty($_COOKIE['remember_me'])) {
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
            echo "<script>console.log('[auth.php] Token ainda válido');</script>";
            // OK, mantém sessão
            return;
        } else {
            echo "<script>console.log('[auth.php] Token expirado — destruindo sessão');</script>";
            setcookie('remember_me', '', time() - 3600, '/');
            session_destroy();
            redirectToLogin();
        }
    } else {
        // Não tem token → assume que foi um login normal (não lembrar)
        echo "<script>console.log('[auth.php] Sessão normal, sem token — OK');</script>";
        return;
    }
}

// Se não tem sessão e tem token → tenta restaurar
if (!empty($_COOKIE['remember_me'])) {
    echo "<script>console.log('[auth.php] Sem sessão — tentando token');</script>";
    
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
        echo "<script>console.log('[auth.php] Token válido — criando sessão');</script>";
        $_SESSION['usuario_id']   = $usuario['Id'];
        $_SESSION['usuario_nome'] = $usuario['Nome'];
        $_SESSION['cargo']        = $usuario['Cargo'];
        return;
    } else {
        echo "<script>console.log('[auth.php] Token inválido/expirado — redirecionando');</script>";
        setcookie('remember_me', '', time() - 3600, '/');
    }
}

echo "<script>console.log('[auth.php] Redirecionando para login — sem sessão e token inválido');</script>";
redirectToLogin();


// --- Função utilitária para redirecionar sempre para /Views/login.php (root do sistema)
function redirectToLogin() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $loginPath = '/Views/login.php';
    header("Location: $protocol://$host$loginPath");
    exit();
}
?>
