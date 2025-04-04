<?php
require '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

// Recebendo campos do POST
$data        = $_POST['data'];
$hora        = $_POST['hora'];
$tipo        = $_POST['tipo'];      // NOVO
$cnpjcpf     = $_POST['cnpjcpf'];   // NOVO
$cliente     = $_POST['cliente'];
$sistema     = $_POST['sistema'];
$consultor   = $_POST['consultor'];
$serial      = $_POST['serial'];    // NOVO
$status      = $_POST['status'];
$observacoes = $_POST['observacoes'] ?? '';

// Insert
$query = "INSERT INTO TB_TREINAMENTOS 
          (data, hora, tipo, cnpjcpf, cliente, sistema, consultor, serial, status, observacoes)
          VALUES (?,?,?,?,?,?,?,?,?,?)";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'ssssssssss',
  $data,
  $hora,
  $tipo,
  $cnpjcpf,
  $cliente,
  $sistema,
  $consultor,
  $serial,
  $status,
  $observacoes
);

if (mysqli_stmt_execute($stmt)) {
  header("Location: treinamento.php?success=1");
} else {
  header("Location: treinamento.php?error=1");
}
exit();
