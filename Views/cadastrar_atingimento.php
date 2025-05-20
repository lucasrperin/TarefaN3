<?php
include '../Config/Database.php';
session_start();

// validação básica
if (empty($_POST['idMeta']) || empty($_POST['mes']) || empty($_POST['realizado'])) {
  die('Campos obrigatórios ausentes');
}

$mes = (int) $_POST['mes'];
$ano = date('Y');

// prepara statements
$check = $conn->prepare("
  SELECT menor_melhor, unidade
    FROM TB_META
   WHERE id = ?
");
$sql = "
  INSERT INTO TB_OKR_ATINGIMENTO
    (idMeta, ano, mes, realizado, realizado_seg)
  VALUES (?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    realizado     = VALUES(realizado),
    realizado_seg = VALUES(realizado_seg)
";
$stmt = $conn->prepare($sql);

// percorre cada meta selecionada
foreach ($_POST['idMeta'] as $i => $rawIdMeta) {
  $idMeta = (int)$rawIdMeta;
  $valor  = trim($_POST['realizado'][$i]);

    // 1) busca se é tempo e qual unidade
  $check->bind_param('i', $idMeta);
  $check->execute();
  $check->store_result();           // ← bufferiza o resultado
  $check->bind_result($menor, $unidade);
  $check->fetch();
  $check->free_result();            // ← libera antes da próxima query

  if ($menor) {
    // Tempo (HH:MM:SS)
    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $valor)) {
      die("Tempo inválido para meta ID {$idMeta}");
    }
    list($h, $m, $s) = array_map('intval', explode(':', $valor));
    $realSeg = $h*3600 + $m*60 + $s;

  } elseif ($unidade === 'unidades') {
    // Quantidade inteira
    if (!preg_match('/^\d+$/', $valor)) {
      die("Quantidade inválida para meta ID {$idMeta}");
    }
    $real = (float)$valor;

  } else {
    // Percentual (%) ou Valor (R$)
    $real = (float) str_replace(',', '.', $valor);
  }

  // grava no banco
  $stmt->bind_param('iiidd',
    $idMeta,
    $ano,
    $mes,
    $real,
    $realSeg
  );
  $stmt->execute();
}

header("Location: okr.php?view=year&q=1&equipe=0&nivel=0&success=7");
exit();
