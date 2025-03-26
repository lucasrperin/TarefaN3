<?php
include '../Config/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Captura os dados do formulário, incluindo o novo campo indisponibilidade
    $id = $_POST['id'];
    $sistema = $_POST['sistema'];
    $gravidade = $_POST['gravidade'];
    $indisponibilidade = $_POST['indisponibilidade']; // Novo campo
    $problema = $_POST['problema'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fim = $_POST['hora_fim'];
    $tempo_total = $_POST['tempo_total'];

    // Monta a query de UPDATE com 8 parâmetros
    $sql = "UPDATE TB_INCIDENTES SET 
                sistema = ?, 
                gravidade = ?, 
                indisponibilidade = ?, 
                problema = ?, 
                hora_inicio = ?, 
                hora_fim = ?, 
                tempo_total = ? 
            WHERE id = ?";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Erro na preparação: " . $conn->error);
    }

    // Bind dos 8 parâmetros: 7 strings e 1 inteiro
    $stmt->bind_param('sssssssi', $sistema, $gravidade, $indisponibilidade, $problema, $hora_inicio, $hora_fim, $tempo_total, $id);

    if($stmt->execute()){
        header("Location: incidente.php?msg=edit_success");
        exit();
    } else {
        echo "Erro ao atualizar o incidente: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
