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
    $user_id     = $_POST['user_id'];
    $data_escuta = $_POST['data_escuta'];
    $transcricao = trim($_POST['transcricao']);
    $feedback    = trim($_POST['feedback']);
    $admin_id    = $_SESSION['usuario_id']; // ID do Admin logado

    // Verifica se o Admin já registrou 5 escutas neste mês
    $currentMonth = date('Y-m');
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM TB_ESCUTAS WHERE admin_id = ? AND DATE_FORMAT(data_escuta, '%Y-%m') = ?");
    $stmt->bind_param("is", $admin_id, $currentMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'];
    $stmt->close();

    if ($count >= 5) {
        $_SESSION['error'] = "Você já registrou 5 escutas neste mês.";
    } else {
        $stmt = $conn->prepare("INSERT INTO TB_ESCUTAS (user_id, admin_id, data_escuta, transcricao, feedback) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $user_id, $admin_id, $data_escuta, $transcricao, $feedback);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Escuta registrada com sucesso.";
        } else {
            $_SESSION['error'] = "Erro ao registrar a escuta. Tente novamente.";
        }
        $stmt->close();
    }
}

header("Location: escutas.php");
exit;
?>
