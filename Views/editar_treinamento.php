<?php
include '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../Views/login.php");
    exit();
}

$id = $_POST['id'];
$action = $_POST['action'];

if ($action === 'salvar') {
    // Atualiza os demais campos conforme o formulário
    $data         = $_POST['data'];
    $hora         = $_POST['hora'];
    $tipo         = $_POST['tipo'];
    $duracao      = isset($_POST['duracao']) ? intval($_POST['duracao']) : 30;
    $cliente_id   = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
    $sistema      = $_POST['sistema'];
    $consultor    = $_POST['consultor'];
    $status       = $_POST['status'];
    $observacoes  = $_POST['observacoes'];

    if ($cliente_id === 0) {
        $queryExisting = "SELECT cliente_id FROM TB_TREINAMENTOS WHERE id = ?";
        $stmtExisting = mysqli_prepare($conn, $queryExisting);
        mysqli_stmt_bind_param($stmtExisting, "i", $id);
        mysqli_stmt_execute($stmtExisting);
        mysqli_stmt_bind_result($stmtExisting, $existingClienteId);
        if (mysqli_stmt_fetch($stmtExisting)) {
            $cliente_id = $existingClienteId;
        }
        mysqli_stmt_close($stmtExisting);
    }

    $queryCheck = "SELECT id FROM TB_CLIENTES WHERE id = ?";
    $stmtCheck = mysqli_prepare($conn, $queryCheck);
    mysqli_stmt_bind_param($stmtCheck, 'i', $cliente_id);
    mysqli_stmt_execute($stmtCheck);
    mysqli_stmt_store_result($stmtCheck);
    if (mysqli_stmt_num_rows($stmtCheck) == 0) {
        mysqli_stmt_close($stmtCheck);
        die("Erro: Cliente selecionado não existe.");
    }
    mysqli_stmt_close($stmtCheck);

    $query = "UPDATE TB_TREINAMENTOS 
              SET data = ?, hora = ?, tipo = ?, duracao = ?, cliente_id = ?, sistema = ?, consultor = ?, status = ?, observacoes = ? 
              WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sssisssssi", $data, $hora, $tipo, $duracao, $cliente_id, $sistema, $consultor, $status, $observacoes, $id);
    if (!mysqli_stmt_execute($stmt)) {
        die("Erro ao atualizar agendamento: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);
    header("Location: /TarefaN3/Views/treinamento.php?success=2");
    exit();
    
} elseif ($action === 'iniciar') {
    // Verifica se o treinamento já foi iniciado
    $queryCheck = "SELECT dt_ini FROM TB_TREINAMENTOS WHERE id = ?";
    $stmtCheck = mysqli_prepare($conn, $queryCheck);
    mysqli_stmt_bind_param($stmtCheck, "i", $id);
    mysqli_stmt_execute($stmtCheck);
    mysqli_stmt_bind_result($stmtCheck, $dt_ini_actual);
    mysqli_stmt_fetch($stmtCheck);
    mysqli_stmt_close($stmtCheck);

    // Se dt_ini já possui valor (diferente de NULL e "0000-00-00 00:00:00"), redireciona
    if (!is_null($dt_ini_actual) && $dt_ini_actual !== "0000-00-00 00:00:00") {
        header("Location: /TarefaN3/Views/treinamento.php?success=4");
        exit();
    }
    
    // Se ainda não foi iniciado, atualiza o campo dt_ini para NOW()
    $query = "UPDATE TB_TREINAMENTOS SET dt_ini = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    if (!mysqli_stmt_execute($stmt)) {
         die("Erro ao iniciar treinamento: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);
    
    header("Location: /TarefaN3/Views/treinamento.php?success=4");
    exit();
    
} elseif ($action === 'encerrar') {
    $query = "UPDATE TB_TREINAMENTOS 
              SET dt_fim = NOW(), status = 'CONCLUIDO', 
                  duracao = TIMESTAMPDIFF(MINUTE, dt_ini, NOW()),
                  total_tempo = TIMEDIFF(NOW(), dt_ini)
              WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    if (!mysqli_stmt_execute($stmt)) {
         die("Erro ao encerrar treinamento: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);
    
    header("Location: /TarefaN3/Views/treinamento.php?success=5");
    exit();
}
?>
