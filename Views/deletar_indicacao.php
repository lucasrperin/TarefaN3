<?php
session_start();
require '../Config/Database.php';

// Verificar se quem tentou excluir é o dono da indicação
$sqlChk = "SELECT user_id FROM TB_INDICACAO WHERE id = '$id'";
$rowChk = mysqli_fetch_assoc(mysqli_query($conn, $sqlChk));
if (!in_array($cargo, ['Admin','Comercial'], true) && $rowChk['user_id'] != $usuarioId) {
    header("Location: indicacao.php?erro=4");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['id'])) {
        header("Location: indicacao.php");
        exit();
    }
    $id = intval($_POST['id']);

    // Exclui a indicação
    $sql = "DELETE FROM TB_INDICACAO WHERE id = '$id'";
    if (mysqli_query($conn, $sql)) {
        header("Location: indicacao.php?success=3");
        exit();
    } else {
        echo "Erro ao excluir: " . mysqli_error($conn);
    }
} else {
    header("Location: indicacao.php?erro=3");
    exit();
}