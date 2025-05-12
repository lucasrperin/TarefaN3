<?php
/* ---------- BOOTSTRAP BÁSICO ---------- */
include '../Config/Database.php';
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}
$usuario_id   = $_SESSION['usuario_id'];
$cargo        = $_SESSION['cargo']   ?? '';
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$anoAtual     = date('Y');

/* ---------- VIEW MODE (anual ou trimestral) ---------- */
$view = $_GET['view'] ?? 'year';        // 'year' ou 'quarter'
$q    = isset($_GET['q']) ? intval($_GET['q']) : 1;
if ($q < 1 || $q > 4) $q = 1;

/* ---------- BUSCA DADOS PARA RELATÓRIO COM EQUIPE/NÍVEIS MÚLTIPLOS ---------- */
$data   = [];
$months = [];

if ($view === 'quarter') {
    $start  = ($q - 1) * 3 + 1;
    $end    = $start + 2;
    $months = range($start, $end);

    $sql = "
      SELECT
        o.id               AS idOkr,
        e.descricao        AS equipe,
        okr_n.niveis       AS niveis,
        o.descricao        AS okr,
        m.id               AS idMeta,
        COALESCE(m.descricao,'-') AS kr,
        m.menor_melhor,
        m.meta,
        m.meta_seg,
        ai.mes            AS mes,
        ai.realizado      AS realizado,
        ai.realizado_seg  AS realizado_seg
      FROM TB_OKR o
      JOIN TB_EQUIPE e ON e.id = o.idEquipe

      LEFT JOIN (
        SELECT
          onl.idOkr       AS okr_id,
          GROUP_CONCAT(n.descricao SEPARATOR ', ') AS niveis
        FROM TB_OKR_NIVEL onl
        JOIN TB_NIVEL n ON n.id = onl.idNivel
        GROUP BY onl.idOkr
      ) okr_n ON okr_n.okr_id = o.id

      JOIN TB_META m
        ON m.idOkr = o.id
       AND m.ano   = ?

      LEFT JOIN TB_OKR_ATINGIMENTO ai
        ON ai.idMeta = m.id
       AND ai.ano    = ?
       AND ai.mes BETWEEN ? AND ?

      ORDER BY e.descricao, okr_n.niveis, o.descricao, m.id, ai.mes
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiii', $anoAtual, $anoAtual, $start, $end);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($r = $rs->fetch_assoc()) {
        $grp = "{$r['equipe']}||{$r['niveis']}";
        $key = "{$r['idOkr']}-{$r['idMeta']}";
        if (!isset($data[$grp][$key])) {
            $data[$grp][$key] = [
                'okr'           => $r['okr'],
                'kr'            => $r['kr'],
                'niveis'        => $r['niveis'],
                'menor_melhor'  => $r['menor_melhor'],
                'meta'          => $r['meta'],
                'meta_seg'      => $r['meta_seg'],
                'real'          => [],
                'real_seg'      => []
            ];
        }
        $data[$grp][$key]['real'][$r['mes']]     = $r['realizado'];
        $data[$grp][$key]['real_seg'][$r['mes']] = $r['realizado_seg'];
    }

  } else {
    // visão ANUAL: traz dados mês a mês
    $sql = "
      SELECT
        o.id               AS idOkr,
        e.descricao        AS equipe,
        okr_n.niveis       AS niveis,
        o.descricao        AS okr,
        m.id               AS idMeta,
        COALESCE(m.descricao,'-') AS kr,
        m.menor_melhor,
        m.meta,
        m.meta_seg,
        ai.mes            AS mes,
        ai.realizado      AS realizado,
        ai.realizado_seg  AS realizado_seg
      FROM TB_OKR o
      JOIN TB_EQUIPE e ON e.id = o.idEquipe

      LEFT JOIN (
        SELECT
          onl.idOkr       AS okr_id,
          GROUP_CONCAT(n.descricao SEPARATOR ', ') AS niveis
        FROM TB_OKR_NIVEL onl
        JOIN TB_NIVEL n ON n.id = onl.idNivel
        GROUP BY onl.idOkr
      ) okr_n ON okr_n.okr_id = o.id

      JOIN TB_META m
        ON m.idOkr = o.id
       AND m.ano   = ?

      LEFT JOIN TB_OKR_ATINGIMENTO ai
        ON ai.idMeta = m.id
       AND ai.ano    = ?

      ORDER BY e.descricao, okr_n.niveis, o.descricao, m.id, ai.mes
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $anoAtual, $anoAtual);
    $stmt->execute();
    $rs = $stmt->get_result();

    while ($r = $rs->fetch_assoc()) {
        $grp = "{$r['equipe']}||{$r['niveis']}";
        $key = "{$r['idOkr']}-{$r['idMeta']}";

        if (!isset($data[$grp][$key])) {
            $data[$grp][$key] = [
                'okr'           => $r['okr'],
                'kr'            => $r['kr'],
                'niveis'        => $r['niveis'],
                'menor_melhor'  => $r['menor_melhor'],
                'meta'          => $r['meta'],
                'meta_seg'      => $r['meta_seg'],
                'real'          => [],
                'real_seg'      => []
            ];
        }

        // usa null coalescing para evitar warnings
        $mes            = $r['mes'];
        $realizado      = $r['realizado']      ?? 0;
        $realizado_seg  = $r['realizado_seg']  ?? 0;

        $data[$grp][$key]['real'][$mes]     = $realizado;
        $data[$grp][$key]['real_seg'][$mes] = $realizado_seg;
    }
}


/* ---------- DADOS PARA O MODAL DINÂMICO ---------- */
// lista de OKRs
$okrList = [];
$resO = $conn->query("SELECT id, descricao FROM TB_OKR ORDER BY descricao");
while ($rowO = $resO->fetch_assoc()) {
    $okrList[] = $rowO;
}
// metas agrupadas por OKR
$metaList = [];
$stmtM = $conn->prepare("SELECT id, idOkr, descricao FROM TB_META WHERE ano = ? ORDER BY descricao");
$stmtM->bind_param('i', $anoAtual);
$stmtM->execute();
$rsM = $stmtM->get_result();
while ($rowM = $rsM->fetch_assoc()) {
    $metaList[$rowM['idOkr']][] = $rowM;
}

/* ---------- FUNÇÕES AUXILIARES ---------- */
function seg2time($s) {
    return gmdate('H:i:s', max(0, $s));
}

function imprimeColsYear($r) {
  // Override de meta para % Nota 5 no OKR “Atender com muita agilidade...” em Nível 1
  if (
      $r['okr'] === 'Atender com muita agilidade, mantendo alta a satisfação do cliente' &&
      $r['kr']  === '% Nota 5' &&
      strpos($r['niveis'], 'Nível 1') !== false
  ) {
      $metaValor = 56.00;
      $realizado = $r['realizado'];
      $meta      = number_format($metaValor, 2, ',', '.') . ' %';
      $real      = number_format($realizado, 2, ',', '.') . ' %';
      $pct       = $metaValor ? round($realizado / $metaValor * 100, 2) : 0;

  } else {
      // Lógica padrão
      if ($r['menor_melhor']) {
          // metas de tempo: menor é melhor
          $meta      = seg2time($r['meta_seg']);
          $real      = $r['realizado_seg'] ? seg2time($r['realizado_seg']) : '-';
          $pct       = ($r['realizado_seg'])
                     ? round($r['meta_seg'] / $r['realizado_seg'] * 100, 2)
                     : 0;
      } else {
          // metas percentuais
          $meta      = number_format($r['meta'], 2, ',', '.') . ' %';
          $real      = number_format($r['realizado'], 2, ',', '.') . ' %';
          $pct       = $r['meta']
                     ? round($r['realizado'] / $r['meta'] * 100, 2)
                     : 0;
      }
  }

  // cor conforme % atingido
  $cls = $pct >= 100
       ? 'bg-success text-dark'
       : ($pct >= 90
          ? 'bg-warning text-dark'
          : 'bg-danger text-light');

  echo "<td class='text-center'>{$meta}</td>";
  echo "<td class='text-center'>{$real}</td>";
  echo "<td class='text-center {$cls}'>". number_format($pct, 2, ',', '.') ." %</td>";
}


function imprimeColsQuarter($r, $m) {
    if ($r['menor_melhor']) {
        $meta = seg2time($r['meta_seg']);
        $real = isset($r['real_seg'][$m]) ? seg2time($r['real_seg'][$m]) : '-';
        $pct  = isset($r['real_seg'][$m]) && $r['real_seg'][$m]
              ? round($r['meta_seg'] / $r['real_seg'][$m] * 100,2) : 0;
    } else {
        $meta = number_format($r['meta'],2,',','.').' %';
        $real = isset($r['real'][$m]) ? number_format($r['real'][$m],2,',','.').' %' : '-';
        $pct  = isset($r['real'][$m]) && $r['meta']
              ? round($r['real'][$m] / $r['meta'] * 100,2) : 0;
    }
    $cls = $pct >= 100 ? 'bg-success text-dark'
         : ($pct >= 90  ? 'bg-warning text-dark'
                        : 'bg-danger text-light');
    echo "<td class='text-center'>{$meta}</td>";
    echo "<td class='text-center'>{$real}</td>";
    echo "<td class='text-center {$cls}'>" . number_format($pct,2,',','.') . " %</td>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Painel N3 - OKRs</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../Public/usuarios.css">
  <link rel="stylesheet" href="../Public/okr.css">
</head>
<body class="bg-light">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<div class="d-flex-wrapper">

  <!-- SIDEBAR -->
  <div class="sidebar">
    <a class="light-logo" href="menu.php">
      <img src="../Public/Image/zucchetti_blue.png" width="150" alt="Logo Zucchetti">
    </a>
    <nav class="nav flex-column">
      <a class="nav-link" href="menu.php"><i class="fa-solid fa-house me-2"></i>Home</a>
      <?php if ($cargo==='Admin'||$cargo==='Conversor'): ?>
        <a class="nav-link" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a>
      <?php endif; ?>
      <?php if ($cargo==='Admin'): ?>
        <a class="nav-link" href="destaque.php"><i class="fa-solid fa-ranking-star me-2"></i>Destaques</a>
        <a class="nav-link" href="escutas.php"><i class="fa-solid fa-headphones me-2"></i>Escutas</a>
        <a class="nav-link" href="folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a>
        <a class="nav-link" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a>
      <?php endif; ?>
      <?php if ($cargo==='Admin'||$cargo==='Comercial'||$cargo==='User'||$cargo==='Conversor'): ?>
        <a class="nav-link" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a>
      <?php endif; ?>
      <?php if ($cargo==='Admin'||$cargo==='Viewer'||$cargo==='User'||$cargo==='Conversor'): ?>
        <a class="nav-link" href="user.php"><i class="fa-solid fa-users-rectangle me-2"></i>Meu Painel</a>
      <?php endif; ?>
      <?php if ($cargo==='Admin'): ?>
        <a class="nav-link" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a>
      <?php endif; ?>
      <?php if ($cargo === 'Admin'): ?>
        <a class="nav-link active" href="okr.php"><img src="../Public/Image/benchmarkbranco.png" class="me-0 ms-0 pe-1" alt="Benchmark" width="27" height="27">OKRs</a>
      <?php endif; ?> 
      <?php if ($cargo==='Admin'): ?>
        <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a>
      <?php endif; ?>
      <?php if ($cargo==='Admin'||$cargo==='Comercial'||$cargo==='Treinamento'): ?>
        <a class="nav-link" href="treinamento.php"><i class="fa-solid fa-calendar-check me-2"></i>Treinamentos</a>
      <?php endif; ?>
      <?php if ($cargo==='Admin'): ?>
        <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a>
      <?php endif; ?>
    </nav>
  </div>
  
  <!-- MAIN -->
  <div class="w-100">

    <!-- HEADER -->
    <div class="header d-flex justify-content-between align-items-center px-3">
      <h3>Controle de OKRs – <?= $anoAtual ?></h3>
      <div class="user-info">
        <span>Bem-vindo(a), <?= htmlspecialchars($usuario_nome) ?>!</span>
        <a href="logout.php" class="btn btn-danger btn-sm">
          <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
        </a>
      </div>
    </div>

    <!-- CONTENT -->
    <div class="content container-fluid mt-3">

      <!-- VIEW + ACTION BUTTONS -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <a href="?view=year" class="btn btn-sm <?= $view==='year'?'btn-primary':'btn-outline-primary' ?>">Visão Anual</a>
          <?php for($i=1;$i<=4;$i++): ?>
            <a href="?view=quarter&q=<?=$i?>" class="btn btn-sm <?= $view==='quarter'&&$q===$i?'btn-primary':'btn-outline-primary' ?>">Q<?=$i?></a>
          <?php endfor; ?>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalNovoOKR">
            <i class="fa-solid fa-bullseye me-1"></i>Novo OKR
          </button>
          <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalNovaMeta">
            <i class="fa-solid fa-flag-checkered me-1"></i>Nova Meta
          </button>
          <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#modalLancamento">
            <i class="fa-solid fa-circle-plus me-1"></i>Lançar Realizado
          </button>
        </div>
      </div>

      <?php foreach ($data as $grp => $metas):
        list($equipe,$niveis)=explode('||',$grp);
        foreach ($metas as $key=>$metaData):
          $okr       = $metaData['okr'];
          $kr        = $metaData['kr'];
          $isTime    = $metaData['menor_melhor'] == 1;
          // escolhe o array e o valor-alvo corretos
          $valuesArr = $isTime ? ($metaData['real_seg'] ?? []) : ($metaData['real'] ?? []);
          $target    = $isTime ? $metaData['meta_seg'] : $metaData['meta'];

          if ($view==='year') {
            // anual: meses 1..12
            $months_all     = range(1,12);
            $labels         = array_map(
                                fn($m)=>strftime('%b',mktime(0,0,0,$m,1)),
                                $months_all
                              );
            $values_all     = [];
            $presentValues  = [];
            foreach($months_all as $m){
              $v = $valuesArr[$m] ?? null;
              if ($v !== null) $presentValues[] = $v;
              $values_all[] = $v;
            }
          } else {
            // trimestral: só meses com dados
            $labels     = array_map(
                            fn($m)=>strftime('%b',mktime(0,0,0,$m,1)),
                            array_keys($valuesArr)
                          );
            $values_all = array_values($valuesArr);
            $presentValues = $values_all;
          }

          $count   = count($presentValues);
          $sum     = array_sum($presentValues);
          $avg     = $count ? round($sum/$count,2) : 0;
          // % de atingimento (para tempo, calcula sobre segundos; se quiser %, divida avg/target*100)
          // calcula o % de atingimento, invertendo quando menor é melhor
          if ($isTime) {
            // para metas de tempo: quanto menor o avg melhor
            $pct = $avg
                ? round($target / $avg * 100, 2)
                : 0;
          } else {
            // para metas percentuais: quanto maior o avg melhor
            $pct = $target
                ? round($avg / $target * 100, 2)
                : 0;
          }
              $metaLine= array_fill(0, count($labels), $target);
              $id      = str_replace('-','_',$key);

            if ($isTime) {
              // garante inteiros em segundos, sem converter pra decimal
              $values_all = array_map(
                fn($v) => $v === null ? null : (int)$v,
                $values_all
              );
              // meta em segundos inteiros
              $metaLine   = array_fill(0, count($labels), (int)$target);
          }  
      ?>
      <div class="card-okr mb-4">
        <div class="okr-header mb-3">
          <h5><?=htmlspecialchars($okr)?></h5>
          <p><small><strong>KR:</strong> <?=htmlspecialchars($kr)?> · <?=$equipe?> / <?=$niveis?></small></p>
        </div>

        <div class="okr-body d-flex justify-content-between">
          <div class="okr-status-circle">
            <div class="circle">
              <strong><?=$pct?>%</strong><span>Status</span>
            </div>
          </div>
          <?php
            $isTime = $metaData['menor_melhor'] == 1;
          ?>
          <div class="okr-chart">
            <canvas id="chart_<?=$id?>"></canvas>
          </div>

          <div class="okr-info">
  <p><strong>
    <?= $view==='quarter' 
        ? "Q$q - $anoAtual" 
        : "Visão Anual" 
    ?>
  </strong></p>

  <!-- EM VEZ DE mostrar $count registros, mostro o “Atingido” -->
  <p>
    <strong>Atingido:</strong>
    <?= $isTime
        // média em segundos → HH:MM:SS
        ? gmdate('H:i:s', $avg)
        // percentual de meta alcançada
        : number_format($pct, 2, ',', '.') . ' %'
    ?>
  </p>

  <!-- você pode manter o Meta abaixo, se quiser -->
  <p>
    <strong>Meta:</strong>
    <?= $isTime 
        ? gmdate('H:i:s', $target) 
        : number_format($target, 2, ',', '.') . ' %' 
    ?>
  </p>
</div>
          
        </div>
        <div class="okr-footer d-flex align-items-center">
            <img src="../Public/Image/Silva.png" class="rounded-circle me-2" width="40" height="40">
            <div>
              <strong>Douglas Silva</strong><br>
              <small>Supervisor</small>
            </div>
          </div>
      </div>
      <script>
        const ctx_<?= $id ?> = document.getElementById('chart_<?= $id ?>');
        new Chart(ctx_<?= $id ?>, {
          type: <?= $view === 'quarter' ? "'bar'" : "'line'" ?>,
          data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [
              {
                label: <?= $isTime ? "'Tempo (mm:ss)'" : "'Check-in'" ?>,
                data: <?= json_encode($values_all) ?>,
                <?= $view === 'quarter'
                  ? "backgroundColor: '#007bff',"
                  : "borderColor: '#007bff', fill:false, tension:0.4," ?>
              },
              {
                label: <?= $isTime ? "'Meta (mm:ss)'" : "'Meta (%)'" ?>,
                data: <?= json_encode($metaLine) ?>,
                <?= $view === 'quarter'
                  ? "backgroundColor: '#ccc',"
                  : "borderColor: '#ccc', borderDash:[5,5], fill:false, tension:0.4," ?>
              }
            ]
          },
          options: {
            responsive: true,
            <?php if ($isTime): ?>
            plugins: {
              tooltip: {
                callbacks: {
                  label: ctx => {
                    const v = ctx.parsed.y;
                    const m = Math.floor(v/60);
                    const s = Math.round(v%60);
                    return ctx.dataset.label + ': ' + 
                          m.toString().padStart(2,'0') + ':' + 
                          s.toString().padStart(2,'0');
                  }
                }
              }
            },
            scales: {
              y: {
                ticks: {
                  callback: value => {
                    const m = Math.floor(value/60);
                    const s = Math.round(value%60);
                    return m.toString().padStart(2,'0') + ':' + 
                          s.toString().padStart(2,'0');
                  }
                }
              },
              x: { ticks:{ maxRotation:0 } }
            }
            <?php else: ?>
            plugins: { legend: { display: false } },
            scales: {
              y: { min: 0, max: 100 },
              x: { ticks: { maxRotation: 0 } }
            }
            <?php endif; ?>
          }
        });
      </script>

<?php endforeach; endforeach; ?>


      <!-- TABELAS EM CARDS POR EQUIPE/NÍVEL -->
      <?php foreach ($data as $grp => $metas): 
        list($equipe, $niveis) = explode('||', $grp);
      ?>
      <div class="card mb-4 shadow-sm">
        <div class="card-header bg-secondary text-white">
          <strong>Equipe:</strong> <?= htmlspecialchars($equipe) ?> &nbsp;&mdash;&nbsp;
          <strong>Níveis:</strong> <?= htmlspecialchars($niveis) ?>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 sticky-header">
              <thead class="table-dark">
                <?php if ($view==='year'): ?>
                <!-- cabeçalho visão anual -->
                <tr>
                  <th>Descrição do OKR</th>
                  <th>KR / Indicador</th>
                  <th class="text-center">Meta</th>
                  <th class="text-center">Alcançado</th>
                  <th class="text-center">% Ating.</th>
                </tr>
                <?php else: ?>
                <!-- cabeçalho visão trimestral -->
                <tr>
                  <th rowspan="3">Descrição do OKR</th>
                  <th rowspan="3">KR / Indicador</th>
                  <th colspan="<?= count($months)*2 ?>" class="text-center">Primeiro Quarter [Q<?= $q ?>]</th>
                </tr>
                <tr>
                  <?php foreach($months as $m): ?>
                    <th colspan="2" class="text-center"><?= ucfirst(strftime('%B',mktime(0,0,0,$m,1))) ?></th>
                  <?php endforeach; ?>
                </tr>
                <tr>
                  <?php foreach($months as $m): ?>
                    <th class="text-center">Meta</th>
                    <th class="text-center">Realizado</th>
                  <?php endforeach; ?>
                </tr>
                <?php endif; ?>
              </thead>
              <tbody>
                <?php
                  // agrupa por OKR
                  $okrGroups = [];
                  foreach ($metas as $key => $r) {
                    list($okrId,) = explode('-', $key);
                    $okrGroups[$okrId][] = $r;
                  }
                  foreach ($okrGroups as $okrRows):
                    $span = count($okrRows);
                    $okrLabel = $okrRows[0]['okr'];
                    foreach (array_values($okrRows) as $idx => $r):
                ?>
                <tr>
                  <?php if ($idx===0): ?>
                    <td rowspan="<?= $span ?>"><strong><?= htmlspecialchars($okrLabel) ?></strong></td>
                  <?php endif; ?>

                  <td><?= htmlspecialchars($r['kr']) ?></td>

                  <?php if ($view==='year'): ?>
                    <?= imprimeColsYear($r) ?>
                  <?php else: ?>
                    <?php foreach($months as $m): ?>
                      <?php
                        if ($r['menor_melhor']) {
                          $meta = seg2time($r['meta_seg']);
                          $real = isset($r['real_seg'][$m]) ? seg2time($r['real_seg'][$m]) : '-';
                        } else {
                          $meta = number_format($r['meta'],2,',','.').' %';
                          $real = isset($r['real'][$m]) ? number_format($r['real'][$m],2,',','.').' %' : '-';
                        }
                      ?>
                      <td class="text-center"><?= $meta ?></td>
                      <td class="text-center"><?= $real ?></td>
                    <?php endforeach; ?>
                  <?php endif; ?>

                </tr>
                <?php endforeach; endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /wrapper -->

<!-- MODAL NOVO OKR -->
<div class="modal fade" id="modalNovoOKR" tabindex="-1" aria-labelledby="modalNovoOKRLabel" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form action="cadastrar_okr.php" method="post">
      <div class="modal-header">
        <h5 class="modal-title" id="modalNovoOKRLabel">Cadastrar OKR</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Descrição</label>
          <input type="text" class="form-control" name="descricao" required>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Equipe</label>
            <select class="form-select" name="idEquipe" required>
              <?php $e=$conn->query("SELECT id,descricao FROM TB_EQUIPE"); while($r=$e->fetch_assoc()): ?>
                <option value="<?=$r['id']?>"><?=$r['descricao']?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Níveis (um ou mais)</label>
            <div class="border rounded p-2" style="max-height:120px;overflow:auto">
              <?php $niv=$conn->query("SELECT id,descricao FROM TB_NIVEL"); while($n=$niv->fetch_assoc()): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="idNivel[]" value="<?=$n['id']?>" id="niv<?=$n['id']?>">
                  <label class="form-check-label" for="niv<?=$n['id']?>"><?=$n['descricao']?></label>
                </div>
              <?php endwhile; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-custom" type="submit">Salvar</button>
      </div>
    </form>
  </div></div>
</div>

<!-- MODAL NOVA META -->
<div class="modal fade" id="modalNovaMeta" tabindex="-1" aria-labelledby="modalNovaMetaLabel" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form action="cadastrar_meta.php" method="post">
      <div class="modal-header">
        <h5 class="modal-title" id="modalNovaMetaLabel">Cadastrar Meta Anual</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">OKR</label>
          <select class="form-select" name="idOkr" required>
            <?php $o=$conn->query("SELECT id,descricao FROM TB_OKR ORDER BY descricao"); while($r=$o->fetch_assoc()): ?>
              <option value="<?=$r['id']?>"><?=$r['descricao']?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Ano</label>
            <input type="number" class="form-control" name="ano" value="<?=$anoAtual?>" required>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Tipo</label>
            <select name="tipo_meta" id="tipoMetaSel" class="form-select" onchange="toggleTipoMeta()" required>
              <option value="valor" selected>Valor (%)</option>
              <option value="tempo">Tempo (HH:MM:SS)</option>
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Prazo</label>
            <input type="date" class="form-control" name="dt_prazo" required>
          </div>
        </div>
        <div class="row">
          <div id="divValor" class="col-md-6 mb-3">
            <label class="form-label">Meta (%)</label>
            <input type="number" step="0.01" class="form-control" name="meta_valor">
          </div>
          <div id="divTempo" class="col-md-6 mb-3 d-none">
            <label class="form-label">Meta (HH:MM:SS)</label>
            <input type="text" class="form-control" name="meta_tempo" placeholder="00:01:10">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Descrição curta (opcional)</label>
          <input type="text" class="form-control" name="descricao">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-custom" type="submit">Salvar</button>
      </div>
    </form>
  </div></div>
</div>

<!-- MODAL LANÇAR REALIZADO -->
<div class="modal fade" id="modalLancamento" tabindex="-1" aria-labelledby="modalLancamentoLabel" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form action="cadastrar_atingimento.php" method="post">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLancamentoLabel">Registrar Realizado Mensal</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">OKR</label>
          <select id="selOkr" class="form-select" required>
            <option value="">Selecione o OKR</option>
            <?php foreach($okrList as $ok): ?>
              <option value="<?=$ok['id']?>"><?=htmlspecialchars($ok['descricao'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Mês</label>
          <select class="form-select" name="mes" required>
            <?php for($i=1;$i<=12;$i++): ?>
              <option value="<?=$i?>"><?=str_pad($i,2,'0',STR_PAD_LEFT)?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div id="divMetasList" class="d-none">
          <table class="table">
            <thead>
              <tr>
                <th>KR / Meta</th>
                <th>Realizado</th>
              </tr>
            </thead>
            <tbody id="tbodyMetas"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-custom" type="submit">Salvar</button>
      </div>
    </form>
  </div></div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// alterna campos modal Nova Meta
function toggleTipoMeta(){
  const sel = document.getElementById('tipoMetaSel').value;
  document.getElementById('divValor').classList.toggle('d-none', sel==='tempo');
  document.getElementById('divTempo').classList.toggle('d-none', sel==='valor');
}
  // JSON gerado no PHP: metas agrupadas por OKR
  const metasByOkr = <?= json_encode($metaList, JSON_UNESCAPED_UNICODE) ?>;

  document
    .getElementById('selOkr')
    .addEventListener('change', function(){
      const metas     = metasByOkr[this.value] || [];
      const container = document.getElementById('divMetasList');
      const tbody     = document.getElementById('tbodyMetas');

      tbody.innerHTML = '';
      metas.forEach(m => {
        const tr     = document.createElement('tr');
        const tdDesc = document.createElement('td');
        tdDesc.textContent = m.descricao;

        // campo oculto para enviar idMeta[]
        const hidden = document.createElement('input');
        hidden.type  = 'hidden';
        hidden.name  = 'idMeta[]';
        hidden.value = m.id;
        tdDesc.appendChild(hidden);

        const tdInput = document.createElement('td');
        const inputVal = document.createElement('input');
        inputVal.type        = 'text';
        inputVal.name        = 'realizado[]';
        inputVal.className   = 'form-control';
        inputVal.required    = true;
        // placeholder condicional pelo tipo da meta
        inputVal.placeholder = m.menor_melhor ? '00:01:55' : '99.99';
        tdInput.appendChild(inputVal);

        tr.appendChild(tdDesc);
        tr.appendChild(tdInput);
        tbody.appendChild(tr);
      });

      container.classList.toggle('d-none', metas.length === 0);
    });
</script>
</body>
</html>
