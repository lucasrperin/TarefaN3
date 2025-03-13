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
    $classificacao = ($_POST['edit_classi_id']);
    $data_escuta = $_POST['data_escuta'];
    $positivo    = ($_POST['edit_positivo']);
    $transcricao = trim($_POST['transcricao']);
    $feedback    = trim($_POST['feedback']);

    $stmt = $conn->prepare("UPDATE TB_ESCUTAS SET user_id = ?, classi_id = ?, data_escuta = ?, transcricao = ?, feedback = ?, P_N = ? WHERE id = ?");
    $stmt->bind_param("iissssi", $user_id, $classificacao, $data_escuta, $transcricao,  $feedback,  $positivo, $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Escuta atualizada com sucesso.";
    } else {
        $_SESSION['error'] = "Erro ao atualizar a escuta. Tente novamente.";
    }
    $stmt->close();
}

header("Location: escutas_por_analista.php?user_id=$user_id");
exit;
?>
