<?php
// total_indicacoes_usuario.php
include '../Config/Database.php';
session_start();
header('Content-Type: application/json');

$usuario_id = $_SESSION['usuario_id'] ?? null;
if (!$usuario_id) {
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit;
}

$sql = "
  SELECT 
    COUNT(*)          AS qtd_indicacoes,
    COALESCE(SUM(vlr_total),0) AS soma_indicacoes
  FROM TB_INDICACAO
  WHERE status = 'Faturado' 
    AND usuario_id = " . intval($usuario_id);

$res = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($res);

echo json_encode([
    'quantidade' => (int)$row['qtd_indicacoes'],
    'valor'      => (float)$row['soma_indicacoes']
]);
