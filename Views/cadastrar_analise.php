<?php
require '../Config/Database.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $descricao = $_POST['descricao'];
    $situacao = $_POST['situacao'];
    $analista = $_POST['analista'];
    $sistema = $_POST['sistema'];
    $status = $_POST['status'];
    $hora_ini = $_POST['hora_ini'];
    $hora_fim = $_POST['hora_fim'];
    $idUsuario = $_SESSION['usuario_id'];

    // Verifica se a ficha foi marcada e se o número da ficha foi informado
    $chkFicha = isset($_POST['chkFicha']) ? 1 : 0;
    $numeroFicha = $chkFicha && !empty($_POST['numeroFicha']) ? $_POST['numeroFicha'] : null;

    // Verifica se o Replicar foi marcada e se a quantidade para replicar foi informada
    $chkMultiplica = isset($_POST['chkMultiplica']) ? 1 : 0;
    $numeroMulti = $chkMultiplica && !empty($_POST['numeroMulti']) ? $_POST['numeroMulti'] : null;

    
    if ($chkMultiplica && $numeroMulti) {
        $totalHora = "0000-00-00 00:00:00";
        $horaini_multi = (new DateTime())->format("Y-m-d H:i:s");
        $horafim_multi = (new DateTime())->format("Y-m-d H:i:s");

        $stmtAuxilio = $conn->prepare("INSERT INTO TB_ANALISES 
            (Descricao, idSituacao, idAnalista, idSistema, idStatus, idUsuario, Hora_ini, Hora_fim, Total_hora) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtAuxilio->bind_param("siiiiisss", $descricao, $situacao, $analista, $sistema, $status, $idUsuario, $horaini_multi, $horafim_multi, $totalHora);
        $stmtAuxilio->execute();
        
    } else {
            // Primeiro INSERT (sempre executado)
        $stmt = $conn->prepare("INSERT INTO TB_ANALISES 
        (Descricao, idSituacao, idAnalista, idSistema, idStatus, idUsuario, Hora_ini, Hora_fim, Total_hora, chkFicha, numeroFicha) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, TIMEDIFF(?, ?), ?, ?)");
        $stmt->bind_param("siiiiissssis", $descricao, $situacao, $analista, $sistema, $status, $idUsuario, $hora_ini, $hora_fim, $hora_fim, $hora_ini, $chkFicha, $numeroFicha);

        if ($stmt->execute()) {
            // Se a ficha foi marcada e o número foi informado, insere o segundo registro
            if ($chkFicha && $numeroFicha) {
                $descricaoFicha = "Ficha criada " . $numeroFicha;
                $situacaoFicha = 3; // Situação Ficha criada fixa
                $statusFicha = 2; // Status DESENVOLVIMENTO fixa
                $totalHora = "0000-00-00 00:00:00";
    
                $stmtFicha = $conn->prepare("INSERT INTO TB_ANALISES 
                    (Descricao, idSituacao, idAnalista, idSistema, idStatus, idUsuario, Hora_ini, Hora_fim, Total_hora) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtFicha->bind_param("siiiiisss", $descricaoFicha, $situacaoFicha, $analista, $sistema, $statusFicha, $idUsuario, $hora_ini, $hora_fim, $totalHora);
                $stmtFicha->execute();
                $stmtFicha->close();
            }
        } else {
            echo "Erro ao cadastrar: " . $stmt->error;
        }
    
        $stmt->close();
    }
    }

    for ($i = 2; $i < $numeroMulti; $i++) {
        if (!$stmtAuxilio->execute()) {
            echo "Erro ao cadastrar: " . $stmtAuxilio->error;
            exit();
        }
    }
    $conn->close();
    header("Location: ../index.php?success=1");
    exit();
?>
