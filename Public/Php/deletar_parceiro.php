<?php
require_once '../../Config/Database.php';

$id = $_POST['id_parceiro'];

$stmt = $conn->prepare("DELETE FROM TB_PARCEIROS WHERE Id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header('Location: ../../Views/parceiros.php?success=3');
} else {
    echo "Erro ao remover parceiro: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
