<?php
// cadastrar_folga.php
include '../Config/Database.php'; // Inclui a conexão (variável $conn)
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Recebe os dados enviados pelo formulário
$usuario_id    = $_POST['usuario_id']    ?? '';
$tipo          = $_POST['tipo']          ?? '';
$data_inicio   = $_POST['data_inicio']   ?? '';
$data_fim      = $_POST['data_fim']      ?? '';
$justificativa = $_POST['justificativa'] ?? ''; // Novo campo para a justificativa

if (!empty($data_inicio) && !empty($data_fim)) {
    $dtInicio = strtotime($data_inicio);
    $dtFim    = strtotime($data_fim);
    if ($dtInicio !== false && $dtFim !== false && $dtFim >= $dtInicio) {
        // +1 para incluir o dia de início
        $diffSegundos    = $dtFim - $dtInicio;
        $quantidade_dias = floor($diffSegundos / 86400) + 1;
    } else {
        $quantidade_dias = 0;
    }

    if ($quantidade_dias >= 1) {
        $sql = "INSERT INTO TB_FOLGA (usuario_id, tipo, data_inicio, data_fim, quantidade_dias, justificativa)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            // 'i' para integer, 's' para string, 's' para string, 's' para string, 'i' para integer e 's' para string
            $stmt->bind_param("isssis", $usuario_id, $tipo, $data_inicio, $data_fim, $quantidade_dias, $justificativa);
            if ($stmt->execute()) {
                // Você pode redirecionar para evitar o reenvio do formulário, se desejar:
                header("Location: folga.php");
                exit();
            } else {
                echo "Erro ao cadastrar: " . $stmt->error;
            }
            $stmt->close();
        } else {
            echo "Erro na preparação da query: " . $conn->error;
        }
    } else {
        echo "Quantidade de dias inválida.";
    }
} else {
    echo "Preencha as datas.";
}
?>
