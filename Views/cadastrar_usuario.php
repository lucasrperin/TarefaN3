<?php
// Alteração no cadastrar_usuario.php para suportar múltiplos níveis por usuário

include '../Config/Database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recupera e sanitiza os dados do formulário
    $nome     = mysqli_real_escape_string($conn, $_POST['Nome']);
    $email    = mysqli_real_escape_string($conn, $_POST['Email']);
    $senha    = mysqli_real_escape_string($conn, $_POST['Senha']);
    $cargo    = mysqli_real_escape_string($conn, $_POST['Cargo']); // Campo Cargo
    $idEquipe = intval($_POST['idEquipe']);
    // Agora esperamos que idNivel seja enviado como um array (suporte a múltiplos níveis)
    $idNiveis = $_POST['idNivel'];
    if (!is_array($idNiveis)) {
        $idNiveis = [$idNiveis];
    }

    // Verifica se o email já existe
    $sqlCheck = "SELECT * FROM TB_USUARIO WHERE Email = '$email'";
    $resultCheck = mysqli_query($conn, $sqlCheck);
    if (mysqli_num_rows($resultCheck) > 0) {
         header("Location: usuarios.php?error=1");
         exit();
    }

    // Insere o usuário na TB_USUARIO
    $sqlUsuario = "INSERT INTO TB_USUARIO (Nome, Email, Senha, Cargo) 
                   VALUES ('$nome', '$email', '$senha', '$cargo')";
    
    if (mysqli_query($conn, $sqlUsuario)) {
        $lastId = mysqli_insert_id($conn);

        // Insere múltiplos vínculos na tabela TB_EQUIPE_NIVEL_ANALISTA para cada nível selecionado
        foreach ($idNiveis as $idNivel) {
            $idNivel = intval($idNivel);
            $sqlVinculo = "INSERT INTO TB_EQUIPE_NIVEL_ANALISTA (idUsuario, idEquipe, idNivel) 
                           VALUES ($lastId, $idEquipe, $idNivel)";
            mysqli_query($conn, $sqlVinculo);
        }

        header("Location: usuarios.php?success=1");
        exit();
    } else {
        echo "Erro ao cadastrar usuário: " . mysqli_error($conn);
    }
} else {
    header("Location: usuarios.php?success=4");
    exit();
}
?>
