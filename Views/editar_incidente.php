<?php
include '../Config/Database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id          = intval($_POST['id']);
    $sistema     = $_POST['sistema'];
    $gravidade   = $_POST['gravidade'];
    $problema    = $_POST['problema'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fim    = $_POST['hora_fim'];
    $tempo_total = $_POST['tempo_total'];
    
    $sql = "UPDATE tb_incidentes SET sistema = ?, gravidade = ?, problema = ?, hora_inicio = ?, hora_fim = ?, tempo_total = ? WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssssssi", $sistema, $gravidade, $problema, $hora_inicio, $hora_fim, $tempo_total, $id);
        if ($stmt->execute()) {
            header("Location: incidente.php?msg=edit_success");
            exit;
        } else {
            echo "Erro ao atualizar: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Erro na preparação da query: " . $conn->error;
    }
    $conn->close();
} else {
    echo "Método inválido.";
}
?>
