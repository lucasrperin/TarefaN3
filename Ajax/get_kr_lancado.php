<?php
include '../Config/Database.php';
$okrId = intval($_GET['okr_id']);
$ano   = intval($_GET['ano'] ?? date('Y'));
$stmt = $conn->prepare("
  SELECT descricao 
  FROM TB_META 
  WHERE idOkr = ? AND ano = ?
  ORDER BY id
");
$stmt->bind_param("ii", $okrId, $ano);
$stmt->execute();
$res = $stmt->get_result();
$data = [];
while ($row = $res->fetch_assoc()) {
  $data[] = $row['descricao'];
}
header('Content-Type: application/json');
echo json_encode($data);
?>
