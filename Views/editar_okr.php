<?php
require_once __DIR__ . '/../Public/Php/okr_php.php';
$id        = intval($_POST['id']);
$desc      = $_POST['descricao'];
$equipe    = intval($_POST['idEquipe']);
$niveis    = $_POST['niveis'] ?? [];
// atualiza tabela principal
$stmt = $conn->prepare("
  UPDATE TB_OKR SET descricao = ?, idEquipe = ?
  WHERE id = ?");
$stmt->bind_param('sii', $desc, $equipe, $id);
$stmt->execute();
// limpa vínculos de níveis
$conn->query("DELETE FROM TB_OKR_NIVEL WHERE idOkr = $id");
// insere novos vínculos
$ins = $conn->prepare("
  INSERT INTO TB_OKR_NIVEL (idOkr, idNivel)
  VALUES (?,?)");
foreach($niveis as $niv) {
  $ins->bind_param('ii', $id, $niv);
  $ins->execute();
}
header('Location: okr_list.php?success=2');
