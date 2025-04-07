<?php
include '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
  echo json_encode([]);
  exit();
}

$query = "SELECT t.*, c.cnpjcpf, c.serial, c.cliente 
          FROM TB_TREINAMENTOS t
          JOIN TB_CLIENTES c ON t.cliente_id = c.id";
$result = mysqli_query($conn, $query);

$eventos = [];
while ($row = mysqli_fetch_assoc($result)) {

    // Define a cor conforme o tipo de agendamento
    $corFundo = '#3788d8';
    switch ($row['tipo']) {
      case 'INSTALACAO':
        $corFundo = '#f39c12';
        break;
      case 'AMBOS':
        $corFundo = '#e74c3c';
        break;
      case 'TREINAMENTO':
      default:
        $corFundo = '#28a745';
        break;
    }

    // Define o símbolo do status
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

    // Monta o título utilizando o nome do cliente (vindo da tabela TB_CLIENTES)
    $tituloOriginal = $row['cliente'] . " - " . $row['sistema'];
    $titulo = $simboloStatus . ' ' . $tituloOriginal;

    $eventos[] = [
        'id'    => $row['id'],
        'title' => $titulo,
        'start' => $row['data'] . ' ' . $row['hora'],
        'color' => $corFundo,
        'extendedProps' => [
            'duracao'     => $row['duracao'],
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
