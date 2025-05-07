<?php
include '../Config/Database.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Apenas Admin pode cadastrar
if (!isset($_SESSION['cargo']) || $_SESSION['cargo'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id       = $_POST['user_id'];
    $data_escuta   = $_POST['data_escuta'];
    $classificacao = $_POST['classi_id'];
    // Se não veio, ficará string vazia e será convertido em NULL no SQL
    $positivo      = isset($_POST['positivo'])  ? $_POST['positivo']  : '';
    $transcricao   = trim($_POST['transcricao']);
    $feedback      = trim($_POST['feedback']);
    $avaliacao     = isset($_POST['avaliacao']) ? $_POST['avaliacao'] : '';
    $admin_id      = $_SESSION['usuario_id']; // ID do Admin logado

    // NULLIF transforma '' em NULL, evitando inserção de valor inválido
    $sql = "
        INSERT INTO TB_ESCUTAS
            (user_id, admin_id, classi_id, data_escuta, transcricao, feedback, P_N, solicitaAva)
        VALUES
            (?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''))
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param(
            "iiisssss",
            $user_id,
            $admin_id,
            $classificacao,
            $data_escuta,
            $transcricao,
            $feedback,
            $positivo,
            $avaliacao
        );

        if ($stmt->execute()) {
            $_SESSION['success'] = "Escuta registrada com sucesso.";
        } else {
            $_SESSION['error'] = "Erro ao registrar a escuta: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $_SESSION['error'] = "Erro na preparação da consulta: " . $conn->error;
    }

    $conn->close();
    header("Location: escutas.php?success=2");
    exit;
}
?>
