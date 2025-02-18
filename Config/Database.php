<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "TarefaN3";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// CONEXÃO COM O BANCO DE DADOS DO infinityfree ;

// $host = "sql203.infinityfree.com";
// $user = "if0_38344132";
// $pass = "0AV91eMAAI";
// $db = "if0_38344132_tarefan3";

// $conn = new mysqli($host, $user, $pass, $db);

// if ($conn->connect_error) {
//     die("Falha na conexão: " . $conn->connect_error);
// }

?>


 
