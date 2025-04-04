<?php
include '../Config/Database.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Apenas Admin pode excluir
if (!isset($_SESSION['cargo']) || $_SESSION['cargo'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $user_id = $_POST['user_id'];
    $stmt = $conn->prepare("DELETE FROM TB_ESCUTAS WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Escuta excluída com sucesso.";
    } else {
        $_SESSION['error'] = "Erro ao excluir a escuta. Tente novamente.";
    }
    $stmt->close();
}

$conn->close();
    header("Location: escutas_por_analista.php?user_id=$user_id&success=4");
    exit();
?>
