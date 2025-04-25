<?php

require __DIR__ . '/../Config/Database.php';

session_start();


// (Opcional) debug de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Captura dos campos
    $id             = $_POST['id'];
    $contato        = $_POST['contato'];
    $serial         = $_POST['serial'];
    $retrabalho     = $_POST['retrabalho'];       // 'Sim' ou 'Não'
    $sistema_id     = $_POST['sistema_id'];       // ID válido em TB_SISTEMA_CONVER
    $status_id      = $_POST['status_id'];        // ID válido em TB_STATUS_CONVER
    $data_recebido  = $_POST['data_recebido'];    // DATETIME
    $prazo_entrega  = $_POST['prazo_entrega'];    // DATETIME
    $data_inicio    = $_POST['data_inicio'] ?: NULL;     // DATETIME
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
                    status_id=?,
                    data_recebido=?,
                    prazo_entrega=?,
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
        'sssiissssisi',
        $contato,
        $serial,
        $retrabalho,
        $sistema_id,
        $status_id,
        $data_recebido,
        $prazo_entrega,
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