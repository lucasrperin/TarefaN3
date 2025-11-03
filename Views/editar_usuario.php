<?php
// editar_usuario.php
include '../Config/Database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id       = intval($_POST['id']);
    $nome     = mysqli_real_escape_string($conn, $_POST['Nome']);
    $email    = mysqli_real_escape_string($conn, $_POST['Email']);
    $cargo    = mysqli_real_escape_string($conn, $_POST['Cargo']);
    $senha    = isset($_POST['Senha']) ? mysqli_real_escape_string($conn, $_POST['Senha']) : '';
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
    $idEquipe = intval($_POST['idEquipe']);
    
    // Recebe os níveis como array (checklist)
    $idNiveis = isset($_POST['idNivel']) ? $_POST['idNivel'] : [];
    if (!is_array($idNiveis)) {
        $idNiveis = [$idNiveis];
    }
    
    // Verifica se o email já existe para outro usuário
    $sqlCheckEmail = "SELECT * FROM TB_USUARIO WHERE Email = '$email' AND Id != $id";
    $resultEmail = mysqli_query($conn, $sqlCheckEmail);
    if (mysqli_num_rows($resultEmail) > 0) {
         header("Location: usuarios.php?error=1");
         exit();
    }
    
    // Atualiza os dados do usuário; atualiza a senha somente se preenchida
    if (!empty($senha)) {
        $sqlUsuario = "UPDATE TB_USUARIO 
                       SET Nome = '$nome', Email = '$email', Senha = '$senha_hash', Cargo = '$cargo'
                       WHERE Id = $id";
    } else {
        $sqlUsuario = "UPDATE TB_USUARIO 
                       SET Nome = '$nome', Email = '$email', Cargo = '$cargo'
                       WHERE Id = $id";
    }
    
    if (mysqli_query($conn, $sqlUsuario)) {
        // Exclui os vínculos atuais do usuário na tabela TB_EQUIPE_NIVEL_ANALISTA
        $sqlDelete = "DELETE FROM TB_EQUIPE_NIVEL_ANALISTA WHERE idUsuario = $id";
        mysqli_query($conn, $sqlDelete);
        
        // Insere um novo vínculo para cada nível selecionado
        foreach ($idNiveis as $nivel) {
            $nivel = intval($nivel);
            $sqlVinculo = "INSERT INTO TB_EQUIPE_NIVEL_ANALISTA (idUsuario, idEquipe, idNivel)
                           VALUES ($id, $idEquipe, $nivel)";
            mysqli_query($conn, $sqlVinculo);
        }
        
        header("Location: usuarios.php?success=2");
        exit();
    } else {
        echo "Erro ao atualizar usuário: " . mysqli_error($conn);
    }
} else {
    header("Location: usuarios.php?success=4");
    exit();
}
?>
