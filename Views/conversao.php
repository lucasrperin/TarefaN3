<?php
include '../Config/Database.php';

session_start();

// Verifica se o usuário está logado; se não, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definir o cargo do usuário (supondo que ele esteja armazenado na sessão, com a chave "Cargo")
$usuario_id = $_SESSION['usuario_id'];
$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';

/****************************************************************
 * 1) Capturar Filtros (GET)
 ****************************************************************/
$dataInicial = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : '';
$dataFinal   = isset($_GET['data_final'])   ? $_GET['data_final']   : '';
// Se os filtros estiverem vazios, preenche com a data atual (formato YYYY-MM-DD)
if(empty($dataInicial)) {
  $dataInicial = date("Y-m-d");
}
if(empty($dataFinal)) {
  $dataFinal = date("Y-m-d");
}
// Limpa o filtro quando pressionado o botão
if (isset($_GET['reset']) && $_GET['reset'] == 1) {
  $dataInicial = '';
  $dataFinal   = '';
  $analistaID  = 0;
} else {
  $dataInicial = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : date("Y-m-d");
  $dataFinal   = isset($_GET['data_final'])   ? $_GET['data_final']   : date("Y-m-d");
  $analistaID  = isset($_GET['analista_id'])  ? intval($_GET['analista_id']) : 0;
}
$analistaID  = isset($_GET['analista_id'])  ? intval($_GET['analista_id']) : 0;

/****************************************************************
 * 2) Montar WHERE Dinâmico
 ****************************************************************/
$where = " WHERE 1=1 ";
if (!empty($dataInicial)) {
    $where .= " AND c.data_recebido >= '{$dataInicial} 00:00:00' ";
}
if (!empty($dataFinal)) {
    $where .= " AND c.data_recebido <= '{$dataFinal} 23:59:59' ";
}
if ($analistaID > 0) {
    $where .= " AND c.analista_id = {$analistaID} ";
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
  <link rel="icon" href="../Public/Image/icone2.png" type="image/png">
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
        <?php if ($cargo === 'Admin'): ?>
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
        <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'User'): ?>
          <a class="nav-link" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'Treinamento'): ?>
          <a class="nav-link" href="treinamento.php"><i class="fa-solid fa-calendar-check me-2"></i>Treinamentos</a>
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
        <!-- Accordion com Gráfico, Filtro Global, Totalizadores e Resumo Geral -->
        <!-- Você pode manter os Accordions originais conforme sua implementação -->
        <div class="accordion" id="accordionConversao">
          <!-- Accordion Item 1: Gráfico e Filtro Global -->
          <div class="accordion-item mb-3">
            <h2 class="accordion-header" id="headingGrafico">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGrafico" aria-expanded="false" aria-controls="collapseGrafico">
                Gráfico e Filtro Global
              </button>
            </h2>
            <div id="collapseGrafico" class="accordion-collapse collapse" aria-labelledby="headingGrafico" data-bs-parent="#accordionConversao">
              <div class="accordion-body">
                <div class="row mb-4">
                  <div class="col-md-8">
                    <div class="card">
                      <div class="card-body">
                        <h5 class="card-title">Conversões Mensais por Analista</h5>
                        <canvas id="chartBarras" height="100"></canvas>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="card">
                      <div class="card-body">
                        <h5 class="card-title">Filtro Global</h5>
                        <form method="GET" class="row gy-2 gx-2">
                          <div class="col-12">
                            <label>Data Inicial</label>
                            <input type="date" name="data_inicial" value="<?= htmlspecialchars($dataInicial) ?>" class="form-control">
                          </div>
                          <div class="col-12">
                            <label>Data Final</label>
                            <input type="date" name="data_final" value="<?= htmlspecialchars($dataFinal) ?>" class="form-control">
                          </div>
                          <div class="col-12">
                            <label>Analista</label>
                            <select name="analista_id" class="form-select">
                              <option value="0">-- Todos --</option>
                              <?php while ($anF = $analistasFiltro->fetch_assoc()): ?>
                                <option value="<?= $anF['id'] ?>" <?= ($analistaID == $anF['id']) ? 'selected' : '' ?>>
                                  <?= $anF['nome'] ?>
                                </option>
                              <?php endwhile; ?>
                            </select>
                          </div>
                          <div class="d-flex justify-content-center gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                            <a href="conversao.php?reset=1" class="btn btn-secondary btn-sm">Limpar Filtros</a>
                          </div>
                        </form>
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
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTotalizadoresLayout2" aria-expanded="true" aria-controls="collapseTotalizadoresLayout2">
                <i class="fa-solid fa-chart-bar me-2"></i> Totalizadores de Conversões
              </button>
            </h2>
            <div id="collapseTotalizadoresLayout2" class="accordion-collapse collapse show" aria-labelledby="headingTotalizadoresLayout2" data-bs-parent="#accordionConversao">
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
                            <span><i class="fa-solid fa-check me-1 ms-1"></i> No prazo</span>
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
                                "ClippFácil" => "ClippFacil.png",
                                "Clipp360"   => "Clipp360.png",
                                "ZetaWeb"   => "ZWeb.png",
                              ];
                              if (isset($imageMap[$systemName])) {
                                $imageFilename = $imageMap[$systemName];
                              } else {
                                $imageFilename = strtolower(str_replace(" ", "", $systemName)) . ".png";
                              }
                              $imagePath = "/TarefaN3/Public/Image/" . $imageFilename;
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
              <button class="accordion-button collapsed layout5-accordion-header" type="button" data-bs-toggle="collapse" data-bs-target="#collapseResumoGeralLayout5" aria-expanded="false" aria-controls="collapseResumoGeralLayout5">
                <i class="fa-solid fa-chart-area me-2"></i> Resumo Geral
              </button>
            </h2>
            <div id="collapseResumoGeralLayout5" class="accordion-collapse collapse" aria-labelledby="headingResumoGeralLayout5" data-bs-parent="#accordionConversao">
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
              data-observacao="<?= htmlspecialchars($rowF['observacao']); ?>"
          >
            <strong><?= $rowF['contato']; ?></strong><br>
            Sistema: <?= $rowF['sistema_nome']; ?><br>
            Recebido: <?= $rowF['data_recebido2']; ?><br>
            Prazo: <?= $rowF['prazo_entrega2']; ?><br>
            Analista: <?= $rowF['analista_nome']; ?><br>
            Status: <span class="card-status"><?= $rowF['status_nome']; ?></span>
        <?php if ($cargo === 'Admin' || $usuario_id == $rowF['analista_id'] || $usuario_id == '16'): ?>
          <div class="acao-card">
          <a class="btn btn-outline-primary btn-sm" onclick="abrirModalEdicaoFromCard(this)">
          <i class='fa-sharp fa-solid fa-pen'></i>
         </a>
            <a href="javascript:void(0)" class="btn btn-outline-danger btn-sm" 
              data-bs-toggle="modal" data-bs-target="#modalExclusao" 
              onclick="excluirAnalise(<?= $rowF['id'] ?>)">
              <i class="fa-sharp fa-solid fa-trash"></i>
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
         data-observacao="<?= htmlspecialchars($rowO['observacao']); ?>"
    >
      <strong><?= $rowO['contato']; ?></strong><br>
      Sistema: <?= $rowO['sistema_nome']; ?><br>
      Status: <span class="card-status"><?= $rowO['status_nome']; ?></span><br>
      Recebido: <?= $rowO['data_recebido2']; ?><br>
      Analista: <?= $rowO['analista_nome']; ?><br>
      <?php if ($cargo === 'Admin' || $rowO['analista_id'] == $usuario_id || $usuario_id == '16'): ?>
        <div class="acao-card">
          <a class="btn btn-outline-primary btn-sm" onclick="abrirModalEdicaoFromCard(this)">
            <i class="fa-sharp fa-solid fa-pen"></i>
          </a>
          <a href="javascript:void(0)" class="btn btn-outline-danger btn-sm" 
             data-bs-toggle="modal" data-bs-target="#modalExclusao" 
             onclick="excluirAnalise(<?= $rowO['id'] ?>)">
            <i class="fa-sharp fa-solid fa-trash"></i>
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
         data-observacao="<?= htmlspecialchars($rowC['observacao']); ?>"
    >
      <strong><?= $rowC['contato']; ?></strong><br>
      Serial: <?= $rowC['serial']; ?><br>
      Retrabalho: <?= $rowC['retrabalho']; ?><br>
      Sistema: <?= $rowC['sistema_nome']; ?><br>
      Status: <span class="card-status"><?= $rowC['status_nome']; ?></span><br>
      Recebido: <?= $rowC['data_recebido2']; ?><br>
      Conclusão: <?= $rowC['data_conclusao2']; ?><br>
      Analista: <?= $rowC['analista_nome']; ?><br>
      <?php if ($cargo === 'Admin' || $rowC['analista_id'] == $usuario_id || $usuario_id == '16'): ?>
        <div class="acao-card">
          <a class="btn btn-outline-primary btn-sm" onclick="abrirModalEdicaoFromCard(this)">
            <i class="fa-sharp fa-solid fa-pen"></i>
          </a>
          <a href="javascript:void(0)" class="btn btn-outline-danger btn-sm" 
             data-bs-toggle="modal" data-bs-target="#modalExclusao" 
             onclick="excluirAnalise(<?= $rowC['id'] ?>)">
            <i class="fa-sharp fa-solid fa-trash"></i>
          </a>
        </div>
      <?php endif; ?>
    </div>
  <?php endwhile; ?>
</div>

</div><!-- Fim Kanban Board -->


      
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

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
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
