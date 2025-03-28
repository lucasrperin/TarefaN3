<?php
// salvar_usuario.php
include '../Config/Database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recupera e sanitiza os dados do formulário
    $nome     = mysqli_real_escape_string($conn, $_POST['Nome']);
    $email    = mysqli_real_escape_string($conn, $_POST['Email']);
    $senha    = mysqli_real_escape_string($conn, $_POST['Senha']);
    $cargo    = mysqli_real_escape_string($conn, $_POST['Cargo']); // Novo campo cargo
    $idEquipe = intval($_POST['idEquipe']);
    $idNivel  = intval($_POST['idNivel']);

    // Insere o usuário na TB_USUARIO (o campo Cargo é informado pelo formulário)
    $sqlUsuario = "INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) 
                   VALUES ('$nome', '$email', '$senha', '$cargo')";
    
    if (mysqli_query($conn, $sqlUsuario)) {
        $lastId = mysqli_insert_id($conn);

        // Insere o vínculo na tabela TB_EQUIPE_NIVEL_ANALISTA
        $sqlVinculo = "INSERT INTO TB_EQUIPE_NIVEL_ANALISTA (idUsuario, idEquipe, idNivel) 
                       VALUES ($lastId, $idEquipe, $idNivel)";
        mysqli_query($conn, $sqlVinculo);

        header("Location: usuarios.php");
        exit();
    } else {
        echo "Erro ao cadastrar usuário: " . mysqli_error($conn);
    }
} else {
    header("Location: usuarios.php");
    exit();
}
?>
