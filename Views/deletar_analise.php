<?php
session_start();
require_once '../Config/Database.php'; 

$codigo = $_GET['codigo'];

$delete = $conn->prepare("delete from TB_ANALISES WHERE id = ?");
$delete->bind_param("i", $codigo);

if ($delete->execute()) {
    echo "<div class=\"alert alert-success\" role=\"alert\">
            Análise excluida com sucesso!
        </div>";
    header("Refresh: 2; URL=../index.php");
} else {
    echo "<h1>Erro</h1>";
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

