<?php
if (ob_get_length()) {
    ob_end_clean();
}
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['usuario_id']) && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $_SESSION['usuario_id'] = $_POST['usuario_id'];
    header("Location: user.php");
    exit();
}
require '../Config/Database.php';

header('Content-Type: application/json');

if (!isset($_POST['nome'])) {
    echo json_encode(array("message" => "Nome do plugin não informado."));
    exit();
}

$nome = mysqli_real_escape_string($conn, trim($_POST['nome']));
$nome = strip_tags($nome); // remove HTML
if (empty($nome)) {
    echo json_encode(array("message" => "Nome do plugin não informado."));
    exit();
}

// Verifica se já existe um plugin com o mesmo nome (comparação case-insensitive e removendo espaços extras)
$sqlCheck = "SELECT id, nome FROM TB_PLUGIN WHERE LOWER(TRIM(nome)) = LOWER(TRIM('$nome'))";
$resultCheck = mysqli_query($conn, $sqlCheck);
if ($resultCheck && mysqli_num_rows($resultCheck) > 0) {
    $row = mysqli_fetch_assoc($resultCheck);
    // Retorna com flag duplicate
    echo json_encode(array(
        "duplicate" => true,
        "message" => "Plugin já cadastrado.",
        "id" => $row['id'],
        "nome" => $row['nome']
    ));
    exit();
}

$sql = "INSERT INTO TB_PLUGIN (nome) VALUES ('$nome')";
if (mysqli_query($conn, $sql)) {
    $id = mysqli_insert_id($conn);
    echo json_encode(array("duplicate" => false, "id" => $id, "nome" => $nome));
} else {
    echo json_encode(array("message" => "Erro ao cadastrar plugin: " . mysqli_error($conn)));
}
exit();
