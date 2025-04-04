<?php
// excluir_usuario.php
include '../Config/Database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = intval($_POST['id']);

    // As tabelas verificadas: TB_ANALISES, TB_ESCUTAS (user_id ou admin_id), TB_INDICACAO, TB_FOLGA, TB_AVALIACOES
    $sqlCheck = "SELECT (
        (SELECT COUNT(*) FROM TB_ANALISES WHERE idUsuario = $id) +
        (SELECT COUNT(*) FROM TB_ESCUTAS WHERE user_id = $id OR admin_id = $id) +
        (SELECT COUNT(*) FROM TB_INDICACAO WHERE user_id = $id) +
        (SELECT COUNT(*) FROM TB_FOLGA WHERE usuario_id = $id) +
        (SELECT COUNT(*) FROM TB_AVALIACOES WHERE usuario_id = $id)
    ) as total";
    
    $resultCheck = mysqli_query($conn, $sqlCheck);
    $row = mysqli_fetch_assoc($resultCheck);
    if ($row['total'] > 0) {
         // Se houver vínculos, redireciona com erro=2
         header("Location: usuarios.php?error=2");
         exit();
    }

    // Exclui os vínculos na tabela TB_EQUIPE_NIVEL_ANALISTA para o usuário
    $sqlDeleteVinculo = "DELETE FROM TB_EQUIPE_NIVEL_ANALISTA WHERE idUsuario = $id";
    mysqli_query($conn, $sqlDeleteVinculo);
    // Se não houver vínculos, prossegue com a exclusão do usuário
    $sqlUsuario = "DELETE FROM TB_USUARIO WHERE Id = $id";
    if (mysqli_query($conn, $sqlUsuario)) {
        header("Location: usuarios.php?success=3");
        exit();
    } else {
        echo "Erro ao excluir usuário: " . mysqli_error($conn);
    }
} else {
    echo "Método de requisição inválido";
    exit();
}
?>
