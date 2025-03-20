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
    $email = $_POST['email'];
    $nome = ($_POST['nome']);
    $senha = ($_POST['senha']);
    $cargo = 'User';

        // Verifica se o email já existe na base
        $checkStmt = $conn->prepare("SELECT id FROM TB_USUARIO WHERE Email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            $conn->close();
            header("Location: escutas.php?success=6");
            exit();
        }
        $checkStmt->close();

        $stmt = $conn->prepare("INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nome, $email, $senha, $cargo);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Usuário registrado com sucesso.";
        } else {
            header("Location: escutas.php?success=6");
        }
        $stmt->close();
    }

    $conn->close();
    header("Location: escutas.php?success=1");
    exit();
?>
