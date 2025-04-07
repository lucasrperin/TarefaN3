<?php
include '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status'=>'error', 'message'=>'Acesso não autorizado']);
    exit();
}

$cliente = trim($_POST['cliente']);
$cnpjcpf = trim($_POST['cnpjcpf']);
$serial = trim($_POST['serial']);
$horas_adquiridas = intval($_POST['horas_adquiridas']);

// Consulta para verificar duplicidade – retorna também os dados para edição
$duplicateQuery = "SELECT id, cliente, cnpjcpf, serial, horas_adquiridas FROM TB_CLIENTES WHERE (cnpjcpf = ? AND ? <> '') OR (serial = ? AND ? <> '')";
$stmt = mysqli_prepare($conn, $duplicateQuery);
mysqli_stmt_bind_param($stmt, 'ssss', $cnpjcpf, $cnpjcpf, $serial, $serial);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $existingId, $existingCliente, $existingCnpjcpf, $existingSerial, $existingHorasAdquiridas);
$duplicateFound = false;
if (mysqli_stmt_fetch($stmt)) {
    $duplicateFound = true;
}
mysqli_stmt_close($stmt);

if ($duplicateFound) {
    echo json_encode([
        'status' => 'duplicate',
        'message' => 'Já existe um cliente com esse CNPJ/CPF ou Serial.',
        'id' => $existingId,
        'cliente' => $existingCliente,
        'cnpjcpf' => $existingCnpjcpf,
        'serial' => $existingSerial,
        'horas_adquiridas' => $existingHorasAdquiridas
    ]);
    exit();
}

// Insere o novo cliente se não houver duplicata
$query = "INSERT INTO TB_CLIENTES (cliente, cnpjcpf, serial, horas_adquiridas, ativo) VALUES (?, ?, ?, ?, 1)";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'sssi', $cliente, $cnpjcpf, $serial, $horas_adquiridas);
if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Cliente cadastrado com sucesso.'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao cadastrar cliente.'
    ]);
}
mysqli_stmt_close($stmt);
exit();
?>
