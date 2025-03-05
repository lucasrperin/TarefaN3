<?php
require '../Config/Database.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $descricao = $_POST['descricao'];
    $situacao = $_POST['situacao'];
    $atendente = $_POST['atendente'];
    $sistema = $_POST['sistema'];
    $status = $_POST['status'];
    $hora_ini = $_POST['hora_ini'];
    $hora_fim = $_POST['hora_fim'];
    $nota = $_POST['nota'];
    $idUsuario = $_SESSION['usuario_id'];
    

    // Verifica se a ficha foi marcada e se o número da ficha foi informado
    $chkFicha = isset($_POST['chkFicha']) ? 'S' : null;
    $numeroFicha = $chkFicha && !empty($_POST['numeroFicha']) ? $_POST['numeroFicha'] : null;

    // Verifica se o Replicar foi marcada e se a quantidade para replicar foi informada
    $chkMultiplica = isset($_POST['chkMultiplica']) ? 'S' : null;
    $numeroMulti = $chkMultiplica && !empty($_POST['numeroMulti']) ? $_POST['numeroMulti'] : null;

    // Verifica se o Cliente parado foi marcado
    $chkParado = isset($_POST['chkParado']) ? 'S' : null;

    if ($chkMultiplica && $numeroMulti) {
        $totalHora = "0000-00-00 00:00:00";
        $horaini_multi = (new DateTime())->format("Y-m-d H:i");
        $horafim_multi = (new DateTime())->format("Y-m-d H:i");

        $stmtAuxilio = $conn->prepare("INSERT INTO TB_ANALISES 
            (Descricao, idSituacao, idAtendente, idSistema, idStatus, idUsuario, Hora_ini, Hora_fim, Total_hora, Nota) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtAuxilio->bind_param("siiiiisssi", $descricao, $situacao, $idUsuario, $sistema, $status, $idUsuario, $horaini_multi, $horafim_multi, $totalHora, $nota);
        $stmtAuxilio->execute();
        
    } elseif ($chkParado || $chkFicha) {
        // Primeiro INSERT (sempre executado quando chkParado ou chkFicha estiver marcado)
        $stmtParado = $conn->prepare("INSERT INTO TB_ANALISES 
            (Descricao, idSituacao, idAtendente, idSistema, idStatus, idUsuario, Hora_ini, Hora_fim, Total_hora, chkFicha, numeroFicha, Nota) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, TIMEDIFF(?, ?), ?, ?, ?)");
        $stmtParado->bind_param("siiiiissssssi", $descricao, $situacao, $atendente, $sistema, $status, $idUsuario, $hora_ini, $hora_fim, $hora_fim, $hora_ini, $chkFicha, $numeroFicha, $nota);
        
        if ($stmtParado->execute()) {
            // Se a ficha foi marcada e o número foi informado, insere o segundo registro (Ficha)
            if ($chkFicha && $numeroFicha) {
                $descricaoFicha = "Ficha criada " . $numeroFicha;
                $situacaoFicha = 3; // Situação Ficha criada fixa
                $statusFicha = 2; // Status DESENVOLVIMENTO fixa
                $totalHora = "0000-00-00 00:00:00";
    
                $stmtFicha = $conn->prepare("INSERT INTO TB_ANALISES 
                    (Descricao, idSituacao, idAtendente, idSistema, idStatus, idUsuario, Hora_ini, Hora_fim, Total_hora, chkFicha, numeroFicha, chkParado) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtFicha->bind_param("siiiiissssss", $descricaoFicha, $situacaoFicha, $atendente, $sistema, $statusFicha, $idUsuario, $hora_ini, $hora_fim, $totalHora, $chkFicha, $numeroFicha, $chkParado);
                $stmtFicha->execute();
                $stmtFicha->close();
            }
        } else {
            echo "Erro ao cadastrar: " . $stmtParado->error;
        }
        $stmtParado->close();
    } else {
        // Primeiro INSERT (se nenhum dos dois estiver marcado, entra aqui)
        $stmt = $conn->prepare("INSERT INTO TB_ANALISES 
        (Descricao, idSituacao, idAtendente, idSistema, idStatus, idUsuario, Hora_ini, Hora_fim, Total_hora, chkFicha, numeroFicha, Nota) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, TIMEDIFF(?, ?), ?, ?, ?)");
        $stmt->bind_param("siiiiissssssi", $descricao, $situacao, $atendente, $sistema, $status, $idUsuario, $hora_ini, $hora_fim, $hora_fim, $hora_ini, $chkFicha, $numeroFicha, $nota);
        
        if ($stmt->execute()) {
            // Se a ficha foi marcada e o número foi informado, insere o segundo registro
            if ($chkFicha && $numeroFicha) {
                $descricaoFicha = "Ficha criada " . $numeroFicha;
                $situacaoFicha = 3; // Situação Ficha criada fixa
                $statusFicha = 2; // Status DESENVOLVIMENTO fixa
                $totalHora = "0000-00-00 00:00:00";
    
                $stmtFicha = $conn->prepare("INSERT INTO TB_ANALISES 
                    (Descricao, idSituacao, idAtendente, idSistema, idStatus, idUsuario, Hora_ini, Hora_fim, Total_hora, chkFicha, numeroFicha, Nota) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtFicha->bind_param("siiiiisssssi", $descricaoFicha, $situacaoFicha, $atendente, $sistema, $statusFicha, $idUsuario, $hora_ini, $hora_fim, $totalHora, $chkFicha, $numeroFicha, $nota);
                $stmtFicha->execute();
                $stmtFicha->close();
            }
        } else {
            echo "Erro ao cadastrar: " . $stmt->error;
        }
        $stmt->close();
    }

    }

    for ($i = 1; $i < $numeroMulti; $i++) {
        if (!$stmtAuxilio->execute()) {
            echo "Erro ao cadastrar: " . $stmtAuxilio->error;
            exit();
        }
    }
    $conn->close();
    header("Location: ../index.php?success=1");
    exit();
?>
