<?php
include '../Config/Database.php';

if (isset($_POST['id_editar'])) {
    // Obtenha os valores enviados pelo formulário
    $id = $_POST['id_editar'];
    $descricao = $_POST['descricao_editar'];
    $situacao = $_POST['situacao_editar'];
    $atendente = $_POST['atendente_editar'];
    $sistema = $_POST['sistema_editar'];
    $status = $_POST['status_editar'];
    $hora_ini = $_POST['hora_ini_editar'];
    $hora_fim = $_POST['hora_fim_editar'];
    $nota = $_POST['nota_editar'];
    $justificativa = $_POST['just_nota_editar'];

    // Use prepared statements para atualizar a análise
    $stmt = $conn->prepare("UPDATE TB_ANALISES SET 
                              Descricao = ?, 
                              idSituacao = ?, 
                              idAtendente = ?, 
                              idSistema = ?, 
                              idStatus = ?, 
                              Hora_ini = ?, 
                              Hora_fim = ?, 
                              Total_hora = TIMEDIFF(?, ?),
                              Nota = ?, 
                              justificativa = ? 
                            WHERE Id = ?");
    
    // Supondo que:
    // - Descricao, Hora_ini, Hora_fim, justificativa são strings (s)
    // - idSituacao, idAtendente, idSistema, idStatus, Nota, Id são inteiros (i)
    $stmt->bind_param("siiiissssisi", $descricao, $situacao, $atendente, $sistema, $status, $hora_ini, $hora_fim, $hora_fim, $hora_ini, $nota, $justificativa, $id);
    
    if ($stmt->execute()) {
        // Atualização realizada com sucesso
        header("Location: ../index.php?success=2");
    } else {
        // Se ocorrer um erro, exiba a mensagem
        echo "Erro: " . $stmt->error;
    }

    $stmt->close();
}
?>
