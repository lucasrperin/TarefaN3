<?php
require_once '../../Config/Database.php';

$nome     = $_POST['nome_parceiro'];
$cnpj_cpf = $_POST['cnpj_cpf'];
$serial   = $_POST['serial'];
$contato  = $_POST['contato'];
$status   = 'A';

$stmt = $conn->prepare("INSERT INTO TB_PARCEIROS (Nome, CPNJ_CPF, serial, contato, status) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $nome, $cnpj_cpf, $serial, $contato, $status);

if ($stmt->execute()) {
    header('Location: ../../Views/parceiros.php?success=1');
} else {
    echo "Erro ao cadastrar parceiro: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
