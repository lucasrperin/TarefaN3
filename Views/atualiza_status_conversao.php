<?php
include '../Config/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $id = $_POST['id'] ?? null;
  $status_id = $_POST['status_id'] ?? null;
  $data_conclusao = $_POST['data_conclusao'] ?? null;
  $data_inicio = $_POST['data_inicio'] ?? null;

  if ($id && $status_id) {
    $id = intval($id);
    $status_id = intval($status_id);
    
    $fields = "status_id = $status_id";
    
    // Processa data_conclusao
    if ($data_conclusao !== null) {
      if ($data_conclusao === "null") {
        $fields .= ", data_conclusao = NULL";
      } else {
        // Mantém somente data, hora e minutos (segundos = "00")
        $data_conclusao = substr($data_conclusao, 0, 16) . ":00";
        $data_conclusao = $conn->real_escape_string($data_conclusao);
        $fields .= ", data_conclusao = '$data_conclusao'";
      }
    }
    
    // Processa data_inicio
    if ($data_inicio !== null) {
      if ($data_inicio === "null") {
        $fields .= ", data_inicio = NULL";
      } else {
        $data_inicio = substr($data_inicio, 0, 16) . ":00";
        $data_inicio = $conn->real_escape_string($data_inicio);
        $fields .= ", data_inicio = '$data_inicio'";
      }
    }
    
    $sql = "UPDATE TB_CONVERSOES SET $fields WHERE id = $id";
    
    if ($conn->query($sql) === TRUE) {
      echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso.']);
    } else {
      echo json_encode(['success' => false, 'error' => $conn->error]);
    }
  } else {
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos.']);
  }
  exit;
}
?>
