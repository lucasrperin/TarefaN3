<?php
include '../Config/Database.php';
session_start();

/* --------- Validação básica --------- */
if (empty($_POST['idOkr']) || empty($_POST['ano']) || empty($_POST['dt_prazo'])) {
  die('Campos obrigatórios ausentes');
}

$idOkr     = (int)$_POST['idOkr'];
$ano       = (int)$_POST['ano'];
$dt_prazo  = $_POST['dt_prazo'];
$descricao = trim($_POST['descricao'] ?? '');

/* --------- Decide tipo de meta --------- */
$tipo = $_POST['tipo_meta'] ?? 'valor';

if ($tipo === 'tempo') {
    /* Espera HH:MM:SS em meta_tempo */
    if (empty($_POST['meta_tempo'])) die('Informe o tempo (HH:MM:SS)');
    [$h,$m,$s] = array_map('intval', explode(':', $_POST['meta_tempo']));
    $meta_seg      = $h*3600 + $m*60 + $s;
    $meta_valor    = null;    // decimal fica NULL
    $menorMelhor   = 1;       // 1 = menor é melhor
} else { /* valor (%) */
    if (empty($_POST['meta_valor'])) die('Informe o valor da meta');
    $meta_valor    = (float) str_replace(',','.', $_POST['meta_valor']);
    $meta_seg      = null;
    $menorMelhor   = 0;
}

/* --------- Inserção --------- */
$sql = "INSERT INTO TB_META
        (idOkr, ano, descricao, meta, meta_seg, menor_melhor, dt_prazo)
        VALUES (?,?,?,?,?,?,?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iisdiis',
    $idOkr,
    $ano,
    $descricao,
    $meta_valor,        // DECIMAL
    $meta_seg,          // INT (segundos)
    $menorMelhor,       // TINYINT
    $dt_prazo           // DATE (string YYYY-MM-DD)
);
$stmt->execute();

header("Location: okr.php?success_meta=1");
exit();
