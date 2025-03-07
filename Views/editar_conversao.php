<?php
session_start();
include '../Config/Database.php'; // Conexão com o banco de dados

// (Opcional) debug de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Captura dos campos
    $id             = $_POST['id'];
    $contato        = $_POST['contato'];
    $serial         = $_POST['serial'] ?: NULL;
    $retrabalho     = $_POST['retrabalho'];       // 'Sim' ou 'Não'
    $sistema_id     = $_POST['sistema_id'];       // ID válido em TB_SISTEMA_CONVER
    $prazo_entrega  = $_POST['prazo_entrega'];    // DATETIME
    $status_id      = $_POST['status_id'];        // ID válido em TB_STATUS_CONVER
    $data_recebido  = $_POST['data_recebido'];    // DATETIME
    $data_inicio    = $_POST['data_inicio'];      // DATETIME
    $data_conclusao = $_POST['data_conclusao'] ?: NULL; // DATETIME ou NULL
    $analista_id    = $_POST['analista_id'];      // ID válido em TB_ANALISTA_CONVER
    $observacao     = $_POST['observacao'];

    // Preparar e executar o UPDATE
    $query = "UPDATE TB_CONVERSOES
                SET 
                    contato=?,
                    serial=?,
                    retrabalho=?,
                    sistema_id=?,
                    prazo_entrega=?,
                    status_id=?,
                    data_recebido=?,
                    data_inicio=?,
                    data_conclusao=?,
                    analista_id=?,
                    observacao=?
                WHERE id=?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo "error: " . $conn->error;
        exit;
    }

    $stmt->bind_param(
        'sssisisssisi',
        $contato,
        $serial,
        $retrabalho,
        $sistema_id,
        $prazo_entrega,
        $status_id,
        $data_recebido,
        $data_inicio,
        $data_conclusao,
        $analista_id,
        $observacao,
        $id
    );
    if ($stmt->execute()) {
        echo "success";
    } else {
        // Se der erro por chave estrangeira (FK), exibe qual foi
        echo "error: " . $stmt->error;
    }
}
$conn->close();
    header("Location: ../Views/conversao.php?success=2");
    exit();
?>