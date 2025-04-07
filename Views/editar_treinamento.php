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
$tipo        = $_POST['tipo'];
$sistema     = $_POST['sistema'];
$consultor   = $_POST['consultor'];
$status      = $_POST['status'];
$observacoes = $_POST['observacoes'];
$duracao     = isset($_POST['duracao']) ? intval($_POST['duracao']) : 30;

// Atualiza o agendamento na TB_TREINAMENTOS
$query = "UPDATE TB_TREINAMENTOS SET data = ?, hora = ?, tipo = ?, duracao = ?, sistema = ?, consultor = ?, status = ?, observacoes = ? WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'sssissssi', $data, $hora, $tipo, $duracao, $sistema, $consultor, $status, $observacoes, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header("Location: treinamento.php");
exit();
?>
