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

$stmt = $conn->prepare("
    INSERT INTO TB_ANALISES_PROD 
    (Descricao, idSituacao, idParceiro, idSistema, idStatus, idUsuario, chkFicha, numeroFicha, chkParado)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "siiiisssi",
    $descricao,
    $idSituacao,
    $idParceiro,
    $idSistema,
    $idStatus,
    $idUsuario,
    $chkFicha,
    $numeroFicha,
    $chkParado
);

if ($stmt->execute()) {
    header('Location: ../../Views/index.php?success=1');
    exit;
} else {
    echo "Erro ao cadastrar análise: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
