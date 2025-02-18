<?php
require '../Config/Database.php'; 
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $descricao = $_POST['descricao'];
    $situacao = $_POST['situacao'];
    $analista = $_POST['analista'];
    $sistema = $_POST['sistema'];
    $status = $_POST['status'];
    $hora_ini = $_POST['hora_ini'];
    $hora_fim = $_POST['hora_fim'];
    $idUsuario = $_SESSION['usuario_id'];

    $stmt = $conn->prepare("INSERT INTO TB_ANALISES (Descricao, idSituacao, idAnalista, idSistema, idStatus, idUsuario, Hora_ini, Hora_fim, Total_hora) VALUES (?, ?, ?, ?, ?, ?, ?, ?, TIMEDIFF(?, ?))");
    $stmt->bind_param("siiiiissss", $descricao, $situacao, $analista, $sistema, $status, $idUsuario, $hora_ini, $hora_fim, $hora_fim, $hora_ini);

    
    if ($stmt->execute()) {
        header("Location: ../index.php?success=1");
        exit();
    } else {
        echo "Erro ao cadastrar: " . $stmt->error;
    }
    
    $stmt->close();
    $conn->close();
}
?>
