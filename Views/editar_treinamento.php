<?php
include '../Config/Database.php';
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Acesso não autorizado']);
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

// Se cliente_id for 0, tentar recuperar o valor atual do agendamento
if ($cliente_id === 0) {
    $queryExisting = "SELECT cliente_id FROM TB_TREINAMENTOS WHERE id = ?";
    $stmtExisting = mysqli_prepare($conn, $queryExisting);
    mysqli_stmt_bind_param($stmtExisting, "i", $id);
    mysqli_stmt_execute($stmtExisting);
    mysqli_stmt_bind_result($stmtExisting, $existingClienteId);
    if (mysqli_stmt_fetch($stmtExisting)) {
        $cliente_id = intval($existingClienteId);
    }
    mysqli_stmt_close($stmtExisting);
}
if ($cliente_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Erro: Cliente selecionado não existe.']);
    exit();
}

// Verifica se o cliente existe e obtém as horas contratadas (em minutos)
$queryCheck = "SELECT id, horas_adquiridas FROM TB_CLIENTES WHERE id = ?";
$stmtCheck = mysqli_prepare($conn, $queryCheck);
mysqli_stmt_bind_param($stmtCheck, 'i', $cliente_id);
mysqli_stmt_execute($stmtCheck);
mysqli_stmt_bind_result($stmtCheck, $dummy, $horasAdquiridas);
if (mysqli_stmt_fetch($stmtCheck) == 0) {
    mysqli_stmt_close($stmtCheck);
    echo json_encode(['status'=>'error', 'message'=>'Erro: Cliente selecionado não existe.']);
    exit();
}
mysqli_stmt_close($stmtCheck);

// Calcula o total de duração de todos os agendamentos para o cliente, excluindo o atual
$querySum = "SELECT SUM(duracao) as total FROM TB_TREINAMENTOS WHERE cliente_id = ? AND id <> ?";
$stmtSum = mysqli_prepare($conn, $querySum);
mysqli_stmt_bind_param($stmtSum, "ii", $cliente_id, $id);
mysqli_stmt_execute($stmtSum);
mysqli_stmt_bind_result($stmtSum, $otherTotal);
mysqli_stmt_fetch($stmtSum);
mysqli_stmt_close($stmtSum);
if (is_null($otherTotal)) {
    $otherTotal = 0;
}
$newTotal = $otherTotal + $duracao;

// Se o novo total ultrapassar as horas contratadas, retorna status "exceeded"
if ($newTotal > $horasAdquiridas) {
    $msg = "O cliente excedeu as horas adquiridas.\nHoras adquiridas: {$horasAdquiridas} minutos.\nJá utilizadas: {$otherTotal} minutos.\nTentativa de alterar para: {$duracao} minutos (novo total: {$newTotal} minutos).";
    echo json_encode([
        'status'  => 'exceeded',
        'message' => $msg
    ]);
    exit();
}

// Atualiza o agendamento na TB_TREINAMENTOS
$query = "UPDATE TB_TREINAMENTOS 
          SET data = ?, hora = ?, tipo = ?, duracao = ?, cliente_id = ?, sistema = ?, consultor = ?, status = ?, observacoes = ? 
          WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    echo json_encode(['status'=>'error', 'message'=>'Erro na preparação da query: ' . mysqli_error($conn)]);
    exit();
}
mysqli_stmt_bind_param($stmt, "sssisssssi", $data, $hora, $tipo, $duracao, $cliente_id, $sistema, $consultor, $status, $observacoes, $id);
if (!mysqli_stmt_execute($stmt)) {
    echo json_encode(['status'=>'error', 'message'=>'Erro ao atualizar agendamento: ' . mysqli_error($conn)]);
    exit();
}
mysqli_stmt_close($stmt);

// Recalcula o total de duração de todos os agendamentos para o cliente (incluindo este)
$querySum2 = "SELECT SUM(duracao) as total FROM TB_TREINAMENTOS WHERE cliente_id = ?";
$stmtSum2 = mysqli_prepare($conn, $querySum2);
mysqli_stmt_bind_param($stmtSum2, "i", $cliente_id);
mysqli_stmt_execute($stmtSum2);
mysqli_stmt_bind_result($stmtSum2, $totalDuracao);
mysqli_stmt_fetch($stmtSum2);
mysqli_stmt_close($stmtSum2);
if (is_null($totalDuracao)) {
    $totalDuracao = 0;
}

// Atualiza o campo horas_utilizadas na TB_CLIENTES para esse cliente
$queryUpdate = "UPDATE TB_CLIENTES SET horas_utilizadas = ? WHERE id = ?";
$stmtUpdate  = mysqli_prepare($conn, $queryUpdate);
mysqli_stmt_bind_param($stmtUpdate, "ii", $totalDuracao, $cliente_id);
if(!mysqli_stmt_execute($stmtUpdate)){
    echo json_encode(['status'=>'error', 'message'=>'Erro ao atualizar horas utilizadas do cliente: ' . mysqli_error($conn)]);
    exit();
}
mysqli_stmt_close($stmtUpdate);

echo json_encode(['status'=>'success', 'message'=>'Agendamento atualizado com sucesso.']);
exit();
?>
