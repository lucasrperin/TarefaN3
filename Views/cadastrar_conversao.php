<?php
include '../Config/Database.php'; // Conexão com o banco de dados

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email_cliente'];
    $contato = $_POST['contato'];
    $serial = $_POST['serial'] ?: NULL;
    $retrabalho = $_POST['retrabalho'];
    $sistema_id = $_POST['sistema_id'];
    $prazo_entrega = $_POST['prazo_entrega'];
    $status_id = $_POST['status_id'];
    $data_recebido = $_POST['data_recebido'];
    $data_inicio = $_POST['data_inicio'];
    $data_conclusao = $_POST['data_conclusao'] ?: NULL;
    $analista_id = $_POST['analista_id'];
    $observacao = $_POST['observacao'];

    $query = "INSERT INTO TB_CONVERSOES (email_cliente, contato, serial, retrabalho, sistema_id, prazo_entrega, status_id, data_recebido, data_inicio, data_conclusao, analista_id, observacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssssisssssss', $email, $contato, $serial, $retrabalho, $sistema_id, $prazo_entrega, $status_id, $data_recebido, $data_inicio, $data_conclusao, $analista_id, $observacao);
    
    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error: " . $stmt->error;
    }
}
