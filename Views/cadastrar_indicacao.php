<?php 
session_start();
require '../Config/Database.php';

if (!isset($_SESSION['usuario_id'])) {
    // redireciona se não estiver logado
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Captura os dados enviados pelo formulário
$plugin_id   = mysqli_real_escape_string($conn, $_POST['plugin_id']);
$data        = mysqli_real_escape_string($conn, $_POST['data']);
$cnpj        = mysqli_real_escape_string($conn, $_POST['cnpj']);
$serial      = mysqli_real_escape_string($conn, $_POST['serial']);
$contato     = mysqli_real_escape_string($conn, $_POST['contato']);
$fone        = mysqli_real_escape_string($conn, $_POST['fone']);
$observacao  = isset($_POST['observacao'])
    ? mysqli_real_escape_string($conn, $_POST['observacao'])
    : '';

// Define o status e o consultor fixos
$status      = 'Pendente';
$consultor   = 29;

// Monta e executa a query de inserção
$sql = "
  INSERT INTO TB_INDICACAO (
    plugin_id, data, cnpj, serial, contato, fone, observacao,
    user_id, idConsultor, status
  ) VALUES (
    '$plugin_id', '$data', '$cnpj', '$serial', '$contato', '$fone', '$observacao',
    '$usuario_id', '$consultor', '$status'
  )
";

if (mysqli_query($conn, $sql)) {
    header('Location: indicacao.php?success=1');
    exit();
} else {
    // em caso de erro, exibe mensagem e redireciona com flag de erro
    error_log("Erro ao cadastrar indicação: " . mysqli_error($conn));
    header("Location: indicacao.php?erro=1");
    exit();
}
