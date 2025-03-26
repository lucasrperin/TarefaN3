<?php
// editar_folga.php
include '../Config/Database.php';
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id            = $_POST['id']            ?? '';
    $usuario_id    = $_POST['usuario_id']    ?? '';
    $tipo          = $_POST['tipo']          ?? '';
    $data_inicio   = $_POST['data_inicio']   ?? '';
    $data_fim      = $_POST['data_fim']      ?? '';
    $justificativa = $_POST['justificativa'] ?? '';

    if (!empty($id) && !empty($data_inicio) && !empty($data_fim)) {
       $dtInicio = strtotime($data_inicio);
       $dtFim    = strtotime($data_fim);
       if ($dtInicio !== false && $dtFim !== false && $dtFim >= $dtInicio) {
           $diffSegundos    = $dtFim - $dtInicio;
           $quantidade_dias = floor($diffSegundos / 86400) + 1;
       } else {
           $quantidade_dias = 0;
       }

       if ($quantidade_dias >= 1) {
           $sql = "UPDATE TB_FOLGA
                      SET usuario_id = ?,
                          tipo = ?,
                          data_inicio = ?,
                          data_fim = ?,
                          quantidade_dias = ?,
                          justificativa = ?
                    WHERE id = ?";
           $stmt = $conn->prepare($sql);
           if ($stmt) {
               $stmt->bind_param("isssisi",
                 $usuario_id,
                 $tipo,
                 $data_inicio,
                 $data_fim,
                 $quantidade_dias,
                 $justificativa,
                 $id
               );
               $stmt->execute();
               $stmt->close();
           } else {
               echo "Erro na preparação da query: " . $conn->error;
           }
       }
    }
}
// Ao terminar, sempre volta para folga.php
header("Location: folga.php");
exit();
