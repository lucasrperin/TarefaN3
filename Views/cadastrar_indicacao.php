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
// Define o consultor explicitamente como "Vanessa" pois o consultor é FK é este campo precisa ser preenchido
// pois somente terá de fato um consultor na edição.
$consultor = 29;

// Monta a query de inserção, incluindo o user_id e o status
$sql = "
  INSERT INTO TB_INDICACAO (plugin_id, data, cnpj, serial, contato, fone, user_id, idConsultor, status)
  VALUES ('$plugin_id', '$data', '$cnpj', '$serial', '$contato', '$fone', '$usuario_id', '$consultor', '$status')
";

if (mysqli_query($conn, $sql)) {
    header('Location: indicacao.php?success=1');
    exit();
} else {
    echo 'Erro ao cadastrar: ' . mysqli_error($conn);
    header("Location: indicacao.php?erro=1");
    exit();
}
