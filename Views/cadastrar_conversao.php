<?php
session_start();
include '../Config/Database.php'; // Conexão com o banco de dados


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contato = $_POST['contato'];
    $serial = $_POST['serial'] ?: NULL;
    $retrabalho = $_POST['retrabalho'];
    $sistema_id = $_POST['sistema_id'];
    $prazo_entrega = $_POST['prazo_entrega'];
    $status_id = $_POST['status_id'];
    // Calcula o prazo_entrega como data_recebido + 3 dias
    $data_recebido = $_POST['data_recebido'];
    // Caso o valor venha no formato "YYYY-MM-DDTHH:MM", substituímos "T" por espaço
    $data_recebido_formatada = str_replace('T', ' ', $data_recebido);
    $date = new DateTime($data_recebido_formatada);
    $date->add(new DateInterval('P3D'));
    $prazo_entrega = $date->format('Y-m-d H:i:s'); // Formato para inserir no banco (ajuste conforme o campo)
    $data_inicio = $_POST['data_inicio'];
    $data_conclusao = $_POST['data_conclusao'] ?: NULL;
    $analista_id = $_POST['analista_id'];
    $observacao = $_POST['observacao'];

    $query = "INSERT INTO TB_CONVERSOES (contato, serial, retrabalho, sistema_id, prazo_entrega, status_id, data_recebido, data_inicio, data_conclusao, analista_id, observacao, tempo_total) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TIMEDIFF(?, ?))";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssisssssssss', $contato, $serial, $retrabalho, $sistema_id, $prazo_entrega, $status_id, $data_recebido, $data_inicio, $data_conclusao, $analista_id, $observacao, $data_recebido, $data_conclusao);
    
    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error: " . $stmt->error;
    }
}
$conn->close();
    header("Location: ../Views/conversao.php?success=1");
    exit();
?>
