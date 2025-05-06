<?php
include '../Config/Database.php';
session_start();

/* --- Validação simples --- */
if (empty($_POST['descricao']) || empty($_POST['idEquipe'])) {
  die('Dados obrigatórios ausentes');
}

$descricao = trim($_POST['descricao']);
$idEquipe  = (int)$_POST['idEquipe'];
$niveis    = $_POST['idNivel'] ?? [];   // array de checkbox

try {
  $conn->begin_transaction();

  /* 1. Inserir OKR (agora só 2 colunas) */
  $stmt = $conn->prepare(
    "INSERT INTO TB_OKR (descricao, idEquipe) VALUES (?, ?)");
  $stmt->bind_param('si', $descricao, $idEquipe);   // 's' = string, 'i' = int
  $stmt->execute();
  $idOkr = $stmt->insert_id;

  /* 2. Vincular níveis na tabela ponte */
  if ($niveis) {
    $stmtNv = $conn->prepare(
      "INSERT INTO TB_OKR_NIVEL (idOkr, idNivel) VALUES (?, ?)");
    foreach ($niveis as $nivel) {
      $nivelInt = (int)$nivel;
      $stmtNv->bind_param('ii', $idOkr, $nivelInt);
      $stmtNv->execute();
    }
  }

  $conn->commit();
  header("Location: okr.php?success=1");
  exit();

} catch (Exception $e) {
  $conn->rollback();
  error_log($e->getMessage());
  header("Location: okr.php?error=1");
  exit();
}
