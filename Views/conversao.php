<?php
// força o fuso-horário de São Paulo (UTC−3)
date_default_timezone_set('America/Sao_Paulo');

require '../Config/Database.php';

require_once __DIR__ . '/../Includes/auth.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definir o cargo do usuário (supondo que ele esteja armazenado na sessão, com a chave "Cargo")
$usuario_id = $_SESSION['usuario_id'];
$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';

// Para preencher os selects do filtro, buscamos os dados dos usuários e demais categorias

/****************************************************************
 * 1) Capturar Filtros (GET)
 ****************************************************************/
// Se não houver valores via GET, utiliza a data atual (formato YYYY-MM-DD)
// Recebe os filtros via GET ou define o valor padrão como a data atual
// Se os parâmetros não forem enviados via GET, redireciona para definir as datas com o dia atual
if (isset($_GET['clear']) && $_GET['clear'] == 1) {
  $data_inicial = '';
  $data_final   = '';
} else {
  // Se não houver valores via GET, define o período para o mês atual
  if (!isset($_GET['data_inicial']) || !isset($_GET['data_final'])) {
    $firstDay = date("Y-m-01");
    $lastDay  = date("Y-m-t");
    header("Location: conversao.php?data_inicial={$firstDay}&data_final={$lastDay}&filterColumn=period&period_recebido=1");
    exit();
  }
  $data_inicial = $_GET['data_inicial'];
  $data_final   = $_GET['data_final'];
}

// Caso o usuário clique em reset, redefina os filtros para o dia atual
// Limpa o filtro quando pressionado o botão
if (isset($_GET['reset']) && $_GET['reset'] == 1) {
    $data_inicial = date("Y-m-d");
    $data_final   = date("Y-m-d");
    $analistaID  = 0;
} else {
    $analistaID  = isset($_GET['analista_id']) ? intval($_GET['analista_id']) : 0;
}
$analistaID  = isset($_GET['analista_id'])  ? intval($_GET['analista_id']) : 0;

/****************************************************************
 * 2) Montar WHERE Dinâmico
 ****************************************************************/
$where = " WHERE 1=1 ";
// Verifica se o filtro de período foi selecionado e se as datas foram enviadas
if (isset($_GET['filterColumn']) && $_GET['filterColumn'] === 'period' && !empty($_GET['data_inicial']) && !empty($_GET['data_final'])) {
  $data_inicial = $_GET['data_inicial'];
  $data_final   = $_GET['data_final'];

  // Verifica se os checkboxes de Data Recebido e Data Conclusão foram marcados
  $filterByRecebido  = isset($_GET['period_recebido']);
  $filterByConclusao = isset($_GET['period_conclusao']);

  if ($filterByRecebido && $filterByConclusao) {
      // Se ambos os checkboxes estiverem marcados, filtra registros onde OU a data_recebido OU a data_conclusao estejam dentro do intervalo
      $where .= " AND (
                     c.data_recebido BETWEEN '{$data_inicial} 00:00:00' AND '{$data_final} 23:59:59'
                     OR
                     c.data_conclusao BETWEEN '{$data_inicial} 00:00:00' AND '{$data_final} 23:59:59'
                  ) ";
  } elseif ($filterByRecebido) {
      // Se somente o checkbox de Data Recebido estiver marcado
      $where .= " AND c.data_recebido BETWEEN '{$data_inicial} 00:00:00' AND '{$data_final} 23:59:59' ";
  } elseif ($filterByConclusao) {
      // Se somente o checkbox de Data Conclusão estiver marcado
      $where .= " AND c.data_conclusao BETWEEN '{$data_inicial} 00:00:00' AND '{$data_final} 23:59:59' ";
  }
  // Se nenhum checkbox for marcado, não adiciona nenhuma condição sobre as datas
}
if ($analistaID > 0) {
  $where .= " AND c.analista_id = {$analistaID} ";
}
// Filtro por sistema
if (!empty($_GET['sistema'])) {
  $where .= " AND c.sistema_id = '" . $_GET['sistema'] . "'";
}
// Filtro por status
if (!empty($_GET['status'])) {
  $where .= " AND c.status_id = '" . $_GET['status'] . "'";
}
// Filtro por metas
if (!empty($_GET['metas'])) {
  switch ($_GET['metas']) {
      case 'dentro':
          // Dentro do prazo: não concluído nem cancelado e que ainda esteja dentro do prazo
          $where .= " AND c.status_id NOT IN (1, 5) 
                      AND NOW() < CASE 
                                     WHEN TIME(c.data_recebido) < '15:00:00' THEN CONCAT(DATE(c.data_recebido), ' 15:00:00')
                                     ELSE CONCAT(DATE(c.data_recebido + INTERVAL 1 DAY), ' 15:00:00')
                                   END ";
          break;
      case 'atrasadas':
          // Atrasadas: status em fila, análise ou dar prioridade e que já passaram do prazo
          $where .= " AND c.status_id IN (3, 4, 6)
                      AND (
                            (TIME(c.data_recebido) < '15:00:00' AND NOW() >= CONCAT(DATE(c.data_recebido), ' 15:00:00'))
                            OR
                            (TIME(c.data_recebido) >= '15:00:00' AND NOW() >= CONCAT(DATE(c.data_recebido + INTERVAL 1 DAY), ' 15:00:00'))
                      ) ";
          break;
      case 'nao_batida':
          // Meta não batida: concluído com data de conclusão diferente do prazo
          $where .= " AND c.status_id = 1
                      AND (
                            (TIME(c.data_recebido) < '15:00:00' AND DATE(c.data_conclusao) <> DATE(c.data_recebido))
                            OR
                            (TIME(c.data_recebido) >= '15:00:00' AND c.data_conclusao >= CONCAT(DATE(c.data_recebido + INTERVAL 1 DAY), ' 15:00:00'))
                      ) ";
          break;
      case 'batida':
          // Batida (No prazo): concluído com data de conclusão conforme o prazo
          $where .= " AND c.status_id = 1
                      AND (
                            (TIME(c.data_recebido) < '15:00:00' AND DATE(c.data_conclusao) = DATE(c.data_recebido))
                            OR
                            (TIME(c.data_recebido) >= '15:00:00' AND c.data_conclusao < CONCAT(DATE(c.data_recebido + INTERVAL 1 DAY), ' 15:00:00'))
                      ) ";
          break;
  }
}

/****************************************************************
 * 3) Dados do Gráfico (Mês x Analista)
 ****************************************************************/
$sqlGrafico = "
    SELECT 
        YEAR(c.data_recebido) AS ano,
        MONTH(c.data_recebido) AS mes,
        a.nome                AS analista_nome,
        COUNT(*)             AS total
      FROM TB_CONVERSOES c
      JOIN TB_ANALISTA_CONVER a ON c.analista_id = a.id
      $where
      AND c.status_id <> 5
      GROUP BY YEAR(c.data_recebido), MONTH(c.data_recebido), c.analista_id
      ORDER BY ano, mes, analista_nome
";
$resGraf = $conn->query($sqlGrafico);

$dataPorMesAnalista = [];
$analistasDistinct  = [];
while ($rowG = $resGraf->fetch_assoc()) {
    $ano  = $rowG['ano'];
    $mes  = $rowG['mes'];
    $anal = $rowG['analista_nome'];
    $tot  = $rowG['total'];

    $rotuloMes = sprintf("%04d-%02d", $ano, $mes);
    if (!isset($dataPorMesAnalista[$rotuloMes])) {
        $dataPorMesAnalista[$rotuloMes] = [];
    }
    $dataPorMesAnalista[$rotuloMes][$anal] = $tot;
    $analistasDistinct[$anal] = true;
}
$labelsMes = array_keys($dataPorMesAnalista);
sort($labelsMes);
$listaAnalistas = array_keys($analistasDistinct);
sort($listaAnalistas);

// Montar datasets para Chart.js
$chartDatasets = [];
$cores = ["#d9534f","#5bc0de","#5cb85c","#f0ad4e","#0275d8","#292b2c","#7f7f7f"];
$corIndex = 0;
foreach ($listaAnalistas as $anal) {
    $dataVals = [];
    foreach ($labelsMes as $m) {
        $val = isset($dataPorMesAnalista[$m][$anal]) ? $dataPorMesAnalista[$m][$anal] : 0;
        $dataVals[] = $val;
    }
    $chartDatasets[] = [
        'label' => $anal,
        'backgroundColor' => $cores[$corIndex % count($cores)],
        'data' => $dataVals
    ];
    $corIndex++;
}

/****************************************************************
 * 4) TOTALIZADORES GERAIS (Quantidade, Tempo Médio, Tempo Conversão)
 ****************************************************************/
$sqlQtd = "
    SELECT COUNT(*)
      FROM TB_CONVERSOES c
      $where
      AND c.status_id <> 5
";
$total_conversoes = $conn->query($sqlQtd)->fetch_row()[0] ?? 0;

$sqlTempoRet = "
    SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(tempo_total)))
      FROM TB_CONVERSOES c
      $where
      AND status_id = 1
";
$tempo_medio_ret = $conn->query($sqlTempoRet)->fetch_row()[0] ?? 'N/A';
$tempo_medio_ret = substr($tempo_medio_ret, 0, 8);

$sqlTempoConv = "
    SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(tempo_conver)))
      FROM TB_CONVERSOES c
      $where
      AND status_id = 1
";
$tempo_medio_conv = $conn->query($sqlTempoConv)->fetch_row()[0] ?? 'N/A';
$tempo_medio_conv = substr($tempo_medio_conv, 0, 8);

$sqlTotalConcluidas = "
    SELECT COUNT(*) 
      FROM TB_CONVERSOES c
      JOIN TB_STATUS_CONVER st ON c.status_id = st.id
      $where
      AND st.descricao = 'Concluido'
";
$totalConcluidas = $conn->query($sqlTotalConcluidas)->fetch_row()[0] ?? 0;

$sqlStatusTot = "
    SELECT st.descricao AS status_nome,
           COUNT(*)     AS total
      FROM TB_CONVERSOES c
      JOIN TB_STATUS_CONVER st ON c.status_id = st.id
      $where
      GROUP BY c.status_id
      ORDER BY st.descricao
";
$resStatusTot = $conn->query($sqlStatusTot);

$sqlSistemaTot = "
    SELECT TRIM(SUBSTRING_INDEX(s.nome, '/', -1)) AS sistema_exibicao,
           COUNT(*) AS total
      FROM TB_CONVERSOES c
      JOIN TB_SISTEMA_CONVER s ON c.sistema_id = s.id
      $where
      AND c.status_id <> 5
      GROUP BY TRIM(SUBSTRING_INDEX(s.nome, '/', -1))
      ORDER BY sistema_exibicao
";
$resSistemaTot = $conn->query($sqlSistemaTot);

$sqlDentroPrazo = "
    SELECT COUNT(*) 
      FROM TB_CONVERSOES c
      JOIN TB_STATUS_CONVER st ON c.status_id = st.id
      $where
      AND st.descricao NOT IN ('Concluido','Cancelada')
      AND NOW() < 
          CASE 
            WHEN TIME(c.data_recebido) < '15:00:00'
              THEN CONCAT(DATE(c.data_recebido), ' 15:00:00')
            ELSE CONCAT(DATE(c.data_recebido + INTERVAL 1 DAY), ' 15:00:00')
          END
";
$countDentroPrazo = $conn->query($sqlDentroPrazo)->fetch_row()[0] ?? 0;

$sqlAtrasadas = "
    SELECT COUNT(*) 
      FROM TB_CONVERSOES c
      JOIN TB_STATUS_CONVER st ON c.status_id = st.id
      $where
      AND st.descricao IN ('Em fila','Analise','Dar prioridade')
      AND (
           (TIME(c.data_recebido) < '15:00:00' AND NOW() >= CONCAT(DATE(c.data_recebido), ' 15:00:00'))
           OR
           (TIME(c.data_recebido) >= '15:00:00' AND NOW() >= CONCAT(DATE(c.data_recebido + INTERVAL 1 DAY), ' 15:00:00'))
      )
";
$countAtrasadas = $conn->query($sqlAtrasadas)->fetch_row()[0] ?? 0;

$sqlMetaNaoBatida = "
     SELECT COUNT(*) 
      FROM TB_CONVERSOES c
      JOIN TB_STATUS_CONVER st ON c.status_id = st.id
      $where
      AND st.descricao = 'Concluido'
      AND (
            (TIME(c.data_recebido) < '15:00:00' AND DATE(c.data_conclusao) <> DATE(c.data_recebido))
            OR
            (TIME(c.data_recebido) >= '15:00:00' AND c.data_conclusao >= CONCAT(DATE(c.data_recebido + INTERVAL 1 DAY), ' 15:00:00'))
      )
";
$countMetaNaoBatida = $conn->query($sqlMetaNaoBatida)->fetch_row()[0] ?? 0;

$sqlMetaBatida = "
    SELECT COUNT(*) 
      FROM TB_CONVERSOES c
      JOIN TB_STATUS_CONVER st ON c.status_id = st.id
      $where
      AND st.descricao = 'Concluido'
      AND (
           (TIME(c.data_recebido) < '15:00:00' 
             AND DATE(c.data_conclusao) = DATE(c.data_recebido))
           OR
           (TIME(c.data_recebido) >= '15:00:00' 
             AND c.data_conclusao < CONCAT(DATE(c.data_recebido + INTERVAL 1 DAY), ' 15:00:00'))
      )
";
$countMetaBatida = $conn->query($sqlMetaBatida)->fetch_row()[0] ?? 0;

$percentAtendimento = $totalConcluidas > 0 ? ($countMetaBatida / $totalConcluidas) * 100 : 0;
$meta = round($percentAtendimento, 2);
if ($meta < 90) {
    $metaColor = "#FF746C";
} elseif ($meta >= 90 && $meta <= 94) {
    $metaColor = "#FFDB58";
} else {
    $metaColor = "#00674F";
}

/****************************************************************
 * 7) Consultas para listagens de conversões
 ****************************************************************/
// Consulta para conversões com status "Em fila"
$sqlFila = "SELECT 
                  c.id,
                  c.contato,
                  c.serial,
                  c.sistema_id,
                  s.nome       AS sistema_nome,
                  c.status_id,
                  st.descricao AS status_nome,
                  c.prazo_entrega,
                  c.data_recebido,
                  c.data_inicio,
                  c.data_conclusao,
                  DATE_FORMAT(c.prazo_entrega, '%d/%m %H:%i:%s') as prazo_entrega2,
                  DATE_FORMAT(c.data_recebido, '%d/%m %H:%i:%s') as data_recebido2,
                  DATE_FORMAT(c.data_inicio, '%d/%m %H:%i:%s') as data_inicio2,
                  DATE_FORMAT(c.data_conclusao, '%d/%m %H:%i:%s') as data_conclusao2,
                  c.analista_id,
                  a.nome       AS analista_nome,
                  c.retrabalho,
                  c.observacao
              FROM TB_CONVERSOES c
              JOIN TB_SISTEMA_CONVER s ON c.sistema_id  = s.id
              JOIN TB_STATUS_CONVER st ON c.status_id   = st.id
              JOIN TB_ANALISTA_CONVER a ON c.analista_id = a.id
              $where
                AND st.descricao = 'Em fila'
            ORDER BY c.data_recebido ASC";
$resFila = $conn->query($sqlFila);

// Consulta para conversões com status diferente de "Em fila", "Concluido" e "Cancelada"
$sqlOutros = "SELECT 
                  c.id,
                  c.contato,
                  c.serial,
                  c.sistema_id,
                  s.nome       AS sistema_nome,
                  c.status_id,
                  st.descricao AS status_nome,
                  c.prazo_entrega,
                  c.data_recebido,
                  c.data_inicio,
                  c.data_conclusao,
                  DATE_FORMAT(c.prazo_entrega, '%d/%m %H:%i:%s') as prazo_entrega2,
                  DATE_FORMAT(c.data_recebido, '%d/%m %H:%i:%s') as data_recebido2,
                  DATE_FORMAT(c.data_inicio, '%d/%m %H:%i:%s') as data_inicio2,
                  DATE_FORMAT(c.data_conclusao, '%d/%m %H:%i:%s') as data_conclusao2,
                  c.analista_id,
                  a.nome       AS analista_nome,
                  c.retrabalho,
                  c.observacao
                FROM TB_CONVERSOES c
                JOIN TB_SISTEMA_CONVER s ON c.sistema_id = s.id
                JOIN TB_STATUS_CONVER st ON c.status_id  = st.id
                JOIN TB_ANALISTA_CONVER a ON c.analista_id = a.id
                $where
                  AND st.descricao NOT IN ('Em fila','Concluido','Cancelada')
              ORDER BY c.data_recebido ASC";
$resOutros = $conn->query($sqlOutros);

// Consulta para conversões finalizadas (Concluido ou Cancelada)
$sqlFinalizados = "SELECT 
                      c.id,
                      c.contato,
                      c.serial,
                      c.sistema_id,
                      s.nome       AS sistema_nome,
                      c.status_id,
                      st.descricao AS status_nome,
                      c.prazo_entrega,
                      c.data_recebido,
                      c.data_inicio,
                      c.data_conclusao,
                      DATE_FORMAT(c.prazo_entrega, '%d/%m %H:%i:%s') as prazo_entrega2,
                      DATE_FORMAT(c.data_recebido, '%d/%m %H:%i:%s') as data_recebido2,
                      DATE_FORMAT(c.data_inicio, '%d/%m %H:%i:%s') as data_inicio2,
                      DATE_FORMAT(c.data_conclusao, '%d/%m %H:%i:%s') as data_conclusao2,
                      c.analista_id,
                      a.nome       AS analista_nome,
                      c.retrabalho,
                      c.observacao
                    FROM TB_CONVERSOES c
                    JOIN TB_SISTEMA_CONVER s ON c.sistema_id = s.id
                    JOIN TB_STATUS_CONVER st ON c.status_id  = st.id
                    JOIN TB_ANALISTA_CONVER a ON c.analista_id = a.id
                    $where
                      AND st.descricao IN ('Concluido','Cancelada')
                  ORDER BY c.data_conclusao DESC";
$resFinalizados = $conn->query($sqlFinalizados);

/****************************************************************
 * 8) Carregar listas para os selects
 ****************************************************************/
$sistemas  = $conn->query("SELECT * FROM TB_SISTEMA_CONVER ORDER BY nome");
$status    = $conn->query("SELECT * FROM TB_STATUS_CONVER ORDER BY descricao");
$analistas = $conn->query("SELECT * FROM TB_ANALISTA_CONVER ana INNER JOIN TB_USUARIO u on u.id = ana.id WHERE u.cargo = 'Conversor' ORDER BY ana.nome");
$analistasFiltro = $conn->query("SELECT * FROM TB_ANALISTA_CONVER ana INNER JOIN TB_USUARIO u on u.id = ana.id WHERE u.cargo = 'Conversor' ORDER BY ana.nome");

// Para preencher os selects do filtro, buscamos os dados dos usuários e demais categorias
$lista_sistemas   = $conn->query("SELECT * FROM TB_SISTEMA_CONVER ORDER BY nome");
$lista_status     = $conn->query("SELECT * FROM TB_STATUS_CONVER ORDER BY descricao");
$resultado_usuarios_dropdown = $conn->query("SELECT * FROM TB_ANALISTA_CONVER ana INNER JOIN TB_USUARIO u on u.id = ana.id WHERE u.cargo = 'Conversor' ORDER BY ana.nome");

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Gerenciar Conversões</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../Public/conversao.css">
  <link rel="icon" href="../Public/Image/LogoTituto.png" type="image/png">
</head>
<body class="bg-light">
  <div class="d-flex-wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
      <a class="light-logo" href="conversao.php">
        <img src="../Public/Image/zucchetti_blue.png" width="150" alt="Logo Zucchetti">
      </a>
      <nav class="nav flex-column">
        <a class="nav-link" href="menu.php"><i class="fa-solid fa-house me-2"></i>Home</a>
        <?php if ($cargo === 'Admin' || $cargo === 'Conversor'): ?>
          <a class="nav-link active" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="destaque.php"><i class="fa-solid fa-ranking-star me-2"></i>Destaques</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="escutas.php"><i class="fa-solid fa-headphones me-2"></i>Escutas</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'User' || $cargo === 'Conversor'): ?>
          <a class="nav-link" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin' || $cargo === 'Viewer' || $cargo === 'User' || $cargo === 'Conversor'): ?>
          <a class="nav-link" href="user.php"><i class="fa-solid fa-users-rectangle me-2"></i>Meu Painel</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a>
        <?php endif; ?>
        <?php if ($cargo != 'Comercial'): ?>
          <a class="nav-link" href="okr.php"><img src="../Public/Image/benchmarkbranco.png" width="27" height="27" class="me-1" alt="Benchmark">OKRs</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'Treinamento'): ?>
          <a class="nav-link" href="treinamento.php"><i class="fa-solid fa-calendar-check me-2"></i>Treinamentos</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a>
        <?php endif; ?>
      </nav>
    </div>
    
    <!-- Área Principal -->
    <div class="w-100">
      <!-- Header -->
      <div class="header">
        <h3>Gerenciar Conversões</h3>
        <div class="user-info">
          <span>Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
          <a href="logout.php" class="btn btn-danger">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
          </a>
        </div>
      </div>
      
      <div class="container mt-4">
        <div class="accordion" id="accordionConversao">
          <!-- Accordion Item 1: Gráfico e Filtro Global -->
          <div class="accordion-item mb-3">
            <h2 class="accordion-header" id="headingGrafico">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGrafico" aria-expanded="false" aria-controls="collapseGrafico">
                <i class="fa-solid fa-chart-simple me-2"></i>Gráfico de Conversões
              </button>
            </h2>
            <div id="collapseGrafico" class="accordion-collapse collapse" aria-labelledby="headingGrafico" data-bs-parent="#accordionConversao">
              <div class="accordion-body">
                <div class="row">
                  <div class="col-md-12">
                    <div class="card"> <!-- Altura definida ou em % conforme sua necessidade -->
                      <div class="card-body">
                        <h5 class="card-title">Conversões Mensais por Analista</h5>
                        <div class="canvas-container">
                          <canvas id="chartBarras" style="height: 4rem"></canvas>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Accordion Item 2: Totalizadores de Conversões -->
          <div class="accordion-item mb-3">
            <h2 class="accordion-header" id="headingTotalizadoresLayout2">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTotalizadoresLayout2" aria-expanded="true" aria-controls="collapseTotalizadoresLayout2">
                <i class="fa-solid fa-chart-bar me-2"></i> Totalizadores de Conversões
              </button>
            </h2>
            <div id="collapseTotalizadoresLayout2" class="accordion-collapse collapse" aria-labelledby="headingTotalizadoresLayout2" data-bs-parent="#accordionConversao">
              <div class="accordion-body layout2-accordion-body">
                <div class="row g-3">
                  <div class="col-md-4">
                    <div class="card layout2-card text-center">
                      <div class="card-body">
                        <div class="icon-circle bg-warning mb-2">
                          <i class="fa-solid fa-hourglass-half fa-lg text-white"></i>
                        </div>
                        <h6 class="card-subtitle mb-2">Pendentes</h6>
                        <div class="list-group">
                          <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-clock me-1"></i> Dentro do prazo</span>
                            <span class="badge rounded-pill bg-info"><?= $countDentroPrazo; ?></span>
                          </div>
                          <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-arrow-down me-1 ms-1"></i> Atrasadas</span>
                            <span class="badge rounded-pill bg-warning"><?= $countAtrasadas; ?></span>
                          </div>
                          <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-exclamation-triangle me-1"></i> Meta não batida</span>
                            <span class="badge rounded-pill bg-danger"><?= $countMetaNaoBatida; ?></span>
                          </div>
                          <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-check me-1 ms-1"></i>Entregues no prazo</span>
                            <span class="badge rounded-pill bg-success"><?= $countMetaBatida; ?></span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="card layout2-card text-center">
                      <div class="card-body">
                        <div class="icon-circle bg-primary mb-2">
                          <i class="fa-solid fa-signal fa-lg text-white"></i>
                        </div>
                        <h6 class="card-subtitle mb-2">Por Status</h6>
                        <div class="list-group">
                          <?php while($rowSt = $resStatusTot->fetch_assoc()): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                              <span><i class="fa-solid fa-circle me-1" style="color: #0d6efd;"></i> <?= $rowSt['status_nome']; ?></span>
                              <span class="badge rounded-pill bg-primary"><?= $rowSt['total']; ?></span>
                            </div>
                          <?php endwhile; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="card layout2-card text-center">
                      <div class="card-body">
                        <div class="icon-circle bg-secondary mb-2">
                          <i class="fa-solid fa-desktop fa-lg text-white"></i>
                        </div>
                        <h6 class="card-subtitle mb-2">Por Sistema</h6>
                        <div class="list-group">
                          <?php while($rowSys = $resSistemaTot->fetch_assoc()): ?>
                            <?php
                              $systemName = $rowSys['sistema_exibicao'];
                              $imageMap = [
                                "ClippPro"   => "ClippPro.png",
                                "ClippMEI"   => "ClippMei.png",
                                "ClippFacil" => "ClippFacil.png",
                                "Clipp360"   => "Clipp360.png",
                                "ZetaWeb"    => "ZWeb.png",
                              ];
                              if (isset($imageMap[$systemName])) {
                                $imageFilename = $imageMap[$systemName];
                              } else {
                                $imageFilename = strtolower(str_replace(" ", "", $systemName)) . ".png";
                              }
                              $imagePath = "../Public/Image/" . $imageFilename;
                            ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                              <span>
                                <img src="<?= $imagePath ?>" alt="<?= $systemName ?>" style="width: 30px; height: auto;" class="me-2">
                                <?= $systemName; ?>
                              </span>
                              <span class="badge rounded-pill bg-secondary"><?= $rowSys['total']; ?></span>
                            </div>
                          <?php endwhile; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </div><!-- Fim da row g-3 -->
              </div><!-- Fim accordion-body -->
            </div>
          </div>
          <!-- Accordion Item: Resumo Geral -->
          <div class="accordion-item mb-3">
            <h2 class="accordion-header" id="headingResumoGeralLayout5">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseResumoGeralLayout5" aria-expanded="true" aria-controls="collapseResumoGeralLayout5">
              <i class="fa-solid fa-chart-line me-2"></i> Resumo Geral
              </button>
            </h2>
            <div id="collapseResumoGeralLayout5" class="accordion-collapse collapse show" aria-labelledby="headingResumoGeralLayout5" data-bs-parent="#accordionConversao">
              <div class="accordion-body layout5-accordion-body">
                <div class="row justify-content-center">
                  <div class="col-lg-12">
                    <div class="row">
                      <div class="col-md-4 mb-4">
                        <div class="card custom-card text-center h-100 border-0">
                          <div class="card-body p-4 d-flex flex-column justify-content-center">
                            <p class="text-muted mb-1" style="font-size: 0.9rem;">Total de Conversões</p>
                            <h3 class="mb-3"><?= $total_conversoes; ?></h3>
                            <div class="icon-wrapper">
                              <i class="fa-solid fa-layer-group fa-2x" style="color: #1e90ff;"></i>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="col-md-4 mb-4">
                        <div class="card custom-card text-center h-100 border-0">
                          <div class="card-body p-4 d-flex flex-column justify-content-center">
                            <p class="text-muted mb-1" style="font-size: 0.9rem;">Atingimento da Meta</p>
                            <h3 class="mb-3"><?= $meta; ?>%</h3>
                            <div class="icon-wrapper">
                              <i class="fa-solid fa-percent fa-2x" style="color: <?= $metaColor; ?>;"></i>
                            </div>
                            <div class="progress mt-3" style="height: 5px;">
                              <div class="progress-bar" role="progressbar" style="width: <?= $meta; ?>%; background-color: <?= $metaColor; ?>;" aria-valuenow="<?= $meta; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <small class="d-block mt-2" style="color: #6c757d;">🟩 >95% | 🟨 90-94% | 🟥 &lt;90%</small>
                          </div>
                        </div>
                      </div>
                      <div class="col-md-4 mb-4">
                        <div class="card custom-card text-center h-100 border-0">
                          <div class="card-body p-4 d-flex flex-column justify-content-center">
                            <div class="mb-2">
                              <i class="fa-solid fa-hourglass-half fa-2x" style="color: #17a2b8;"></i>
                            </div>
                            <h6 class="mb-3">Tempo Médio</h6>
                            <div class="row">
                              <div class="col-6 border-end">
                                <h6 class="small mb-1">Entrega</h6>
                                <p class="mb-0"><?= $tempo_medio_ret; ?></p>
                              </div>
                              <div class="col-6">
                                <h6 class="small mb-1">Conversão</h6>
                                <p class="mb-0"><?= $tempo_medio_conv; ?></p>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div><!-- Fim da row interna -->
                  </div><!-- Fim col-lg-12 -->
                </div><!-- Fim row justify-content-center -->
              </div><!-- Fim accordion-body -->
            </div><!-- Fim accordion-collapse -->
          </div>
        </div><!-- Fim Accordion -->
        
        <!-- Botão e campo de pesquisa -->
        <div class="d-flex justify-content-end mb-3 gap-2">
          <!-- Botão para abrir o modal de filtro -->
          <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
            <i class="fa-solid fa-filter"></i>
          </button>
          <input type="text" id="searchInput" class="form-control ms-2" style="max-width: 200px;" placeholder="Pesquisar...">
          <?php if ($cargo === 'Admin' || $cargo === 'Conversor'): ?>
            <button class="btn btn-primary" onclick="abrirModalCadastro()">Cadastrar</button>
          <?php endif; ?>
        </div>
        
        
         <!-- Área de listagem utilizando o novo layout Kanban -->
     <!-- Área de listagem utilizando o novo layout Kanban -->
     <div class="kanban-board">
    <!-- Coluna: Em Fila -->
<div class="kanban-column">
  <h3>Em Fila</h3>
  <?php while ($rowF = $resFila->fetch_assoc()): ?>
    <?php
      // Valor original do sistema, ex: "AC p/ Clipp360" ou "Clipp p/ AC"
      $systemNameOriginal = $rowF['sistema_nome'];
      if (strpos($systemNameOriginal, 'p/') !== false) {
          $parts = explode('p/', $systemNameOriginal);
          $originSystem = trim($parts[0]);
          $destinationSystem = trim($parts[1]);
      } else {
          $originSystem = $systemNameOriginal;
          $destinationSystem = '';
      }
      
      // Se o sistema aparecer como "Clipp", transforma-o em "ClippPro"
      if (strcasecmp($originSystem, "Clipp") === 0) {
          $originSystem = "ClippPro";
      }
      if ($destinationSystem !== '' && strcasecmp($destinationSystem, "Clipp") === 0) {
          $destinationSystem = "ClippPro";
      }
      
      // Mapeamento dos sistemas para os arquivos de imagem
      $imageMap = [
          "AC"           => "AC.png",
          "ClippFacil"   => "ClippFacil.png",
          "Clipp360"     => "Clipp360.png",
          "ClippPro"     => "ClippPro.png",
          "ClippMei"     => "ClippMei.png",
          "Small"        => "Small.png",
          "Gdoor"        => "Gdoor.png",
          "Conc"         => "Concorrente.png",
          "ZetaWeb"      => "ZWeb.png"
      ];
      
      // Determina o arquivo da imagem para o sistema de origem
      if (isset($imageMap[$originSystem])) {
          $originImage = $imageMap[$originSystem];
      } else {
          $originImage = strtolower(str_replace(" ", "", $originSystem)) . ".png";
      }
      
      // Determina o arquivo da imagem para o sistema de destino, se existir
      if ($destinationSystem !== '') {
          if (isset($imageMap[$destinationSystem])) {
              $destinationImage = $imageMap[$destinationSystem];
          } else {
              $destinationImage = strtolower(str_replace(" ", "", $destinationSystem)) . ".png";
          }
      }
      
      // Define os caminhos absolutos para as imagens
      $originPath = '../Public/Image/' . $originImage;
      if ($destinationSystem !== '') {
          $destinationPath = '../Public/Image/' . $destinationImage;
      }
    ?>
    <div class="kanban-card"
         data-id="<?= $rowF['id']; ?>"
         data-status_id="<?= $rowF['status_id']; ?>"
         data-contato="<?= htmlspecialchars($rowF['contato']); ?>"
         data-serial="<?= htmlspecialchars($rowF['serial']); ?>"
         data-retrabalho="<?= htmlspecialchars($rowF['retrabalho']); ?>"
         data-sistema_id="<?= $rowF['sistema_id']; ?>"
         data-data_recebido="<?= $rowF['data_recebido']; ?>"
         data-prazo_entrega="<?= $rowF['prazo_entrega']; ?>"
         data-data_inicio="<?= $rowF['data_inicio']; ?>"
         data-data_conclusao="<?= $rowF['data_conclusao']; ?>"
         data-analista_id="<?= $rowF['analista_id']; ?>"
         data-observacao="<?= htmlspecialchars($rowF['observacao']); ?>">
      <div class="card-header p-2">
        <div class="icon p-3">
          <i class="fa-solid fa-inbox"></i>
        </div>
        <div class="card-title"><?= htmlspecialchars($rowF['contato']); ?></div>
      </div>
      <div class="card-details">
        <!-- Linha do Sistema -->
        <div class="row-item">
          <div class="label-col">
            <i class="fa-solid fa-desktop me-0"></i>
            <b>Sistema:</b>
          </div>
          <div class="value-col">
            <img src="<?= $originPath; ?>" alt="<?= $originSystem; ?>" style="width:20px; vertical-align:middle; margin:0 0px;">
            <?= $originSystem; ?>
            <?php if($destinationSystem !== ''): ?>
              <i class="fa-solid fa-arrow-right" style="margin: 0 5px;"></i>
              <img src="<?= $destinationPath; ?>" alt="<?= $destinationSystem; ?>" style="width:20px; vertical-align:middle; margin:0 5px;">
              <?= $destinationSystem; ?>
            <?php endif; ?>
          </div>
        </div>
        <!-- Linha do Recebido -->
        <div class="row-item">
          <div class="label-col">
            <i class="fa-solid fa-clock"></i>
            <b>Recebido:</b>
          </div>
          <div class="value-col">
            <?= $rowF['data_recebido2']; ?>
          </div>
        </div>
        <!-- Linha do Analista -->
        <div class="row-item">
          <div class="label-col">
            <i class="fa-solid fa-user-check me-0"></i>
            <b>Analista:</b>
          </div>
          <div class="value-col">
            <?= $rowF['analista_nome']; ?>
          </div>
        </div>
      </div>
      <?php if ($cargo === 'Admin' || $usuario_id == $rowF['analista_id'] || $usuario_id == '16'): ?>
        <div class="acao-card">
          <a href="javascript:void(0)" onclick="abrirModalEdicaoFromCard(this)">
            <i class="fa-solid fa-pen"></i>
          </a>
          <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalExclusao"
             onclick="excluirAnalise(<?= $rowF['id'] ?>)">
            <i class="fa-solid fa-trash"></i>
          </a>
        </div>
      <?php endif; ?>
    </div>
  <?php endwhile; ?>
</div>

<!-- Coluna: Em Andamento -->
<div class="kanban-column">
  <h3>Em Andamento</h3>
  <?php while ($rowO = $resOutros->fetch_assoc()): ?>
    <?php
      // Valor original do sistema, por exemplo "Clipp p/ AC" ou outro
      $systemNameOriginal = $rowO['sistema_nome'];
      if (strpos($systemNameOriginal, 'p/') !== false) {
          $parts = explode('p/', $systemNameOriginal);
          $originSystem = trim($parts[0]);
          $destinationSystem = trim($parts[1]);
      } else {
          $originSystem = $systemNameOriginal;
          $destinationSystem = '';
      }
      
      // Se o sistema aparecer como "Clipp", considere-o como "ClippPro"
      if (strcasecmp($originSystem, "Clipp") === 0) {
          $originSystem = "ClippPro";
      }
      if ($destinationSystem !== '' && strcasecmp($destinationSystem, "Clipp") === 0) {
          $destinationSystem = "ClippPro";
      }
      
      // Mapeamento dos sistemas para os nomes dos arquivos de imagem
      $imageMap = [
          "AC"           => "AC.png",
          "ClippFacil"   => "ClippFacil.png",
          "Clipp360"     => "Clipp360.png",
          "ClippPro"     => "ClippPro.png",
          "ClippMei"     => "ClippMei.png",
          "Small"        => "Small.png",
          "Gdoor"        => "Gdoor.png",
          "Conc"         => "Concorrente.png",
          "ZetaWeb"      => "ZWeb.png"
      ];
      
      // Logo para o sistema de origem
      if (isset($imageMap[$originSystem])) {
          $originImage = $imageMap[$originSystem];
      } else {
          $originImage = strtolower(str_replace(" ", "", $originSystem)) . ".png";
      }
      
      // Logo para o sistema de destino, se existir
      if ($destinationSystem !== '') {
          if (isset($imageMap[$destinationSystem])) {
              $destinationImage = $imageMap[$destinationSystem];
          } else {
              $destinationImage = strtolower(str_replace(" ", "", $destinationSystem)) . ".png";
          }
      }
      
      // Caminhos absolutos para as imagens
      $originPath = '../Public/Image/' . $originImage;
      if ($destinationSystem !== '') {
          $destinationPath = '../Public/Image/' . $destinationImage;
      }
    ?>
    <div class="kanban-card"
         data-id="<?= $rowO['id']; ?>"
         data-status_id="<?= $rowO['status_id']; ?>"
         data-contato="<?= htmlspecialchars($rowO['contato']); ?>"
         data-serial="<?= htmlspecialchars($rowO['serial']); ?>"
         data-retrabalho="<?= htmlspecialchars($rowO['retrabalho']); ?>"
         data-sistema_id="<?= $rowO['sistema_id']; ?>"
         data-data_recebido="<?= $rowO['data_recebido']; ?>"
         data-prazo_entrega="<?= $rowO['prazo_entrega']; ?>"
         data-data_inicio="<?= $rowO['data_inicio']; ?>"
         data-data_conclusao="<?= $rowO['data_conclusao']; ?>"
         data-analista_id="<?= $rowO['analista_id']; ?>"
         data-observacao="<?= htmlspecialchars($rowO['observacao']); ?>">
      <div class="card-header p-2">
        <div class="icon p-3">
          <i class="fa-solid fa-spinner fa-spin"></i>
        </div>
        <div class="card-title"><?= htmlspecialchars($rowO['contato']); ?></div>
      </div>
      <div class="card-details">
        <!-- Linha do Sistema -->
        <div class="row-item">
          <div class="label-col">
            <i class="fa-solid fa-desktop me-0"></i>
            <b>Sistema:</b>
          </div>
          <div class="value-col">
            <img src="<?= $originPath; ?>" alt="<?= $originSystem; ?>" style="width:20px; vertical-align:middle; margin:0 0px;">
            <?= $originSystem; ?>
            <?php if($destinationSystem !== ''): ?>
              <i class="fa-solid fa-arrow-right" style="margin: 0 5px;"></i>
              <img src="<?= $destinationPath; ?>" alt="<?= $destinationSystem; ?>" style="width:20px; vertical-align:middle; margin:0 5px;">
              <?= $destinationSystem; ?>
            <?php endif; ?>
          </div>
        </div>
        <!-- Linha do Recebido -->
        <div class="row-item">
          <div class="label-col">
            <i class="fa-solid fa-clock"></i>
            <b>Recebido:</b>
          </div>
          <div class="value-col">
            <?= $rowO['data_recebido2']; ?>
          </div>
        </div>
        <!-- Linha do Início -->
        <div class="row-item">
          <div class="label-col">
            <i class="fa-solid fa-play ms-1"></i>
            <b>Início:</b>
          </div>
          <div class="value-col">
            <?= $rowO['data_inicio2']; ?>
          </div>
        </div>
        <!-- Linha do Analista -->
        <div class="row-item">
          <div class="label-col">
            <i class="fa-solid fa-user-check me-0"></i>
            <b>Analista:</b>
          </div>
          <div class="value-col">
            <?= $rowO['analista_nome']; ?>
          </div>
        </div>
      </div>
      <?php if ($cargo === 'Admin' || $rowO['analista_id'] == $usuario_id || $usuario_id == '16'): ?>
        <div class="acao-card">
          <a href="javascript:void(0)" onclick="abrirModalEdicaoFromCard(this)">
            <i class="fa-solid fa-pen"></i>
          </a>
          <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalExclusao"
             onclick="excluirAnalise(<?= $rowO['id'] ?>)">
            <i class="fa-solid fa-trash"></i>
          </a>
        </div>
      <?php endif; ?>
    </div>
  <?php endwhile; ?>
</div>

 <!-- Coluna: Finalizadas -->
 <div class="kanban-column">
  <h3>Finalizadas</h3>
  <?php while ($rowC = $resFinalizados->fetch_assoc()): ?>
    <?php
      $systemNameOriginal = $rowC['sistema_nome'];
      if (strpos($systemNameOriginal, 'p/') !== false) {
          $parts = explode('p/', $systemNameOriginal);
          $originSystem = trim($parts[0]);
          $destinationSystem = trim($parts[1]);
      } else {
          $originSystem = $systemNameOriginal;
          $destinationSystem = '';
      }
      
      if (strcasecmp($originSystem, "Clipp") === 0) {
          $originSystem = "ClippPro";
      }
      if ($destinationSystem !== '' && strcasecmp($destinationSystem, "Clipp") === 0) {
          $destinationSystem = "ClippPro";
      }
      
      $imageMap = [
          "AC"           => "AC.png",
          "ClippFacil"   => "ClippFacil.png",
          "Clipp360"     => "Clipp360.png",
          "ClippPro"     => "ClippPro.png",
          "ClippMei"     => "ClippMei.png",
          "Small"        => "Small.png",
          "Gdoor"        => "Gdoor.png",
          "Conc"         => "Concorrente.png",
          "ZetaWeb"      => "ZWeb.png"
      ];
      
      if (isset($imageMap[$originSystem])) {
          $originImage = $imageMap[$originSystem];
      } else {
          $originImage = strtolower(str_replace(" ", "", $originSystem)) . ".png";
      }
      
      if ($destinationSystem !== '') {
          if (isset($imageMap[$destinationSystem])) {
              $destinationImage = $imageMap[$destinationSystem];
          } else {
              $destinationImage = strtolower(str_replace(" ", "", $destinationSystem)) . ".png";
          }
      }
      
      $originPath = '../Public/Image/' . $originImage;
      if ($destinationSystem !== '') {
          $destinationPath = '../Public/Image/' . $destinationImage;
      }
    ?>
    <div class="kanban-card"
         data-id="<?= $rowC['id']; ?>"
         data-status_id="<?= $rowC['status_id']; ?>"
         data-contato="<?= htmlspecialchars($rowC['contato']); ?>"
         data-serial="<?= htmlspecialchars($rowC['serial']); ?>"
         data-retrabalho="<?= htmlspecialchars($rowC['retrabalho']); ?>"
         data-sistema_id="<?= $rowC['sistema_id']; ?>"
         data-data_recebido="<?= $rowC['data_recebido']; ?>"
         data-prazo_entrega="<?= $rowC['prazo_entrega']; ?>"
         data-data_inicio="<?= $rowC['data_inicio']; ?>"
         data-data_conclusao="<?= $rowC['data_conclusao']; ?>"
         data-analista_id="<?= $rowC['analista_id']; ?>"
         data-observacao="<?= htmlspecialchars($rowC['observacao']); ?>">
      <div class="card-header p-2">
        <div class="icon p-3">
          <i class="fa-solid fa-check-circle"></i>
        </div>
        <div class="card-title"><?= htmlspecialchars($rowC['contato']); ?></div>
      </div>
      <div class="card-details">
        <!-- Linha do Sistema -->
        <div class="row-item">
          <div class="label-col">
            <i class="fa-solid fa-desktop me-0"></i>
            <b>Sistema:</b>
          </div>
          <div class="value-col">
            <img src="<?= $originPath; ?>" alt="<?= $originSystem; ?>" style="width:20px; vertical-align:middle; margin:0 0px;">
            <?= $originSystem; ?>
            <?php if($destinationSystem !== ''): ?>
              <i class="fa-solid fa-arrow-right" style="margin: 0 5px;"></i> 
              <img src="<?= $destinationPath; ?>" alt="<?= $destinationSystem; ?>" style="width:20px; vertical-align:middle; margin:0 5px;">
              <?= $destinationSystem; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Linha do Recebido -->
        <div class="row-item">
          <div class="label-col">
            <i class="fa-solid fa-clock"></i>
            <b>Recebido:</b>
          </div>
          <div class="value-col">
            <?= $rowC['data_recebido2']; ?>
          </div>
        </div>

        <!-- Linha do Início -->
        <div class="row-item">
          <div class="label-col">
            <i class="fa-solid fa-play ms-1"></i>
            <b>Início:</b>
          </div>
          <div class="value-col">
            <?= $rowC['data_inicio2']; ?>
          </div>
        </div>

        <!-- Linha do Concluído -->
        <div class="row-item">
          <div class="label-col">
            <i class="fa-solid fa-check"></i>
            <b>Concluído:</b>
          </div>
          <div class="value-col">
            <?= $rowC['data_conclusao2']; ?>
          </div>
        </div>

        <!-- Linha do Analista -->
        <div class="row-item">
          <div class="label-col">
            <i class="fa-solid fa-user-check me-0"></i>
            <b>Analista:</b>
          </div>
          <div class="value-col">
            <?= $rowC['analista_nome']; ?>
          </div>
        </div>
      </div>
      <?php if ($cargo === 'Admin' || $rowC['analista_id'] == $usuario_id || $usuario_id == '16'): ?>
        <div class="acao-card">
          <a href="javascript:void(0)" onclick="abrirModalEdicaoFromCard(this)">
            <i class="fa-solid fa-pen"></i>
          </a>
          <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalExclusao"
             onclick="excluirAnalise(<?= $rowC['id'] ?>)">
            <i class="fa-solid fa-trash"></i>
          </a>
        </div>
      <?php endif; ?>
    </div>
  <?php endwhile; ?>
</div>
      
      <!-- Modal de Cadastro -->
      <div class="modal fade" id="modalCadastro" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content p-4">
            <h4 class="modal-title mb-3">Cadastrar Conversão</h4>
            <form id="formCadastro" action="cadastrar_conversao.php" method="POST">
              <input type="hidden" name="id">
              <!-- Campos de cadastro (contato, serial, retrabalho, sistema, status, analista, datas, observação) -->
              <!-- Mantém-se o conteúdo original do modal de cadastro -->
              <div class="row mb-2"> 
                <div class="col-md-5">
                  <div class="mb-3">
                    <label class="form-label">Contato:</label>
                    <input type="text" class="form-control" name="contato" required>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label">Serial / CNPJ:</label>
                    <input type="text" class="form-control" name="serial">
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="mb-3">
                    <label class="form-label">Retrabalho:</label>
                    <select name="retrabalho" class="form-select">
                      <option value="Sim">Sim</option>
                      <option value="Nao" selected>Nao</option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="row mb-3"> 
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label">Sistema:</label>
                    <select name="sistema_id" class="form-select" required>
                      <option value="">Selecione...</option>
                      <?php
                      mysqli_data_seek($sistemas, 0);
                      while ($sis = $sistemas->fetch_assoc()):
                      ?>
                        <option value="<?= $sis['id']; ?>"><?= $sis['nome']; ?></option>
                      <?php endwhile; ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label">Status:</label>
                    <select name="status_id" id="status_id" class="form-select" required onchange="verificarStatus()">
                      <option value="">Selecione...</option>
                      <?php
                      mysqli_data_seek($status, 0);
                      while ($st = $status->fetch_assoc()):
                      ?>
                        <option value="<?= $st['id']; ?>"><?= $st['descricao']; ?></option>
                      <?php endwhile; ?>
                    </select>
                    <span id="statusError2" class="text-danger mt-1" style="display: none;">Para concluir, selecione "Concluido".</span>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label">Analista:</label>
                    <select name="analista_id" class="form-select" required>
                      <option value="">Selecione...</option>
                      <?php
                      mysqli_data_seek($analistas, 0);
                      while ($an = $analistas->fetch_assoc()):
                      ?>
                        <option value="<?= $an['id']; ?>" <?= ($an['id'] == $usuario_id) ? 'selected' : ''; ?>>
                          <?= $an['nome']; ?>
                        </option>
                      <?php endwhile; ?>
                    </select>
                  </div>
                </div>
              </div>
              <div class="row mb-3"> 
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label">Data Recebido:</label>
                    <input type="datetime-local" class="form-control" name="data_recebido" required>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label">Data Início:</label>
                    <input type="datetime-local" class="form-control" name="data_inicio" id="data_inicio">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label">Data Conclusão:</label>
                    <input type="datetime-local" class="form-control" name="data_conclusao" id="data_conclusao">
                  </div>
                </div>
              </div>
              <div class="row mb-4"> 
                <div class="mb-3">
                  <label class="form-label">Observação:</label>
                  <textarea name="observacao" class="form-control" rows="3"></textarea>
                </div>
              </div>
              <div class="text-end">
                <button type="submit" class="btn btn-success">Salvar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      
      <!-- Modal de Edição -->
      <div class="modal fade" id="modalEdicao" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content p-4">
            <h4 class="modal-title mb-3">Editar Conversão</h4>
            <form id="formEdicao" action="editar_conversao.php" method="POST">
              <input type="hidden" name="id" id="edit_id">
              <!-- Campos de edição idênticos aos do cadastro -->
              <div class="row mb-2"> 
                <div class="col-md-5">
                  <div class="mb-3">
                    <label class="form-label">Contato:</label>
                    <input type="text" class="form-control" name="contato" id="edit_contato" required>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label">Serial / CNPJ:</label>
                    <input type="text" class="form-control" name="serial" id="edit_serial">
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="mb-3">
                    <label class="form-label">Retrabalho:</label>
                    <select name="retrabalho" class="form-select" id="edit_retrabalho">
                      <option value="Sim">Sim</option>
                      <option value="Nao" selected>Nao</option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="row mb-3"> 
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label">Sistema:</label>
                    <select name="sistema_id" class="form-select" id="edit_sistema" required>
                      <option value="">Selecione...</option>
                      <?php
                      mysqli_data_seek($sistemas, 0);
                      while ($sis = $sistemas->fetch_assoc()):
                      ?>
                        <option value="<?= $sis['id']; ?>"><?= $sis['nome']; ?></option>
                      <?php endwhile; ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label">Status:</label>
                    <select name="status_id" class="form-select" id="edit_status" required onchange="verificarStatusEdit()">
                      <option value="">Selecione...</option>
                      <?php
                      mysqli_data_seek($status, 0);
                      while ($st = $status->fetch_assoc()):
                      ?>
                        <option value="<?= $st['id']; ?>"><?= $st['descricao']; ?></option>
                      <?php endwhile; ?>
                    </select>
                    <span id="statusError" class="text-danger mt-1" style="display: none;">Para concluir, selecione "Concluido".</span>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label">Analista:</label>
                    <select name="analista_id" class="form-select" id="edit_analista" required>
                      <option value="">Selecione...</option>
                      <?php
                      mysqli_data_seek($analistas, 0);
                      while ($an = $analistas->fetch_assoc()):
                      ?>
                        <option value="<?= $an['id']; ?>"><?= $an['nome']; ?></option>
                      <?php endwhile; ?>
                    </select>
                  </div>
                </div>
              </div>
              <div class="row mb-3">
                <div class="col-md-3">
                  <div class="mb-3">
                    <label class="form-label">Prazo Entrega:</label>
                    <input type="datetime-local" class="form-control" id="edit_prazo_entrega" name="prazo_entrega" required>
                  </div>  
                </div> 
                <div class="col-md-3">
                  <div class="mb-3">
                    <label class="form-label">Data Recebido:</label>
                    <input type="datetime-local" class="form-control" name="data_recebido" id="edit_data_recebido" required>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="mb-3">
                    <label class="form-label">Data Início:</label>
                    <input type="datetime-local" class="form-control" name="data_inicio" id="edit_data_inicio">
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="mb-3">
                    <label class="form-label">Data Conclusão:</label>
                    <input type="datetime-local" class="form-control" name="data_conclusao" id="edit_data_conclusao">
                  </div>
                </div>
              </div>
              <div class="row mb-4"> 
                <div class="mb-3">
                  <label class="form-label">Observação:</label>
                  <textarea name="observacao" class="form-control" id="edit_observacao" rows="3"></textarea>
                </div>
              </div>
              <div class="text-end">
                <button type="submit" class="btn btn-success">Salvar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      
      <!-- Modal de Exclusão -->
      <div class="modal fade" id="modalExclusao" tabindex="-1" aria-labelledby="modalExclusaoLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="modalExclusaoLabel">Confirma a Exclusão da Análise?</h5>
            </div>
            <div class="modal-body">
              <form action="deletar_conversao.php" method="POST">
                <input type="hidden" id="id_excluir" name="id_excluir">
                <div class="text-end">
                  <button type="submit" class="btn btn-success">Sim</button>
                  <button type="button" class="btn btn-danger" data-bs-dismiss="modal" aria-label="Close">Não</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div><!-- Fim da Área Principal -->
  </div><!-- Fim do d-flex-wrapper -->


  <!-- Modal de Filtro com Controle por Coluna -->
<div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <form method="GET" action="conversao.php">
        <div class="modal-header">
          <h5 class="modal-title" id="filterModalLabel">Filtro</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <!-- Seletor de Coluna para filtrar -->
          <div class="mb-3">
            <label for="filterColumn" class="form-label">Filtrar por Coluna:</label>
            <select class="form-select" id="filterColumn" name="filterColumn">
              <option value="period" <?php if(isset($_GET['filterColumn']) && $_GET['filterColumn'] == 'period') echo "selected"; ?>>Período</option>
              <option value="analista" <?php if(isset($_GET['filterColumn']) && $_GET['filterColumn'] == 'analista') echo "selected"; ?>>Analista</option>
              <option value="metas" <?php if(isset($_GET['filterColumn']) && $_GET['filterColumn'] == 'metas') echo "selected"; ?>>Metas</option>
              <option value="sistema" <?php if(isset($_GET['filterColumn']) && $_GET['filterColumn'] == 'sistema') echo "selected"; ?>>Sistema</option>
              <option value="status" <?php if(isset($_GET['filterColumn']) && $_GET['filterColumn'] == 'status') echo "selected"; ?>>Status</option>
            </select>
          </div>
          <!-- Campo para filtro por Período com os dois checkboxes -->
          <div id="filterPeriod" style="display: none;">
            <!-- Checkboxes para selecionar qual campo será filtrado -->
            <div class="mb-3 form-check">
              <input type="checkbox" class="form-check-input" id="period_recebido" name="period_recebido" value="1" <?php if(isset($_GET['period_recebido'])) echo "checked"; ?> checked>
              <label class="form-check-label" for="period_recebido">Filtrar por Data Recebido</label>
            </div>
            <div class="mb-3 form-check">
              <input type="checkbox" class="form-check-input" id="period_conclusao" name="period_conclusao" value="1" <?php if(isset($_GET['period_conclusao'])) echo "checked"; ?>>
              <label class="form-check-label" for="period_conclusao">Filtrar por Data Conclusão</label>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="data_inicial" class="form-label">Data Início:</label>
                <input type="date" class="form-control" id="data_inicial" name="data_inicial" value="<?php echo isset($_GET['data_inicial']) ? $_GET['data_inicial'] : ''; ?>">
              </div>
              <div class="col-md-6 mb-3">
                <label for="data_final" class="form-label">Data Fim:</label>
                <input type="date" class="form-control" id="data_final" name="data_final" value="<?php echo isset($_GET['data_final']) ? $_GET['data_final'] : ''; ?>">
              </div>
            </div>
          </div>
          <div id="filterAnalista" style="display: none;">
            <div class="mb-3">
              <label for="analista" class="form-label">Analista:</label>
              <!-- Atualize o "name" para "analista_id" conforme esperado no PHP -->
              <select class="form-select" id="analista" name="analista_id">
                <option value="">Selecione</option>
                <?php while ($row = $resultado_usuarios_dropdown->fetch_assoc()) { ?>
                  <option value="<?php echo $row['Id']; ?>" <?php if(isset($_GET['analista_id']) && $_GET['analista_id'] == $row['Id']) echo "selected"; ?>><?php echo $row['Nome']; ?></option>
                <?php } ?>
              </select>
            </div>
          </div>
          <div id="filterSistema" style="display: none;">
            <div class="mb-3">
              <label for="sistema" class="form-label">Sistema:</label>
              <select class="form-select" id="sistema" name="sistema">
                <option value="">Selecione</option>
                <?php while ($row = $lista_sistemas->fetch_assoc()) { ?>
                  <option value="<?php echo $row['id']; ?>" <?php if(isset($_GET['sistema']) && $_GET['sistema'] == $row['id']) echo "selected"; ?>><?php echo $row['nome']; ?></option>
                <?php } ?>
              </select>
            </div>
          </div>
          <div id="filterStatus" style="display: none;">
            <div class="mb-3">
              <label for="status" class="form-label">Status:</label>
              <select class="form-select" id="status" name="status">
                <option value="">Selecione</option>
                <?php while ($row = $lista_status->fetch_assoc()) { ?>
                  <option value="<?php echo $row['id']; ?>" <?php if(isset($_GET['status']) && $_GET['status'] == $row['id']) echo "selected"; ?>><?php echo $row['descricao']; ?></option>
                <?php } ?>
              </select>
            </div>
          </div>
          <!-- Novo campo para filtros de Metas -->
          <div id="filterMetas" style="display: none;">
            <div class="mb-3">
              <label for="metas" class="form-label">Metas:</label>
              <select class="form-select" id="metas" name="metas">
                <option value="">Selecione</option>
                <option value="dentro" <?php if(isset($_GET['metas']) && $_GET['metas'] == 'dentro') echo "selected"; ?>>Dentro do prazo</option>
                <option value="atrasadas" <?php if(isset($_GET['metas']) && $_GET['metas'] == 'atrasadas') echo "selected"; ?>>Atrasadas</option>
                <option value="nao_batida" <?php if(isset($_GET['metas']) && $_GET['metas'] == 'nao_batida') echo "selected"; ?>>Meta não batida</option>
                <option value="batida" <?php if(isset($_GET['metas']) && $_GET['metas'] == 'batida') echo "selected"; ?>>Entregues no prazo</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <!-- Alterado o link para incluir o parâmetro clear=1 -->
          <button type="button" class="btn btn-secondary" onclick="window.location.href='conversao.php?clear=1'">Limpar Filtro</button>
          <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
        <input type="hidden" name="filterColumn" id="filterColumnHidden">
      </form>
    </div>
  </div>
</div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
  <!-- Script para alternar entre os campos de filtro -->
  <script>
    function adjustFilterFields() {
      let filterColumn = document.getElementById("filterColumn").value;
      document.getElementById("filterColumnHidden").value = filterColumn;
      // Esconde todos os containers
      document.getElementById("filterPeriod").style.display = "none";
      document.getElementById("filterAnalista").style.display = "none";
      document.getElementById("filterSistema").style.display = "none";
      document.getElementById("filterStatus").style.display = "none";
      document.getElementById("filterMetas").style.display = "none";
      // Exibe o container da opção selecionada
      if (filterColumn === "period") {
        document.getElementById("filterPeriod").style.display = "block";
      } else if (filterColumn === "analista") {
        document.getElementById("filterAnalista").style.display = "block";
      } else if (filterColumn === "sistema") {
        document.getElementById("filterSistema").style.display = "block";
      } else if (filterColumn === "status") {
        document.getElementById("filterStatus").style.display = "block";
      } else if (filterColumn === "metas") {
        document.getElementById("filterMetas").style.display = "block";
      }
    }
    document.addEventListener("DOMContentLoaded", function() {
      adjustFilterFields();
      document.getElementById("filterColumn").addEventListener("change", adjustFilterFields);
    });
  </script>

  <script>
    // Chart.js para o gráfico de barras
    let labelsMes = <?= json_encode($labelsMes); ?>;
    let chartDatasets = <?= json_encode($chartDatasets); ?>;
    let ctx = document.getElementById('chartBarras').getContext('2d');
    let chartBarras = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labelsMes,
        datasets: chartDatasets
      },
      options: {
        responsive: true,
        scales: {
          x: { title: { display: true, text: 'Mês (ano-mês)' } },
          y: { beginAtZero: true, title: { display: true, text: 'Quantidade' } }
        }
      }
    });
  </script>
  
  <script>
    // Funções de pesquisa nas colunas Kanban
    $(document).ready(function(){
      $("#searchInput").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $(".kanban-card").filter(function() {
          $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
      });
    });
  </script>
  
  <script>
    // Funções para abrir modais de cadastro, edição e exclusão
    function abrirModalCadastro() {
      $("#modalCadastro").modal('show');
    }
    
    function abrirModalEdicao(
      id, contato, serial, retrabalho,
      sistemaID, statusID, dataRecebido,
      prazoEntrega, dataInicio, dataConclusao,
      analistaID, observacao
    ) {
      $("#edit_id").val(id);
      $("#edit_contato").val(contato);
      $("#edit_serial").val(serial);
      $("#edit_retrabalho").val(retrabalho);
      $("#edit_sistema").val(sistemaID);
      $("#edit_status").val(statusID);
      $("#edit_data_recebido").val(dataRecebido);
      $("#edit_prazo_entrega").val(prazoEntrega);
      $("#edit_data_inicio").val(dataInicio);
      $("#edit_data_conclusao").val(dataConclusao);
      $("#edit_analista").val(analistaID);
      $("#edit_observacao").val(observacao);
      $("#modalEdicao").modal('show');
    }
    
    function abrirModalEdicaoFinal(
      id, contato, serial, retrabalho,
      sistemaID, statusID, dataRecebido,
      prazoEntrega, dataInicio, dataConclusao,
      analistaID, observacao
    ) {
      abrirModalEdicao(id, contato, serial, retrabalho, sistemaID, statusID, dataRecebido, prazoEntrega, dataInicio, dataConclusao, analistaID, observacao);
    }

    function abrirModalEdicaoFromCard(el) {
  var card = $(el).closest('.kanban-card');
  
  // Extraindo os dados adicionais
  var id            = card.data('id');
  var contato       = card.data('contato');
  var serial        = card.data('serial');
  var retrabalho    = card.data('retrabalho');
  var sistemaID     = card.data('sistema_id');
  var statusID      = card.data('status_id');
  var dataRecebido  = card.data('data_recebido');
  var prazoEntrega  = card.data('prazo_entrega');
  var dataInicio    = card.data('data_inicio');
  var dataConclusao = card.data('data_conclusao');
  var analistaID    = card.data('analista_id');
  var observacao    = card.data('observacao');
  
  // Preenche o modal com os dados extraídos
  abrirModalEdicao(
    id, contato, serial, retrabalho,
    sistemaID, statusID, dataRecebido,
    prazoEntrega, dataInicio, dataConclusao,
    analistaID, observacao
  );
}
    
    function excluirAnalise(id) {
      document.getElementById("id_excluir").value = id;
    }
  </script>

  <!-- Verificar se o status é "Concluido" para obrigar preenchimento da data de conclusão -->
  <script>
    document.getElementById("formCadastro").addEventListener("submit", function(event) {
      var status = document.getElementById("status_id");
      var dataConclusao = document.getElementById("data_conclusao");
      var statusError2 = document.getElementById("statusError2");
      var idConcluido = "1"; // Substitua pelo ID correto
      if (dataConclusao.value.trim() !== "" && status.value !== idConcluido) {
        statusError2.style.display = "block";
        event.preventDefault();
        setTimeout(function() {
          statusError2.style.display = "none";
        }, 2000);
      } else {
        statusError2.style.display = "none";
      }
    });
    
    function verificarStatus() {
      var status = document.getElementById("status_id");
      var dataConclusao = document.getElementById("data_conclusao");
      var dataInicio = document.getElementById("data_inicio");
      var statusSelecionado = status.options[status.selectedIndex].text.trim();
      if (statusSelecionado === "Concluido") {
        dataConclusao.setAttribute("required", "true");
        dataInicio.setAttribute("required", "true");
        var now = new Date();
        var year = now.getFullYear();
        var month = ("0" + (now.getMonth() + 1)).slice(-2);
        var day = ("0" + now.getDate()).slice(-2);
        var hours = ("0" + now.getHours()).slice(-2);
        var minutes = ("0" + now.getMinutes()).slice(-2);
        var currentDatetime = year + "-" + month + "-" + day + "T" + hours + ":" + minutes;
        dataConclusao.value = currentDatetime;
      } else {
        dataConclusao.removeAttribute("required");
        dataInicio.removeAttribute("required");
        dataConclusao.value = "";
      }
    }
    
    document.getElementById("formEdicao").addEventListener("submit", function(event) {
      var statusEdit = document.getElementById("edit_status");
      var dataEditConclusao = document.getElementById("edit_data_conclusao");
      var statusError = document.getElementById("statusError");
      var idConcluido = "1";
      if (dataEditConclusao.value.trim() !== "" && statusEdit.value !== idConcluido) {
        statusError.style.display = "block";
        event.preventDefault();
        setTimeout(function() {
          statusError.style.display = "none";
        }, 2000);
      } else {
        statusError.style.display = "none";
      }
    });
    
    function verificarStatusEdit() {
      var statusEdit2 = document.getElementById("edit_status");
      var dataConclusao2 = document.getElementById("edit_data_conclusao");
      var dataInicio2 = document.getElementById("edit_data_inicio");
      var statusSelecionado2 = statusEdit2.options[statusEdit2.selectedIndex].text.trim();
      if (statusSelecionado2 === "Concluido") {
        dataConclusao2.setAttribute("required", "true");
        dataInicio2.setAttribute("required", "true");
        var now = new Date();
        var year = now.getFullYear();
        var month = ("0" + (now.getMonth() + 1)).slice(-2);
        var day = ("0" + now.getDate()).slice(-2);
        var hours = ("0" + now.getHours()).slice(-2);
        var minutes = ("0" + now.getMinutes()).slice(-2);
        var currentDatetime = year + "-" + month + "-" + day + "T" + hours + ":" + minutes;
        dataConclusao2.value = currentDatetime;
      } else {
        dataConclusao2.removeAttribute("required");
        dataInicio2.removeAttribute("required");
        dataConclusao2.value = "";
      }
    }
  </script>
  
  <!-- Drag and Drop com SortableJS para o Kanban (opcional) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
  <script>
document.querySelectorAll('.kanban-column').forEach(column => {
  new Sortable(column, {
    group: 'shared',
    draggable: '.kanban-card',
    
    animation: 150,
    onEnd: function(evt) {
      let card = evt.item;
      let convId = card.getAttribute('data-id');
      
      // Obtenha os títulos das colunas de origem e destino
      let sourceColumnTitle = evt.from.querySelector('h3').textContent.trim();
      let targetColumnTitle = evt.to.querySelector('h3').textContent.trim();

      if (sourceColumnTitle === targetColumnTitle) {
    return; 
  }
      console.log("Cartão movido de: " + sourceColumnTitle + " para: " + targetColumnTitle + " | ConvId: " + convId);

      // Mapeamento dos status:
      // "Em Fila" -> status "Em fila" (id 4)
      // "Em Andamento" -> status "Analise" (id 3)
      // "Finalizadas" -> status "Concluido" (id 1)
      let statusMapping = {
        "Em Fila": 4,
        "Em Andamento": 3,
        "Finalizadas": 1
      };

      let newStatusId = statusMapping[targetColumnTitle];
      if (!newStatusId) {
        console.error("Status não definido para a coluna: " + targetColumnTitle);
        return;
      }

      // Cria o objeto de dados inicial para a requisição AJAX
      let ajaxData = { id: convId, status_id: newStatusId };

      // Função auxiliar para gerar a data/hora atual no formato "YYYY-MM-DD HH:MM:SS"
      function getCurrentDatetime() {
        let now = new Date();
        let year = now.getFullYear();
        let month = ("0" + (now.getMonth() + 1)).slice(-2);
        let day = ("0" + now.getDate()).slice(-2);
        let hours = ("0" + now.getHours()).slice(-2);
        let minutes = ("0" + now.getMinutes()).slice(-2);
        let seconds = ("0" + now.getSeconds()).slice(-2);
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
      }
      let currentDatetime = getCurrentDatetime();

      // Se o destino for "Finalizadas", preenche data_conclusao com a data/hora atual
      if (targetColumnTitle === "Finalizadas") {
        ajaxData.data_conclusao = currentDatetime;
        card.setAttribute('data-data_conclusao', currentDatetime);
      }
      // Se o destino for "Em Andamento"
      else if (targetColumnTitle === "Em Andamento") {
        ajaxData.data_inicio = currentDatetime;
        card.setAttribute('data-data_inicio', currentDatetime);
        // Se o cartão veio de "Finalizadas", limpar data_conclusao
        if (sourceColumnTitle === "Finalizadas") {
          ajaxData.data_conclusao = "null";
          card.setAttribute('data-data_conclusao', "");
        }
      }
      // Se o destino for "Em Fila"
      else if (targetColumnTitle === "Em Fila") {
        // Se o cartão veio de "Finalizadas", limpar data_conclusao e data_inicio
        if (sourceColumnTitle === "Finalizadas") {
          ajaxData.data_conclusao = "null";
          ajaxData.data_inicio = "null";
          card.setAttribute('data-data_conclusao', "");
          card.setAttribute('data-data_inicio', "");
        }
        // Se veio de "Em Andamento", limpar data_inicio
        else if (sourceColumnTitle === "Em Andamento") {
          ajaxData.data_inicio = "null";
          card.setAttribute('data-data_inicio', "");
        }
      }

      $.ajax({
        url: 'atualiza_status_conversao.php',
        method: 'POST',
        data: ajaxData,
        dataType: 'json',
        success: function(response) {
          console.log("Resposta AJAX:", response);
          if (response.success) {
            let statusLabels = {
              4: "Em fila",
              3: "Analise",
              1: "Concluido"
            };
            let statusSpan = card.querySelector('.card-status');
            if (statusSpan) {
              statusSpan.textContent = statusLabels[newStatusId];
            }
            card.setAttribute('data-status_id', newStatusId);
            location.reload();
          } else {
            alert("Erro ao atualizar: " + response.error);
          }
        },
        error: function(jqXHR, textStatus, errorThrown) {
          console.error("Erro AJAX:", textStatus, errorThrown);
        }
      });
    }
  });
});
  </script>

          
</body>
</html>
