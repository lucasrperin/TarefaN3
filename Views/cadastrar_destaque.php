<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

require '../Config/Database.php'; // O arquivo já inicializa a variável $conn

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recebe os dados do formulário
    $usuario_id = isset($_POST['usuario_id']) ? intval($_POST['usuario_id']) : null;
    $trimestre = isset($_POST['trimestre']) ? trim($_POST['trimestre']) : '';
    // 'criterio' agora é o id do critério, que é um INT
    $criterio = isset($_POST['criterio']) ? intval($_POST['criterio']) : 0;
    $valor = isset($_POST['valor']) ? intval($_POST['valor']) : 0;
    
    // Validação básica: usuário, trimestre e critério são obrigatórios
    if (!$usuario_id || empty($trimestre) || $criterio === 0) {
        die("Erro: Dados obrigatórios não informados.");
    }
    
    // Prepara a consulta para inserir os dados na nova estrutura da tabela TB_AVALIACOES
    $sql = "INSERT INTO TB_AVALIACOES (usuario_id, trimestre, criterio, valor) VALUES (?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("isii", $usuario_id, $trimestre, $criterio, $valor);
        
        if ($stmt->execute()) {
            header("Location: destaque.php?success=1");
            exit();
        } else {
            die("Erro ao salvar avaliação: " . $stmt->error);
        }
        
        $stmt->close();
    } else {
        die("Erro na preparação da consulta: " . $conn->error);
    }
} else {
    header("Location: destaque.php");
    exit();
}
?>
