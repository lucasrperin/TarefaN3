<?php
include '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Content-Type: application/json");
    echo json_encode(['status' => 'error', 'message' => 'Acesso não autorizado']);
    exit();
}

$cliente_id = $_POST['cliente_id'] ?? 0;
$duracao = isset($_POST['duracao']) ? intval($_POST['duracao']) : 0;

if (empty($cliente_id) || $duracao <= 0) {
    header("Content-Type: application/json");
    echo json_encode(['status' => 'error', 'message' => 'Dados inválidos.']);
    exit();
}

$query = "SELECT horas_adquiridas, horas_utilizadas FROM TB_CLIENTES WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $cliente_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $horasAdquiridas, $horasUtilizadas);
if (!mysqli_stmt_fetch($stmt)) {
    mysqli_stmt_close($stmt);
    header("Content-Type: application/json");
    echo json_encode(['status' => 'error', 'message' => 'Cliente não encontrado.']);
    exit();
}
mysqli_stmt_close($stmt);

$newTotal = $horasUtilizadas + $duracao;
if ($newTotal > $horasAdquiridas) {
    $msg = "O cliente excedeu as horas adquiridas.\nHoras adquiridas: {$horasAdquiridas} minutos.\nJá utilizadas: {$horasUtilizadas} minutos.\nTentativa de adicionar: {$duracao} minutos.";
    header("Content-Type: application/json");
    echo json_encode(['status' => 'exceeded', 'message' => $msg]);
    exit();
}

header("Content-Type: application/json");
echo json_encode(['status' => 'ok']);
exit();
?>
