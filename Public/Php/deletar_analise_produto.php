<?php
require '../../Config/Database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id_remover_produto'] ?? 0);

    if ($id > 0) {
        $sql = "DELETE FROM TB_ANALISES_PROD WHERE Id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            header("Location: ../../index.php?success=3");
        } else {
            header("Location: ../../index.php?error=4");
        }
        exit;
    }
}
header("Location: ../../index.php?error=5");
