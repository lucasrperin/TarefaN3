<?php
include '../Config/Database.php'; // Conexão com o banco de dados

error_reporting(E_ALL);
ini_set('display_errors', 1);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $email = $_POST['email_cliente'];
    $status = $_POST['status'];
    $data_solicitacao = $_POST['data_solicitacao'];
    $data_fim = $_POST['data_fim'] ?: NULL;

    $query = "UPDATE TB_CONVERSOES SET email_cliente=?, status=?, data_solicitacao=?, data_fim=? WHERE id=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssssi', $email, $status, $data_solicitacao, $data_fim, $id);
    
    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }
}
