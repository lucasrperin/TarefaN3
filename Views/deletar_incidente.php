<?php
include '../Config/Database.php';
session_start();

if (!isset($_GET['id'])) {
    header("Location: incidente.php");
    exit;
}

$id = intval($_GET['id']);

$sql = "DELETE FROM TB_INCIDENTES WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    header("Location: incidente.php?msg=delete_success");
    exit;
} else {
    echo "Erro ao excluir: " . $stmt->error;
}
$stmt->close();
$conn->close();
?>
