<?php
session_start();
require_once '../Config/Database.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Pega os dados do formulário
    $id = $_POST['id_excluir'];
   
    $sql = "delete from TB_CONVERSOES WHERE id = '$id'";

    if ($conn->query($sql) === TRUE) {
        header("Location: ../Views/conversao.php?success=3"); // Redireciona com mensagem de sucesso
    } else {
        echo "Erro: " . $sql . "<br>" . $conn->error;
    }
}
?>
