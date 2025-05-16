<?php
// 1) inclua o Database.php — usando document root fica à prova de qualquer subpasta
require_once $_SERVER['DOCUMENT_ROOT'] . '/TarefaN3/Config/Database.php';
session_start();

// —————— auto-login via cookie ——————
if (!isset($_SESSION['usuario_id']) && !empty($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $stmt = $conn->prepare("
      SELECT Id, Nome, Cargo
        FROM TB_USUARIO
       WHERE remember_token = ?
         AND remember_expiry > NOW()
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    if ($u = $stmt->get_result()->fetch_assoc()) {
        $_SESSION['usuario_id']   = $u['Id'];
        $_SESSION['usuario_nome'] = $u['Nome'];
        $_SESSION['cargo']        = $u['Cargo'];
    } else {
        setcookie('remember_me','',time()-3600,'/');
    }
}

// —————— proteção de rota ——————
if (!isset($_SESSION['usuario_id'])) {
    // redireciona para o login dentro de Views
    header('Location: /TarefaN3/Views/login.php');
    exit();
}
