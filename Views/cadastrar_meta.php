<?php
include '../Config/Database.php';
session_start();

/* --------- Validação básica --------- */
if (empty($_POST['idOkr']) || empty($_POST['ano']) || empty($_POST['dt_prazo'])) {
  die('Campos obrigatórios ausentes');
}

$idOkr     = (int) $_POST['idOkr'];
$ano       = (int) $_POST['ano'];
$dt_prazo  = $_POST['dt_prazo'];
$descricao = trim($_POST['descricao'] ?? '');

/* --------- Decide tipo de meta --------- */
$tipo         = $_POST['tipo_meta'] ?? 'valor';
$meta_valor   = null;
$meta_seg     = null;
$menorMelhor  = 0;
$unidade      = null;

if ($tipo === 'tempo') {
    /* HH:MM:SS */
    if (empty($_POST['meta_tempo'])) die('Informe o tempo (HH:MM:SS)');
    [$h,$m,$s]    = array_map('intval', explode(':', $_POST['meta_tempo']));
    $meta_seg     = $h*3600 + $m*60 + $s;
    $menorMelhor  = 1;
    $unidade      = 's';

} elseif ($tipo === 'moeda') {
    /* R$ */
    if (empty($_POST['meta_moeda'])) die('Informe o valor em R$');
    $raw         = str_replace(',', '.', $_POST['meta_moeda']);      // "100,00" → "100.00"
    $meta_vlr    = number_format((float)$raw, 4, '.', '');            // → "100.0000"
    $meta_valor    = null;
    $meta_seg      = null;
    $menorMelhor   = 0;
    $unidade       = 'R$';

} elseif ($tipo === 'quantidade') {
    if (!isset($_POST['meta_qtd'])) die('Informe a quantidade da meta');
    $meta_qtd      = intval($_POST['meta_qtd']);
    $meta_valor    = null;
    $meta_seg      = null;
    $meta_vlr      = null;
    $menorMelhor   = 0;
    $unidade       = 'unidades'; 
} else {
    /* percentual */
    if (empty($_POST['meta_valor'])) die('Informe o valor da meta (%)');
    $meta_valor   = (float) str_replace(',', '.', $_POST['meta_valor']);
    $menorMelhor  = 0;
    $unidade      = '%';
}

/* --------- Inserção --------- */
$sql = "
  INSERT INTO TB_META
    (idOkr, ano, descricao, meta, meta_seg, meta_vlr, meta_qtd, menor_melhor, dt_prazo, unidade)
  VALUES (?,?,?,?,?,?,?,?,?,?)
";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
  'iisdiidiss',
  $idOkr,        
  $ano,          
  $descricao,   
  $meta_valor,   
  $meta_seg,    
  $meta_vlr,    
  $meta_qtd,     
  $menorMelhor,  
  $dt_prazo,     
  $unidade      
);
$stmt->execute();

header("Location: okr.php?view=year&q=1&equipe=0&nivel=0&success=4");
exit();
