<?php
session_start();
require_once '../Config/Database.php'; 

$codigo = $_GET['codigo'];

$delete = $conn->prepare("delete from TB_ANALISES WHERE id = ?");
$delete->bind_param("i", $codigo);

if ($conn->query($delete) === TRUE) {
    header("Location: ../index.php?success=3"); // Redireciona com mensagem de sucesso
} else {
    echo "Erro: " . $delete . "<br>" . $conn->error;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tarefas N3</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

</head>
</html>

