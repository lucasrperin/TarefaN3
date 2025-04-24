<?php
// editar_recorrente.php
// Atualiza dados de um caso recorrente e seus cards vinculados

include '../Config/Database.php';
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Recebe dados do POST
$id        = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$situacao  = $conn->real_escape_string($_POST['situacao']);
$raw       = trim($_POST['card_nums']);

// Atualiza a situação no registro principal
if ($id > 0) {
    $conn->query("
        UPDATE TB_RECORRENTES
        SET situacao = '$situacao'
        WHERE id = $id
    ");

    // Remove todos os cards antigos vinculados
    $conn->query("
        DELETE FROM TB_RECORRENTES_CARDS
        WHERE recorrente_id = $id
    ");

    // Insere novamente cada número de card informado
    foreach (explode("\n", $raw) as $line) {
        $num = trim($line);
        if ($num === '' || !ctype_digit($num)) {
            continue;
        }
        $conn->query("
            INSERT INTO TB_RECORRENTES_CARDS (recorrente_id, card_num)
            VALUES ($id, '$num')
        ");
    }
}

// Redireciona de volta para a aba Recorrentes em incidente.php
header("Location: incidente.php?tab=recorrentes");
exit();
?>