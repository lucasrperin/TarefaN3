<?php
include '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

$id          = $_POST['id'];
$data        = $_POST['data'];
$hora        = $_POST['hora'];
$cliente     = $_POST['cliente'];
$sistema     = $_POST['sistema'];
$consultor   = $_POST['consultor'];
$status      = $_POST['status'];
$observacoes = $_POST['observacoes'] ?? '';

$query = "UPDATE TB_TREINAMENTOS
          SET data = ?, hora = ?, cliente = ?, sistema = ?, consultor = ?, status = ?, observacoes = ?
          WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'sssssssi',
  $data,
  $hora,
  $cliente,
  $sistema,
  $consultor,
  $status,
  $observacoes,
  $id
);

if (mysqli_stmt_execute($stmt)) {
  header("Location: treinamento.php?success=2");
} else {
  header("Location: treinamento.php?error=2");
}
exit();
