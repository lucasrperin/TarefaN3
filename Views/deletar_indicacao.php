<?php
session_start();
require '../Config/Database.php';

if (!isset($_GET['id'])) {
    header("Location: indicacao.php");
    exit();
}

$id = $_GET['id'];

// Exclui a indicação
$sql = "DELETE FROM TB_INDICACAO WHERE id = '$id'";
if (mysqli_query($conn, $sql)) {
    header("Location: indicacao.php");
    exit();
} else {
    echo "Erro ao excluir: " . mysqli_error($conn);
}
