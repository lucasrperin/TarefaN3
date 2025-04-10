<?php
include '../Config/Database.php';
session_start();

header("Content-Type: application/json");

// Opcional: verifique se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Acesso não autorizado']);
    exit();
}

$notifs = [];

if (isset($_GET['cliente_id'])) {
    // Se for passado o parâmetro cliente_id, retorna apenas notificações desse cliente
    $cliente_id = intval($_GET['cliente_id']);
    $queryNotifs = "SELECT id, titulo, mensagem, data_envio FROM TB_NOTIFICACOES WHERE cliente_id = ? ORDER BY data_envio DESC";
    $stmt = mysqli_prepare($conn, $queryNotifs);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $cliente_id);
        mysqli_stmt_execute($stmt);
        $resultNotifs = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($resultNotifs)) {
            $notifs[] = $row;
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
        exit();
    }
} else {
    // Se não for passado, retorna todas as notificações
    $queryNotifs = "SELECT id, titulo, mensagem, data_envio FROM TB_NOTIFICACOES ORDER BY data_envio DESC";
    $resultNotifs = mysqli_query($conn, $queryNotifs);
    while ($row = mysqli_fetch_assoc($resultNotifs)) {
        $notifs[] = $row;
    }
}

echo json_encode(["status" => "success", "data" => $notifs]);
exit();
?>
