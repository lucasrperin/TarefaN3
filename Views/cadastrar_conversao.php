<?php
include '../Config/Database.php'; // Conexão com o banco de dados

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email_cliente'];
    $status = $_POST['status'];
    $data_solicitacao = $_POST['data_solicitacao'];
    $data_fim = $_POST['data_fim'] ?: NULL;

    $query = "INSERT INTO TB_CONVERSOES (email_cliente, status, data_solicitacao, data_fim) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssss', $email, $status, $data_solicitacao, $data_fim);
    
    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }
}
