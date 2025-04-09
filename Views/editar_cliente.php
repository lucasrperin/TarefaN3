<?php
include '../Config/Database.php';
session_start();

// Em ambiente de produção você pode desativar a exibição de erros
ini_set('display_errors', 0);
error_reporting(0);

header("Content-Type: application/json");

// Verifica se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Acesso não autorizado']);
    exit();
}

$id = $_POST['id'] ?? '';
$cliente = trim($_POST['cliente'] ?? '');
$cnpjcpf = trim($_POST['cnpjcpf'] ?? '');
$serial = trim($_POST['serial'] ?? '');
$horas_adquiridas = intval($_POST['horas_adquiridas'] ?? 0);
$whatsapp = trim($_POST['whatsapp'] ?? '');
$data_conclusao = trim($_POST['data_conclusao'] ?? '');

// Valida os campos obrigatórios
if (empty($id) || empty($cliente) || $horas_adquiridas <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Campos obrigatórios não informados.']);
    exit();
}

$query = "UPDATE TB_CLIENTES SET cliente = ?, cnpjcpf = ?, serial = ?, horas_adquiridas = ?, whatsapp = ?, data_conclusao = ? WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Erro na preparação da query: ' . mysqli_error($conn)]);
    exit();
}
mysqli_stmt_bind_param($stmt, 'sssissi', $cliente, $cnpjcpf, $serial, $horas_adquiridas, $whatsapp, $data_conclusao, $id);
if (!mysqli_stmt_execute($stmt)) {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao atualizar cliente: ' . mysqli_error($conn)]);
    exit();
}
mysqli_stmt_close($stmt);

echo json_encode(['status' => 'success', 'message' => 'Cliente atualizado com sucesso.']);
exit();
?>
