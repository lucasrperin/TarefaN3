<?php 
session_start();

// Verifica se o usuário está logado; se não, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../Config/Database.php';

$usuario_id    = $_SESSION['usuario_id'];
$cargo         = $_SESSION['cargo']        ?? '';
$nivel         = $_SESSION['nivel']        ?? '';
$usuario_nome  = $_SESSION['usuario_nome'] ?? 'Usuário';

// verifica se o usuário tem nível 6 (Supervisão) ou 7 (Gestão)
$userId = $_SESSION['usuario_id'];
// busca todos os níveis do usuário
$sqlNiveis = "
SELECT idNivel
FROM TB_EQUIPE_NIVEL_ANALISTA
WHERE idUsuario = {$userId}
";
$resNiveis = mysqli_query($conn, $sqlNiveis) or die(mysqli_error($conn));
$niveis = [];
while ($r = mysqli_fetch_assoc($resNiveis)) {
$niveis[] = (int)$r['idNivel'];
}
$temSupervisaoOuGestao = in_array(7, $niveis) || in_array(8, $niveis);

$logos = require '../Config/logos.php';

// 1) Carrega listas para os selects do filtro
$sqlUsuarios = "
  SELECT Id, Nome 
    FROM TB_USUARIO 
   WHERE Cargo IN ('Comercial','Admin','User')
   ORDER BY Nome";
$resUsuarios = mysqli_query($conn, $sqlUsuarios);

$sqlPlugins  = "SELECT id, nome FROM TB_PLUGIN ORDER BY nome";
$resPlugins  = mysqli_query($conn, $sqlPlugins);

// 2) Lê parâmetros de filtro
$filterColumn = $_GET['filterColumn'] ?? '';
$dataInicio   = $_GET['data_inicio']   ?? '';
$dataFim      = $_GET['data_fim']      ?? '';
$useCycle     = isset($_GET['use_cycle']);
$competencia  = $_GET['competencia']    ?? '';  // ex: "2025-03"
$usuario      = $_GET['usuario']      ?? '';
$plugin       = $_GET['plugin']       ?? '';
$status       = $_GET['status']       ?? '';

// 3) Monta cláusula WHERE dinamicamente
$where = [];
if ($filterColumn === 'periodo') {
  if ($useCycle && $competencia) {
    list($year,$month) = explode('-', $competencia);
    $cycleStart = sprintf('%04d-%02d-01',$year,$month);
    $cycleEnd   = date('Y-m-d', strtotime("$cycleStart +1 month +14 days"));
    $where[] = "
      YEAR(i.data) = {$year}
      AND MONTH(i.data) = {$month}
      AND i.data_faturamento BETWEEN '{$cycleStart}' AND '{$cycleEnd}'
    ";
  }
  elseif ($dataInicio && $dataFim) {
    $where[] = "i.data BETWEEN '{$dataInicio}' AND '{$dataFim}'";
  }
}

if ($filterColumn === 'usuario' && $usuario) {
  $campoUsuario = $cargo === 'Comercial'
    ? 'i.idConsultor'
    : 'i.user_id';
  $where[] = "$campoUsuario = " . intval($usuario);
}
if ($filterColumn === 'plugin' && $plugin) {
  $where[] = "i.plugin_id = "   . intval($plugin);
}
if ($filterColumn === 'status' && $status) {
  // escapa o valor vindo do GET
  $statusEsc = mysqli_real_escape_string($conn, $status);
  $where[]   = "i.status = '{$statusEsc}'";
}
$whereSQL = $where 
  ? ' WHERE ' . implode(' AND ', $where) 
  : '';

// 4) Consulta principal de indicações
$sql = "
  SELECT 
    i.*,
    i.user_id,
    p.nome AS plugin_nome,
    CASE
      WHEN i.idConsultor = 29 THEN 'Não Possui'
      ELSE c.nome
    END AS consultor_nome
  FROM TB_INDICACAO i
  JOIN TB_PLUGIN   p ON p.id = i.plugin_id
  JOIN TB_USUARIO  c ON c.id = i.idConsultor
  $whereSQL
  ORDER BY i.data DESC
";
$resIndic   = mysqli_query($conn, $sql);
$indicacoes = mysqli_fetch_all($resIndic, MYSQLI_ASSOC);

// 5) Ranking de indicações por usuário (mesmo filtro)
$sqlRanking = "
  SELECT 
    u.nome AS usuario_nome, 
    COUNT(i.id) AS total_indicacoes
  FROM TB_INDICACAO i
  JOIN TB_USUARIO u ON u.id = i.user_id
  $whereSQL
  GROUP BY u.id
  ORDER BY total_indicacoes DESC
";
$resRanking = mysqli_query($conn, $sqlRanking);
$ranking    = mysqli_fetch_all($resRanking, MYSQLI_ASSOC);

$sqlRankingFat = "
  SELECT 
    u.nome AS usuario_nome, 
    COUNT(i.id) AS total_indicacoes
  FROM TB_INDICACAO i
  JOIN TB_USUARIO u ON u.id = i.user_id
  $whereSQL
  AND i.status = 'Faturado'
  GROUP BY u.id
  ORDER BY total_indicacoes DESC
";
$resRankingFat = mysqli_query($conn, $sqlRankingFat);
$rankingFat    = mysqli_fetch_all($resRankingFat, MYSQLI_ASSOC);

// 6) Ranking de faturamento por consultor (filtra apenas faturados)
$sqlRankingConsult = "
  SELECT 
    c.nome AS usuario_nome,
    SUM(CASE WHEN i.status = 'Faturado' THEN i.vlr_total ELSE 0 END) AS total_faturado_consult
  FROM TB_INDICACAO i
  JOIN TB_USUARIO c ON c.id = i.idConsultor
  $whereSQL
  AND i.status = 'Faturado'
  GROUP BY c.id
  ORDER BY total_faturado_consult DESC
";
$resRankingConsult = mysqli_query($conn, $sqlRankingConsult);
$rankingConsult    = mysqli_fetch_all($resRankingConsult, MYSQLI_ASSOC);

// 7) Totais gerais (mês + treinamentos)
$sqlFatur = "
  SELECT SUM(vlr_total) AS total_faturamento 
  FROM TB_INDICACAO i
  " . ($whereSQL ? $whereSQL . " AND i.status = 'Faturado'" : "WHERE i.status = 'Faturado'");

$resFatur          = mysqli_query($conn, $sqlFatur);
$totalFaturamento  = (float) mysqli_fetch_assoc($resFatur)['total_faturamento'];

$qTrein = "
  SELECT SUM(valor_faturamento) AS total_treinamentos
    FROM TB_CLIENTES
   WHERE faturamento = 'FATURADO'
";
$rTrein             = mysqli_query($conn, $qTrein);
$totalTreinamentos  = (float) mysqli_fetch_assoc($rTrein)['total_treinamentos'];

$totalGeral = $totalFaturamento + $totalTreinamentos;

// 8) Totalizador por Plugin (aplica mesmo filtro)
$sqlPluginsCount = "
  SELECT 
    p.nome AS plugin_nome,
    COUNT(i.id) AS total_indicacoes,
    SUM(CASE WHEN i.status = 'Faturado' THEN i.vlr_total ELSE 0 END) AS total_faturado
  FROM TB_INDICACAO i
  JOIN TB_PLUGIN p ON p.id = i.plugin_id
  $whereSQL
  GROUP BY p.id
  ORDER BY total_indicacoes DESC
";
$resPluginsCount = mysqli_query($conn, $sqlPluginsCount);
$pluginsCount    = mysqli_fetch_all($resPluginsCount, MYSQLI_ASSOC);


// 1) Monta cláusula WHERE específica para o gráfico de indicações
$whereChart = ["i.status = 'Faturado'"];
// para treinamentos, vamos filtrar data_conclusao
$whereTrain = ["c.faturamento = 'FATURADO'"];

if ($filterColumn === 'periodo' && $useCycle && $competencia) {
  list($year, $month) = explode('-', $competencia);
  $cycleStart = sprintf('%04d-%02d-01', $year, $month);
  $cycleEnd   = date('Y-m-d', strtotime("$cycleStart +44 days"));

  // filtra só o mês de registro + faturamentos dentro dos 45 dias
  $whereChart[] = "YEAR(i.data) = {$year}";
  $whereChart[] = "MONTH(i.data) = {$month}";
  $whereChart[] = "i.data_faturamento BETWEEN '{$cycleStart}' AND '{$cycleEnd}'";
}
elseif ($filterColumn === 'periodo' && !$useCycle && $dataInicio && $dataFim) {
  // Período livre
  $whereChart[] = "(i.data BETWEEN '$dataInicio' AND '$dataFim')";
  $whereTrain[] = "(c.data_conclusao BETWEEN '$dataInicio' AND '$dataFim')";
}
else {
  // Sem filtro “Período”: restringe ao ano atual
  $currentYear = date('Y');
  $whereChart[] = "YEAR(i.data) = $currentYear";
  $whereTrain[] = "YEAR(c.data_conclusao) = $currentYear";
}

$whereChartSql = "WHERE " . implode(' AND ', $whereChart);
$whereTrainSql = "WHERE " . implode(' AND ', $whereTrain);

// 2) Queries adaptadas
$qInd = "
  SELECT 
    DATE_FORMAT(i.data, '%Y-%m') AS mes,
    SUM(i.vlr_total)              AS tot
  FROM TB_INDICACAO i
  $whereChartSql
  GROUP BY mes
";

$qTrein = "
  SELECT 
    DATE_FORMAT(c.data_conclusao, '%Y-%m') AS mes,
    SUM(c.valor_faturamento)               AS tot
  FROM TB_CLIENTES c
  $whereTrainSql
  GROUP BY mes
";

// 3) Alimenta os arrays [YYYY-MM] => valor
$indPorMes   = [];
$treinPorMes = [];

$res = mysqli_query($conn, $qInd);
while($r = mysqli_fetch_assoc($res)) {
  $indPorMes[$r['mes']] = (float)$r['tot'];
}

$res = mysqli_query($conn, $qTrein);
while($r = mysqli_fetch_assoc($res)) {
  $treinPorMes[$r['mes']] = (float)$r['tot'];
}

// 4) Gera labels e dados para TODO o ano atual
$mesesAbv = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
$labels     = [];
$dadosInd   = [];
$dadosTrein = [];
$currentYear = date('Y');

for ($m = 1; $m <= 12; $m++) {
  $labels[]     = "{$mesesAbv[$m-1]}/{$currentYear}";
  $mm           = str_pad($m, 2, '0', STR_PAD_LEFT);
  $key          = "{$currentYear}-{$mm}";
  $dadosInd[]   = $indPorMes[$key]   ?? 0;
  $dadosTrein[] = $treinPorMes[$key] ?? 0;
}

// 5) Serializa para o JS
$labelsJson     = json_encode($labels,     JSON_UNESCAPED_UNICODE);
$dadosIndJson   = json_encode($dadosInd);
$dadosTreinJson = json_encode($dadosTrein);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Painel N3 - Indicações de Plugins</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- CSS personalizado para Indicações -->
  <link rel="icon" href="../Public/Image/LogoTituto.png" type="image/png">
  <link href="../Public/indicacao.css" rel="stylesheet">
  
</head>
<body class="bg-light">
  <div class="d-flex-wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
      <a class="light-logo" href="indicacao.php">
        <img src="../Public/Image/zucchetti_blue.png" width="150" alt="Logo Zucchetti">
      </a>
      <nav class="nav flex-column">
      <a class="nav-link" href="menu.php"><i class="fa-solid fa-house me-2"></i>Home</a>
        <?php if ($cargo === 'Admin' || $cargo === 'Conversor'): ?>
          <a class="nav-link" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a>
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
          <a class="nav-link active" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a>
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
    
    <!-- Minimalist Modern Toast Layout -->
    <div id="toast-container" class="toast-container">
      <div id="toastSucesso" class="toast toast-success">
        <i class="fa-solid fa-check-circle"></i>
        <span id="toastMensagem"></span>
      </div>
      <div id="toastErro" class="toast toast-error">
        <i class="fa-solid fa-exclamation-triangle"></i>
        <span id="toastMensagemErro"></span>
      </div>
    </div>
    <script>
      function showToast(message, type) {
        const container = document.getElementById("toast-container");
        const toast = document.createElement("div");
        toast.className = "toast " + type;
        toast.textContent = message;
        container.appendChild(toast);
        // Trigger the CSS animation
        setTimeout(() => {
          toast.classList.add("show");
        }, 10);
        // Hide after 2 seconds and remove from DOM
        setTimeout(() => {
          toast.classList.remove("show");
          setTimeout(() => {
            container.removeChild(toast);
          }, 300);
        }, 2000);
      }

      document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get("success");
        const error = urlParams.get("error");

        if (success) {
          let msg = "";
          switch (success) {
            case "1":
              msg = "Indicação Cadastrada!";
              break;
            case "2":
              msg = "Indicação Editada!";
              break;
            case "3":
              msg = "Indicação Excluída!";
              break;
          }
          if (msg) showToast(msg, "success");
        }
        if (erro) {
          let msg = "";
          switch (erro) {
            case "1":
              msg = "Erro ao Cadastrar Indicação!";
              break;
            case "2":
              msg = "Erro ao Editar Indicação!";
              break;
            case "3":
              msg = "Erro ao Excluír Indicação!";
              break;
            case "4":
              msg = "Acesso negado: você só pode editar suas próprias indicações.";
              break;
          }
          if (msg) showToast(msg, "erro");
        }
      });
    </script>
    
      <!-- Área principal -->
      <div class="w-100">
      <!-- Header -->
      <div class="header">
        <h3>Controle de Indicações</h3>
        <div class="user-info">
          <span>Bem‑vindo(a), <?=htmlspecialchars($usuario_nome,ENT_QUOTES,'UTF-8');?>!</span>
          <a href="logout.php" class="btn btn-danger">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
          </a>
        </div>
      </div>

      <!-- Conteúdo -->
      <div class="content container-fluid">

        <!-- ACCORDION RESUMO -------------------------------------------------->
        <div class="accordion mb-4" id="accordionIndicadores">
          <div class="accordion-item border-0">
            <h2 class="accordion-header" id="headingIndicadores">
              <button class="accordion-button" type="button" data-bs-toggle="collapse"
                      data-bs-target="#collapseIndicadores" aria-expanded="true"
                      aria-controls="collapseIndicadores">
                <b>Indicadores do Mês – Resumo e Totalizações</b>
              </button>
            </h2>

            <div id="collapseIndicadores" class="accordion-collapse collapse show"
                 aria-labelledby="headingIndicadores" data-bs-parent="#accordionIndicadores">

              <!-- LAYOUT 4: PILL VERTICAL + CONTEÚDO -->
              <div class="d-flex layout4-container me-1">

                <!-- NAV LATERAL -->
                <nav class="nav nav-pills flex-column">
                  <button class="nav-link active" id="v-tab-ranking" data-bs-toggle="pill"
                          data-bs-target="#v-pane-ranking" type="button">🥇 Ranking</button>
                  <button class="nav-link" id="v-tab-plugins" data-bs-toggle="pill"
                          data-bs-target="#v-pane-plugins" type="button">🧩 Plugins</button>
                  <button class="nav-link" id="v-tab-geral" data-bs-toggle="pill"
                          data-bs-target="#v-pane-geral" type="button">💰 Geral</button>
                  <button class="nav-link" id="v-tab-mensal" data-bs-toggle="pill"
                          data-bs-target="#v-pane-mensal" type="button">📈 Mensal</button>
                </nav>

                <!-- CONTEÚDO DAS PILLS -->
                <div class="tab-content flex-grow-1">

                  <!-- Ranking com tabela e progress bars -->
<div class="tab-pane fade show active mt-1" id="v-pane-ranking">
  <?php 
    // escolhe o array certo e extrai o maior valor
    $dados = ($cargo!=='Comercial' ? $ranking : $rankingConsult);
    $valores = array_map(fn($r) => $cargo!=='Comercial'
      ? $r['total_indicacoes']
      : $r['total_faturado_consult']
    , $dados);

    if (!empty($valores)) {
      $max = max($valores);
    } else {
      $max = 1;
    }
  ?>
  <div class="table-responsive ranking-scroll">
    <table class="table table-striped align-middle mb-0">
      <thead>
        <tr>
          <th>Posição</th>
          <th>Usuário</th>
          <th class="text-end">Total</th>
          <th style="width:40%"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($dados as $k => $r): 
          $value = $cargo!=='Comercial'
            ? $r['total_indicacoes']
            : $r['total_faturado_consult'];
          $percent = round($value / $max * 100);
        ?>
        <tr>
          <td><?= ['🥇','🥈','🥉'][$k] ?? ($k+1).'º' ?></td>
          <td><?= htmlspecialchars($r['usuario_nome']) ?></td>
          <td class="text-end">
            <?= $cargo!=='Comercial'
                ? $value
                : 'R$ '.number_format($value,2,',','.') ?>
          </td>
          <td>
            <div class="progress" style="height: .75rem;">
              <div 
                class="progress-bar" 
                role="progressbar" 
                style="width: <?= $percent ?>%" 
                aria-valuenow="<?= $percent ?>" 
                aria-valuemin="0" 
                aria-valuemax="100">
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>


                  <!-- Plugins em Masonry (fundo claro) -->
                  <div class="table-scroll tab-pane fade" id="v-pane-plugins">
                    <div class="masonry-section">
                      <?php foreach($pluginsCount as $pc): ?>
                      <div class="masonry-card">
                        <?php if(isset($logos[$pc['plugin_nome']])): ?>
                        <img src="../<?=$logos[$pc['plugin_nome']]?>" 
                             alt="<?=$pc['plugin_nome']?>" 
                             class="masonry-logo" style="border-radius:3px;">
                        <?php endif; ?>
                        <div class="masonry-title"><?=$pc['plugin_nome']?></div>
                        <div class="masonry-stats">
                          <?=$pc['total_indicacoes']?> indicações · 
                          R$ <?=number_format($pc['total_faturado'],2,',','.')?>
                        </div>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <!-- Geral como cards -->
<div class="tab-pane fade mt-4" id="v-pane-geral">
  <div class="row row-cols-1 row-cols-md-3 g-4 p-2">
    <div class="col">
      <div class="card general-card text-center">
        <div class="card-body">
          <i class="fa-solid fa-coins fa-2x mb-2"></i>
          <h6 class="card-subtitle mb-1">Total Acumulado</h6>
          <p class="card-text h5">R$ <?=number_format($totalGeral,2,',','.')?></p>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card general-card text-center">
        <div class="card-body">
          <i class="fa-solid fa-chalkboard-user fa-2x mb-2 text-success"></i>
          <h6 class="card-subtitle mb-1">Treinamentos</h6>
          <p class="card-text h5">R$ <?=number_format($totalTreinamentos,2,',','.')?></p>
        </div>
      </div>
    </div>
    <div class="col">
      <div class="card general-card text-center">
        <div class="card-body">
          <i class="fa-solid fa-handshake fa-2x mb-2 text-warning"></i>
          <h6 class="card-subtitle mb-1">Indicações</h6>
          <p class="card-text h5">R$ <?=number_format($totalFaturamento,2,',','.')?></p>
        </div>
      </div>
    </div>
  </div>
</div>

                  <!-- Mensal -->
                  <div class="tab-pane fade" id="v-pane-mensal">
                    <canvas id="graficoFaturamento" class="chart"></canvas>
                  </div>

                </div>
              </div>
              <!-- FIM DO ACCORDION CONTENT -->
            </div>
          </div>
        </div>
        <!-- FIM DO ACCORDION -->

        <div class="card shadow mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Indicações de Plugins</h4>
            <div class="d-flex justify-content-end gap-2">
              <!-- Botão para abrir o modal de filtro -->
              <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
                <i class="fa-solid fa-filter"></i>
              </button>
              <input type="text" id="filtro-indicacoes" class="form-control" style="max-width:200px;" placeholder="Pesquisar...">
              <?php 
                if (
                  ( $cargo === 'Admin' || $cargo === 'User' || $cargo === 'Conversor') || $temSupervisaoOuGestao
                ):
              ?>
                <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#modalNovaIndicacao">
                  <i class="fa-solid fa-plus-circle me-1"></i> Cadastrar
                </button>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body">
        <div class="table-responsive access-scroll">
          <table class="table align-middle mb-0" id="tabela-indicacoes">
                <thead class="table-header-light">    
                  <tr>
                    <th>Plugin</th>
                    <th>Data</th>
                    <th>Status</th>
                    <th>Consultor(a)</th>
                    <th class="text-center">Ações</th>
                  </tr>
                </thead>
              <tbody>
                <?php foreach ($indicacoes as $i): ?>
                  <?php
                    // chaves únicas para ligar linha ↔ collapse
                    $uid = 'ind_'.$i['id'];
                  ?>
                  <!-- linha principal -------------------------------------------------->
                  <tr class="table-row-hover"
                      data-bs-toggle="collapse"
                      data-bs-target="#<?= $uid ?>"
                      aria-expanded="false"
                      aria-controls="<?= $uid ?>">
                      <?php $logo = $logos[$i['plugin_nome']] ?? null; ?>
                      <td class="d-flex align-items-center gap-2 w-100">
                      <?php if ($logo): ?>
                        <img src="../<?= $logo; ?>" alt="" style="width:31px;height:31px;border-radius:3px;">
                      <?php endif; ?>
                      <span><?= htmlspecialchars($i['plugin_nome']); ?></span>
                      </td>
                      <td><?= date('d/m/Y', strtotime($i['data'])); ?></td>
                      <td>
                      <?php
                        switch ($i['status']) {
                          case 'Faturado':   $badge = 'bg-success';    break;   // verde
                          case 'Cancelado':  $badge = 'bg-danger';     break;   // vermelho
                          default:           $badge = 'bg-secondary';  break;   // cinza (Pendente, etc.)
                        }
                      ?>
                      <span class="badge <?= $badge; ?>">
                        <?= $i['status']; ?>
                      </span>
                      </td>
                      <td><?= htmlspecialchars($i['consultor_nome']); ?></td>
                      <?php 
                        $autorId = $i['user_id'];
                        $status  = $i['status'];
                        $pode = in_array($cargo, ['Admin','Comercial'], true) || ($autorId == $_SESSION['usuario_id'] && $status !== 'Faturado');
                      ?>
                        <td class="text-center">
                          <?php if($pode): ?>
                            <button
                              type="button"
                              class="btn btn-sm btn-primary btn-editar-indicacao"
                              data-bs-toggle="modal"
                              data-bs-target="#modalEditarIndicacao"
                              data-id="<?= $i['id'] ?>"
                              data-plugin_id="<?= $i['plugin_id'] ?>"
                              data-data="<?= date('Y-m-d', strtotime($i['data'])) ?>"
                              data-data_faturamento="<?= !empty($i['data_faturamento']) ? date('Y-m-d', strtotime($i['data_faturamento'])) : '' ?>"
                              data-cnpj="<?= htmlspecialchars($i['cnpj'], ENT_QUOTES) ?>"
                              data-serial="<?= htmlspecialchars($i['serial'], ENT_QUOTES) ?>"
                              data-contato="<?= htmlspecialchars($i['contato'], ENT_QUOTES) ?>"
                              data-fone="<?= htmlspecialchars($i['fone'],    ENT_QUOTES) ?>"
                              data-idconsultor="<?= $i['idConsultor'] ?>"
                              data-status="<?= $i['status'] ?>"
                              data-vlr_total="<?= $i['vlr_total']   ?? '' ?>"
                              data-n_venda="<?= $i['n_venda']      ?? '' ?>"
                              data-observacao="<?= $i['observacao']      ?? '' ?>"
                              title="Editar"
                            >
                              <i class="fa-solid fa-pen-to-square"></i>
                            </button>

                            <!-- Botão EXCLUIR --------------------------------------------->
                            <button class="btn btn-sm btn-danger"
                                    title="Excluir"
                                    onclick="event.stopPropagation(); modalExcluir(<?= $i['id'] ?>);">
                              <i class="fa-solid fa-trash"></i>
                            </button>
                          <?php else: ?>
                            <!-- Somente para não deixar vazio -->
                          <?php endif; ?>
                        </td>
                    </tr>
                <!-- linha de detalhe (collapse) ------------------------------------->
                    <tr class="collapse" id="<?= $uid ?>">
                      <td colspan="5" class="p-0 border-0">
                        <div class="card rounded-0 rounded-bottom bg-light-subtle">
                          <div class="card-body py-2">

                            <div class="row gy-2 align-items-center">

                            <?php
                              // remove qualquer caractere que não seja dígito
                              $cnpjDig = preg_replace('/\D/','',$i['cnpj']);
                            ?>
                            <div class="col-6 col-md-3 d-flex align-items-center gap-1">
                              <i class="fa-solid fa-id-card text-primary"></i>
                              <span class="fw-semibold small text-muted">CNPJ:</span>
                              <span class="small"><?= $i['cnpj']; ?></span>

                              <!-- Ícone de consulta -->
                              <a href="#" class="text-decoration-none consulta-cnpj"
                                data-cnpj="<?= $cnpjDig ?>" title="Consultar CNPJ">
                                <i class="fa-solid fa-magnifying-glass small"></i>
                              </a>
                            </div>

                              <div class="col-6 col-md-3 d-flex align-items-center gap-1">
                                <i class="fa-solid fa-key text-secondary"></i>
                                <span class="fw-semibold small text-muted">Serial:</span>
                                <span class="small"><?= $i['serial']; ?></span>
                              </div>

                              <div class="col-6 col-md-3 d-flex align-items-center gap-1">
                                <i class="fa-solid fa-user text-success"></i>
                                <span class="fw-semibold small text-muted">Contato:</span>
                                <span class="small"><?= htmlspecialchars($i['contato']); ?></span>
                              </div>

                              <div class="col-6 col-md-3 d-flex align-items-center gap-1">
                                <i class="fa-solid fa-phone text-warning"></i>
                                <span class="fw-semibold small text-muted">Fone:</span>
                                <?php
                                    $foneDig = preg_replace('/\D/','', $i['fone']);   // só dígitos
                                    if (strlen($foneDig) === 10)     $foneDig = '55'.$foneDig; // acrescenta +55 se faltar
                                    elseif (strlen($foneDig) === 11) $foneDig = '55'.$foneDig;
                                  ?>
                                  <a href="https://wa.me/<?= $foneDig ?>"
                                    target="_blank" class="text-decoration-none"
                                    title="Enviar mensagem por WhatsApp">
                                    <i class="fa-brands fa-whatsapp text-success"></i>
                                    <?= $i['fone']; ?>
                                  </a>
                              </div>
                              <?php if ($i['status']==='Faturado' && !empty($i['n_venda'])): 
                                        // ano da venda a partir da data da indicação
                                        $anoVenda = date('Y', strtotime($i['data']));

                                        // nome do PDF → V000123456.pdf
                                        $arquivo  = 'V'.str_pad($i['n_venda'], 9, '0', STR_PAD_LEFT).'.pdf';

                                        $urlVenda = "http://atendimento.compufour.com.br/vendas/{$anoVenda}/{$arquivo}";
                                  ?>
                                  <div class="col-6 col-md-3 d-flex align-items-center gap-1">
                                    <i class="fa-solid fa-receipt text-info"></i>
                                    <span class="fw-semibold small text-muted">Nº Venda:</span>
                                    <a href="<?= $urlVenda ?>" target="_blank" class="small text-decoration-none">
                                      <?= htmlspecialchars($i['n_venda']); ?>
                                    </a>
                                  </div>
                                  <?php endif; ?>
                                  <div class="col-6 col-md-8 d-flex align-items-center gap-1">
                                <i class="fa-solid fa-comment-dots text-secondary"></i>
                                <span class="fw-semibold small text-muted">Observação:</span>
                                <span class="small"><?= htmlspecialchars($i['observacao']); ?></span>
                              </div>
                            </div><!-- /row -->
                          </div><!-- /card-body -->
                        </div><!-- /card -->
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div><!-- /content -->
    </div><!-- /Área principal -->
  </div><!-- /d-flex-wrapper -->
  <!-- Função de pesquisa nas tabelas-->

  <script>
    // mantém seu script de collapse
    document.querySelectorAll('#tabela-indicacoes tbody tr[data-bs-toggle]')
      .forEach(row => {
        row.addEventListener('show.bs.collapse', () => row.classList.add('table-active'));
        row.addEventListener('hide.bs.collapse', () => row.classList.remove('table-active'));
      });

    // agora o filtro geral
    document.getElementById('filtro-indicacoes').addEventListener('input', function(){
      const termo = this.value.toLowerCase();

      // 1) filtra tabela
      document.querySelectorAll('#tabela-indicacoes tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(termo) ? '' : 'none';
      });

      // 2) filtra Masonry
      document.querySelectorAll('.masonry-card').forEach(card => {
        const titulo = card.querySelector('.masonry-title').textContent.toLowerCase();
        card.style.display = titulo.includes(termo) ? '' : 'none';
      });
    });
  </script>

    <!-- Evita que cliques em botões/links dentro da linha disparem o collapse -->
    <script>
    document.querySelectorAll(
      '#tabela-indicacoes tbody tr[data-bs-toggle] button,' +
      '#tabela-indicacoes tbody tr[data-bs-toggle] a'
    ).forEach(el => {
      el.addEventListener('click', e => e.stopPropagation());
    });
    </script>
      <script>
    $(document).ready(function () {
      $('#searchInput').on('keyup', function () {
        const termo = $(this).val().trim().toLowerCase();

        // percorre apenas as linhas principais (as que têm data-bs-toggle)
        $('#tabela-indicacoes tbody tr[data-bs-toggle]').each(function () {
          const linha    = $(this);                            // <tr> principal
          const detalhe  = $('#' + linha.attr('aria-controls')); // <tr> collapse

          // texto da linha + texto do detalhe
          const texto = (linha.text() + ' ' + detalhe.text()).toLowerCase();
          const match = texto.indexOf(termo) !== -1;

          if (match) {
            linha.removeClass('d-none');     // mostra registro
            detalhe.removeClass('d-none');   // (fica oculto pelo CSS .collapse)
          } else {
            linha.addClass('d-none');        // esconde registro
            detalhe.addClass('d-none');      
            detalhe.removeClass('show');     // força fechar se estava aberto
          }
        });
      });
    });
  </script>
  
  <!-- Modal para cadastro de nova indicação -->
  <div class="modal fade" id="modalNovaIndicacao" tabindex="-1" role="dialog" aria-labelledby="modalNovaIndicacaoLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <!-- Conteúdo do modal de cadastro -->
        <div class="modal-header">
          <h5 class="modal-title" id="modalNovaIndicacaoLabel">Nova Indicação</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="cadastrar_indicacao.php" method="POST">
          <div class="modal-body">
            <!-- Linha 1: Plugin e Data -->
            <div class="row mb-2">
              <div class="col-md-6">
                <div class="form-group position-relative">
                  <label for="plugin_id" class="form-label mb-0">Plugin</label>
                  <div class="input-group mt-0">
                    <select class="form-control" id="plugin_id" name="plugin_id" required>
                      <?php
                        $sqlPlugins = "SELECT * FROM TB_PLUGIN ORDER BY nome";
                        $resPlugins = mysqli_query($conn, $sqlPlugins);
                        while($plugin = mysqli_fetch_assoc($resPlugins)):
                      ?>
                        <option value="<?php echo $plugin['id']; ?>"><?php echo $plugin['nome']; ?></option>
                      <?php endwhile; ?>
                    </select>
                    <?php if (
                      ($cargo === 'Admin' || $cargo === 'Comercial') || $temSupervisaoOuGestao
                    ): ?>
                      <button class="btn btn-outline-secondary" type="button"
                              data-bs-toggle="collapse"
                              data-bs-target="#novoPluginCollapse"
                              aria-expanded="false"
                              aria-controls="novoPluginCollapse">
                        <i class="fa-solid fa-plus"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="data">Data</label>
                  <input type="date" class="form-control" id="data" name="data" required>
                </div>
              </div>
            </div>
            <!-- Collapse para novo plugin -->
            <div class="collapse mb-3 mt-2" id="novoPluginCollapse">
              <div class="card card-body">
                <div class="form-group">
                  <label for="novo_plugin">Nome do Novo Plugin</label>
                  <input type="text" class="form-control" id="novo_plugin" placeholder="Informe o nome do novo plugin">
                </div>
                <button type="button" class="btn btn-primary mt-1" id="btnCadastrarPlugin">Salvar Plugin</button>
              </div>
            </div>
            <!-- Linha 2: CNPJ e Serial -->
            <div class="row mb-2">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="cnpj">CNPJ</label>
                  <input type="text" class="form-control" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00" maxlength="18" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="serial">Serial</label>
                  <input type="text" class="form-control" id="serial" name="serial" required>
                </div>
              </div>
            </div>
            <!-- Linha 3: Contato e Fone -->
            <div class="row mb-2">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="contato">Contato</label>
                  <input type="text" class="form-control" id="contato" name="contato" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="fone">Fone</label>
                  <input type="text" class="form-control" id="fone" name="fone" required>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-12">
                <div class="form-group">
                  <label for="observacao">Observação</label>
                  <textarea class="form-control" id="observacao" name="observacao" rows="3"
                    placeholder="Digite aqui alguma observação (opcional)"></textarea>
                </div>
              </div>
            </div>
          </div><!-- /modal-body -->
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Cadastrar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal para edição de indicação -->
  <div class="modal fade" id="modalEditarIndicacao" tabindex="-1" role="dialog" aria-labelledby="modalEditarIndicacaoLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form action="editar_indicacao.php" method="POST">
          <div class="modal-header">
            <h5 class="modal-title" id="modalEditarIndicacaoLabel">Editar Indicação</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <!-- Campo oculto para o ID -->
            <input type="hidden" id="editar_id" name="id">
            <input type="hidden" id="editar_consultor_hidden" name="consultor">
            <input type="hidden" id="editar_status_hidden"  name="editar_status">
            <input type="hidden" id="editar_valor_hidden"   name="editar_valor">
            <input type="hidden" id="editar_venda_hidden"   name="editar_venda">
            <input type="hidden" id="editar_data_faturamento_hidden" name="data_faturamento">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group position-relative">
                  <label for="editar_plugin_id">Plugin</label>
                  <div class="input-group mt-0">
                    <select class="form-control" id="editar_plugin_id" name="plugin_id" required>
                      <?php
                        $sqlPlugins = "SELECT * FROM TB_PLUGIN ORDER BY nome";
                        $resPlugins = mysqli_query($conn, $sqlPlugins);
                        while($plugin = mysqli_fetch_assoc($resPlugins)):
                      ?>
                        <option value="<?php echo $plugin['id']; ?>"><?php echo $plugin['nome']; ?></option>
                      <?php endwhile; ?>
                    </select>
                    <?php if (
                      $cargo === 'Admin' || ( $cargo === 'Comercial' && $temSupervisaoOuGestao)
                    ): ?>
                      <button class="btn btn-outline-secondary" type="button"
                              data-bs-toggle="collapse"
                              data-bs-target="#novoPluginCollapseEdicao"
                              aria-expanded="false"
                              aria-controls="novoPluginCollapseEdicao">
                        <i class="fa-solid fa-plus"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editar_data">Data</label>
                  <input type="date" class="form-control" id="editar_data" name="data" required>
                </div>
              </div>
            </div>
            <!-- Collapse para novo plugin (Edição) -->
            <div class="collapse mb-3 mt-2" id="novoPluginCollapseEdicao">
              <div class="card card-body">
                <div class="form-group">
                  <label for="novo_plugin_edit">Nome do Novo Plugin</label>
                  <input type="text" class="form-control" id="novo_plugin_edit" placeholder="Informe o nome do novo plugin">
                </div>
                <button type="button" class="btn btn-primary mt-1" id="btnCadastrarPluginEdit">Salvar Plugin</button>
              </div>
            </div>
            <div class="row mt-2">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editar_cnpj">CNPJ</label>
                  <input type="text" class="form-control" id="editar_cnpj" name="editar_cnpj" maxlength="18" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editar_serial">Serial</label>
                  <input type="text" class="form-control" id="editar_serial" name="serial" required>
                </div>
              </div>
            </div>
            <div class="row mt-2">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editar_contato">Contato</label>
                  <input type="text" class="form-control" id="editar_contato" name="contato" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editar_fone">Fone</label>
                  <input type="text" class="form-control" id="editar_fone" name="fone" required>
                </div>
              </div>
            </div>
            <?php if ($cargo === 'Admin' || $cargo === 'Comercial'): ?>
              <div class="row mt-2">
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="editar_status">Status</label>
                    <select name="editar_status" class="form-select" id="editar_status" onchange="verificarStatus()">
                      <option value="Pendente">Pendente</option>
                      <option value="Faturado">Faturado</option>
                      <option value="Cancelado">Cancelado</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="editar_consultor">Consultor</label>
                    <select class="form-select" id="editar_consultor" name="consultor">
                        <option value="">Selecione</option>
                        <?php
                        $sqlConsult = "SELECT Id, Nome FROM TB_USUARIO WHERE Cargo = 'Comercial' AND Id <> 29 ORDER BY Nome";
                        $resConsult = $conn->query($sqlConsult);
                        while ($row = $resConsult->fetch_assoc()) {
                            echo "<option value='" . $row['Id'] . "'>" . $row['Nome'] . "</option>";
                        }
                        ?>
                    </select>
                  </div>
                </div>
              </div>
              <div class="row mt-2">
                <!-- Container para campos adicionais quando o status for Faturado -->
                <div class="col-md-6" id="valorContainer" style="display: none;">
                  <div class="form-groupo mt-2">
                    <label for="editar_valor">Valor R$</label>
                    <input type="text" class="form-control" id="editar_valor" name="editar_valor">
                  </div>
                </div>
                <div class="col-md-6" id="vendaContainer" style="display: none;"> 
                  <div class="form-group mt-2">
                    <label for="editar_venda">Nº Venda</label>
                    <input type="text" class="form-control" id="editar_venda" name="editar_venda">
                  </div>
                </div>
                <div class="col-md-6" id="faturamentoContainer" style="display:none">
                  <div class="form-group mt-2">
                    <label for="editar_data_faturamento">Data de Faturamento</label>
                    <input type="date" class="form-control" id="editar_data_faturamento" name="data_faturamento" value="<?= isset($i['data_faturamento']) ? date('Y-m-d', strtotime($i['data_faturamento'])) : '' ?>">
                  </div>
                </div>
              </div>
            <?php endif; ?>
            <div class="row mb-3 mt-2"> 
              <div>
                <label for="obs" class="form-label">Observação</label>
                <textarea name="observacao" id="obs" class="form-control" rows="2"></textarea>
              </div>
            </div>
          </div><!-- /modal-body -->
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Salvar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal de Exclusão -->
  <div class="modal fade" id="modalExcluir" tabindex="-1" aria-labelledby="modalExcluirLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalExcluirLabel">Excluir Indicação</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <form action="deletar_indicacao.php" method="post">
          <div class="modal-body">
            <input type="hidden" name="id" id="excluir_id">
            <p>Tem certeza que deseja excluir essa indicação?</p>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-danger">Excluir</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal de Filtro -->
  <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <form method="GET" action="indicacao.php">
          <div class="modal-header">
            <h5 class="modal-title" id="filterModalLabel">Filtrar Indicações</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">

            <!-- Escolha da coluna -->
            <div class="mb-3">
              <label for="filterColumn" class="form-label">Filtrar por:</label>
              <select class="form-select" id="filterColumn" name="filterColumn">
                <option value="periodo" <?php if($filterColumn=='periodo') echo 'selected'; ?>>Período</option>
                <option value="usuario" <?php if($filterColumn=='usuario') echo 'selected'; ?>>Usuário</option>
                <option value="plugin"  <?php if($filterColumn=='plugin')  echo 'selected'; ?>>Plugin</option>
                <option value="status"  <?php if($filterColumn=='status')  echo 'selected'; ?>>Status</option>
              </select>
            </div>

            <!-- Filtrar por Período -->
            <div id="filterPeriod" style="display:none;">
              <div class="col-md-6">
                <div class="form-check form-switch mt-4 mb-4">
                  <input class="form-check-input" type="checkbox" role="switch" id="use_cycle" name="use_cycle" <?= isset($_GET['use_cycle']) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="use_cycle">
                    Ciclo (45 dias)
                  </label>
                </div>
              </div>
              <!-- container de data início/fim + checkbox -->
              <div id="dateRangeContainer" class="row align-items-end mb-2">
                <div class="col-md-5">
                  <label for="data_inicio" class="form-label">Data Início</label>
                  <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>">
                </div>
                <div class="col-md-5">
                  <label for="data_fim" class="form-label">Data Fim</label>
                  <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>">
                </div>
              </div>
              <!-- novo container de competência (mês/ano) -->
              <div id="competenciaContainer" class="mb-2" style="display:none;">
                <label for="competencia" class="form-label">Competência</label>
                <input type="month" class="form-control" id="competencia" name="competencia" value="<?= htmlspecialchars($competencia ?? '') ?>">
              </div>
            </div>

            <!-- Filtrar por Usuário -->
            <div id="filterUsuario" style="display:none;">
              <div class="mb-3">
                <label for="usuario" class="form-label">Usuário</label>
                <select class="form-select" id="usuario" name="usuario">
                  <option value="">Selecione</option>
                  <?php while($u = mysqli_fetch_assoc($resUsuarios)): ?>
                  <option value="<?= $u['Id'] ?>" <?= $u['Id']==$usuario?'selected':'' ?>>
                    <?= htmlspecialchars($u['Nome']) ?>
                  </option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>

            <!-- Filtrar por Plugin -->
            <div id="filterPlugin" style="display:none;">
              <div class="mb-3">
                <label for="plugin" class="form-label">Plugin</label>
                <select class="form-select" id="plugin" name="plugin">
                  <option value="">Selecione</option>
                  <?php
                    $sqlPlugins = "SELECT * FROM TB_PLUGIN ORDER BY nome";
                    $resPlugins = mysqli_query($conn, $sqlPlugins);
                    while($plugin = mysqli_fetch_assoc($resPlugins)):
                  ?>
                    <option value="<?php echo $plugin['id']; ?>"><?php echo $plugin['nome']; ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>

            <!-- Filtrar por Status -->
            <div id="filterStatus" style="display:none;">
              <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select name="status" class="form-select" id="status">
                  <option value="Pendente"  <?= $status==='Pendente' ? 'selected' : '' ?>>Pendente</option>
                  <option value="Faturado"  <?= $status==='Faturado' ? 'selected' : '' ?>>Faturado</option>
                  <option value="Cancelado" <?= $status==='Cancelado'? 'selected' : '' ?>>Cancelado</option>
                </select>
              </div>
            </div>

          </div>
          <div class="modal-footer">
            <a href="indicacao.php" class="btn btn-secondary">Limpar</a>
            <button type="submit" class="btn btn-primary">Aplicar</button>
          </div>
        </form>
      </div>
    </div>
  </div>


  <!-- Modal Consulta CNPJ -------------------------------------------->
<div class="modal fade" id="modalConsultaCnpj" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Consulta de CNPJ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="cnpjCarga" class="text-center py-3 d-none">
          <div class="spinner-border" role="status"></div>
          <p class="mt-2">Carregando dados…</p>
        </div>

        <div id="cnpjErro" class="alert alert-danger d-none"></div>

        <div id="cnpjConteudo" class="d-none">
          <h6 class="fw-bold" id="cnpjNome"></h6>
          <p class="mb-1"><span class="fw-semibold">Situação:</span> <span id="cnpjSituacao"></span></p>

          <div class="row">
            <div class="col-md-6">
              <p class="mb-1"><span class="fw-semibold">Abertura:</span> <span id="cnpjAbertura"></span></p>
              <p class="mb-1"><span class="fw-semibold">Porte:</span> <span id="cnpjPorte"></span></p>
              <p class="mb-1"><span class="fw-semibold">Natureza:</span> <span id="cnpjNatureza"></span></p>
            </div>
            <div class="col-md-6">
              <p class="mb-1"><span class="fw-semibold">Telefone:</span> <span id="cnpjFone"></span></p>
              <p class="mb-1"><span class="fw-semibold">E‑mail:</span> <span id="cnpjEmail"></span></p>
              <p class="mb-1"><span class="fw-semibold">Capital social:</span> <span id="cnpjCapital"></span></p>
            </div>
          </div>

          <hr>
          <h6 class="fw-semibold">Endereço</h6>
          <p id="cnpjEndereco" class="mb-1"></p>

          <hr>
          <h6 class="fw-semibold">Atividade principal</h6>
          <p id="cnpjAtividade" class="mb-1"></p>
        </div>
      </div>
    </div>
  </div>
</div>

  <!-- Scripts JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
  const currentUserId = <?= json_encode($_SESSION['usuario_id']); ?>;

  function verificarStatus() {
    const status               = document.getElementById("editar_status").value;
    const valorContainer       = document.getElementById("valorContainer");
    const vendaContainer       = document.getElementById("vendaContainer");
    const faturamentoContainer = document.getElementById("faturamentoContainer");
    const valor                = document.getElementById("editar_valor");
    const venda                = document.getElementById("editar_venda");
    const faturamento          = document.getElementById("editar_data_faturamento");
    const consultor            = document.getElementById("editar_consultor");

    if (status === "Faturado") {
      if (!consultor.value) {
        consultor.value = currentUserId;
      }
      valorContainer.style.display       = "block";
      vendaContainer.style.display       = "block";
      faturamentoContainer.style.display = "block";
      valor.required         = true;
      venda.required         = true;
      faturamento.required   = true;
    }
    else if (status === "Cancelado") {
      if (!consultor.value) {
        consultor.value = currentUserId;
      }
    }
    else {
      valorContainer.style.display       = "none";
      vendaContainer.style.display       = "none";
      faturamentoContainer.style.display = "none";
      valor.required         = false;
      venda.required         = false;
      faturamento.required   = false;
    }
  }

  // muda o status
  document.getElementById("editar_status")
          .addEventListener("change", verificarStatus);

  // ao abrir o modal
  $('#modalEditarIndicacao').on('shown.bs.modal', function() {
    // preenche data de hoje (YYYY-MM-DD)
    const today = new Date().toISOString().slice(0,10);
    const faturField      = $('#editar_data_faturamento');

    if (!faturField.val()) {
      faturField.val(today);
    }

    // depois ajusta visibilidade e consultor
    verificarStatus();
  });
</script>

<!-- Scripts para preencher data no cadastro -->
<script>
  $('#modalNovaIndicacao').on('shown.bs.modal', function() {
    const today = new Date().toISOString().slice(0,10);
    const $modal = $(this);

    // preenche o campo "data" se estiver vazio
    const $data = $modal.find('input[name="data"]');
    if (!$data.val()) {
      $data.val(today);
    }
  });
</script>

<script>
  // Recebe uma string só com dígitos e devolve "R$X.YY"
  function formatCurrency(digits) {
    // garante só números
    digits = digits.replace(/\D/g, "");
    // pelo menos 4 dígitos (2 casas decimais + algo antes)
    while (digits.length < 4) digits = "0" + digits;
    // parte inteira e decimal
    let intPart = digits.slice(0, digits.length - 2);
    let decPart = digits.slice(-2);
    // remove zeros à esquerda
    intPart = intPart.replace(/^0+/, "") || "0";
    // ponto a cada 3 dígitos
    intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    return "R$" + intPart + "," + decPart;
  }

  // Chamado a cada digitação para formatação incremental
  function updateValorField() {
    const input = document.getElementById("editar_valor");
    if (!input) return;
    const digits = input.value.replace(/\D/g, "");
    input.value = formatCurrency(digits);
  }

  // Formata corretamente o valor bruto vindo do banco (decimal(18,4))
  function initValorFormat() {
    const input = document.getElementById("editar_valor");
    if (!input) return;
    let raw = input.value.trim();
    // raw ex: "1.0000" ou "1.000"
    raw = raw.replace(/,/, ".");           // caso venha com vírgula
    let num = parseFloat(raw);
    if (isNaN(num)) num = 0;
    // arredonda a 2 casas
    num = Math.round(num * 100) / 100;
    // garante duas casas na string
    const [intPart, decPart] = num.toFixed(2).split(".");
    // monta string de dígitos "1234" representando centavos
    const digits = intPart + decPart;
    input.value = formatCurrency(digits);
  }

  document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("editar_valor");
    if (!input) return;

    // 1) Só ao abrir o modal: lê o valor bruto do banco e formata
    $('#modalEditarIndicacao').on('shown.bs.modal', initValorFormat);

    // 2) Depois, a cada digitação: mantém formatação incremental
    input.addEventListener("input", updateValorField);
  });
</script>

<script>
  // Cadastrar novo plugin via AJAX (modal de cadastro)
  $(document).ready(function(){
    $('#btnCadastrarPlugin').click(function(){
      var novoPlugin = $('#novo_plugin').val().trim();
      if(novoPlugin === ''){
        alert('Informe o nome do novo plugin.');
        return;
      }
      $.ajax({
        url: 'cadastrar_plugin.php',
        type: 'POST',
        data: { nome: novoPlugin },
        dataType: 'json',
        success: function(resp){
          if (resp.duplicate === true) {
            alert(resp.message);
            $('#plugin_id').val(resp.id);
          } else if (resp.id) {
            $('#plugin_id').append('<option value="' + resp.id + '">' + resp.nome + '</option>');
            $('#plugin_id').val(resp.id);
            alert('Plugin cadastrado com sucesso!');
          } else {
            alert('Erro: ' + resp.message);
          }
          $('#novo_plugin').val('');
          $('#novoPluginCollapse, #novoPluginCollapseEdicao').collapse('hide');
        },
        error: function(jqXHR, textStatus, errorThrown){
          alert('Erro na requisição: ' + errorThrown);
        }
      });
    });
  });

  // Cadastrar novo plugin via AJAX (modal de edição)
  $(document).ready(function(){
    $('#btnCadastrarPluginEdit').click(function(){
      var novoPluginEdit = $('#novo_plugin_edit').val().trim();
      if(novoPluginEdit === ''){
        alert('Informe o nome do novo plugin.');
        return;
      }
      $.ajax({
        url: 'cadastrar_plugin.php',
        type: 'POST',
        data: { nome: novoPluginEdit },
        dataType: 'json',
        success: function(resp){
          if (resp.duplicate === true) {
            alert(resp.message);
            $('#editar_plugin_id').val(resp.id);
          } else if (resp.id) {
            $('#editar_plugin_id').append('<option value="' + resp.id + '">' + resp.nome + '</option>');
            $('#editar_plugin_id').val(resp.id);
            alert('Plugin cadastrado com sucesso!');
          } else {
            alert('Erro: ' + resp.message);
          }
          $('#novo_plugin_edit').val('');
          $('#novoPluginCollapse, #novoPluginCollapseEdicao').collapse('hide');
        },
        error: function(jqXHR, textStatus, errorThrown){
          alert('Erro na requisição: ' + errorThrown);
        }
      });
    });
  });
</script>
<script>
  // Máscara de CNPJ para #cnpj e #editar_cnpj
  document.addEventListener("DOMContentLoaded", function(){
    var cnpjInputs = document.querySelectorAll("#cnpj, #editar_cnpj");
    cnpjInputs.forEach(function(input) {
      input.addEventListener("input", function(e) {
        var v = e.target.value.replace(/\D/g, "");
        // Formata: 00.000.000/0000-00
        v = v.replace(/^(\d{2})(\d)/, "$1.$2");
        v = v.replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3");
        v = v.replace(/\.(\d{3})(\d)/, ".$1/$2");
        v = v.replace(/(\d{4})(\d)/, "$1-$2");
        e.target.value = v;
      });
      input.addEventListener("blur", function(e) {
        var formattedValue = e.target.value;
        var errorSpanId = e.target.id + "-error";
        var errorSpan = document.getElementById(errorSpanId);
        if (formattedValue.length !== 18) {
          if (!errorSpan) {
            errorSpan = document.createElement("span");
            errorSpan.id = errorSpanId;
            errorSpan.style.color = "red";
            errorSpan.style.fontSize = "0.9em";
            e.target.parentNode.appendChild(errorSpan);
          }
          errorSpan.textContent = "CNPJ inválido. Revise e tente novamente!";
          setTimeout(function(){
            if(e.target.value.length !== 18) {
              e.target.focus();
            }
            errorSpan.textContent = "";
          }, 3000);
        }
      });
    });
  });
</script>
<script>
  const modalEdit = document.getElementById('modalEditarIndicacao');

modalEdit.addEventListener('show.bs.modal', function(event) {
  // o botão que disparou o modal
  const btn = event.relatedTarget;

  // lê todos os dados de uma vez
  const {
    id,
    plugin_id,
    data,
    data_faturamento,
    cnpj,
    serial,
    contato,
    fone,
    idconsultor,
    status,
    vlr_total,
    n_venda,
    observacao
  } = btn.dataset;

  console.log(btn.dataset); // debug: veja tudo no console!

  // helper para datas
  const toDate = s => s || '';

  // 1) Campos sempre visíveis
  modalEdit.querySelector('#editar_id').value              = id;
  modalEdit.querySelector('#editar_plugin_id').value       = plugin_id;
  modalEdit.querySelector('#editar_data').value            = toDate(data);
  modalEdit.querySelector('#editar_cnpj').value            = cnpj;
  modalEdit.querySelector('#editar_serial').value          = serial;
  modalEdit.querySelector('#editar_contato').value         = contato;
  modalEdit.querySelector('#editar_fone').value            = fone;
  modalEdit.querySelector('#obs').value                    = observacao;

  // 2) Campos de “Admin/Comercial” ou seus hidden backups
  const setField = (sel, hid, val) => {
    const eSel = modalEdit.querySelector(sel);
    const eHid = modalEdit.querySelector(hid);
    if (eSel) eSel.value = val;
    if (eHid) eHid.value = val;
  };

  setField('#editar_consultor',          '#editar_consultor_hidden',          idconsultor);
  setField('#editar_status',             '#editar_status_hidden',             status);
  setField('#editar_data_faturamento',   '#editar_data_faturamento_hidden',   data_faturamento);
  setField('#editar_valor',              '#editar_valor_hidden',              vlr_total);
  setField('#editar_venda',              '#editar_venda_hidden',              n_venda);

  // 3) Se “Faturado”, mostra containers
  if (status === 'Faturado') {
    document.getElementById('valorContainer').style.display       = 'block';
    document.getElementById('vendaContainer').style.display       = 'block';
    document.getElementById('faturamentoContainer').style.display = 'block';
  } else {
    verificarStatus();
  }
});

  // Função para excluir indicação
  function modalExcluir(id) {
      document.getElementById('excluir_id').value = id;
      new bootstrap.Modal(document.getElementById('modalExcluir')).show();
    }
</script>
<script>
const lbls       = <?php echo $labelsJson; ?>;
const indValores = <?php echo $dadosIndJson; ?>;
const treValores = <?php echo $dadosTreinJson; ?>;

const ctx = document.getElementById('graficoFaturamento').getContext('2d');
new Chart(ctx,{
  type:'bar',                                 // mude para 'line' se preferir
  data:{
    labels: lbls,
    datasets:[
      { label:'Indicações',  data:indValores,  borderWidth:1, backgroundColor:'rgba(75,121,161,.8)' },
      { label:'Treinamentos',data:treValores,  borderWidth:1, backgroundColor:'rgba(40,62,81,.8)'  }
    ]
  },
  options:{
    responsive:true, maintainAspectRatio:false,
    scales:{ y:{ beginAtZero:true } },
    plugins:{ legend:{ position:'bottom' } }
  }
});
</script>
<script>
// clicar na lupa
document.querySelectorAll('.consulta-cnpj').forEach(btn=>{
  btn.addEventListener('click', e=>{
    e.preventDefault();
    const cnpj   = btn.dataset.cnpj;
    const modal  = new bootstrap.Modal(document.getElementById('modalConsultaCnpj'));
    const carga  = document.getElementById('cnpjCarga');
    const erro   = document.getElementById('cnpjErro');
    const corpo  = document.getElementById('cnpjConteudo');

    carga.classList.remove('d-none');
    erro.classList.add('d-none');
    corpo.classList.add('d-none');
    modal.show();

    fetch(`../Ajax/cnpj_consulta.php?cnpj=${cnpj}`)
      .then(r=>r.json())
      .then(data=>{
        if (data.status==='ERROR'){
          throw new Error(data.message || 'Erro desconhecido');
        }
        // preenche campos
        document.getElementById('cnpjNome'    ).textContent = data.nome;
        document.getElementById('cnpjSituacao').textContent = data.situacao;
        document.getElementById('cnpjAbertura').textContent = data.abertura;
        document.getElementById('cnpjPorte'   ).textContent = data.porte;
        document.getElementById('cnpjNatureza').textContent = data.natureza_juridica;
        document.getElementById('cnpjFone'    ).textContent = data.telefone;
        document.getElementById('cnpjEmail'   ).textContent = data.email;
        document.getElementById('cnpjCapital' ).textContent = 'R$ '+Number(data.capital_social).toLocaleString('pt-BR');
        document.getElementById('cnpjEndereco').textContent =
          `${data.logradouro}, ${data.numero} - ${data.bairro}, ${data.municipio}/${data.uf}, CEP ${data.cep}`;
        document.getElementById('cnpjAtividade').textContent =
          `${data.atividade_principal[0].code} – ${data.atividade_principal[0].text}`;

        carga.classList.add('d-none');
        corpo.classList.remove('d-none');
      })
      .catch(err=>{
        carga.classList.add('d-none');
        erro.textContent = err.message;
        erro.classList.remove('d-none');
      });
  });
});

// SCRIPT PARA MODAL DE FILTRO
  document.addEventListener('DOMContentLoaded', function(){
    function toggleFilters(){
      const col = document.getElementById('filterColumn').value;
      document.getElementById('filterPeriod').style.display  = col==='periodo' ? 'block' : 'none';
      document.getElementById('filterUsuario').style.display = col==='usuario'? 'block' : 'none';
      document.getElementById('filterPlugin').style.display  = col==='plugin' ? 'block' : 'none';
      document.getElementById('filterStatus').style.display  = col==='status' ? 'block' : 'none';
    }
    document.getElementById('filterColumn')
            .addEventListener('change', toggleFilters);
    // ao abrir modal, ajusta visibilidade atual
    document.getElementById('filterModal')
            .addEventListener('shown.bs.modal', toggleFilters);
  });

  // alterna entre data-range e competência
  function toggleCycleMode() {
    const useCycle = document.getElementById('use_cycle').checked;
    document.getElementById('dateRangeContainer').style.display     = useCycle ? 'none' : 'flex';
    document.getElementById('competenciaContainer').style.display   = useCycle ? 'block' : 'none';
  }

  document.addEventListener('DOMContentLoaded', function(){
    // quando abrir o modal de filtro, ajusta visibilidade
    const filterModal = document.getElementById('filterModal');
    filterModal.addEventListener('shown.bs.modal', toggleCycleMode);

    // ao marcar/desmarcar o checkbox
    document.getElementById('use_cycle')
            .addEventListener('change', toggleCycleMode);
  });

  //SCRIPT PARA CONTROLE DE PREENCHIMENTO DE DATA
  document.addEventListener('DOMContentLoaded', function() {
    const cadastroInput     = document.getElementById('editar_data');
    const faturamentoInput  = document.getElementById('editar_data_faturamento');
    const editForm          = document.querySelector('#modalEditarIndicacao form');

    if (!cadastroInput || !faturamentoInput || !editForm) return;

    // 1) Garante que data de faturamento nunca possa ser menor que data de cadastro
    function updateFaturamentoMin() {
      faturamentoInput.min = cadastroInput.value;
      // opcional: se já estiver fora do novo min, limpa ou ajusta
      if (faturamentoInput.value && faturamentoInput.value < cadastroInput.value) {
        faturamentoInput.value = cadastroInput.value;
      }
    }

    // dispara sempre que o usuário mudar a data de cadastro
    cadastroInput.addEventListener('change', updateFaturamentoMin);

    // ajusta assim que o modal abrir
    $('#modalEditarIndicacao').on('shown.bs.modal', updateFaturamentoMin);

    // 2) Validação extra antes de submeter
    editForm.addEventListener('submit', function(e) {
      if (faturamentoInput.value < cadastroInput.value) {
        e.preventDefault();
        showToast('Data de faturamento não pode ser menor que a data de cadastro','error');
      }
    });
  });

  //CONTROLE PARA NÂO DEIXAR DATA FATURAMENTO MENOR QUE DATA CADASTRO
document.addEventListener('DOMContentLoaded', () => {
  const cadastroInput    = document.getElementById('editar_data');
  const faturamentoInput = document.getElementById('editar_data_faturamento');
  const form             = document.querySelector('#modalEditarIndicacao form');
  if (!cadastroInput || !faturamentoInput || !form) return;

  // 45 dias exatos a partir do 1º do mês de cadastro
  function getCycleWindow(cadDate) {
    const start = new Date(cadDate.getFullYear(), cadDate.getMonth(), 1);
    const end   = new Date(start);
    end.setDate(start.getDate() + 44);
    return { start, end };
  }

  // Atualiza min/max no picker de faturamento sempre que cadastro muda
  cadastroInput.addEventListener('change', () => {
    if (!cadastroInput.value) return;
    const [y, m] = cadastroInput.value.split('-').map(Number);
    faturamentoInput.min = cadastroInput.value;
    faturamentoInput.max = getCycleWindow(new Date(y, m-1, 1))
                             .end.toISOString().slice(0,10);
    checkCycle();
  });

  function checkCycle() {
    const cadVal = cadastroInput.value;
    const fatVal = faturamentoInput.value;
    if (!cadVal || !fatVal) return true;

    const [yC, mC, dC] = cadVal.split('-').map(Number);
    const [yF, mF, dF] = fatVal.split('-').map(Number);
    const cadDate = new Date(yC, mC-1, dC);
    const fatDate = new Date(yF, mF-1, dF);

    // 1) Se for mesmo mês/ano, sempre válido
    if (yF === yC && (mF-1) === (mC-1)) {
      return true;
    }

    // 2) Senão, aplica janela de 45 dias
    const { start, end } = getCycleWindow(cadDate);
    if (fatDate < start || fatDate > end) {
      const fmt = d => d.toLocaleDateString('pt-BR');
      showEscapeToast(
        `Fora do ciclo (${fmt(start)} → ${fmt(end)}).`
      );
      return false;
    }
    return true;
  }

  faturamentoInput.addEventListener('change', checkCycle);
  form.addEventListener('submit', e => {
    if (!checkCycle()) e.preventDefault();
  });
  $('#modalEditarIndicacao').on('shown.bs.modal', () => {
    cadastroInput.dispatchEvent(new Event('change'));
  });
});


// cria ou recupera o container de escape
function getEscapeContainer() {
  let c = document.getElementById('escape-toast-container');
  if (!c) {
    c = document.createElement('div');
    c.id = 'escape-toast-container';
    document.body.appendChild(c);
  }
  return c;
}

// mostra toast fora do modal, com duração estendida (5s)
function showEscapeToast(message) {
  const container = getEscapeContainer();
  const toast = document.createElement('div');
  toast.className = 'toast error';  // reutiliza classes de estilo
  toast.textContent = message;
  container.appendChild(toast);
  // força reflow para animação
  requestAnimationFrame(() => toast.classList.add('show'));
  // remove após 5s
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => container.removeChild(toast), 300);
  }, 5000);
}
</script>


</body>
</html>
