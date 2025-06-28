<?php
require '../Config/Database.php';
session_start();

// Se existir cookie “remember_me” e usuário logado, limpa token no banco
if (!empty($_COOKIE['remember_me']) && isset($_SESSION['usuario_id'])) {
    $stmt = $conn->prepare("
      UPDATE TB_USUARIO
         SET remember_token  = NULL,
             remember_expiry = NULL
       WHERE Id = ?
    ");
    $stmt->bind_param('i', $_SESSION['usuario_id']);
    $stmt->execute();

    // apaga o cookie
    setcookie('remember_me', '', time() - 3600, '/');
}

// destrói sessão
session_destroy();

// redireciona para o login
header('Location: ../Views/login.php');
exit();
?>
