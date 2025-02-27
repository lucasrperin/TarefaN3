<?php
require '../Config/Database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Pega os dados do formulário
    $id = $_POST['id_editar'];
    $descricao = $_POST['descricao_editar'];
    $situacao = $_POST['situacao_editar'];
    $analista = $_POST['atendente_editar'];
    $sistema = $_POST['sistema_editar'];
    $status = $_POST['status_editar'];
    $hora_ini = $_POST['hora_ini_editar'];
    $hora_fim = $_POST['hora_fim_editar'];

    // Calcular o total de horas
    $sql = "UPDATE TB_ANALISES SET
            Descricao = '$descricao',
            idSituacao = '$situacao',
            idAtendente = '$analista',
            idSistema = '$sistema',
            idStatus = '$status',
            Hora_ini = '$hora_ini',
            Hora_fim = '$hora_fim',
            Total_hora = TIMEDIFF('$hora_fim', '$hora_ini') 
            WHERE Id = '$id'";

    if ($conn->query($sql) === TRUE) {
        header("Location: ../index.php?success=2"); // Redireciona com mensagem de sucesso
    } else {
        echo "Erro: " . $sql . "<br>" . $conn->error;
    }
}
?>
