<?php
include '../Config/Database.php';
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

// Recebe dados
$situacao = $conn->real_escape_string($_POST['situacao']);
$raw      = trim($_POST['card_nums']);

// Insere a situação
$conn->query("INSERT INTO TB_RECORRENTES (situacao) VALUES ('$situacao')");
$recId = $conn->insert_id;

// Insere cada número
foreach (explode("\n", $raw) as $line) {
  $num = trim($line);
  if ($num === '' || !ctype_digit($num)) continue;
  $conn->query("
    INSERT INTO TB_RECORRENTES_CARDS (recorrente_id, card_num)
    VALUES ($recId, '$num')
  ");
}

// Redireciona para incidente.php indicando aba Recorrentes
header("Location: incidente.php?tab=recorrentes");
exit();