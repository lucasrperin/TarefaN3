<?php
include '../Config/Database.php';
session_start();

header("Content-Type: application/json");

// Verifica se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Acesso não autorizado'
    ]);
    exit();
}

// Recebe os dados do formulário
$cliente = trim($_POST['cliente'] ?? '');
$cnpjcpf = trim($_POST['cnpjcpf'] ?? '');
$serial = trim($_POST['serial'] ?? '');
$horas_adquiridas = intval($_POST['horas_adquiridas'] ?? 0);

// Recebe os novos campos: WhatsApp e Data de Conclusão
$whatsapp = trim($_POST['whatsapp'] ?? '');
$data_conclusao = trim($_POST['data_conclusao'] ?? '');

// Valida os campos obrigatórios
if (empty($cliente) || $horas_adquiridas <= 0) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Informe o nome do cliente e um valor válido para as horas adquiridas.'
    ]);
    exit();
}

// Verifica se já existe um cliente com o mesmo CNPJ/CPF ou Serial (se informados)
$queryDuplicate = "
  SELECT 
    id,
    cliente,
    cnpjcpf,
    serial,
    horas_adquiridas,
    whatsapp,
    DATE_FORMAT(data_conclusao, '%Y-%m-%d') AS data_conclusao,
    faturamento,
    valor_faturamento
  FROM TB_CLIENTES
  WHERE (cnpjcpf = ? AND ? <> '')
     OR (serial   = ? AND ? <> '')
";
$stmtDup = mysqli_prepare($conn, $queryDuplicate);
mysqli_stmt_bind_param($stmtDup, "ssss", $cnpjcpf, $cnpjcpf, $serial, $serial);
mysqli_stmt_execute($stmtDup);
mysqli_stmt_bind_result(
    $stmtDup,
    $dupId,
    $dupCliente,
    $dupCnpjCpf,
    $dupSerial,
    $dupHoras,
    $dupWhatsapp,
    $dupDataConclusao,
    $dupFaturamento,
    $dupValorFaturamento
  );
  $duplicateFound = mysqli_stmt_fetch($stmtDup);
  mysqli_stmt_close($stmtDup);

  if ($duplicateFound) {
    echo json_encode([
        'status'           => 'duplicate',
        'message'          => 'Já existe um cliente com esse CNPJ/CPF ou Serial.',
        'id'               => $dupId,
        'cliente'          => $dupCliente,
        'cnpjcpf'          => $dupCnpjCpf,
        'serial'           => $dupSerial,
        'horas_adquiridas' => $dupHoras,
        'whatsapp'         => $dupWhatsapp,
        'data_conclusao'   => $dupDataConclusao,
        'faturamento'      => $dupFaturamento,
        'valor_faturamento'=> $dupValorFaturamento
    ]);
    exit();
}

// Insere o novo cliente na TB_CLIENTES incluindo os novos campos
$queryInsert = "INSERT INTO TB_CLIENTES (cliente, cnpjcpf, serial, horas_adquiridas, whatsapp, data_conclusao, ativo) 
                VALUES (?, ?, ?, ?, ?, ?, 1)";
$stmtInsert = mysqli_prepare($conn, $queryInsert);
if (!$stmtInsert) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Erro na preparação da query: ' . mysqli_error($conn)
    ]);
    exit();
}
mysqli_stmt_bind_param($stmtInsert, "sssiis", $cliente, $cnpjcpf, $serial, $horas_adquiridas, $whatsapp, $data_conclusao);
if (!mysqli_stmt_execute($stmtInsert)) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Erro ao cadastrar cliente: ' . mysqli_error($conn)
    ]);
    exit();
}
mysqli_stmt_close($stmtInsert);

echo json_encode([
    'status'  => 'success',
    'message' => 'Cliente cadastrado com sucesso.'
]);
exit();
?>
