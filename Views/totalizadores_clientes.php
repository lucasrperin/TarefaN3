<?php
include '../Config/Database.php';
header('Content-Type: application/json');

// total geral de clientes faturados
$qClientes = "
  SELECT SUM(valor_faturamento) AS total_clientes
  FROM TB_CLIENTES
  WHERE faturamento = 'FATURADO'
";
$r1 = mysqli_query($conn, $qClientes);
$totalClientes = (float) mysqli_fetch_assoc($r1)['total_clientes'];

// total de indicações faturadas
$qIndic = "
  SELECT SUM(vlr_total) AS total_indicacoes
  FROM TB_INDICACAO
  WHERE status = 'Faturado'
";
$r2 = mysqli_query($conn, $qIndic);
$totalIndic   = (float) mysqli_fetch_assoc($r2)['total_indicacoes'];

// totalizadores mensais (permanece igual)
$qMensal = "
  SELECT 
    DATE_FORMAT(data_conclusao, '%Y-%m') AS mes,
    SUM(faturamento = 'BRINDE')   AS brinde,
    SUM(faturamento = 'FATURADO') AS faturado
  FROM TB_CLIENTES
  GROUP BY mes
  ORDER BY mes
";
$res = mysqli_query($conn, $qMensal);

$monthly = [];
while ($row = mysqli_fetch_assoc($res)) {
  $monthly[] = [
    'mes'      => $row['mes'],
    'brinde'   => (int)$row['brinde'],
    'faturado' => (int)$row['faturado']
  ];
}

echo json_encode([
  'monthly'            => $monthly,
  'totalGeral'         => $totalClientes + $totalIndic,
  'totalTreinamentos'  => $totalClientes,
  'totalIndicacoes'    => $totalIndic
]);
