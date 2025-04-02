<?php
if (ob_get_length()) {
    ob_end_clean();
}
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
// Apenas Admin pode cadastrar classificação
if (!isset($_SESSION['cargo']) || $_SESSION['cargo'] !== 'Admin') {
    header('Content-Type: application/json');
    echo json_encode(array("message" => "Acesso negado."));
    exit();
}

require '../Config/Database.php';

header('Content-Type: application/json');

if (!isset($_POST['descricao'])) {
    echo json_encode(array("message" => "Descrição da classificação não informada."));
    exit();
}

$descricao = mysqli_real_escape_string($conn, trim($_POST['descricao']));
if (empty($descricao)) {
    echo json_encode(array("message" => "Descrição da classificação não informada."));
    exit();
}

// Verifica se já existe uma classificação com o mesmo nome (comparação case-insensitive e removendo espaços extras)
$sqlCheck = "SELECT id, descricao FROM TB_CLASSIFICACAO WHERE LOWER(TRIM(descricao)) = LOWER(TRIM('$descricao'))";
$resultCheck = mysqli_query($conn, $sqlCheck);
if ($resultCheck && mysqli_num_rows($resultCheck) > 0) {
    $row = mysqli_fetch_assoc($resultCheck);
    echo json_encode(array(
        "duplicate" => true,
        "message" => "Classificação já cadastrada.",
        "id" => $row['id'],
        "descricao" => $row['descricao']
    ));
    exit();
}

$sql = "INSERT INTO TB_CLASSIFICACAO (descricao) VALUES ('$descricao')";
if (mysqli_query($conn, $sql)) {
    $id = mysqli_insert_id($conn);
    echo json_encode(array("duplicate" => false, "id" => $id, "descricao" => $descricao));
} else {
    echo json_encode(array("message" => "Erro ao cadastrar classificação: " . mysqli_error($conn)));
}
exit();
?>
