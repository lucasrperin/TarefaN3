<?php
include '../Config/Database.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Apenas Admin pode editar
if (!isset($_SESSION['cargo']) || $_SESSION['cargo'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = $_POST['id'];
    $user_id     = $_POST['user_id'];
    $data_escuta = $_POST['data_escuta'];
    $transcricao = trim($_POST['transcricao']);
    $feedback    = trim($_POST['feedback']);

    $stmt = $conn->prepare("UPDATE TB_ESCUTAS SET user_id = ?, data_escuta = ?, transcricao = ?, feedback = ? WHERE id = ?");
    $stmt->bind_param("isssi", $user_id, $data_escuta, $transcricao, $feedback, $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Escuta atualizada com sucesso.";
    } else {
        $_SESSION['error'] = "Erro ao atualizar a escuta. Tente novamente.";
    }
    $stmt->close();
}

header("Location: escutas.php");
exit;
?>
