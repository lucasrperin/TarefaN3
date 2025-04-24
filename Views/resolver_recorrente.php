<?php
// resolver_recorrente.php
// Atualizado para lidar com conclusão via POST (com resposta) e reabertura via GET

include '../Config/Database.php';
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Conclusão com resposta do desenvolvimento (via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resposta'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $resposta = $conn->real_escape_string(trim($_POST['resposta']));
    if ($id > 0) {
        $conn->query("
            UPDATE TB_RECORRENTES
               SET resolvido = 1,
                   completed_at = NOW(),
                   resposta = '{$resposta}'
             WHERE id = {$id}
        ");
    }
} else {
    // Toggle de reabertura via GET
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        // Verifica estado atual
        $res = $conn->query("SELECT resolvido FROM TB_RECORRENTES WHERE id = {$id}");
        $row = $res->fetch_assoc();
        $current = (int)$row['resolvido'];
        if ($current === 1) {
            // Reabrir: desmarca e limpa completed_at e resposta
            $conn->query("
                UPDATE TB_RECORRENTES
                   SET resolvido = 0,
                       completed_at = NULL,
                       resposta = NULL
                 WHERE id = {$id}
            ");
        }
    }
}

// Redireciona para a aba Recorrentes
header("Location: incidente.php?tab=recorrentes");
exit();
