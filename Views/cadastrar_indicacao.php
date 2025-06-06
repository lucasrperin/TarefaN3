<?php 
session_start();
require '../Config/Database.php';

if (!isset($_SESSION['usuario_id'])) {
    // redireciona se não estiver logado
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Captura os dados enviados pelo formulário
$plugin_id   = mysqli_real_escape_string($conn, $_POST['plugin_id']);
$data        = mysqli_real_escape_string($conn, $_POST['data']);   // formato esperado: YYYY-MM-DD
$cnpj        = mysqli_real_escape_string($conn, $_POST['cnpj']);
$serial      = mysqli_real_escape_string($conn, $_POST['serial']);
$contato     = mysqli_real_escape_string($conn, $_POST['contato']);
$fone        = mysqli_real_escape_string($conn, $_POST['fone']);
$observacao  = isset($_POST['observacao'])
    ? mysqli_real_escape_string($conn, $_POST['observacao'])
    : '';
$revenda = isset($_POST['revenda']) ? 1 : 0;

// Define o status e o consultor fixos
$status      = 'Pendente';
$consultor   = 29;

// === 1) Calcula início e fim do ciclo com base na data informada ===
// Ex: se $data = "2025-06-10", então $year="2025", $month="06"
$timestamp = strtotime($data);
$year  = date('Y', $timestamp);
$month = date('m', $timestamp);

// Primeiro dia do mês
$cycleStart = sprintf('%04d-%02d-01', $year, $month);
// 15 dias depois do primeiro dia do mês (i.e. +1 mês +14 dias = 45 dias a partir de dia 1)
$cycleEnd   = date('Y-m-d', strtotime("$cycleStart +1 month +14 days"));

// === 2) Verifica se já existe indicação no mesmo ciclo com mesmo plugin_id e serial ===
$sqlCheck = "
  SELECT COUNT(*) AS cnt
    FROM TB_INDICACAO
   WHERE plugin_id = '{$plugin_id}'
     AND serial    = '{$serial}'
     AND data BETWEEN '{$cycleStart}' AND '{$cycleEnd}'
";

$resCheck = mysqli_query($conn, $sqlCheck);
$rowCheck = mysqli_fetch_assoc($resCheck);
if ((int)$rowCheck['cnt'] > 0) {
    // Já existe indicação para esse plugin+serial dentro do ciclo vigente
    header("Location: indicacao.php?erro=duplicado_ciclo");
    exit();
}

// === 3) Se não encontrou duplicado, executa inserção ===
$sql = "
  INSERT INTO TB_INDICACAO (
    plugin_id, data, cnpj, serial, contato, fone, observacao,
    user_id, idConsultor, status, revenda
  ) VALUES (
    '{$plugin_id}', 
    '{$data}', 
    '{$cnpj}', 
    '{$serial}', 
    '{$contato}', 
    '{$fone}', 
    '{$observacao}',
    '{$usuario_id}', 
    '{$consultor}', 
    '{$status}', 
    {$revenda}
  )
";

if (mysqli_query($conn, $sql)) {
    header('Location: indicacao.php?success=1');
    exit();
} else {
    // em caso de erro de banco, loga e redireciona com flag de erro genérico
    error_log("Erro ao cadastrar indicação: " . mysqli_error($conn));
    header("Location: indicacao.php?erro=1");
    exit();
}
?>
