<?php
session_start();
require '../Config/Database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = $_POST['id'];
    $plugin_id = $_POST['plugin_id'];
    $data      = $_POST['data'];
    $cnpj      = $_POST['cnpj'];
    $serial    = $_POST['serial'];
    $contato   = $_POST['contato'];
    $fone      = $_POST['fone'];
    
    // Atualiza a indicação somente se o status for Pendente
    $sqlCheck = "SELECT status FROM TB_INDICACAO WHERE id = '$id'";
    $resCheck = mysqli_query($conn, $sqlCheck);
    $rowCheck = mysqli_fetch_assoc($resCheck);
    if ($rowCheck['status'] === 'Pendente') {
        $sqlUpdate = "
            UPDATE TB_INDICACAO
            SET plugin_id = '$plugin_id',
                data = '$data',
                cnpj = '$cnpj',
                serial = '$serial',
                contato = '$contato',
                fone = '$fone'
            WHERE id = '$id'
        ";
        if (mysqli_query($conn, $sqlUpdate)) {
            header("Location: indicacao.php");
            exit();
        } else {
            echo "Erro ao atualizar: " . mysqli_error($conn);
        }
    } else {
        echo "Não é possível editar indicações que não estão pendentes.";
    }
} else {
    header("Location: indicacao.php");
    exit();
}
