<?php 
require '../Config/Database.php';
require_once __DIR__ . '/../Includes/auth.php';

$usuario_id = $_SESSION['usuario_id'];
$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';
// Ranking de média por analista
$sql_ranking = "SELECT 
                  a.idAtendente, 
                  u.Nome as usuario_nome, 
                  AVG(a.Nota) as mediaNotas
                FROM TB_ANALISES a
                JOIN TB_USUARIO u ON a.idAtendente = u.Id
                WHERE a.idStatus = 1";
if (!empty($_GET['data_inicio'])) {
    $sql_ranking .= " AND a.Hora_ini >= '{$_GET['data_inicio']}'";
}
if (!empty($_GET['data_fim'])) {
    $sql_ranking .= " AND a.Hora_ini <= '{$_GET['data_fim']}'";
}
if (!empty($_GET['analista'])) {
    $sql_ranking .= " AND a.idAtendente = '{$_GET['analista']}'";
}
$sql_ranking .= " GROUP BY a.idAtendente, u.Nome
                  ORDER BY mediaNotas DESC";
$result_ranking = $conn->query($sql_ranking);
$ranking = [];
if ($result_ranking) {
    while ($row = $result_ranking->fetch_assoc()) {
         $ranking[] = $row;
    }
}

// Ranking Geral de Nota (Média da equipe)
$sql_media_geral = "SELECT 
                        ROUND(AVG(mediaNotas), 2) AS MediaGeral
                    FROM (
                        SELECT 
                            a.idAtendente, 
                            u.Nome AS usuario_nome, 
                            ROUND(AVG(a.Nota), 2) AS mediaNotas
                        FROM TB_ANALISES a
                        JOIN TB_USUARIO u ON a.idAtendente = u.Id
                        WHERE a.idStatus = 1";
if (!empty($_GET['data_inicio'])) {
    $sql_media_geral .= " AND a.Hora_ini >= '{$_GET['data_inicio']}'";
}
if (!empty($_GET['data_fim'])) {
    $sql_media_geral .= " AND a.Hora_ini <= '{$_GET['data_fim']}'";
}
if (!empty($_GET['analista'])) {
    $sql_media_geral .= " AND a.idAtendente = '{$_GET['analista']}'";
}
$sql_media_geral .= " GROUP BY a.idAtendente, u.Nome) AS sub;";
$stmt_media = $conn->prepare($sql_media_geral);
$stmt_media->execute();
$resultado_media = $stmt_media->get_result()->fetch_assoc();
$media_geral = number_format($resultado_media['MediaGeral'], 2, '.', '');

// Consulta para obter todos os usuários para a seção "Acessos aos Usuários"
$sql_usuarios = "SELECT Id, Nome FROM TB_USUARIO WHERE CARGO in ('User', 'Conversor', 'Viewer') ORDER BY Nome";
$stmt_usuarios_acessos = $conn->prepare($sql_usuarios);
$stmt_usuarios_acessos->execute();
$resultado_usuarios_acessos = $stmt_usuarios_acessos->get_result();

// Consulta para preencher o dropdown do filtro
$stmt_usuarios_dropdown = $conn->prepare($sql_usuarios);
$stmt_usuarios_dropdown->execute();
$resultado_usuarios_dropdown = $stmt_usuarios_dropdown->get_result();

// Consulta para o gráfico
$sql_grafico = "SELECT  
                  usu.Nome as Nome, 
                  DATE_FORMAT(tas.Hora_ini, '%Y-%m') AS Mes,
                  AVG(tas.Nota) as MediaNota
                FROM TB_ANALISES tas
                JOIN TB_USUARIO usu ON tas.idAtendente = usu.Id
                WHERE tas.idStatus = 1";
if (!empty($_GET['data_inicio'])) {
    $sql_grafico .= " AND tas.Hora_ini >= '{$_GET['data_inicio']}'";
}
if (!empty($_GET['data_fim'])) {
    $sql_grafico .= " AND tas.Hora_ini <= '{$_GET['data_fim']}'";
}
if (!empty($_GET['analista'])) {
    $sql_grafico .= " AND tas.idAtendente = '{$_GET['analista']}'";
}
$sql_grafico .= " GROUP BY usu.Nome, Mes ORDER BY Mes, Nome";
$stmt_grafico = $conn->prepare($sql_grafico);
$stmt_grafico->execute();
$resultado_grafico = $stmt_grafico->get_result();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Painel N3 - Totalizadores</title>
  <link rel="icon" href="../Public/Image/LogoTituto.png" type="image/png">
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icones -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Google Fonts: Montserrat -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- Css personalizado -->
  <link rel="stylesheet" href="../Public/dashboard.css">
</head>
<body class="bg-light"> 
  <div class="d-flex-wrapper">
    <!-- Sidebar fixa para desktop -->
    <?php include __DIR__ . '/../components/sidebar.php'; ?>
    
    <!-- Conteúdo Principal -->
    <div class="w-100 ">
      <div class="header">
        <h3>Totalizadores</h3>
        <div class="user-info">
          <span>Bem-vindo, <?php echo htmlspecialchars($_SESSION['usuario_nome'], ENT_QUOTES, 'UTF-8'); ?>!</span>
          <a href="logout.php" class="btn btn-danger">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
          </a>
        </div>
      </div>
      
    <!-- Substitua a seção de filtros existente por este bloco -->
    <div class="container mt-4">
    <!-- Botão que abre o modal de filtros -->
    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
        <i class="fa-solid fa-filter"></i>
    </button>
    </div>

    <!-- Modal de Filtro com select -->
<!-- Modal de Filtro com select -->
<div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <form method="GET" action="dashboard.php">
        <div class="modal-header">
          <h5 class="modal-title" id="filterModalLabel">Filtro</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row mb-3">
            <!-- Campo select para escolher o tipo de filtro - use col-12 para ocupar toda a largura -->
            <div class="col-12">
              <label for="filterType" class="form-label">Filtrar por:</label>
              <select class="form-select" id="filterType" name="filterType">
                <option value="period" selected>Período</option>
                <option value="analyst">Analista</option>
              </select>
            </div>
          </div>
          <div class="row" id="periodFields">
            <!-- Campos para filtro por período -->
            <div class="col-md-6 mb-3">
              <label for="data_inicio" class="form-label">Data Início:</label>
              <input type="date" class="form-control" id="data_inicio" name="data_inicio">
            </div>
            <div class="col-md-6 mb-3">
              <label for="data_fim" class="form-label">Data Fim:</label>
              <input type="date" class="form-control" id="data_fim" name="data_fim">
            </div>
          </div>
          <div class="row" id="analystField" style="display: none;">
            <!-- Campo para filtro por analista -->
            <div class="col-12 mb-3">
              <label for="analista" class="form-label">Selecione o Analista:</label>
              <select class="form-select" id="analista" name="analista">
                <option value="">Selecione</option>
                <?php 
                while ($row = $resultado_usuarios_dropdown->fetch_assoc()) { ?>
                  <option value="<?php echo $row['Id']; ?>">
                    <?php echo $row['Nome']; ?>
                  </option>
                <?php } ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="window.location.href='dashboard.php'">Limpar Filtro</button>
          <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
      </form>
    </div>
  </div>
</div>

        <!-- Script para alternar entre os campos de período e analista -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const filterTypeSelect = document.getElementById('filterType');
  const periodFields = document.getElementById('periodFields');
  const analystField = document.getElementById('analystField');

  filterTypeSelect.addEventListener('change', function() {
    if (this.value === 'period') {
      periodFields.style.display = 'flex';
      analystField.style.display = 'none';
    } else if (this.value === 'analyst') {
      periodFields.style.display = 'none';
      analystField.style.display = 'flex';
    }
  });
});
</script>
      
    <!-- Linha de KPIs -->
    <div class="container justify-content-center mt-4">
        <div class="row g-4 kpi-row">
          <!-- Card: Média de Notas -->
          <div class="col-md-4">
            <div class="kpi-card">
              <h2><?php echo $media_geral; ?> ⭐</h2>
              <p>Média de Notas da Equipe</p>
            </div>
          </div>
          <!-- Card: Melhor Analista -->
          <div class="col-md-4">
            <div class="kpi-card">
              <h2>
                <?php 
                if (count($ranking) > 0) {
                  echo ($ranking[0]['usuario_nome']);
                } else {
                  echo '-';
                }
                ?>
              </h2>
              <p>Analista Destaque</p>
            </div>
          </div>
        </div>
    </div>
      
      <!-- Accordion para Gráfico, Ranking e Acessos -->
      <div class="container mt-4">
        <div class="accordion" id="dashboardAccordion">
          <!-- Accordion Item: Gráfico -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingChart">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseChart" aria-expanded="true" aria-controls="collapseChart">
                Evolução da Média de Notas (Mensal)
              </button>
            </h2>
            <div id="collapseChart" class="accordion-collapse collapse" aria-labelledby="headingChart" data-bs-parent="#dashboardAccordion">
              <div class="accordion-body">
                <div class="card chart-card">
                  <div class="card-body">
                    <canvas id="graficoNotas"></canvas>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Accordion Item: Ranking Detalhado -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingRanking">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRanking" aria-expanded="false" aria-controls="collapseRanking">
                Ranking Detalhado
              </button>
            </h2>
            <div id="collapseRanking" class="accordion-collapse collapse" aria-labelledby="headingRanking" data-bs-parent="#dashboardAccordion">
              <div class="accordion-body">
                <div class="card ranking-card">
                  <div class="card-body">
                    <?php if (count($ranking) > 0): ?>
                      <ul class="list-group ranking-scroll">
                        <?php foreach ($ranking as $index => $rank): ?>
                          <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="ranking-name">
                              <?php
                              if ($index == 0) { echo "🥇 "; }
                              elseif ($index == 1) { echo "🥈 "; }
                              elseif ($index == 2) { echo "🥉 "; }
                              else { echo ($index + 1) . "º "; }
                              echo $rank['usuario_nome'];
                              ?>
                            </span>
                            <span class="badge bg-primary rounded-pill">
                              <?php echo number_format($rank['mediaNotas'], 2, ',', '.'); ?>
                            </span>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    <?php else: ?>
                      <p class="text-center">Nenhum ranking disponível.</p>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Accordion Item: Acessos aos Usuários -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingAccess">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAccess" aria-expanded="false" aria-controls="collapseAccess">
                Acessos aos Usuários
              </button>
            </h2>
            <div id="collapseAccess" class="accordion-collapse collapse" aria-labelledby="headingAccess" data-bs-parent="#dashboardAccordion">
              <div class="accordion-body">
                <div class="card access-card">
                  <div class="card-body">
                    <ul class="list-group ranking-scroll">
                      <?php while ($user = $resultado_usuarios_acessos->fetch_assoc()): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                          <span><?php echo $user['Nome']; ?></span>
                          <a href="user.php?usuario_id=<?php echo $user['Id']; ?>" target="_blank" class="btn btn-primary btn-sm">Acessar</a>
                        </li>
                      <?php endwhile; ?>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div><!-- Fim do Accordion -->
      </div>
    </div>
  </div>

  <!-- Scripts do Chart.js e Bootstrap -->
  <script>
    <?php
    if (!empty($_GET['data_inicio']) && !empty($_GET['data_fim'])) {
        $start = new DateTime($_GET['data_inicio']);
        $end = new DateTime($_GET['data_fim']);
        $labels = [];
        $interval = new DateInterval('P1M');
        $endLabel = clone $end;
        $endLabel->modify('first day of next month');
        $period = new DatePeriod($start, $interval, $endLabel);
        foreach($period as $dt) {
            $labels[] = $dt->format('Y-m');
        }
    } else {
        $year = date("Y");
        $labels = [];
        for ($m=1; $m<=12; $m++){
             $labels[] = sprintf("%s-%02d", $year, $m);
        }
    }
    echo "const labels = " . json_encode($labels) . ";\n";
    
    $analistaData = [];
    while ($row = $resultado_grafico->fetch_assoc()) {
        $mes = $row['Mes'];
        $nome = $row['Nome'];
        $mediaNota = number_format($row['MediaNota'], 2, '.', '');
        if (!isset($analistaData[$mes])) {
            $analistaData[$mes] = [];
        }
        $analistaData[$mes][$nome] = $mediaNota;
    }
    ksort($analistaData);
    $analistasUnion = [];
    foreach ($analistaData as $mesData) {
        foreach ($mesData as $nome => $nota) {
            $analistasUnion[$nome] = true;
        }
    }
    $analistas = array_keys($analistasUnion);
    echo "const datasets = [];\n";
    foreach ($analistas as $analista) {
        echo "datasets.push({\n";
        echo "  label: '" . addslashes($analista) . "',\n";
        echo "  data: [],\n";
        echo "  fill: false,\n";
        echo "  borderWidth: 2,\n";
        echo "  tension: 0.3\n";
        echo "});\n";
    }
    foreach ($labels as $mes) {
        foreach ($analistas as $index => $analista) {
            $nota = (isset($analistaData[$mes]) && isset($analistaData[$mes][$analista])) ? $analistaData[$mes][$analista] : "null";
            echo "datasets[$index].data.push(" . $nota . ");\n";
        }
    }
    ?>

    const ctx = document.getElementById('graficoNotas').getContext('2d');
     const graficoNotas = new Chart(ctx, {
    type: 'line',
    data: { labels, datasets },
    options: {
      responsive: true,
      scales: {
        x: { title: { display: true, text: 'Mês' } },
        y: {
          title: { display: true, text: 'Média das Notas' },
          min: 0,
          max: 6,
          ticks: {
            stepSize: 1,              // 0,1,2,3,4,5
            callback: v => v          // mostra apenas o número inteiro
          }
        }
      },
      plugins: { legend: { position: 'top' } }
    }
  });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
