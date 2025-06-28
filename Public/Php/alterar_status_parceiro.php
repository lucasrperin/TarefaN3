<?php
require_once '../../Config/Database.php';

$id     = $_GET['id'];
$status = $_GET['status']; // 'A' ou 'I'

// Validação simples:
if (!in_array($status, ['A', 'I'])) {
    echo "Status inválido.";
    exit;
}

$stmt = $conn->prepare("UPDATE TB_PARCEIROS SET status = ? WHERE Id = ?");
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    header('Location: ../../Views/parceiros.php?success=4');
    exit;
} else {
    echo "Erro ao alterar status do parceiro: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
