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
    $status    = $_POST['editar_status']; // Status enviado pelo formulário
    $vlr_total = $_POST['editar_valor'];
    $n_venda    = $_POST['editar_venda'];

    // Consulta o status atual no banco (se necessário para regras de transição)
    $sqlCheck = "SELECT status FROM TB_INDICACAO WHERE id = '$id'";
    $resCheck = mysqli_query($conn, $sqlCheck);
    $rowCheck = mysqli_fetch_assoc($resCheck);

    // Permitir edição se o status atual for 'Pendente', 'Faturado' ou 'Cancelado'
    if (
        $rowCheck['status'] === 'Pendente' ||
        $rowCheck['status'] === 'Faturado' ||
        $rowCheck['status'] === 'Cancelado'
    ) {
        if ($status === 'Faturado') {
            // Se o status enviado for "Faturado", pega os campos extras
            $editar_valor = $_POST['editar_valor'];
            $editar_venda = $_POST['editar_venda'];
            $sqlUpdate = "
                UPDATE TB_INDICACAO
                SET plugin_id = '$plugin_id',
                    data = '$data',
                    cnpj = '$cnpj',
                    serial = '$serial',
                    contato = '$contato',
                    fone = '$fone',
                    status = '$status',
                    vlr_total = '$vlr_total',
                    n_venda = '$n_venda'
                WHERE id = '$id'
            ";
        } else {
            // Para status "Pendente" ou "Cancelado", atualiza somente os campos comuns (além do status)
            $sqlUpdate = "
                UPDATE TB_INDICACAO
                SET plugin_id = '$plugin_id',
                    data = '$data',
                    cnpj = '$cnpj',
                    serial = '$serial',
                    contato = '$contato',
                    fone = '$fone',
                    status = '$status'
                WHERE id = '$id'
            ";
        }

        if (mysqli_query($conn, $sqlUpdate)) {
            header("Location: indicacao.php");
            exit();
        } else {
            echo "Erro ao atualizar: " . mysqli_error($conn);
        }
    } else {
        echo "Não é possível editar indicações com status incompatível.";
    }
} else {
    header("Location: indicacao.php");
    exit();
}
?>
