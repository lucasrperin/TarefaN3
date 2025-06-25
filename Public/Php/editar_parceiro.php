<?php
require_once '../../Config/Database.php';

$id       = $_POST['id_parceiro'];
$nome     = $_POST['nome_parceiro'];
$cnpj_cpf = $_POST['cnpj_cpf'];
$serial   = $_POST['serial'];
$contato  = $_POST['contato'];

$stmt = $conn->prepare("UPDATE TB_PARCEIROS SET Nome = ?, CPNJ_CPF = ?, serial = ?, contato = ? WHERE Id = ?");
$stmt->bind_param("ssssi", $nome, $cnpj_cpf, $serial, $contato, $id);

if ($stmt->execute()) {
    header('Location: ../../Views/parceiros.php?success=2');
} else {
    echo "Erro ao editar parceiro: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
