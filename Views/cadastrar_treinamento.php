<?php
include '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../Views/login.php");
    exit();
}

$data         = $_POST['data'];         // Exemplo: "2023-04-11"
$hora         = $_POST['hora'];         // Exemplo: "08:00" (sem segundos)
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

// Converte o "data" e "hora" para um objeto DateTime (assumindo que a hora vem em formato "H:i")
$startDateTime = DateTime::createFromFormat('Y-m-d H:i', "$data $hora");
if (!$startDateTime) {
    header("Location: ../Views/treinamento.php?error=" . urlencode("Formato de data/hora inválido."));
    exit();
}
$startStr = $startDateTime->format('Y-m-d H:i:s');

// Calcula o fim do agendamento com base na duração
$endDateTime = clone $startDateTime;
$endDateTime->modify("+{$duracao} minutes");
$endStr = $endDateTime->format('Y-m-d H:i:s');

// --- Verificação de Conflito de Agendamento ---
// A query busca por registros na mesma data com status "PENDENTE" ou "EM ANDAMENTO"
// em que o horário já cadastrado se sobreponha com o intervalo do novo agendamento.
// A sobreposição é definida quando:
//   (horário existente < fim do novo) AND (horário final do existente > início do novo)
$queryOverlap = "
    SELECT COUNT(*) AS cnt 
    FROM TB_TREINAMENTOS 
    WHERE data = ?
      AND status IN ('PENDENTE', 'EM ANDAMENTO')
      AND (
            STR_TO_DATE(CONCAT(data, ' ', hora), '%Y-%m-%d %H:%i:%s') < ?
            AND DATE_ADD(STR_TO_DATE(CONCAT(data, ' ', hora), '%Y-%m-%d %H:%i:%s'), INTERVAL duracao MINUTE) > ?
          )
";

$stmtOverlap = mysqli_prepare($conn, $queryOverlap);
if (!$stmtOverlap) {
    header("Location: ../Views/treinamento.php?error=" . urlencode("Erro na preparação da query de conflito: " . mysqli_error($conn)));
    exit();
}
mysqli_stmt_bind_param($stmtOverlap, "sss", $data, $endStr, $startStr);
mysqli_stmt_execute($stmtOverlap);
mysqli_stmt_bind_result($stmtOverlap, $countOverlap);
mysqli_stmt_fetch($stmtOverlap);
mysqli_stmt_close($stmtOverlap);

if ($countOverlap > 0) {
    header("Location: ../Views/treinamento.php?error=" . urlencode("Conflito: já existe agendamento neste horário."));
    exit();
}

// --- Verificação das Horas do Cliente ---
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
// Observação: a verificação de excesso de horas deve ter sido feita via check_hours.php

// --- Inserindo o Novo Agendamento ---
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

// --- Atualiza as Horas Utilizadas do Cliente ---
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
