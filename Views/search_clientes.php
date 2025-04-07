<?php
include '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([]);
    exit();
}

$query = "SELECT id, cliente, cnpjcpf, serial 
          FROM TB_CLIENTES 
          WHERE ativo = 1 
          AND (cliente LIKE ? OR cnpjcpf LIKE ? OR serial LIKE ?) 
          LIMIT 10";
$search = "%" . $_GET['q'] . "%";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'sss', $search, $search, $search);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$clientes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $clientes[] = $row;
}
mysqli_stmt_close($stmt);
header('Content-Type: application/json');
echo json_encode($clientes);
exit();
?>
