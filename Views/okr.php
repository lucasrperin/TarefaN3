<?php require_once __DIR__ . '/../Public/Php/okr_php.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Painel N3 - OKRs</title>

  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">

  <!-- Custom Styles -->
  <link rel="stylesheet" href="../Public/usuarios.css">
  <link rel="stylesheet" href="../Public/okr.css">
  <link rel="icon" href="../Public/Image/LogoTituto.png" type="image/png">
</head>

<body class="bg-light">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <div class="d-flex-wrapper">

    <!-- SIDEBAR -->
    <div class="sidebar">
      <a class="light-logo d-block" href="menu.php">
        <img src="../Public/Image/zucchetti_blue.png" width="150" alt="Logo Zucchetti">
      </a>

      <nav class="nav flex-column"><a class="nav-link" href="menu.php"><i class="fa-solid fa-house me-2"></i>Home</a>
        <?php if ($cargo==='Admin' || $cargo==='Conversor'): ?>
          <a class="nav-link" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a>
        <?php endif; ?>
        <?php if ($cargo==='Admin'): ?>
          <a class="nav-link" href="destaque.php"><i class="fa-solid fa-ranking-star me-2"></i>Destaques</a>
          <a class="nav-link" href="escutas.php"><i class="fa-solid fa-headphones me-2"></i>Escutas</a>
          <a class="nav-link" href="folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a>
          <a class="nav-link" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a>
        <?php endif; ?>
        <?php if (in_array($cargo, ['Admin','Comercial','User','Conversor'])): ?>
          <a class="nav-link" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a>
        <?php endif; ?>
        <?php if (in_array($cargo, ['Admin','Viewer','User','Conversor'])): ?>
          <a class="nav-link" href="user.php"><i class="fa-solid fa-users-rectangle me-2"></i>Meu Painel</a>
        <?php endif; ?>
        <?php if ($cargo==='Admin'): ?>
          <a class="nav-link" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a>
        <?php endif; ?>
        <?php if ($cargo != 'Comercial'): ?>
          <a class="nav-link active" href="okr.php"><img src="../Public/Image/benchmarkbranco.png" width="27" height="27" class="me-1" alt="Benchmark">OKRs</a>
        <?php endif; ?>
        <?php if ($cargo==='Admin'): ?>
          <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a>
        <?php endif; ?>
        <?php if (in_array($cargo, ['Admin','Comercial','Treinamento'])): ?>
          <a class="nav-link" href="treinamento.php"><i class="fa-solid fa-calendar-check me-2"></i>Treinamentos</a>
        <?php endif; ?>
        <?php if ($cargo==='Admin'): ?>
          <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a>
        <?php endif; ?>
      </nav>
        </div>

    <!-- MAIN -->
    <main class="w-100">
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

      <!-- HEADER -->
      <header class="header d-flex justify-content-between align-items-center px-3">
        <h3>Controle de OKRs – <?= $anoAtual ?></h3>

        <div class="user-info">
          <span>Bem-vindo(a), <?= htmlspecialchars($usuario_nome) ?>!</span>
          <a href="logout.php" class="btn btn-danger btn-sm">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
          </a>
        </div>
      </header>

      <!-- CONTENT -->
      <section class="content container-fluid flex-fill">

      <?php
        if (!isset($_GET['nivel'])) {
            // inclui o menu externo e encerra aqui
            include __DIR__ . '/../Public/Php/levels_menu.php';
            return;
        }
      ?>

        <!-- VIEW + ACTION BUTTONS -->
<section class="content container-fluid flex-fill m-0">

<!-- Accordion único com todos os filtros -->
<div class="accordion mb-4" id="filtersAccordion">
  <div class="accordion-item">
    <h2 class="accordion-header" id="headingFilters">
      <button
        class="accordion-button accordion-css"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#collapseFilters"
        aria-expanded="true"
        aria-controls="collapseFilters"
      >
        Filtros de OKR
      </button>
    </h2>
    <div
      id="collapseFilters"
      class="accordion-collapse collapse show"
      aria-labelledby="headingFilters"
      data-bs-parent="#filtersAccordion"
    >
      <div class="accordion-body">
        <!-- Visão -->
        <div class="mb-3">
          <strong>Visão</strong>
          <div class="btn-group ms-2" role="group">
            <a
              href="?view=year&nivel=<?= $nivelSel ?>&equipe=<?= $equipeSel ?>"
              class="btn btn-sm <?= $view==='year' ? 'btn-primary' : 'btn-outline-primary' ?>"
            >Anual</a>
            <?php for($i=1; $i<=4; $i++): ?>
              <a
                href="?view=quarter&q=<?= $i ?>&nivel=<?= $nivelSel ?>&equipe=<?= $equipeSel ?>"
                class="btn btn-sm <?= ($view==='quarter' && $q===$i) ? 'btn-primary' : 'btn-outline-primary' ?>"
              >Q<?= $i ?></a>
            <?php endfor; ?>
          </div>
        </div>

        <!-- Equipe -->
        <div class="mb-3">
          <strong>Equipe</strong>
          <div class="btn-group flex-wrap ms-2" role="group">
            <a
              href="?view=<?= $view ?>&q=<?= $q ?>&equipe=0&nivel=<?= $nivelSel ?>"
              class="btn btn-sm <?= $equipeSel===0 ? 'btn-primary' : 'btn-outline-primary' ?>"
            >Todas</a>
            <?php 
              $listaEquipes->data_seek(0);
              while($e = $listaEquipes->fetch_assoc()):
                $idE = (int)$e['id'];
            ?>
              <a
                href="?view=<?= $view ?>&q=<?= $q ?>&equipe=<?= $idE ?>&nivel=<?= $nivelSel ?>"
                class="btn btn-sm <?= ($equipeSel === $idE) ? 'btn-primary' : 'btn-outline-primary' ?>"
              ><?= htmlspecialchars($e['descricao']) ?></a>
            <?php endwhile; ?>
          </div>
        </div>

        <!-- Nível -->
        <div class="mb-0">
          <strong>Nível</strong>
          <div class="btn-group flex-wrap ms-2" role="group">
            <a
              href="?view=<?= $view ?>&q=<?= $q ?>&equipe=<?= $equipeSel ?>&nivel=0"
              class="btn btn-sm <?= $nivelSel===0 ? 'btn-primary' : 'btn-outline-primary' ?>"
            >Todos</a>
            <?php
              $shownLevels = []; 
              $listaNiveis->data_seek(0);
              while($n = $listaNiveis->fetch_assoc()):
                $idN = (int)$n['id'];
                // 1) se tiver equipe filtrada e este nível não for dela, pula
                if ($equipeSel && (int)$n['idEquipe'] !== $equipeSel) {
                  continue;
                }
                // 2) se não há filtro de equipe (equipeSel==0) e este nível já foi exibido, pula
                if ($equipeSel === 0 && in_array($idN, $shownLevels, true)) {
                  continue;
                }
                // marca como exibido
                $shownLevels[] = $idN;
            ?>
              <a
                href="?view=<?= $view ?>&q=<?= $q ?>&equipe=<?= $equipeSel ?>&nivel=<?= $idN ?>"
                class="btn btn-sm <?= ($nivelSel === $idN) ? 'btn-primary' : 'btn-outline-primary' ?>"
              ><?= htmlspecialchars($n['descricao']) ?></a>
            <?php endwhile; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if($cargo==='Admin'): ?>
  <!-- Ações Admin em linha separada -->
  <div class="d-flex justify-content-end gap-2 mb-4">
    <a href="okr_list.php" class="btn btn-sm btn-outline-secondary">
      <i class="fa-solid fa-list me-1"></i> Listar OKRs
    </a>
    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalNovaMeta">
      <i class="fa-solid fa-flag-checkered me-1"></i> Nova Meta
    </button>
    <button class="btn btn-sm btn-custom" data-bs-toggle="modal" data-bs-target="#modalLancamento">
      <i class="fa-solid fa-circle-plus me-1"></i> Lançar Realizado
    </button>
  </div>
<?php endif; ?>

  <!-- MENU NAVEGÁVEL -->
  <ul class="nav nav-tabs mb-4" id="okrTab" role="tablist">
    <li class="nav-item" role="presentation">
      <button 
        class="nav-link active" 
        id="geral-tab" 
        data-bs-toggle="tab" 
        data-bs-target="#geral" 
        type="button" 
        role="tab" 
        aria-controls="geral" 
        aria-selected="true"
      >Visão Geral</button>
    </li>
    <li class="nav-item" role="presentation">
      <button 
        class="nav-link" 
        id="individual-tab" 
        data-bs-toggle="tab" 
        data-bs-target="#individual" 
        type="button" 
        role="tab" 
        aria-controls="individual" 
        aria-selected="false"
      >Visão Individual</button>
    </li>
  </ul>

  <!-- resto do conteúdo… -->
        <div class="tab-content" id="okrTabContent">
          <!-- ABA GERAL: TABELAS -->
          <div 
            class="tab-pane fade show active" 
            id="geral" 
            role="tabpanel" 
            aria-labelledby="geral-tab"
          >
        <!-- TABELAS EM CARDS POR EQUIPE/NÍVEL -->
        <?php foreach ($data as $grp => $metas):
          // filtra metas pelo nível
          $filtered = [];
          foreach ($metas as $key => $r) {
              if (!$nivelSel || in_array($nivelSel, $r['niveis_ids'])) {
                  $filtered[$key] = $r;
              }
          }
          if (empty($filtered)) continue;
          list($equipeId, $equipe, $niveis) = explode('||', $grp);

          // pula equipes não selecionadas
          if ($equipeSel && $equipeId != $equipeSel) continue;

          // agrupa para exibir as tabelas
          $tableGroups = [];
          foreach ($filtered as $key => $r) {
              list($okrId,) = explode('-', $key);
              if (! isset($tableGroups[$okrId])) {
                  $tableGroups[$okrId] = [
                      'okr'   => $r['okr'],
                      'niveis'  => $niveis,
                      'equipe'  => $equipe,
                      'items' => []
                  ];
              }
              $tableGroups[$okrId]['items'][] = $r;
          }
        ?>
        <div class="accordion mb-3" id="okrTableAccordion">
          <?php 
          foreach($tableGroups as $okrId => $grpData): ?>
            
            <div class="accordion-item">
              <h2 class="accordion-header" id="headingTable<?= $okrId ?>">
                <button
                  class="accordion-button collapsed"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#collapseTable<?= $okrId ?>"
                  aria-expanded="false"
                  aria-controls="collapseTable<?= $okrId ?>"
                >
                  <?= htmlspecialchars($grpData['okr']) ?>
                  <?php if ($nivelSel === 0): ?>
                    <small class="text-secondary ms-2">
                      · <?= htmlspecialchars($grpData['niveis']) ?>
                      - <?= htmlspecialchars($grpData['equipe']) ?>
                    </small>
                  <?php endif; ?>
                </button>
              </h2>
              <div
                id="collapseTable<?= $okrId ?>"
                class="accordion-collapse collapse"
                aria-labelledby="headingTable<?= $okrId ?>"
                data-bs-parent="#okrTableAccordion"
              >
                <div class="accordion-body p-0">
                  <div class="table-responsive px-3 pb-3">
                    <table class="table table-modern mb-0">
                      <thead>
                        <tr>
                          <th>KR / Indicador</th>
                          <th class="text-center">Meta</th>
                          <th class="text-center">Realizado</th>
                          <th class="text-center">% Ating.</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach($grpData['items'] as $r):
                            // 1) Tempo (menor é melhor)
                            if ($r['menor_melhor']) {
                                $sum      = array_sum($r['real_seg']);
                                $cnt      = count($r['real_seg']);
                                $avg      = $cnt ? round($sum/$cnt) : 0;
                                $metaSeg  = $r['meta_seg'];
                                $cls      = ($avg <= $metaSeg) ? 'success' : 'danger';
                                $metaDisp = seg2time($metaSeg);
                                $realDisp = $avg ? seg2time($avg) : '-';
                                // aproveitar % de atingimento original:
                                $pct      = $avg ? round($metaSeg/$avg*100,2) : 0;

                            // 2) Moeda (R$)
                            } elseif ($r['unidade'] === 'R$') {
                                // aqui assumimos que $r['real'] contém os valores realizados em número (ex: [100.00, 120.00,…])
                                $sum      = array_sum($r['real']);
                                $cnt      = count($r['real']);
                                $avg      = $cnt ? round($sum/$cnt,2) : 0;
                                $metaVlr  = $r['meta_vlr'];
                                // sucesso se realizado ≥ meta em R$
                                $cls      = ($avg >= $metaVlr) ? 'success' : 'danger';
                                $metaDisp = number_format($metaVlr,2,',','.') . ' R$';
                                $realDisp = number_format($avg,2,',','.') . ' R$';
                                // % de atingimento em relação ao objetivo em R$
                                $pct      = $metaVlr ? round($avg/$metaVlr*100,2) : 0;

                            // 3) Quantidade
                            } elseif ($r['unidade'] === 'unidades') {
                                $sum      = array_sum($r['real']);
                                $cnt      = count($r['real']);
                                $avg      = $cnt ? round($sum/$cnt,2) : 0;
                                $metaQtd  = $r['meta_qtd'];
                                $cls      = ($avg >= $metaQtd) ? 'success' : 'danger';
                                $metaDisp = number_format($metaQtd,0,',','.') . ' uni';
                                $realDisp = number_format($avg,0,',','.')   . ' uni';
                                $pct      = $metaQtd ? round($avg/$metaQtd*100,2) : 0;
                                
                            // 3) Percentual (%)
                            } else {
                                $sum      = array_sum($r['real']);
                                $cnt      = count($r['real']);
                                $avg      = $cnt ? round($sum/$cnt,2) : 0;
                                $meta     = $r['meta'];
                                $cls      = ($avg >= $meta) ? 'success' : 'danger';
                                $metaDisp = number_format($meta,2,',','.') . ' %';
                                $realDisp = number_format($avg,2,',','.') . ' %';
                                $pct      = $meta ? round($avg/$meta*100,2) : 0;
                            }
                        ?>
                        <tr>
                          <td><?= htmlspecialchars($r['kr']) ?></td>
                          <td class="text-center"><?= $metaDisp ?></td>
                          <td class="text-center"><?= $realDisp ?></td>
                          <td class="text-center">
                            <span class="badge bg-<?= $cls ?> text-dark">
                              <?= number_format($pct,2,',','.') ?>%
                            </span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  
 <!-- ABA INDIVIDUAL: ACCORDION DE GRÁFICOS -->
      <div class="tab-pane fade" id="individual" role="tabpanel" aria-labelledby="individual-tab">
  <div class="accordion" id="okrChartAccordion">
    <?php foreach($okrGroups as $okrId => $group):
        // se escolheu uma equipe, pula grupos de outras equipes
        if ($equipeSel && $group['equipe_id'] !== $equipeSel) {
            continue;
        }
    ?>
      <div class="accordion-item mb-3">
        <h2 class="accordion-header" id="headingChart<?= $okrId ?>">
          <button
            class="accordion-button collapsed"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#collapseChart<?= $okrId ?>"
            aria-expanded="false"
            aria-controls="collapseChart<?= $okrId ?>"
          >
            <?= htmlspecialchars($group['okr']) ?>
            <?php if ($nivelSel === 0): ?>
              <small class="text-secondary ms-2">
                · <?= htmlspecialchars($group['niveis']) ?>
                – <?= htmlspecialchars($group['equipe']) ?>
              </small>
                  <?php endif; ?>
                </button>
              </h2>
              <div
                id="collapseChart<?= $okrId ?>"
                class="accordion-collapse collapse"
                aria-labelledby="headingChart<?= $okrId ?>"
                data-bs-parent="#okrChartAccordion"
              >
              <div class="accordion-body p-0">
            <ul class="list-group list-group-flush">
                  <?php foreach($group['items'] as $metaId => $item):
                    $krId = "{$okrId}_{$metaId}";
                    // prepara dados do gráfico como antes...
                    if ($view==='quarter') {
                      $start  = ($q-1)*3+1;
                      $months = range($start, $start+2);
                    } else {
                      $months = range(1,12);
                    }
                    $ptMeses   = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
                    $labels    = array_map(fn($m)=> $ptMeses[$m-1], $months);

                    // escolhe o array bruto de realizado
                    $valuesArr  = $item['unit']==='s'
                                  ? $item['real_seg']
                                  : $item['real'];

                    // mapeia mês→valor ou null
                    $values_all = array_map(fn($m)=> isset($valuesArr[$m]) ? $valuesArr[$m] : null, $months);

                    // linha de meta
                    $metaLine   = array_fill(0, count($months), $item['target']);
                    $chartType  = ($view==='quarter') ? 'bar' : 'line';
                    // calcula pct e avg
                    $cnt  = $item['isTime']
                          ? count(array_filter($values_all, fn($v)=>$v!==null))
                          : count($valuesArr);
                    $sum  = $item['isTime']
                          ? array_sum($values_all)
                          : array_sum($valuesArr);
                    $avg  = $cnt ? round($sum/$cnt,2) : 0;
                    $pct  = ($avg && $item['target'])
                          ? round($item['isTime']
                                  ? ($item['target']/$avg*100)
                                  : ($avg/$item['target']*100),
                                  2)
                          : 0;
                    
                  ?>
                    <li class="list-group-item">
                      <a 
                        class="d-flex justify-content-between text-decoration-none" 
                        data-bs-toggle="collapse" 
                        href="#detail<?= $krId ?>" 
                        aria-expanded="false" 
                        aria-controls="detail<?= $krId ?>"
                      >
                        <span><?= htmlspecialchars($item['kr']) ?></span>
                        <i class="fa fa-chevron-down"></i>
                      </a>
                      <div 
                        id="detail<?= $krId ?>" 
                        class="collapse mt-2" 
                        data-bs-parent="#collapseChart<?= $okrId ?>"
                      >
                        <div class="card-okr mb-4 p-3">
                          <header class="okr-header mb-3">
                            <p><small>
                              <strong>KR:</strong> <?= htmlspecialchars($item['kr']) ?>
                              · <?= htmlspecialchars($item['equipe']) ?> / <?= htmlspecialchars($item['niveis']) ?>
                            </small></p>
                          </header>
                          <div class="okr-body d-flex justify-content-between align-items-center">
                            <?php
                              // determina o valor real do atingimento (pode passar de 100)
                              $pctReal   = round($pct,2);
                              // clamp só para preencher o arco
                              $pctFill   = min(max($pctReal, 0), 100);

                              // cor conforme atingimento
                              $color = ($pctReal >= 100) ? '#28a745' : '#dc3545';

                              // formata texto
                              if ($item['unit'] === 's') {
                                $label = gmdate('H:i:s', $avg);
                              } elseif ($item['unit'] === '%') {
                                $label = number_format($pctReal,2,',','.').' %';
                              } elseif ($item['unit'] === 'R$') {
                                $label = 'R$'.number_format($avg,2,',','.');
                              } else {
                                $label = intval($avg).' uni';
                              }
                            ?>
                            <div class="okr-status-circle me-4">
                              <div class="half-circle-wrapper">
                                <div
                                  class="circle-progress"
                                  style="
                                    --fill:  <?= $pctFill ?>;
                                    --color: <?= $color   ?>;
                                  "
                                ></div>
                              </div>
                              <div class="okr-status-value"><?= $label ?></div>
                            </div>

                            <div class="okr-chart flex-fill" style="height:200px;">
                              <canvas id="chart_<?= $krId ?>"></canvas>
                            </div>
                            <script>
                              (function(){
                                const unit      = <?= json_encode($item['unit']) ?>;        // 's','%','R$' ou 'unidades'
                                const labels    = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
                                const dataPts   = <?= json_encode($values_all, JSON_NUMERIC_CHECK) ?>;
                                const metaLine  = <?= json_encode($metaLine, JSON_NUMERIC_CHECK) ?>;

                                // função de formatação para todos os casos
                                const getFmt = {
                                  s: v => {
                                    if (v===null) return ''; 
                                    const s   = Math.floor(v),
                                          h   = Math.floor(s/3600),
                                          m   = Math.floor((s%3600)/60),
                                          sec = s%60;
                                    return (h?`${h}:`.padStart(3,'0'):'')
                                        +String(m).padStart(2,'0')
                                        +':'+String(sec).padStart(2,'0');
                                  },
                                  '%': v => v!=null ? v.toFixed(2)+' %' : '',
                                  'R$': v => v!=null ? 'R$'+v.toFixed(2).replace('.',',') : '',
                                  'unidades': v => v!=null ? String(Math.round(v))+' uni' : ''
                                };

                                const ctx = document.getElementById('chart_<?= $krId ?>').getContext('2d');
                                new Chart(ctx, {
                                  type: '<?= $chartType ?>',
                                  data: {
                                    labels,
                                    datasets: [
                                      {
                                        label: unit==='s'   ? 'Realizado (Tempo)'
                                              : unit==='%'  ? 'Realizado (%)'
                                              : unit==='R$' ? 'Realizado (R$)'
                                              : 'Realizado (Qtd)',
                                        data: dataPts,
                                        <?= $view==='quarter'
                                            ? "backgroundColor:'#007bff',"
                                            : "borderColor:'#007bff',tension:0.4,fill:false," ?>
                                      },
                                      {
                                        label: unit==='s'   ? 'Meta (Tempo)'
                                              : unit==='%'  ? 'Meta (%)'
                                              : unit==='R$' ? 'Meta (R$)'
                                              : 'Meta (Qtd)',
                                        data: metaLine,
                                        <?= $view==='quarter'
                                            ? "backgroundColor:'#ccc',"
                                            : "borderColor:'#ccc',borderDash:[5,5],tension:0.4,fill:false," ?>
                                      }
                                    ]
                                  },
                                  options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                      x: { ticks:{ autoSkip:false, maxRotation:0, minRotation:0 } },
                                      y: {
                                        ticks: {
                                          callback: value => getFmt[unit](value)
                                        }
                                      }
                                    },
                                    plugins: {
                                      tooltip: {
                                        callbacks: {
                                          label: ctx => {
                                            return ctx.dataset.label + ': ' + getFmt[unit](ctx.parsed.y);
                                          }
                                        }
                                      },
                                      // só escondemos legenda em linha; para barras trimestrais pode deixar
                                      legend: { display: <?= $view==='quarter' ? 'true' : 'false' ?> }
                                    }
                                  }
                                });
                              })();
                            </script>
                            <div class="okr-info ms-4">
                              <p><strong>
                                <?= $view==='quarter' ? "Q$q - $anoAtual" : "Visão Anual" ?>
                              </strong></p>
                              <p><strong>Atingido:</strong>
                                <?php
                                  switch($item['unit']) {
                                    case 's':
                                      echo gmdate('H:i:s', $avg);
                                      break;
                                    case '%':
                                      echo number_format($pct, 2, ',', '.') . ' %';
                                      break;
                                    case 'R$':
                                      echo 'R$' . number_format($avg, 2, ',', '.');
                                      break;
                                    case 'unidades':
                                      echo intval($avg) . ' uni';
                                      break;
                                  }
                                ?>
                              </p>
                              <p><strong>Meta:</strong>
                                <?php
                                  switch($item['unit']) {
                                    case 's':
                                      echo gmdate('H:i:s', $item['target']);
                                      break;
                                    case '%':
                                      echo number_format($item['target'], 2, ',', '.') . ' %';
                                      break;
                                    case 'R$':
                                      echo 'R$' . number_format($item['target'], 2, ',', '.');
                                      break;
                                    case 'unidades':
                                      echo intval($item['target']) . ' uni';
                                      break;
                                  }
                                ?>
                              </p>
                            </div>
                          </div>
                          <footer class="okr-footer d-flex align-items-center mt-3">
                            <img
                              src="../Public/Image/Silva.png"
                              class="rounded-circle me-2"
                              width="40" height="40"
                            >
                            <div>
                              <strong>Douglas Silva</strong><br>
                              <small>Supervisor</small>
                            </div>
                          </footer>
                        </div>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
      </section> <!-- /.content -->
    </main> <!-- /main -->
  </div> <!-- /.d-flex-wrapper -->

  <!-- MODAIS -->
  <?php include '../Public/Modals/okr_modals.php'; ?>
  <?php include __DIR__ . '/../Public/Modals/okr_edit_modal.php'; ?>

  <script>
    // Pegamos o PHP e transformamos em JS
    window.metasByOkr = <?= json_encode($metaList, JSON_UNESCAPED_UNICODE) ?>;
    window.atingsByOkr = <?= json_encode($atingsByOkr, JSON_UNESCAPED_UNICODE) ?>;
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../Public/Js/okr.js"></script>
</body>
</html>
