<?php
// deletar_folga.php
include '../Config/Database.php';
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "DELETE FROM TB_FOLGA WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if($stmt) {
       $stmt->bind_param("i", $id);
       $stmt->execute();
       $stmt->close();
       header("Location: folga.php");
       exit();
    } else {
       echo "Erro na preparação da query: " . $conn->error;
    }
} else {
    echo "ID inválido";
}
?>
