<?php
include '../Config/Database.php';

// Captura os valores enviados pelo formulário
$sistema = $_POST['sistema'];
$gravidade = $_POST['gravidade'];
$indisponibilidade = $_POST['indisponibilidade']; // Novo campo
$problema = $_POST['problema'];
$hora_inicio = $_POST['hora_inicio'];
$hora_fim = $_POST['hora_fim'];
$tempo_total = $_POST['tempo_total'];

// Prepara a consulta SQL para inserir os dados, incluindo indisponibilidade
$sql = "INSERT INTO TB_INCIDENTES (sistema, gravidade, indisponibilidade, problema, hora_inicio, hora_fim, tempo_total) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if(!$stmt){
    die("Erro na preparação: " . $conn->error);
}

// Ajusta o bind_param para 7 parâmetros (todos como string)
$stmt->bind_param('sssssss', $sistema, $gravidade, $indisponibilidade, $problema, $hora_inicio, $hora_fim, $tempo_total);

if($stmt->execute()){
    header("Location: incidente.php?msg=success");
    exit();
} else {
    echo "Erro ao inserir o incidente: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
