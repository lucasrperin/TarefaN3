<?php
include '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
  echo json_encode([]);
  exit();
}

// Consulta
$query = "SELECT * FROM TB_TREINAMENTOS";
$result = mysqli_query($conn, $query);

$eventos = [];
while ($row = mysqli_fetch_assoc($result)) {
    $titulo = $row['cliente'] . " - " . $row['sistema'];

    $eventos[] = [
        'id'    => $row['id'],
        'title' => $titulo,
        'start' => $row['data'] . ' ' . $row['hora'],
        'extendedProps' => [
          'cliente'    => $row['cliente'],
          'sistema'    => $row['sistema'],
          'consultor'  => $row['consultor'],
          'status'     => $row['status'],
          'observacoes'=> $row['observacoes']
        ]
    ];
}

header('Content-Type: application/json');
echo json_encode($eventos);
