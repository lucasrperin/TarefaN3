<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../Login.php");
    exit();
}

require '../Config/Database.php';

$usuario_id = $_SESSION['usuario_id'];

// Ranking de média por analista
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

// Analiste por período
$sql_analises_mes = "SELECT DATE_FORMAT(Hora_ini, '%Y-%m') as Mes, COUNT(*) as Total
                     FROM TB_ANALISES 
                     GROUP BY Mes 
                     ORDER BY Mes";
$stmt_analises_mes = $conn->prepare($sql_analises_mes);
$stmt_analises_mes->execute();
$dados_analises = $stmt_analises_mes->get_result();

// Função de filtro
$sql_filtro = "SELECT * FROM TB_ANALISES WHERE 1=1";
if (!empty($_GET['data_inicio'])) {
    $sql_filtro .= " AND Hora_ini >= '{$_GET['data_inicio']}'";
}
if (!empty($_GET['data_fim'])) {
    $sql_filtro .= " AND Hora_fim <= '{$_GET['data_fim']}'";
}
if (!empty($_GET['analista'])) {
    $sql_filtro .= " AND idUsuario = '{$_GET['analista']}'";
}
$stmt_filtro = $conn->prepare($sql_filtro);
$stmt_filtro->execute();
$resultado_filtrado = $stmt_filtro->get_result();
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

<div class="container mt-4">
    <div class="row align-items-start">
        <!-- Bloco 1: Média de Notas da Equipe (classe bg-blue define o fundo) -->
        <div class="col-md-3">
            <div class="card custom-card bg-blue ranking-media">
                <div class="card-header header-blue">Média de Notas da Equipe</div>
                <div class="card-body">
                    <h5 class="card-title"><?php echo $media_geral; ?>⭐</h5>
                </div>
            </div>
        </div>

        <!-- Bloco 2: Ranking de Analistas (classe bg-white define o fundo) -->
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
    </div>
</div>

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
                $sql_usuarios = "SELECT Id, Nome FROM TB_USUARIO";
                $stmt_usuarios = $conn->prepare($sql_usuarios);
                $stmt_usuarios->execute();
                $usuarios = $stmt_usuarios->get_result();
                while ($user = $usuarios->fetch_assoc()) {
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

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
