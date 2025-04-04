<?php
include '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

$id = $_POST['id'];

$query = "DELETE FROM TB_TREINAMENTOS WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $id);

if (mysqli_stmt_execute($stmt)) {
  header("Location: treinamento.php?success=3");
} else {
  header("Location: treinamento.php?error=3");
}
exit();
