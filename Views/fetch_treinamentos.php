<?php
include '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
  echo json_encode([]);
  exit();
}

$query = "SELECT * FROM TB_TREINAMENTOS";
$result = mysqli_query($conn, $query);

$eventos = [];
while ($row = mysqli_fetch_assoc($result)) {

    // Define cor pelo tipo (Treinamento, Instalação ou Ambos)
    $corFundo = '#3788d8'; 
    switch ($row['tipo']) {
      case 'INSTALACAO':
        $corFundo = '#f39c12'; // Exemplo
        break;
      case 'AMBOS':
        $corFundo = '#e74c3c';
        break;
      case 'TREINAMENTO':
      default:
        $corFundo = '#28a745';
        break;
    }

    // Define símbolo pelo status
    $simboloStatus = '';
    switch ($row['status']) {
      case 'PENDENTE':
        $simboloStatus = '⏳'; 
        break;
      case 'CANCELADO':
        $simboloStatus = '❌';
        break;
      case 'CONCLUIDO':
        $simboloStatus = '✅';
        break;
      default:
        $simboloStatus = ''; 
        break;
    }

    // Monta o título original
    $tituloOriginal = $row['cliente'] . " - " . $row['sistema'];

    // Junta o símbolo com o título
    // Ex: “⏳ João da Silva - Clipp 360”
    $titulo = $simboloStatus . ' ' . $tituloOriginal;

    // Monta o evento
    $eventos[] = [
        'id'    => $row['id'],
        'title' => $titulo,
        'start' => $row['data'] . ' ' . $row['hora'],
        
        // Cor associada ao tipo
        'color' => $corFundo,
        
        'extendedProps' => [
          'tipo'        => $row['tipo'],
          'status'      => $row['status'],
          'cnpjcpf'     => $row['cnpjcpf'],
          'cliente'     => $row['cliente'],
          'sistema'     => $row['sistema'],
          'consultor'   => $row['consultor'],
          'serial'      => $row['serial'],
          'observacoes' => $row['observacoes']
        ]
    ];
}

header('Content-Type: application/json');
echo json_encode($eventos);
