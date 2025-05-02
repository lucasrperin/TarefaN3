<?php
session_start();
require '../Config/Database.php';

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
