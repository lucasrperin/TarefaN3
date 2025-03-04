<?php 
session_start();
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['usuario_id'])) {
    $_SESSION['usuario_id'] = $_POST['usuario_id'];
    header("Location: user.php");
    exit();
}
require '../Config/Database.php';

$usuario_id = $_SESSION['usuario_id'];

// Ranking de média por analista (apenas para exibição, sem links)
$sql_ranking = "SELECT
                    usu.Id AS idAtendente,
                    usu.Nome AS Nome,
                    AVG(tas.Nota) AS MediaNota
                FROM TB_ANALISES tas
                LEFT JOIN TB_USUARIO usu ON usu.Id = tas.idAtendente
                WHERE tas.Nota IS NOT NULL
                GROUP BY usu.Id, usu.Nome
                ORDER BY MediaNota DESC";
$stmt_ranking = $conn->prepare($sql_ranking);
$stmt_ranking->execute();
$resultado_ranking = $stmt_ranking->get_result();

// Ranking Geral de Nota
$sql_media_geral = "SELECT AVG(Nota) as MediaGeral FROM TB_ANALISES WHERE Nota IS NOT NULL";
$stmt_media = $conn->prepare($sql_media_geral);
$stmt_media->execute();
$resultado_media = $stmt_media->get_result()->fetch_assoc();
$media_geral = number_format($resultado_media['MediaGeral'], 2, '.', '');

// Análises por período (não utilizado no gráfico, mas mantido)
$sql_analises_mes = "SELECT DATE_FORMAT(Hora_ini, '%Y-%m') as Mes, COUNT(*) as Total
                     FROM TB_ANALISES 
                     GROUP BY Mes 
                     ORDER BY Mes";
$stmt_analises_mes = $conn->prepare($sql_analises_mes);
$stmt_analises_mes->execute();
$dados_analises = $stmt_analises_mes->get_result();

// Filtro (para outras seções, se necessário)
$sql_filtro = "SELECT * FROM TB_ANALISES WHERE 1=1";
if (!empty($_GET['data_inicio'])) {
    $sql_filtro .= " AND Hora_ini >= '{$_GET['data_inicio']}'";
}
if (!empty($_GET['data_fim'])) {
    // Usando o campo Hora_ini para manter a consistência com o gráfico
    $sql_filtro .= " AND Hora_ini <= '{$_GET['data_fim']}'";
}
if (!empty($_GET['analista'])) {
    $sql_filtro .= " AND idUsuario = '{$_GET['analista']}'";
}
$stmt_filtro = $conn->prepare($sql_filtro);
$stmt_filtro->execute();
$resultado_filtrado = $stmt_filtro->get_result();

// Consulta para obter todos os usuários para a seção "Acessos aos Usuários"
$sql_usuarios = "SELECT Id, Nome FROM TB_USUARIO WHERE CARGO = 'User'";
$stmt_usuarios_acessos = $conn->prepare($sql_usuarios);
$stmt_usuarios_acessos->execute();
$resultado_usuarios_acessos = $stmt_usuarios_acessos->get_result();

// Consulta separada para preencher o dropdown do filtro
$stmt_usuarios_dropdown = $conn->prepare($sql_usuarios);
$stmt_usuarios_dropdown->execute();
$resultado_usuarios_dropdown = $stmt_usuarios_dropdown->get_result();

// Consulta para obter a média de notas por analista e mês para o gráfico
$sql_grafico = "
    SELECT 
        usu.Nome AS Nome,
        DATE_FORMAT(tas.Hora_ini, '%Y-%m') AS Mes,
        AVG(tas.Nota) AS MediaNota
    FROM 
        TB_ANALISES tas
    LEFT JOIN 
        TB_USUARIO usu ON usu.Id = tas.idAtendente
    WHERE 
        tas.Nota IS NOT NULL
";
if (!empty($_GET['data_inicio'])) {
    $sql_grafico .= " AND tas.Hora_ini >= '{$_GET['data_inicio']}'";
}
if (!empty($_GET['data_fim'])) {
    $sql_grafico .= " AND tas.Hora_ini <= '{$_GET['data_fim']}'";
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
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Ícones personalizados -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <li><a class="dropdown-item" href="../index.php">Tarefas N3</a></li>
            </ul>
        </div>
        <span class="text-white">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
        <a href="../index.php" class="btn btn-danger">
            <i class="fa-solid fa-arrow-left me-2" style="font-size: 0.8em;"></i>Voltar
        </a>
    </div>
</nav>

<!-- Filtro -->
<form method="GET" class="container mt-4">
    <div class="row g-3">
        <div class="col-auto">
            <label for="data_inicio" class="form-label">Período:</label>
            <input type="date" name="data_inicio" id="data_inicio" class="form-control">
        </div>
        <div class="col-auto">
            <label for="data_fim" class="form-label">Até:</label>
            <input type="date" name="data_fim" id="data_fim" class="form-control">
        </div>
        <div class="col-auto">
            <label for="analista" class="form-label">Analista:</label>
            <select name="analista" id="analista" class="form-select">
                <option value="">Todos</option>
                <?php
                while ($user = $resultado_usuarios_dropdown->fetch_assoc()) {
                    echo "<option value='{$user['Id']}'>{$user['Nome']}</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-auto align-self-end">
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
    </div>
</form>

<div class="container mt-4 ">
    <div class="row d-flex align-items-start">
        <!-- Bloco 1: Média de Notas da Equipe -->
        <div class="col-md-3 ">
            <div class="card custom-card bg-blue ranking-media">
                <div class="card-header header-blue">Média de Notas da Equipe</div>
                <div class="card-body">
                    <h5 class="card-title"><?php echo $media_geral; ?>⭐</h5>
                </div>
            </div>
        </div>

        <!-- Bloco 2: Ranking de Analistas (apenas exibição) -->
        <div class="ranking col-md-4">
            <div class="card custom-card bg-white ranking-card">
                <div class="card-header header-white">Ranking de Analistas</div>
                <div class="card-body ranking-body">
                    <ul class="no-list-style">
                        <?php 
                        $contador = 0;
                        while ($analista = $resultado_ranking->fetch_assoc()) {
                            $mediaNota = number_format($analista['MediaNota'], 2, '.', '');
                            $medalha = '';
                            $posicaoClass = '';
                            if ($contador == 0) {
                                $medalha = "🥇";
                            } elseif ($contador == 1) {
                                $medalha = "🥈";
                            } elseif ($contador == 2) {
                                $medalha = "🥉";
                            }
                            if ($contador >= 3) {
                                $posicao = ($contador + 1) . "º";
                                $posicaoClass = 'position-number';
                            } else {
                                $posicao = $medalha;
                            }
                            echo "<li><strong class='$posicaoClass'>$posicao</strong> {$analista['Nome']} <strong><span class='rank-media'>$mediaNota</span></strong></li>";
                            $contador++;
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Gráfico de Linhas -->
<div  class="container  col-lg-6 ">
    <div  class="card">
        <div  class="card-header">Evolução da Média de Notas dos Analistas (Mensal)</div>
        <div  class="card-body">
            <canvas id="graficoNotas"></canvas>
        </div>
    </div>
</div>
        <!-- Bloco 3: Acessos aos Usuários (à direita) -->
        <div class="col-md-4">
            <div class="card custom-card">
                <div class="card-header">Acessos aos Usuários</div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php while ($user = $resultado_usuarios_acessos->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo $user['Nome']; ?></span>
                                <form method="post" action="dashboard.php" style="margin: 0;">
                                    <input type="hidden" name="usuario_id" value="<?php echo $user['Id']; ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">Acessar</button>
                                </form>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>



<script>
    // Gerar os labels (meses) de forma contínua
    <?php
    // Cria um array de labels com todos os meses entre data_inicio e data_fim (ou um padrão de 12 meses do ano atual)
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
    
    // Ordena os meses cronologicamente (não estritamente necessário, pois os labels já foram definidos)
    ksort($analistaData);
    
    // Obter a união de todos os analistas presentes em qualquer mês
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
        // Aqui você pode definir cores fixas ou gerar cores aleatórias para cada analista
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
