<?php
require_once '../../Config/Database.php';
session_start();

$idUsuario = $_SESSION['usuario_id'] ?? null;

if (!$idUsuario) {
    header('Location: ../../login.php');
    exit;
}

$descricao    = $_POST['descricao'];
$idSituacao   = $_POST['situacao'];
$idParceiro   = $_POST['atendente'];
$idSistema    = $_POST['sistema'];
$idStatus     = $_POST['status'];

$chkFicha     = isset($_POST['chkFicha']) ? 'S' : 'N';
$numeroFicha  = !empty($_POST['numeroFicha']) ? $_POST['numeroFicha'] : null;
$chkParado    = isset($_POST['chkParado']) ? 'S' : 'N';

// Insert principal
$stmt = $conn->prepare("
    INSERT INTO TB_ANALISES_PROD 
    (Descricao, idSituacao, idParceiro, idSistema, idStatus, idUsuario, chkFicha, numeroFicha)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "siiiisss",
    $descricao,
    $idSituacao,
    $idParceiro,
    $idSistema,
    $idStatus,
    $idUsuario,
    $chkFicha,
    $numeroFicha
);

if ($stmt->execute()) {
    // Se for ficha, insere o registro extra igual no cadastrar_analise.php
    if ($chkFicha === 'S' && $numeroFicha) {
        $descricaoFicha = "Ficha criada " . $numeroFicha;
        $situacaoFicha = 3; // Situação Ficha criada fixa (ajuste conforme necessário)
        $statusFicha   = 2; // Status DESENVOLVIMENTO fixa (ajuste conforme necessário)

        $stmtFicha = $conn->prepare("
            INSERT INTO TB_ANALISES_PROD
            (Descricao, idSituacao, idParceiro, idSistema, idStatus, idUsuario, chkFicha, numeroFicha, chkParado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmtFicha->bind_param(
            "siiiissss",
            $descricaoFicha,
            $situacaoFicha,
            $idParceiro,
            $idSistema,
            $statusFicha,
            $idUsuario,
            $chkFicha,
            $numeroFicha,
            $chkParado
        );
        $stmtFicha->execute();
        $stmtFicha->close();
    }

    header('Location: ../../index.php?success=1');
    exit;
} else {
    echo "Erro ao cadastrar análise: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
