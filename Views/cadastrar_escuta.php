<?php
include '../Config/Database.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Apenas Admin pode cadastrar
if (!isset($_SESSION['cargo']) || $_SESSION['cargo'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id     = $_POST['user_id'];
    $data_escuta = $_POST['data_escuta'];
    $classificacao = ($_POST['classi_id']);
    $positivo    = ($_POST['positivo']);
    $transcricao = trim($_POST['transcricao']);
    $feedback    = trim($_POST['feedback']);
    $admin_id    = $_SESSION['usuario_id']; // ID do Admin logado

    
        $stmt = $conn->prepare("INSERT INTO TB_ESCUTAS (user_id, admin_id, classi_id, data_escuta, transcricao, feedback, P_N) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiissss", $user_id, $admin_id, $classificacao, $data_escuta, $transcricao, $feedback, $positivo);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Escuta registrada com sucesso.";
        } else {
            $_SESSION['error'] = "Erro ao registrar a escuta. Tente novamente.";
        }
        $stmt->close();
    }


header("Location: escutas.php");
exit;
?>
