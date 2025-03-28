<?php
// atualizar_usuario.php
include '../Config/Database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id       = intval($_POST['id']);
    $nome     = mysqli_real_escape_string($conn, $_POST['Nome']);
    $email    = mysqli_real_escape_string($conn, $_POST['Email']);
    $cargo    = mysqli_real_escape_string($conn, $_POST['Cargo']); // Novo campo cargo
    $senha    = isset($_POST['Senha']) ? mysqli_real_escape_string($conn, $_POST['Senha']) : '';
    $idEquipe = intval($_POST['idEquipe']);
    $idNivel  = intval($_POST['idNivel']);

    // Atualiza os dados do usuário; a senha é atualizada somente se preenchida
    if (!empty($senha)) {
        $sqlUsuario = "UPDATE TB_USUARIO 
                       SET Nome = '$nome', Email = '$email', Senha = '$senha', Cargo = '$cargo'
                       WHERE Id = $id";
    } else {
        $sqlUsuario = "UPDATE TB_USUARIO 
                       SET Nome = '$nome', Email = '$email', Cargo = '$cargo'
                       WHERE Id = $id";
    }

    if (mysqli_query($conn, $sqlUsuario)) {
        // Verifica se já existe um vínculo para o usuário
        $sqlCheck = "SELECT * FROM TB_EQUIPE_NIVEL_ANALISTA WHERE idUsuario = $id";
        $resultCheck = mysqli_query($conn, $sqlCheck);
        if (mysqli_num_rows($resultCheck) > 0) {
            // Atualiza o vínculo
            $sqlVinculo = "UPDATE TB_EQUIPE_NIVEL_ANALISTA 
                           SET idEquipe = $idEquipe, idNivel = $idNivel 
                           WHERE idUsuario = $id";
            mysqli_query($conn, $sqlVinculo);
        } else {
            // Insere o vínculo caso não exista
            $sqlVinculo = "INSERT INTO TB_EQUIPE_NIVEL_ANALISTA (idUsuario, idEquipe, idNivel) 
                           VALUES ($id, $idEquipe, $idNivel)";
            mysqli_query($conn, $sqlVinculo);
        }

        header("Location: usuarios.php");
        exit();
    } else {
        echo "Erro ao atualizar usuário: " . mysqli_error($conn);
    }
} else {
    header("Location: usuarios.php");
    exit();
}
?>
