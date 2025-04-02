<?php 
require '../Config/Database.php';

session_start();

// Verifica se o usuário está logado; se não, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
  }

$usuario_id = $_SESSION['usuario_id'];

// Ranking de média por analista (apenas para exibição, sem links)
$sql_ranking = "SELECT 
                  a.idAtendente, 
                  u.Nome as usuario_nome, 
                  AVG(a.Nota) as mediaNotas
                FROM TB_ANALISES a
                JOIN TB_USUARIO u ON a.idAtendente = u.Id
                WHERE a.idStatus = 1";

// Filtros por data
if (!empty($_GET['data_inicio'])) {
    $sql_ranking .= " AND a.Hora_ini >= '{$_GET['data_inicio']}'";
}
if (!empty($_GET['data_fim'])) {
    $sql_ranking .= " AND a.Hora_ini <= '{$_GET['data_fim']}'";
}
// Filtro por analista
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

// Ranking Geral de Nota
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
                        // Filtros por data
                        if (!empty($_GET['data_inicio'])) {
                            $sql_media_geral .= " AND a.Hora_ini >= '{$_GET['data_inicio']}'";
                        }
                        if (!empty($_GET['data_fim'])) {
                            $sql_media_geral .= " AND a.Hora_ini <= '{$_GET['data_fim']}'";
                        }
                        // Filtro por analista
                        if (!empty($_GET['analista'])) {
                            $sql_media_geral .= " AND a.idAtendente = '{$_GET['analista']}'";
                        }
                        $sql_media_geral .= " GROUP BY a.idAtendente, u.Nome) AS sub;";
$stmt_media = $conn->prepare($sql_media_geral);
$stmt_media->execute();
$resultado_media = $stmt_media->get_result()->fetch_assoc();
$media_geral = number_format($resultado_media['MediaGeral'], 2, '.', '');

// Filtro (para outras seções, se necessário)
$sql_filtro = "SELECT * FROM TB_ANALISES WHERE 1=1";
if (!empty($_GET['data_inicio'])) {
    $sql_filtro .= " AND Hora_ini >= '{$_GET['data_inicio']}'";
}
if (!empty($_GET['data_fim'])) {
    $sql_filtro .= " AND Hora_ini <= '{$_GET['data_fim']}'";
}
if (!empty($_GET['analista'])) {
    $sql_filtro .= " AND idUsuario = '{$_GET['analista']}'";
}
$stmt_filtro = $conn->prepare($sql_filtro);
$stmt_filtro->execute();
$resultado_filtrado = $stmt_filtro->get_result();

// Consulta para obter todos os usuários para a seção "Acessos aos Usuários"
$sql_usuarios = "SELECT Id, Nome FROM TB_USUARIO WHERE CARGO in ('User', 'Conversor') ORDER BY Nome";
$stmt_usuarios_acessos = $conn->prepare($sql_usuarios);
$stmt_usuarios_acessos->execute();
$resultado_usuarios_acessos = $stmt_usuarios_acessos->get_result();

// Consulta separada para preencher o dropdown do filtro
$stmt_usuarios_dropdown = $conn->prepare($sql_usuarios);
$stmt_usuarios_dropdown->execute();
$resultado_usuarios_dropdown = $stmt_usuarios_dropdown->get_result();

// Consulta para obter a média de notas por analista e mês para o gráfico
$sql_grafico = "SELECT  
                  usu.Nome as Nome, 
                  DATE_FORMAT(tas.Hora_ini, '%Y-%m') AS Mes,
                  AVG(tas.Nota) as MediaNota
                FROM TB_ANALISES tas
                JOIN TB_USUARIO usu ON tas.idAtendente = usu.Id
                WHERE 
                    tas.idStatus = 1";
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
  <title>Totalizadores</title>
  <!-- Arquivo CSS personalizado -->
  <link href="../Public/dashboard.css" rel="stylesheet">
  <link rel="icon" href="..\Public\Image\icone2.png" type="image/png">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Ícones personalizados -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    /* Define uma altura mínima para o container da linha */
    .equal-height-container {
      min-height: 70vh;
    }
  </style>
</head>
<body class="bg-light">
  <nav class="navbar navbar-dark bg-dark">
    <div class="container d-flex justify-content-between align-items-center">
      <!-- Botão Hamburguer com Dropdown -->
      <div class="dropdown">
        <button class="navbar-toggler" type="button" id="menuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="navbar-toggler-icon"></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="menuDropdown">
            <li><a class="dropdown-item" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a></li>
            <li><a class="dropdown-item" href="escutas.php"><i class="fa-solid fa-headphones me-2"></i>Escutas</a></li>
            <li><a class="dropdown-item" href="folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a></li>
            <li><a class="dropdown-item" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a></li>
            <li><a class="dropdown-item" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-1"></i>Indicações</a></li>
            <li><a class="dropdown-item" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a></li>
            <li><a class="dropdown-item" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a></li>
        </ul>
      </div>
      <span class="text-white">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
      <a href="menu.php" class="btn btn-danger">
        <i class="fa-solid fa-arrow-left me-2" style="font-size: 0.8em;"></i>Voltar
      </a>
    </div>
  </nav>

  <!-- Filtro -->
  <form method="GET" class="container mt-4">
        <div class="row g-3">
            <div class="col-auto">
                <label for="data_inicio" class="form-label">Período:</label>
                <input type="date" name="data_inicio" id="data_inicio" class="form-control" value="<?php echo isset($_GET['data_inicio']) ? $_GET['data_inicio'] : ''; ?>">
            </div>
            <div class="col-auto">
                <label for="data_fim" class="form-label">Até:</label>
                <input type="date" name="data_fim" id="data_fim" class="form-control" value="<?php echo isset($_GET['data_fim']) ? $_GET['data_fim'] : ''; ?>">
            </div>
            <div class="col-auto">
                <label for="analista" class="form-label">Analista:</label>
                <select name="analista" id="analista" class="form-select">
                    <option value="">Selecione</option>
                    <?php 
                    // Certifique-se de que $resultado_usuarios_dropdown esteja disponível e
                    // se necessário, armazene os resultados em um array para não esgotar o ponteiro.
                    while ($row = $resultado_usuarios_dropdown->fetch_assoc()) { ?>
                        <option value="<?php echo $row['Id']; ?>" <?php echo (isset($_GET['analista']) && $_GET['analista'] == $row['Id']) ? 'selected' : ''; ?>>
                        <?php echo $row['Nome']; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-auto align-self-end">
                <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                <a href="dashboard.php" class="btn btn-secondary btn-sm">Limpar Filtros</a>
            </div>
        </div>
    </form>

  <!-- Container com altura mínima para que as 3 colunas fiquem iguais -->
  <div class="container mt-4 equal-height-container">
    <div class="row align-items-stretch">
      <!-- Coluna Esquerda: Média de Notas + Ranking -->
      <div class="col-md-3">
        <!-- Container flex para empilhar os 2 cards -->
        <div class="d-flex flex-column h-100">
          <!-- Card da Média de Notas (altura natural) -->
            <div class="card custom-card bg-blue ranking-media mb-4">
                <div class="card-header header-blue text-center">
                    Média de Notas da Equipe
                </div>
                <div class="card-body text-center">
                    <h5 class="card-title"><?php echo $media_geral; ?>⭐</h5>
                </div>
            </div>
          <!-- Card do Ranking (ocupa o espaço restante) -->
            <div class="card text-center card-ranking flex-grow-1">
                <div class="card-header header-blue text-center">Ranking</div>
                    <div class="card-body">  
                    <?php if (count($ranking) > 0): ?>
                        <div class="ranking-scroll">
                            <ul class="list-group">
                                <?php foreach ($ranking as $index => $rank): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span class="ranking-name">
                                            <?php
                                            if ($index == 0) {
                                                echo "🥇 ";
                                            } elseif ($index == 1) {
                                                echo "🥈 ";
                                            } elseif ($index == 2) {
                                                echo "🥉 ";
                                            } else {
                                                echo ($index + 1) . "º ";
                                            }
                                            echo $rank['usuario_nome'];
                                            ?>
                                        </span>
                                        <span class="badge bg-primary rounded-pill">
                                            <?php echo number_format($rank['mediaNotas'], 2, ',', '.'); ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php else: ?>
                            <p>Nenhum ranking disponível.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

      <!-- Coluna Central: Gráfico -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header text-center">Evolução da Média de Notas dos Analistas (Mensal)</div>
                <div class="card-body mt-4">
                    <canvas id="graficoNotas"></canvas>
                </div>
            </div>
        </div>

      <!-- Coluna Direita: Acessos aos Usuários -->
        <div class="col-md-3">
            <div class="card custom-card h-100">
                <div class="card-header text-center">Acessos aos Usuários</div>
                    <div class="card-body">
                        <div class="access-scroll">
                            <ul class="list-group">
                            <?php while ($user = $resultado_usuarios_acessos->fetch_assoc()): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo $user['Nome']; ?></span>
                                <form method="post" action="user.php" style="margin: 0;" target="_blank">
                                    <input type="hidden" name="usuario_id" value="<?php echo $user['Id']; ?>">
                                    <button type="submit" class="btn btn-primary btn-sm ">Acessar</button>
                                </form>
                                </li>
                            <?php endwhile; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

  <script>
    // Gerar os labels (meses) de forma contínua
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
    ?>

    // Organiza os dados retornados da consulta do gráfico em um array multidimensional
    <?php
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
    
    echo "const labels = " . json_encode($labels) . ";\n";
    echo "const datasets = [];\n";
    foreach ($analistas as $analista) {
        echo "datasets.push({\n";
        echo "  label: '" . addslashes($analista) . "',\n";
        echo "  data: [],\n";
        echo "  fill: false,\n";
        echo "  borderWidth: 2\n";
        echo "});\n";
    }
    foreach ($labels as $mes) {
        foreach ($analistas as $index => $analista) {
            $nota = (isset($analistaData[$mes]) && isset($analistaData[$mes][$analista])) ? $analistaData[$mes][$analista] : "null";
            echo "datasets[$index].data.push(" . $nota . ");\n";
        }
    }
    ?>
    
    // Configuração do gráfico usando Chart.js no modo linha
    const ctx = document.getElementById('graficoNotas').getContext('2d');
    const graficoNotas = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Mês'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Média das Notas'
                    },
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                }
            }
        }
    });
  </script>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
