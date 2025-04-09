<?php
include '../Config/Database.php';
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Verifica se o ID do cliente foi enviado via GET
if (!isset($_GET['id'])) {
    die("ID do cliente não especificado.");
}

$id = intval($_GET['id']);

// Atualiza o status do cliente para ativo
$query = "UPDATE TB_CLIENTES SET ativo = 1 WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    die("Erro na preparação da query: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "i", $id);
if (!mysqli_stmt_execute($stmt)) {
    die("Erro ao atualizar o cliente: " . mysqli_error($conn));
}
mysqli_stmt_close($stmt);

// Redireciona de volta para a página de clientes
header("Location: clientes.php");
exit();
?>
