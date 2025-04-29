<?php
include '../Config/Database.php';
session_start();

// Em produção, desative a exibição de erros
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

// Verifica autenticação
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status'=>'error','message'=>'Acesso não autorizado']);
    exit();
}

// Recebe e sanitiza os dados do POST
$id                = intval($_POST['id'] ?? 0);
$cliente           = mysqli_real_escape_string($conn, trim($_POST['cliente'] ?? ''));
$cnpjcpf           = mysqli_real_escape_string($conn, trim($_POST['cnpjcpf'] ?? ''));
$serial            = mysqli_real_escape_string($conn, trim($_POST['serial'] ?? ''));
$horas_adquiridas  = intval($_POST['horas_adquiridas'] ?? 0);
$whatsapp          = mysqli_real_escape_string($conn, trim($_POST['whatsapp'] ?? ''));
$data_conclusao    = trim($_POST['data_conclusao'] ?? '');

// Faturamento e valor
$faturamento_raw   = $_POST['faturamento'] ?? '';
$faturamento       = in_array($faturamento_raw, ['BRINDE','FATURADO'])
                     ? $faturamento_raw 
                     : 'BRINDE';
$valor_raw         = $_POST['valor_faturamento'] ?? '';
$valor_faturamento = ($faturamento === 'FATURADO' && $valor_raw !== '')
                     ? floatval($valor_raw)
                     : 'NULL';

// Validações básicas
if ($id <= 0 || $cliente === '' || $horas_adquiridas <= 0) {
    echo json_encode([
        'status'=>'error',
        'message'=>'Campos obrigatórios faltando (id, nome ou minutos adquiridos).'
    ]);
    exit();
}

// Monta o SQL de UPDATE
$sql = "
  UPDATE TB_CLIENTES SET
    cliente           = '$cliente',
    cnpjcpf           = '$cnpjcpf',
    serial            = '$serial',
    horas_adquiridas  = $horas_adquiridas,
    whatsapp          = '$whatsapp',
    data_conclusao    = ".($data_conclusao
                             ? "'".mysqli_real_escape_string($conn,$data_conclusao)."'"
                             : "NULL").",
    faturamento       = '$faturamento',
    valor_faturamento = $valor_faturamento,
    atualizado_em     = NOW()
  WHERE id = $id
";

// Executa a query
$res = mysqli_query($conn, $sql);
$err = mysqli_error($conn);
$aff = mysqli_affected_rows($conn);

// Retorna JSON de debug e status
echo json_encode([
  'status'    => $err
                 ? 'error'
                 : ($aff > 0 ? 'success' : 'warning'),
  'message'   => $err
                 ? "Erro no MySQL: $err"
                 : ($aff > 0
                    ? "Cliente atualizado com sucesso (linhas afetadas: $aff)."
                    : "Nenhuma linha alterada — verifique se o ID existe e se os dados mudaram."),
  'sql'       => $sql,
  'affected'  => $aff
]);
exit();
?>
