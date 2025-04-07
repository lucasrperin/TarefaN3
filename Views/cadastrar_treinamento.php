<?php
include '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Recebe os dados do formulário
$data         = $_POST['data'];
$hora         = $_POST['hora'];
$tipo         = $_POST['tipo'];
$cliente_id   = $_POST['cliente_id']; // Agora o formulário envia somente o ID do cliente
$sistema      = $_POST['sistema'];
$consultor    = $_POST['consultor'];
$status       = $_POST['status'];
$observacoes  = $_POST['observacoes'];
$duracao      = isset($_POST['duracao']) ? intval($_POST['duracao']) : 30;

// Valida se o cliente foi selecionado
if (empty($cliente_id)) {
    die("Erro: Cliente não selecionado.");
}

// Insere o treinamento na TB_TREINAMENTOS
$query = "INSERT INTO TB_TREINAMENTOS (data, hora, tipo, duracao, cliente_id, sistema, consultor, status, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt  = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'sssisssss', $data, $hora, $tipo, $duracao, $cliente_id, $sistema, $consultor, $status, $observacoes);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Redireciona para a página de treinamento
header("Location: treinamento.php");
exit();
?>
