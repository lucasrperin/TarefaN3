<?php

require '../Config/Database.php';

require_once __DIR__ . '/../Includes/auth.php';

// Se um usuário foi passado via GET, use-o; caso contrário, use o usuário logado
$usuario_id = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : $_SESSION['usuario_id'];

$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';


// 1) Se vier ?clear=1, zera tudo sem redirect
$clear = isset($_GET['clear']);

// 2) Captura valores do filtro (ou defaults DO MÊS ATUAL,
//    mas só se não estivermos limpando)
date_default_timezone_set('America/Sao_Paulo');

if (isset($_GET['clear'])) {
  // limpa tudo
  $filterColumn      = null;
  $dataInicial       = '';
  $dataFinal         = '';
  $whereSqlIndicacao = '';               // <<< sem filtro
} else {
  // mês atual ou valores submetidos
  $filterColumn = $_GET['filterColumn'] ?? 'period';
  $dataInicial  = $_GET['data_inicial']  ?? date('Y-m-01');
  $dataFinal    = $_GET['data_final']    ?? date('Y-m-t');

  // só monta o filtro de data para indicações quando houver datas válidas
  $whereSqlIndicacao = "
    AND ind.data BETWEEN '{$dataInicial} 00:00:00'
                      AND '{$dataFinal} 23:59:59'
  ";
}

// 3) Monta as cláusulas de filtro
$filters = [];
if (!$clear && $filterColumn === 'period' && $dataInicial && $dataFinal) {
    $filters[] = "a.Hora_ini BETWEEN '{$dataInicial} 00:00:00' AND '{$dataFinal} 23:59:59'";
}
$whereSql = $filters
  ? ' AND ' . implode(' AND ', $filters)
  : '';

// supondo $dataInicial = '2025-03-01' e $dataFinal = '2025-03-31'
$dt = new DateTime($dataInicial);
$dt->modify('-1 month');
$prevInicial = $dt->format('Y-m-01');  // ex: '2025-02-01'
$prevFinal   = $dt->format('Y-m-t');   // ex: '2025-02-28'

// cláusula WHERE para o período ANTERIOR
$whereSqlPrev = " AND a.Hora_ini BETWEEN '{$prevInicial} 00:00:00' AND '{$prevFinal} 23:59:59'";

// Consulta para obter análises (incluindo o campo Nota) do usuário logado
$sql_analises = "
  SELECT
    a.Id, a.Descricao, a.Nota, a.numeroFicha,
    DATE_FORMAT(a.Hora_ini,'%d/%m %H:%i:%s') AS Hora_ini,
    a.justificativa, u.Nome AS Usuario
  FROM TB_ANALISES a
  LEFT JOIN TB_USUARIO u ON u.Id = a.idUsuario
  WHERE a.idAtendente = ? 
    AND a.idSituacao = 1 
    AND a.idStatus   = 1
    {$whereSql}
";
$stmt = $conn->prepare($sql_analises);
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$resultado_analises = $stmt->get_result();

// Armazenar análises em um array
$analises = [];
while ($row = $resultado_analises->fetch_assoc()) {
    $analises[] = $row;
}

$totalAnalises = count($analises);

// Calcular a média das notas do usuário logado
$somaNotas = 0;
foreach ($analises as $analise) {
    $somaNotas += $analise['Nota'];
}
$mediaValor = $totalAnalises > 0 ? $somaNotas / $totalAnalises : 0;
$mediaFormatada = number_format($mediaValor, 2, ',', '.');

// Definir a classe e o texto conforme a média do usuário logado
if ($mediaValor >= 4.5) {
    $classeMedia = 'nota-verde';
    $textoMedia = 'Acima do Esperado';
} elseif ($mediaValor <= 2.99) {
    $classeMedia = 'nota-vermelha';
    $textoMedia = 'Abaixo do Esperado';
} else {
    $classeMedia = 'nota-amarela';
    $textoMedia = 'Dentro do Esperado';
}

// Consulta para obter fichas do usuário logado
$sql_fichas = "
  SELECT
    a.Id, a.Descricao, a.numeroFicha,
    DATE_FORMAT(a.Hora_ini,'%d/%m %H:%i:%s') AS Hora_ini
  FROM TB_ANALISES a
  WHERE a.idAtendente = ? 
    AND a.idSituacao  = 3
    {$whereSql}
";
$stmt = $conn->prepare($sql_fichas);
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$resultado_fichas = $stmt->get_result();
$fichas_por_numero = [];
while ($f = $resultado_fichas->fetch_assoc()) {
  $fichas_por_numero[$f['numeroFicha']][] = $f;
}

// Calcular total de fichas
$totalFichas = 0;
foreach ($fichas_por_numero as $numeroFicha => $fichas) {
    $totalFichas += count($fichas);
}

// Consulta para ranking: top 5 usuários com maior média de notas

$sql_ranking = "
  SELECT 
    a.idAtendente,
    u.Nome       AS usuario_nome,
    AVG(a.Nota)  AS mediaNotas
  FROM TB_ANALISES a
  JOIN TB_USUARIO u ON u.Id = a.idAtendente
  WHERE a.idStatus = 1
    {$whereSql}         
  GROUP BY a.idAtendente, u.Nome
  ORDER BY mediaNotas DESC, usuario_nome ASC
";
$result = $conn->query($sql_ranking);
$ranking = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];


// logo após criar o $whereSql, antes de qualquer consulta de posição:
$colocacaoAtual   = null;
$colocacaoAnterior = null;


// ————————————————————————————————
// 1) Verifica se tem análises no MÊS ATUAL
// ————————————————————————————————
$sql_cnt_atual = "
  SELECT COUNT(*) AS cnt
  FROM TB_ANALISES a
  WHERE a.idAtendente = ?
    AND a.idStatus = 1
    {$whereSql}
";
$stmt_cnt = $conn->prepare($sql_cnt_atual);
$stmt_cnt->bind_param("i", $usuario_id);
$stmt_cnt->execute();
$res_cnt = $stmt_cnt->get_result()->fetch_assoc();
$cntAtual = (int) $res_cnt['cnt'];

// 1) Posição no mês atual (Dense Rank)
if ($cntAtual > 0) {
  $sql_posicao_atual = "
  WITH MonthlyAvg AS (
    SELECT 
      a.idAtendente,
      u.Nome    AS usuario_nome,
      AVG(a.Nota) AS mediaMes
    FROM TB_ANALISES a
    JOIN TB_USUARIO u ON u.Id = a.idAtendente
    WHERE a.idStatus = 1
      {$whereSql}
    GROUP BY a.idAtendente, u.Nome
  )
  SELECT posicaoAtual
  FROM (
    SELECT
      idAtendente,
      ROW_NUMBER() OVER (
        ORDER BY mediaMes DESC, usuario_nome ASC
      ) AS posicaoAtual
    FROM MonthlyAvg
  ) AS ranked
  WHERE idAtendente = ?
";

  $stmt = $conn->prepare($sql_posicao_atual);
  $stmt->bind_param('i', $usuario_id);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($res && $res->num_rows > 0) {
      $row = $res->fetch_assoc();
      $colocacaoAtual = (int)$row['posicaoAtual'];
  } else {
      $colocacaoAtual = null;  // ou 0, ou '-' — como já faz no render
  }
}

// ————————————————————————————————
// 2) Verifica se tem análises no MÊS ANTERIOR
// ————————————————————————————————
$sql_cnt_ant = "
  SELECT COUNT(*) AS cnt
  FROM TB_ANALISES a
  WHERE a.idAtendente = ?
    AND YEAR(a.Hora_ini) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
    AND MONTH(a.Hora_ini) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
    AND a.idStatus = 1
";
$stmt_cnt = $conn->prepare($sql_cnt_ant);
$stmt_cnt->bind_param("i", $usuario_id);
$stmt_cnt->execute();
$res_cnt = $stmt_cnt->get_result()->fetch_assoc();
$cntAnt = (int) $res_cnt['cnt'];

// 2) Posição no mês anterior (Dense Rank)
if ($cntAnt > 0) {
  $sql_posicao_anterior = "
    WITH PrevMonthAvg AS (
      SELECT 
        a.idAtendente,
        u.Nome       AS usuario_nome,
        AVG(a.Nota)  AS mediaMes
      FROM TB_ANALISES a
      JOIN TB_USUARIO u ON u.Id = a.idAtendente
      WHERE a.idStatus = 1
        {$whereSqlPrev}
      GROUP BY a.idAtendente, u.Nome
    )
    SELECT posicaoAnterior
    FROM (
      SELECT 
        idAtendente,
        ROW_NUMBER() OVER (
          ORDER BY mediaMes DESC, usuario_nome ASC
        ) AS posicaoAnterior
      FROM PrevMonthAvg
    ) AS ranked
    WHERE idAtendente = ?
  ";
  $stmt = $conn->prepare($sql_posicao_anterior);
  $stmt->bind_param('i', $usuario_id);
  $stmt->execute();

  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;

  if (is_array($row) && array_key_exists('posicaoAnterior', $row)) {
      $colocacaoAnterior = (int) $row['posicaoAnterior'];
  } else {
      $colocacaoAnterior = null;
  }
}

// define a função (sem alteração)
function classeRank(int $pos): string {
  if ($pos === 1) return 'text-rank-1';
  if ($pos === 2) return 'text-rank-2';
  if ($pos === 3) return 'text-rank-3';
  return 'text-rank-default';
}

$clsAtual    = is_int($colocacaoAtual) && $colocacaoAtual > 0
                ? classeRank($colocacaoAtual)
                : 'text-rank-default';

$clsAnterior = is_int($colocacaoAnterior) && $colocacaoAnterior > 0
                ? classeRank($colocacaoAnterior)
                : 'text-rank-default';

// 5) Folgas
$hoje = date('Y-m-d');
$sql_passadas = "
  SELECT tipo,
         DATE_FORMAT(data_inicio,'%d/%m/%Y') AS inicio,
         DATE_FORMAT(data_fim,'%d/%m/%Y')    AS fim,
         quantidade_dias,
         justificativa
  FROM TB_FOLGA
  WHERE usuario_id = ? AND data_fim < ?
  ORDER BY data_inicio DESC
";
$stmt = $conn->prepare($sql_passadas);
$stmt->bind_param('is',$usuario_id,$hoje);
$stmt->execute();
$folgas_passadas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$sql_prox = "
  SELECT tipo,
         DATE_FORMAT(data_inicio,'%d/%m/%Y') AS inicio,
         DATE_FORMAT(data_fim,'%d/%m/%Y')    AS fim,
         quantidade_dias,
         justificativa
  FROM TB_FOLGA
  WHERE usuario_id = ? AND data_inicio >= ?
  ORDER BY data_inicio ASC
";
$stmt = $conn->prepare($sql_prox);
$stmt->bind_param('is',$usuario_id,$hoje);
$stmt->execute();
$folgas_proximas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ————————————————————————————————
// BUSCA A PRÓXIMA FOLGA DO USUÁRIO
// ————————————————————————————————
$sql_prox_folga = "
  SELECT 
    tipo, 
    data_inicio, 
    data_fim,
    justificativa,
    quantidade_dias
  FROM TB_FOLGA
  WHERE usuario_id = ?
    AND data_inicio >= CURDATE()
  ORDER BY data_inicio ASC
  LIMIT 1
";
$stmt_pf = $conn->prepare($sql_prox_folga);
$stmt_pf->bind_param("i", $usuario_id);
$stmt_pf->execute();
$res_pf = $stmt_pf->get_result();

if ($f = $res_pf->fetch_assoc()) {
    $proximaFolga_tipo   = $f['tipo']; // 'Ferias' ou 'Folga'
    $proximaFolga_inicio = date('d/m', strtotime($f['data_inicio']));
    $proximaFolga_fim    = date('d/m', strtotime($f['data_fim']));
    $proximaFolga_justificativa   = $f['justificativa'];
    $proximaFolga_qtdDias   = $f['quantidade_dias'];
} else {
    $proximaFolga_tipo          = null;
    $proximaFolga_inicio        = null;
    $proximaFolga_fim           = null;
    $proximaFolga_justificativa = null;
    $proximaFolga_qtdDias       = null;
}

// Detalhes das indicações faturadas
$qtdIndic   = 0;
$somaIndic  = 0.0;

if ($usuario_id) {
    $sql = "
      SELECT
        COUNT(*)                   AS qtd,
        COALESCE(SUM(ind.vlr_total),0) AS soma
      FROM TB_INDICACAO ind
      WHERE ind.status = 'Faturado'
      {$whereSqlIndicacao}
        AND ind.user_id = " . intval($usuario_id);
    $res = mysqli_query($conn, $sql);
    if ($res) {
      $row = mysqli_fetch_assoc($res);
      $qtdIndic  = (int)   $row['qtd'];
      $somaIndic = (float) $row['soma'];
    }
}

 // Detalhes das indicações faturadas
 $indicacoes = [];
 $sqlDet = "
    SELECT
      plu.nome,
      ind.data,
      ind.cnpj,
      ind.serial,
      ind.contato,
      ind.vlr_total
    FROM TB_INDICACAO ind
    LEFT JOIN TB_PLUGIN plu
      ON plu.id = ind.plugin_id
   WHERE status = 'Faturado'
     AND user_id = " . intval($usuario_id) . "
     {$whereSqlIndicacao}
   ORDER BY ind.data DESC";
 $resDet = mysqli_query($conn, $sqlDet);
 while ($row = mysqli_fetch_assoc($resDet)) {
   $indicacoes[] = $row;
 }
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Meu Painel</title>
  <!-- Fontes, Bootstrap, Font‑Awesome e CSS -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="../Public/user.css" rel="stylesheet">
  <link rel="icon" href="../Public/Image/LogoTituto.png" type="image/png">
</head>
<body class="bg-light">
  <div class="d-flex-wrapper">
    <!-- SIDEBAR -->
    <div class="sidebar">
      <a class="light-logo" href="user.php">
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
          <a class="nav-link" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin' || $cargo === 'Viewer' || $cargo === 'User' || $cargo === 'Conversor'): ?>
          <a class="nav-link active" href="user.php"><i class="fa-solid fa-users-rectangle me-2"></i>Meu Painel</a>
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

   <!-- ÁREA PRINCIPAL -->
<div class="w-100">
  <!-- HEADER (inalterado) -->
  <div class="header">
    <h3>Meu Painel</h3>
    <div class="user-info">
      <span>Bem‑vindo, <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>!</span>
      <a href="logout.php" class="btn btn-danger btn-sm">
        <i class="fa-solid fa-right-from-bracket"></i>
      </a>
    </div>
  </div>

  <!-- CONTENT (Layout ajustado) -->
  <div class="content">
    <div class="row gx-4 gy-4 ">
      <!-- PRIMEIRA ROW: MÉTRICAS (4 cards lado a lado) -->
      <div class="col-12">
        <!-- Botão para abrir o modal de filtro -->
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
          <i class="fa-solid fa-filter"></i>
        </button>
        <div class="row gx-4 gy-4 justify-content-center ">
          <!-- Coluna 1: Média das Notas + Colocação -->
          <div class="col-sm-6 col-md-3 d-flex flex-column">
            <!-- 1) Card Média das Notas -->
            <div class="card border-start border-4 border-secondary shadow-sm mb-3 h-100">
              <div class="card-body d-flex align-items-center">
                <div class="bg-secondary text-white rounded-circle icon-circle me-3">
                  <i class="fa-solid fa-star"></i>
                </div>
                <div>
                  <small class="text-muted">Média das Notas</small>
                  <h5 class="<?php echo $classeMedia; ?>"><?php echo $mediaFormatada; ?></h5>
                  <small class="<?php echo $classeMedia; ?>"><?php echo $textoMedia; ?></small>
                </div>
              </div>
            </div>

   
            <!-- Card Colocação Mensal -->
            <div class="card card-ranking border-start border-4 border-secondary shadow-sm h-100">
              <div class="card-header d-flex align-items-center bg-secondary text-white border-0">
                <i class="fa-solid fa-award fa-lg me-2"></i>
                <h6 class="mb-0">Posição no Ranking</h6>
              </div>
              <div class="card-body">
                <div class="row text-center">
                  <!-- Atual -->
                  <div class="col">
                    <div class="position-current mb-1 <?= $clsAtual ?>">
                      <i class="fa-solid fa-trophy me-1"></i>
                      <?= $colocacaoAtual !== null ? $colocacaoAtual.'º' : '–'; ?>
                    </div>
                    <div class="text-muted small">Atual</div>
                  </div>
                  <!-- Anterior -->
                  <div class="col border-start">
                    <div class="position-previous mb-1 <?= $clsAnterior ?>">
                      <i class="fa-solid fa-rotate-left me-1"></i>
                      <?= $colocacaoAnterior !== null ? $colocacaoAnterior.'º' : '–'; ?>
                    </div>
                    <div class="text-muted small">Mês Anterior</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <!-- Ranking -->
            <div class="card border-start border-4 border-warning shadow-sm ">
              <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-2">
                  <div class="bg-warning text-white rounded-circle icon-circle me-2">
                    <i class="fa-solid fa-trophy"></i>
                  </div>
                  <h6 class="mb-0">Ranking</h6>
                </div>
                <?php if(count($ranking)>0): ?>
                  <ul class="list-unstyled small ranking-scroll mb-0">
                    <?php foreach($ranking as $i=>$r): ?>
                      <li class="d-flex justify-content-between py-2 border-bottom">
                        <span class="sobrepor-ranking">
                          <?php 
                            echo ($i<3? ['🥇','🥈','🥉'][$i] : ($i+1).'º')
                              .' '.htmlspecialchars($r['usuario_nome']);
                          ?>
                        </span>
                        <span class="badge bg-secondary rounded-pill">
                          <?php echo number_format($r['mediaNotas'],2,',','.'); ?>
                        </span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php else: ?>
                  <small class="text-muted">Nenhum ranking disponível</small>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="col-sm-6 col-md-3">
            <!-- Total de Análises -->
            <div class="card metric-card border-start border-4 border-primary shadow-sm mb-2">
              <div class="card-body d-flex align-items-center">
                <div class="bg-primary text-white rounded-circle icon-circle me-3">
                  <i class="fa-solid fa-chart-line"></i>
                </div>
                <div>
                  <small class="text-muted">Total de Análises</small>
                  <h4 class="mb-0"><?php echo $totalAnalises; ?></h4>
                </div>
              </div>
            </div>
            <!-- Total de Fichas -->
            <div class="card metric-card border-start border-4 border-info shadow-sm">
              <div class="card-body d-flex align-items-center">
                <div class="bg-info text-white rounded-circle icon-circle me-3">
                  <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <div>
                  <small class="text-muted">Total de Fichas</small>
                  <h4 class="mb-0"><?php echo $totalFichas; ?></h4>
                </div>
              </div>
            </div>
          </div>
          <!-- 4) Próxima Folga -->
          <div class="col-sm-6 col-md-3">
            <div class="card metric-card border-start border-4 border-teal shadow-sm mb-2">
              <div class="card-body">

                <!-- Label -->
                <small class="text-muted">Próxima Folga</small>

                <?php if($proximaFolga_inicio !== null): ?>
                <!-- Badges juntos -->
                <div class="d-flex align-items-center flex-wrap gap-2 my-2">
                  <!-- Tipo -->
                  <span class="badge bg-teal rounded-pill px-2 py-1">
                    <i class="fa-solid <?= $proximaFolga_tipo==='Ferias'?'fa-umbrella-beach':'fa-calendar-day' ?> me-1"></i>
                    <?= htmlspecialchars($proximaFolga_tipo) ?>
                  </span>
                  <!-- Período -->
                  <span class="badge bg-light-pill text-teal rounded-pill px-2 py-1">
                    <i class="fa-regular fa-calendar me-1"></i>
                    <?= "{$proximaFolga_inicio} → {$proximaFolga_fim}" ?>
                  </span>
                  <!-- Dias -->
                  <span class="badge bg-light-pill text-teal rounded-pill px-2 py-1">
                    <i class="fa-solid fa-clock me-1"></i>
                    <?= $proximaFolga_qtdDias ?>d
                  </span>
                </div>

                <!-- Justificativa -->
                <?php if(!empty($proximaFolga_justificativa)): ?>
                <p class="justification-text mb-0 small">
                  <i class="fa-solid fa-comment-dots me-1 text-teal"></i>
                  <?= htmlspecialchars($proximaFolga_justificativa) ?>
                </p>
                <?php endif; ?>

                <?php else: ?>
                  <h5 class="mb-0 text-center">–</h5>
                <?php endif; ?>

              </div>
            </div>
       
            <!-- 5) Total Faturado (Indicações) -->
            <div class="card metric-card border-start border-4 border-success shadow-sm">
              <div class="card-body d-flex align-items-center">
                <div class="bg-success text-white rounded-circle icon-circle me-3">
                  <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
                <div>
                  <small class="text-muted">Total Faturado</small>
                  <!-- Ajuste $totalFaturado para sua variável -->
                  <h5 class="mb-0">R$ <?= number_format($somaIndic, 2, ',', '.') ?></h5>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

        <!-- 1) Menu navegável -->
        <ul class="nav nav-tabs nav-fill mb-4" id="mainMenu" role="tablist">
        <li class="nav-item">
          <button class="nav-link active"
                  id="analises-tab"
                  data-bs-toggle="tab"
                  data-bs-target="#analises"
                  type="button"
                  role="tab"
                  aria-controls="analises"
                  aria-selected="true">
            <i class="fa-solid fa-magnifying-glass-chart me-1"></i> Análises
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link"
                  id="folgas-tab"
                  data-bs-toggle="tab"
                  data-bs-target="#folgas"
                  type="button"
                  role="tab"
                  aria-controls="folgas"
                  aria-selected="false">
            <i class="fa-solid fa-calendar-days me-1"></i> Folgas
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link"
                  id="indicacoes-tab"
                  data-bs-toggle="tab"
                  data-bs-target="#indicacoes"
                  type="button"
                  role="tab"
                  aria-controls="indicacoes"
                  aria-selected="false">
            <i class="fa-solid fa-handshake-angle me-1"></i> Indicações
          </button>
        </li>
      </ul>

      <!-- 2) Conteúdo das tabs -->
      <div class="tab-content">
        <!-- 2.1) Aba Análises (ativa por padrão) -->
        <div class="tab-pane fade show active" id="analises" role="tabpanel" aria-labelledby="analises-tab">
          <div class="row gx-4 gy-4">
            <div class="col-lg-6">
              <!-- Análises Recentes -->
              <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom-0 d-flex align-items-center">
                  <i class="fa-solid fa-magnifying-glass-chart text-primary fa-lg me-2"></i>
                  <h6 class="mb-0">Análises Recentes</h6>
                </div>
                <div class="table-responsive table-scroll" style="max-height:350px; overflow:auto;">
                  <div class="grid-table">
                    <div class="grid-header">
                      <div><i class="fa-solid fa-align-left me-1"></i>Descrição</div>
                      <div><i class="fa-solid fa-hashtag me-1"></i>Ficha</div>
                      <div><i class="fa-solid fa-calendar-day me-1"></i>Data</div>
                      <div><i class="fa-solid fa-star me-1"></i>Nota</div>
                    </div>
                    <?php foreach($analises as $a): ?>
                      <div class="grid-row clickable nota-<?php echo $a['Nota']; ?>"
                          data-justificativa="<?php echo htmlspecialchars($a['justificativa'],ENT_QUOTES); ?>"
                          data-usuario="<?php echo htmlspecialchars($a['Usuario'],ENT_QUOTES); ?>"
                          onclick="mostrarJustificativaModal(this.dataset.justificativa,this.dataset.usuario)">
                        <div class="sobrepor"><?php echo htmlspecialchars($a['Descricao']); ?></div>
                        <div><?php echo $a['numeroFicha']?: '-'; ?></div>
                        <div><?php echo htmlspecialchars($a['Hora_ini']); ?></div>
                        <div class="nota"><?php echo $a['Nota']; ?> <i class="fa-solid fa-star text-warning ms-1"></i></div>
                      </div>
                    <?php endforeach; ?>
                    <?php if(empty($analises)): ?>
                      <div class="grid-row">
                        <div colspan="4" class="text-center text-muted">Nenhuma análise cadastrada.</div>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <!-- Fichas Recentes -->
              <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom-0 d-flex align-items-center">
                  <i class="fa-solid fa-file-lines text-info fa-lg me-2"></i>
                  <h6 class="mb-0">Fichas Recentes</h6>
                </div>
                <div class="table-responsive table-scroll" style="max-height:350px; overflow:auto;">
                  <div class="grid-table">
                    <div class="grid-header">
                      <div><i class="fa-solid fa-hashtag me-1"></i>Ficha</div>
                      <div><i class="fa-solid fa-calendar-day me-1"></i>Data</div>
                      <div style="justify-content:center;"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Ação</div>
                    </div>
                    <?php foreach($fichas_por_numero as $fs): foreach($fs as $f): ?>
                      <div class="grid-row">
                        <div><?php echo htmlspecialchars($f['numeroFicha']); ?></div>
                        <div><?php echo htmlspecialchars($f['Hora_ini']); ?></div>
                        <div>
                          <a href="https://zmap.zpos.com.br/#/detailsIncidente/<?php echo htmlspecialchars($f['numeroFicha']); ?>"
                            target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> ZMap
                          </a>
                        </div>
                      </div>
                    <?php endforeach; endforeach; ?>
                    <?php if(empty($fichas_por_numero)): ?>
                      <div class="grid-row">
                        <div colspan="3" class="text-center text-muted">Nenhuma ficha cadastrada.</div>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 2.2) Aba Folgas -->
        <div class="tab-pane fade" id="folgas" role="tabpanel" aria-labelledby="folgas-tab">
          <div class="p-3">
            <div class="row gx-4">
              <!-- Próximas Folgas -->
              <div class="col-md-6">
                <h5 class="mb-3">
                  <i class="fa-solid fa-calendar-plus text-primary me-2"></i>Próximas Folgas
                </h5>
                <?php if(count($folgas_proximas)): ?>
                  <ul class="timeline">
                    <?php foreach($folgas_proximas as $idx => $f): 
                      $isNext = $idx === 0;
                      $icon   = $f['tipo']==='Ferias' ? 'fa-umbrella-beach' : 'fa-calendar-day';
                      $bg     = $isNext ? 'bg-warning' : 'bg-primary';
                    ?>
                    <li class="timeline-event">
                      <div class="timeline-icon <?= $bg ?>">
                        <i class="fa-solid <?= $icon ?>"></i>
                      </div>
                      
                      <div class="timeline-content">
                      <?php if($isNext): ?>
                        <div class="ribbon-label"><b>Próxima</b></div>
                      <?php endif; ?>
                        <h6 class="mb-1">
                          <?= htmlspecialchars($f['tipo']) ?>
                          <span class="badge <?= $isNext ? 'bg-warning text-dark' : 'bg-primary' ?> ms-2">
                            <?= $f['quantidade_dias'] ?>d
                          </span>
                        </h6>
                        <small class="text-muted d-block mb-1">
                          <i class="fa-regular fa-calendar me-1"></i>
                          <?= $f['inicio'] ?> → <?= $f['fim'] ?>
                        </small>
                        <?php if(trim($f['justificativa'])): ?>
                        <p class="justificativa-folga small text-muted mb-0">
                          <i class="fa-solid fa-comment-dots me-1"></i>
                          <?= htmlspecialchars($f['justificativa']) ?>
                        </p>
                        <?php endif; ?>
                      </div>
                    </li>
                    <?php endforeach; ?>
                  </ul>
                <?php else: ?>
                  <div class="text-muted">Nenhuma folga futura agendada.</div>
                <?php endif; ?>
              </div>

              <!-- Folgas Passadas -->
              <div class="col-md-6">
                <h5 class="mb-3">
                  <i class="fa-solid fa-clock-rotate-left text-secondary me-2"></i>Folgas Passadas
                </h5>
                <?php if(count($folgas_passadas)): ?>
                  <ul class="timeline">
                    <?php foreach($folgas_passadas as $f): ?>
                    <li class="timeline-event">
                      <div class="timeline-icon bg-secondary">
                        <i class="fa-solid <?= $f['tipo']==='Ferias' ? 'fa-umbrella-beach' : 'fa-calendar-day' ?>"></i>
                      </div>
                      <div class="timeline-content ps-4">
                        <h6>
                          <?= htmlspecialchars($f['tipo']) ?>
                          <span class="badge bg-secondary ms-2"><?= $f['quantidade_dias'] ?>d</span>
                        </h6>
                        <small>
                          <i class="fa-regular fa-calendar me-1"></i>
                          <?= $f['inicio'] ?> → <?= $f['fim'] ?>
                        </small>
                        <?php if(trim($f['justificativa'])): ?>
                        <p>
                          <i class="fa-solid fa-comment-dots me-1"></i>
                          <?= htmlspecialchars($f['justificativa']) ?>
                        </p>
                        <?php endif; ?>
                      </div>
                    </li>
                    <?php endforeach; ?>
                  </ul>
                <?php else: ?>
                  <div class="text-muted">Nenhuma folga passada.</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- 2.3) Aba Indicações -->
<!-- Layout 10: List Group com Barra de Progresso -->
<div class="tab-pane fade" id="indicacoes" role="tabpanel" aria-labelledby="indicacoes-tab">
  <div class="p-3">
    <h5 class="mb-4">Minhas Indicações Faturadas</h5>

    <?php if ($qtdIndic > 0): ?>
      <ul class="table-scroll list-group mb-4">
        <?php foreach ($indicacoes as $ind):
          $percent = $somaIndic > 0 ? ($ind['vlr_total'] / $somaIndic * 100) : 0;
        ?>
          <li class="list-group-item ">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <div>
                <i class="fa-solid fa-puzzle-piece me-2 text-primary"></i>
                <?= htmlspecialchars($ind['nome'], ENT_QUOTES) ?>
              </div>
              <div class="text-end">
                <span class="fw-semibold">R$ <?= number_format($ind['vlr_total'], 2, ',', '.') ?></span><br>
                <small class="text-muted"><?= number_format($percent, 1, ',', '.') ?>%</small>
              </div>
            </div>
            <div class="progress" style="height: 6px;" title="Percentual de equivalencia do valor total">
              <div class="progress-bar bg-success" role="progressbar"
                   style="width: <?= $percent ?>%;"
                   aria-valuenow="<?= $percent ?>"
                   aria-valuemin="0"
                   aria-valuemax="100"> 
              </div>
            </div>
            <div class="small text-muted mt-2">
              <i class="fa-solid fa-calendar-days me-1"></i><?= date('d/m/Y', strtotime($ind['data'])) ?>
              &nbsp;|&nbsp;
              <i class="fa-solid fa-building me-1"></i><?= htmlspecialchars($ind['cnpj'], ENT_QUOTES) ?>
              &nbsp;|&nbsp;
              <i class="fa-solid fa-barcode me-1"></i><?= htmlspecialchars($ind['serial'], ENT_QUOTES) ?>
              &nbsp;|&nbsp;
              <i class="fa-solid fa-address-book me-1"></i><?= htmlspecialchars($ind['contato'], ENT_QUOTES) ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="text-end"><strong>Total:</strong> R$ <?= number_format($somaIndic, 2, ',', '.') ?></p>
    <?php else: ?>
      <div class="text-center text-muted mt-5">
        <i class="fa-solid fa-folder-open fa-2x mb-2"></i>
        <p>Você ainda não tem indicações faturadas.</p>
      </div>
    <?php endif; ?>
  </div>
</div>



      </div>
    </div>
  </div>
</div>

  <!-- Modal para exibir a Justificativa -->
  <div class="modal fade" id="justificativaModal" tabindex="-1" aria-labelledby="justificativaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <!-- Cabeçalho do Modal -->
        <div class="modal-header">
          <div class="d-flex flex-column">
            <h5 class="modal-title" id="justificativaModalLabel">
              Justificativa da Nota
            </h5>
            <small class="text-muted" style="color: #fff">
              Atribuído por: <span id="modalUsuario"></span>
            </small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <!-- Corpo do Modal -->
        <div class="modal-body" style="overflow-wrap: break-word; " id="justificativaModalBody">
        <!-- Conteúdo da justificativa inserido dinamicamente -->
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de Filtro com Controle por Coluna -->
  <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <form method="GET" action="user.php">
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
              </select>
            </div>
            <!-- Campo para filtro por Período com os dois checkboxes -->
            <div id="filterPeriod" style="display: none;">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="data_inicial" class="form-label">Data Início:</label>
                  <input type="date" class="form-control" id="data_inicial" name="data_inicial" value="<?= htmlspecialchars($dataInicial) ?>">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="data_final" class="form-label">Data Fim:</label>
                  <input type="date" class="form-control" id="data_final" name="data_final" value="<?= htmlspecialchars($dataFinal) ?>">
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <!-- Alterado o link para incluir o parâmetro clear=1 -->
            <button type="button" class="btn btn-secondary" onclick="window.location.href='user.php?clear=1'">Limpar Filtro</button>
            <button type="submit" class="btn btn-primary">Filtrar</button>
          </div>
          <input type="hidden" name="filterColumn" id="filterColumnHidden">
        </form>
      </div>
    </div>
  </div>

  <!-- Bootstrap Bundle com Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function mostrarJustificativaModal(justificativa, usuario) {
      // Insere a justificativa no corpo do modal
      document.getElementById("justificativaModalBody").innerText = justificativa;
      // Atualiza o campo de usuário no modal
      document.getElementById("modalUsuario").innerText = usuario;
      // Cria a instância do modal e exibe-o
      var modalElement = document.getElementById("justificativaModal");
      var modal = new bootstrap.Modal(modalElement);
      modal.show();
    }
  </script>
  <!-- Script para alternar entre os campos de filtro -->
  <script>
    function adjustFilterFields() {
      let filterColumn = document.getElementById("filterColumn").value;
      document.getElementById("filterColumnHidden").value = filterColumn;
      // Esconde todos os containers
      document.getElementById("filterPeriod").style.display = "none";
      // Exibe o container da opção selecionada
      if (filterColumn === "period") {
        document.getElementById("filterPeriod").style.display = "block";
      } 
    }
    document.addEventListener("DOMContentLoaded", function() {
      adjustFilterFields();
      document.getElementById("filterColumn").addEventListener("change", adjustFilterFields);
    });
  </script>

  

</body>
</html>
