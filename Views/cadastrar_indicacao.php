<?php 
session_start();
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['usuario_id'])) {
    $_SESSION['usuario_id'] = $_POST['usuario_id'];
    header("Location: user.php");
    exit();
}
require '../Config/Database.php';

$usuario_id = $_SESSION['usuario_id'];

// Captura os dados enviados pelo formulário
$plugin_id = $_POST['plugin_id'];
$data      = $_POST['data'];
$cnpj      = $_POST['cnpj'];
$serial    = $_POST['serial'];
$contato   = $_POST['contato'];
$fone      = $_POST['fone'];

// Define o status explicitamente como "Pendente"
$status = 'Pendente';

// Monta a query de inserção, incluindo o user_id e o status
$sql = "
  INSERT INTO TB_INDICACAO (plugin_id, data, cnpj, serial, contato, fone, user_id, status)
  VALUES ('$plugin_id', '$data', '$cnpj', '$serial', '$contato', '$fone', '$usuario_id', '$status')
";

if (mysqli_query($conn, $sql)) {
    header('Location: indicacao.php');
    exit();
} else {
    echo 'Erro ao cadastrar: ' . mysqli_error($conn);
}
