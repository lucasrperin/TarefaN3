<?php
include '../Config/Database.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sistema     = $_POST['sistema'];
    $gravidade   = $_POST['gravidade'];  // "Gravissimo" sem acento
    $problema    = $_POST['problema'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fim    = $_POST['hora_fim'];
    $tempo_total = $_POST['tempo_total'];

    $sql = "INSERT INTO TB_INCIDENTES (sistema, gravidade, problema, hora_inicio, hora_fim, tempo_total) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssssss", $sistema, $gravidade, $problema, $hora_inicio, $hora_fim, $tempo_total);
        if ($stmt->execute()) {
            // Redireciona de volta para a página principal com mensagem de sucesso
            header("Location: incidente.php?msg=success");
            exit;
        } else {
            echo "Erro ao registrar incidente: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Erro na preparação da query: " . $conn->error;
    }
    $conn->close();
} else {
    echo "Método de requisição inválido.";
}
?>
