<?php
include '../Config/Database.php';
session_start();

header("Content-Type: application/json");

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status'=>'error', 'message'=>'Acesso não autorizado']);
    exit();
}

// Recebe os dados do formulário
$data         = $_POST['data'];
$hora         = $_POST['hora'];
$tipo         = $_POST['tipo'];
$cliente_id   = $_POST['cliente_id'];  // Campo hidden com o ID do cliente selecionado
$sistema      = $_POST['sistema'];
$consultor    = $_POST['consultor'];
$status       = $_POST['status'];
$observacoes  = $_POST['observacoes'];
$duracao      = isset($_POST['duracao']) ? intval($_POST['duracao']) : 30; // Duração em minutos

if (empty($cliente_id)) {
    echo json_encode(['status'=>'error','message'=>'Erro: Cliente não selecionado.']);
    exit();
}

// Recupera as horas contratadas e já utilizadas pelo cliente (em minutos)
$queryClient = "SELECT horas_adquiridas, horas_utilizadas FROM TB_CLIENTES WHERE id = ?";
$stmtClient  = mysqli_prepare($conn, $queryClient);
mysqli_stmt_bind_param($stmtClient, "i", $cliente_id);
mysqli_stmt_execute($stmtClient);
mysqli_stmt_bind_result($stmtClient, $horasAdquiridas, $horasUtilizadas);
if (!mysqli_stmt_fetch($stmtClient)) {
    mysqli_stmt_close($stmtClient);
    echo json_encode(['status'=>'error','message'=>'Erro: Cliente não encontrado.']);
    exit();
}
mysqli_stmt_close($stmtClient);

// Calcula o novo total de minutos utilizados pelo cliente
$newTotal = $horasUtilizadas + $duracao;

// Se o novo total ultrapassar o tempo contratado, retorna status "exceeded" com a mensagem formatada
if ($newTotal > $horasAdquiridas) {
    $msg = "O cliente excedeu as horas adquiridas.\nHoras adquiridas: {$horasAdquiridas} minutos.\nJá utilizadas: {$horasUtilizadas} minutos.\nTentativa de adicionar: {$duracao} minutos.";
    echo json_encode([
        'status' => 'exceeded',
        'message' => $msg
    ]);
    exit();
}

// Insere o novo agendamento na TB_TREINAMENTOS
$query = "INSERT INTO TB_TREINAMENTOS (data, hora, tipo, duracao, cliente_id, sistema, consultor, status, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt  = mysqli_prepare($conn, $query);
if (!$stmt) {
    echo json_encode(['status'=>'error','message'=>'Erro na preparação da query: ' . mysqli_error($conn)]);
    exit();
}
mysqli_stmt_bind_param($stmt, "sssisssss", $data, $hora, $tipo, $duracao, $cliente_id, $sistema, $consultor, $status, $observacoes);
if (!mysqli_stmt_execute($stmt)) {
    echo json_encode(['status'=>'error','message'=>'Erro ao cadastrar agendamento: ' . mysqli_error($conn)]);
    exit();
}
mysqli_stmt_close($stmt);

// Atualiza o campo horas_utilizadas na TB_CLIENTES com o novo total
$queryUpdate = "UPDATE TB_CLIENTES SET horas_utilizadas = ? WHERE id = ?";
$stmtUpdate  = mysqli_prepare($conn, $queryUpdate);
mysqli_stmt_bind_param($stmtUpdate, "ii", $newTotal, $cliente_id);
if (!mysqli_stmt_execute($stmtUpdate)) {
    echo json_encode(['status'=>'error','message'=>'Erro ao atualizar horas utilizadas do cliente: ' . mysqli_error($conn)]);
    exit();
}
mysqli_stmt_close($stmtUpdate);

echo json_encode(['status'=>'success','message'=>'Agendamento cadastrado com sucesso.']);
exit();
?>
