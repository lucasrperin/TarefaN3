<?php
include '../Config/Database.php';
session_start();

if (empty($_POST['idMeta']) || empty($_POST['mes']) || empty($_POST['realizado']))
  die('Campos obrigatórios ausentes');

$idMeta = (int)$_POST['idMeta'];
$mes    = (int)$_POST['mes'];
$ano    = date('Y');
$valor  = trim($_POST['realizado']);

/* -- Descobre se a meta é “menor é melhor” (tempo) -- */
$check  = $conn->prepare("SELECT menor_melhor FROM TB_META WHERE id=?");
$check->bind_param('i',$idMeta); $check->execute();
$menor  = (int)$check->get_result()->fetch_column();

/* Converte o valor informado */
$real   = null;   // decimal
$realSeg= null;   // segundos

if ($menor){                    // meta de tempo → HH:MM:SS
    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/',$valor)) die('Tempo inválido');
    [$h,$m,$s] = array_map('intval', explode(':',$valor));
    $realSeg = $h*3600 + $m*60 + $s;
}else{                          // meta de valor
    $real = (float)str_replace(',','.',$valor);
}

/* INSERT ou UPDATE (1 por mês) */
$sql = "INSERT INTO TB_OKR_ATINGIMENTO
        (idMeta, ano, mes, realizado, realizado_seg)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
          realizado=VALUES(realizado),
          realizado_seg=VALUES(realizado_seg)";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iiidd', $idMeta,$ano,$mes,$real,$realSeg);
$stmt->execute();

header("Location: okr.php?success_lanc=1");
exit();
