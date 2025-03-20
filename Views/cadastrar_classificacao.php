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
    $descricao = $_POST['descricao'];
        // Verifica se o email já existe na base
        $checkStmt = $conn->prepare("SELECT id FROM TB_CLASSIFICACAO WHERE descricao = ?");
        $checkStmt->bind_param("s", $descricao);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            $_SESSION['error'] = "Classificação já cadastrada.";
            header("Location: escutas.php");
            exit;
        }
        $checkStmt->close();

        $stmt = $conn->prepare("INSERT INTO TB_CLASSIFICACAO (descricao) VALUES (?)");
        $stmt->bind_param("s", $descricao);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Classificação registrada com sucesso.";
        } else {
            $_SESSION['error'] = "Erro ao registrar a classificação. Tente novamente.";
        }
        $stmt->close();
    }
    $conn->close();
    header("Location: escutas.php?success=5");
    exit();
?>
