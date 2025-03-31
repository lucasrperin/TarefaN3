<?php
// excluir_usuario.php
include '../Config/Database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = intval($_POST['id']);

    // Remove o vínculo na tabela TB_EQUIPE_NIVEL_ANALISTA
    $sqlVinculo = "DELETE FROM TB_EQUIPE_NIVEL_ANALISTA WHERE idUsuario = $id";
    mysqli_query($conn, $sqlVinculo);

    // Exclui o usuário da tabela TB_USUARIO
    $sqlUsuario = "DELETE FROM TB_USUARIO WHERE Id = $id";
    if (mysqli_query($conn, $sqlUsuario)) {
        header("Location: usuarios.php?success=3");
        exit();
    } else {
        echo "Erro ao excluir usuário: " . mysqli_error($conn);
    }
} else {
    header("Location: usuarios.php?success=4");
    exit();
}
?>
