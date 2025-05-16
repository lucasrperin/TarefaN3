<?php
require_once __DIR__ . '/../Public/Php/okr_php.php';

$id = intval($_POST['id'] ?? 0);
if (!$id) {
  header('Location: okr_list.php');
  exit;
}

// 1) Verifica se existe algum registro em TB_OKR_ATINGIMENTO
$stmt = $conn->prepare("
  SELECT COUNT(*) 
    FROM TB_OKR_ATINGIMENTO a
    JOIN TB_META m      ON a.idMeta = m.id
   WHERE m.idOkr = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($countAting);
$stmt->fetch();
$stmt->close();

if ($countAting > 0) {
  // há atingimentos para alguma meta deste OKR → bloqueia
  header('Location: okr_list.php?error=2');
  exit;
}

// se não há atingimento, pode apagar o OKR (cascata vai remover metas vazias)
$stmt = $conn->prepare("DELETE FROM TB_OKR WHERE id = ?");
$stmt->bind_param('i', $id);
if (! $stmt->execute()) {
  die("Erro ao excluir OKR: " . $stmt->error);
}
$stmt->close();

header('Location: okr_list.php?success=3');
exit;
