<?php
include '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$id           = $_POST['id'];
$data         = $_POST['data'];
$hora         = $_POST['hora'];
$tipo         = $_POST['tipo'];
$duracao      = isset($_POST['duracao']) ? intval($_POST['duracao']) : 30;
$cliente_id   = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
$sistema      = $_POST['sistema'];
$consultor    = $_POST['consultor'];
$status       = $_POST['status'];
$observacoes  = $_POST['observacoes'];

// Se cliente_id estiver 0, tenta recuperar o valor já cadastrado no agendamento
if ($cliente_id === 0) {
    $queryExisting = "SELECT cliente_id FROM TB_TREINAMENTOS WHERE id = ?";
    $stmtExisting = mysqli_prepare($conn, $queryExisting);
    mysqli_stmt_bind_param($stmtExisting, "i", $id);
    mysqli_stmt_execute($stmtExisting);
    mysqli_stmt_bind_result($stmtExisting, $existingClienteId);
    if (mysqli_stmt_fetch($stmtExisting)) {
        $cliente_id = $existingClienteId;
    }
    mysqli_stmt_close($stmtExisting);
}

// Verifica se o cliente_id existe na TB_CLIENTES
$queryCheck = "SELECT id FROM TB_CLIENTES WHERE id = ?";
$stmtCheck = mysqli_prepare($conn, $queryCheck);
mysqli_stmt_bind_param($stmtCheck, 'i', $cliente_id);
mysqli_stmt_execute($stmtCheck);
mysqli_stmt_store_result($stmtCheck);
if(mysqli_stmt_num_rows($stmtCheck) == 0) {
    mysqli_stmt_close($stmtCheck);
    die("Erro: Cliente selecionado não existe.");
}
mysqli_stmt_close($stmtCheck);

// Atualiza o agendamento na TB_TREINAMENTOS
$query = "UPDATE TB_TREINAMENTOS 
          SET data = ?, hora = ?, tipo = ?, duracao = ?, cliente_id = ?, sistema = ?, consultor = ?, status = ?, observacoes = ? 
          WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "sssisssssi", $data, $hora, $tipo, $duracao, $cliente_id, $sistema, $consultor, $status, $observacoes, $id);

if(!mysqli_stmt_execute($stmt)){
    die("Erro ao atualizar agendamento: " . mysqli_error($conn));
}
mysqli_stmt_close($stmt);
header("Location: treinamento.php");
exit();
?>
