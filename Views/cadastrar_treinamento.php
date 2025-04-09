<?php
include '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../Views/login.php");
    exit();
}

$data         = $_POST['data'];
$hora         = $_POST['hora'];
$tipo         = $_POST['tipo'];
$cliente_id   = $_POST['cliente_id'];
$sistema      = $_POST['sistema'];
$consultor    = $_POST['consultor'];
$status       = $_POST['status'];
$observacoes  = $_POST['observacoes'];
$duracao      = isset($_POST['duracao']) ? intval($_POST['duracao']) : 30;

if (empty($cliente_id)) {
    header("Location: ../Views/treinamento.php?error=" . urlencode("Cliente não selecionado."));
    exit();
}

$queryClient = "SELECT horas_adquiridas, horas_utilizadas FROM TB_CLIENTES WHERE id = ?";
$stmtClient  = mysqli_prepare($conn, $queryClient);
mysqli_stmt_bind_param($stmtClient, "i", $cliente_id);
mysqli_stmt_execute($stmtClient);
mysqli_stmt_bind_result($stmtClient, $horasAdquiridas, $horasUtilizadas);
if (!mysqli_stmt_fetch($stmtClient)) {
    mysqli_stmt_close($stmtClient);
    header("Location: ../Views/treinamento.php?error=" . urlencode("Cliente não encontrado."));
    exit();
}
mysqli_stmt_close($stmtClient);

$newTotal = $horasUtilizadas + $duracao;
// Aqui a verificação de excesso já deve ter sido feita via check_hours.php

$query = "INSERT INTO TB_TREINAMENTOS (data, hora, tipo, duracao, cliente_id, sistema, consultor, status, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt  = mysqli_prepare($conn, $query);
if (!$stmt) {
    header("Location: ../Views/treinamento.php?error=" . urlencode("Erro na preparação da query: " . mysqli_error($conn)));
    exit();
}
mysqli_stmt_bind_param($stmt, "sssisssss", $data, $hora, $tipo, $duracao, $cliente_id, $sistema, $consultor, $status, $observacoes);
if (!mysqli_stmt_execute($stmt)) {
    header("Location: ../Views/treinamento.php?error=" . urlencode("Erro ao cadastrar agendamento: " . mysqli_error($conn)));
    exit();
}
mysqli_stmt_close($stmt);

$queryUpdate = "UPDATE TB_CLIENTES SET horas_utilizadas = ? WHERE id = ?";
$stmtUpdate  = mysqli_prepare($conn, $queryUpdate);
mysqli_stmt_bind_param($stmtUpdate, "ii", $newTotal, $cliente_id);
if (!mysqli_stmt_execute($stmtUpdate)) {
    header("Location: ../Views/treinamento.php?error=" . urlencode("Erro ao atualizar horas utilizadas do cliente: " . mysqli_error($conn)));
    exit();
}
mysqli_stmt_close($stmtUpdate);

header("Location: ../Views/treinamento.php?success=1");
exit();
?>
