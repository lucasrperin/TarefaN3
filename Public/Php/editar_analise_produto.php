<?php
require '../../Config/Database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id             = intval($_POST['id_editar_produto'] ?? 0);
    $descricao      = trim($_POST['descricao_editar_produto'] ?? '');
    $idSituacao     = intval($_POST['situacao_editar_produto'] ?? 0);
    $idParceiro     = intval($_POST['parceiro_editar_produto'] ?? 0);
    $idSistema      = intval($_POST['sistema_editar_produto'] ?? 0);
    $idStatus       = intval($_POST['status_editar_produto'] ?? 0);
    $chkFicha       = isset($_POST['chkFicha_editar_produto']) ? 'S' : 'N';
    $numeroFicha    = !empty($_POST['numeroFicha_editar_produto']) ? intval($_POST['numeroFicha_editar_produto']) : null;
    $chkParado      = isset($_POST['chkParado_editar_produto']) ? 'S' : 'N';

    // Segurança básica
    if ($id <= 0) {
        header("Location: ../../index.php?error=1");
        exit;
    }

    $sql = "UPDATE TB_ANALISES_PROD
            SET Descricao=?, idSituacao=?, idParceiro=?, idSistema=?, idStatus=?, chkFicha=?, numeroFicha=?, chkParado=?, ult_edicao = CURRENT_TIMESTAMP
            WHERE Id=?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'siiiisisi',
        $descricao,
        $idSituacao,
        $idParceiro,
        $idSistema,
        $idStatus,
        $chkFicha,
        $numeroFicha,
        $chkParado,
        $id
    );

    if ($stmt->execute()) {
        header("Location: ../../index.php?success=2");
    } else {
        header("Location: ../../index.php?error=2");
    }
    exit;
}
header("Location: ../../index.php?error=3");
