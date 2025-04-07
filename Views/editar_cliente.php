<?php
include '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$id = $_POST['id'];
$cliente = $_POST['cliente'];
$cnpjcpf = $_POST['cnpjcpf'];
$serial = $_POST['serial'];
$horas_adquiridas = intval($_POST['horas_adquiridas']);

// Atualiza os dados do cliente
$query = "UPDATE TB_CLIENTES SET cliente = ?, cnpjcpf = ?, serial = ?, horas_adquiridas = ? WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'sssii', $cliente, $cnpjcpf, $serial, $horas_adquiridas, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header("Location: clientes.php");
exit();
?>
