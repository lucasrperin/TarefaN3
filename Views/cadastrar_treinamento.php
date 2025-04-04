<?php
include '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

// Recebe os dados do formulário
$data        = $_POST['data'];
$hora        = $_POST['hora'];
$cliente     = $_POST['cliente'];
$sistema     = $_POST['sistema'];
$consultor   = $_POST['consultor'];
$status      = $_POST['status'];
$observacoes = $_POST['observacoes'] ?? '';

// Insert
$query = "INSERT INTO TB_TREINAMENTOS (data, hora, cliente, sistema, consultor, status, observacoes)
          VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'sssssss',
  $data,
  $hora,
  $cliente,
  $sistema,
  $consultor,
  $status,
  $observacoes
);

if (mysqli_stmt_execute($stmt)) {
  // Redireciona de volta para treinamentos.php
  header("Location: treinamento.php?success=1");
} else {
  header("Location: treinamento.php?error=1");
}
exit();
