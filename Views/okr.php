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

      <?php if ( ! isset($_GET['nivel']) ): 
          // reseta ponteiro e exibe cards
          $listaNiveis->data_seek(0);
          echo '<section class="levels-menu container py-5">
                  <h4 class="text-center mb-4 fw-light text-secondary">Selecione um Nível</h4>
                  <div class="row justify-content-center g-4">';
          while($n = $listaNiveis->fetch_assoc()){
            echo '<div class="col-6 col-sm-4 col-md-3">
                    <div class="card level-card text-center h-100 p-4 clickable" 
                        style="cursor:pointer"
                        onclick="location.href=\'okr.php?view='.$view.'&q='.$q.'&nivel='.$n['id'].'\'">
                      <i class="fa-solid fa-layer-group fa-2x mb-3 text-primary"></i>
                      <h5 class="fw-semibold mb-0">'.htmlspecialchars($n['descricao']).'</h5>
                    </div>
                  </div>';
          }
          echo '  </div>
                </section>';
          return;
        endif;?>
        <!-- VIEW + ACTION BUTTONS -->
        <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a
              href="?view=year&nivel=<?= $nivelSel ?>"
              class="btn btn-sm <?= $view==='year' ? 'btn-primary' : 'btn-outline-primary' ?>"
            >Visão Anual</a>

            <?php for($i=1; $i<=4; $i++): ?>
              <a
                href="?view=quarter&q=<?=$i?>&nivel=<?= $nivelSel ?>"
                class="btn btn-sm <?= $view==='quarter' && $q===$i ? 'btn-primary' : 'btn-outline-primary' ?>"
              >Q<?=$i?></a>
            <?php endfor; ?>
          </div>
        <?php if ($cargo==='Admin'): ?>
          <div class="d-flex gap-2">
            <button
              class="btn btn-outline-secondary"
              data-bs-toggle="modal"
              data-bs-target="#modalNovoOKR"
            >
              <i class="fa-solid fa-bullseye me-1"></i>Novo OKR
            </button>

            <button
              class="btn btn-outline-secondary"
              data-bs-toggle="modal"
              data-bs-target="#modalNovaMeta"
            >
              <i class="fa-solid fa-flag-checkered me-1"></i>Nova Meta
            </button>

            <button
              class="btn btn-custom"
              data-bs-toggle="modal"
              data-bs-target="#modalLancamento"
            >
              <i class="fa-solid fa-circle-plus me-1"></i>Lançar Realizado
            </button>
          </div>
        <?php endif; ?>
        </div>

        <!-- FILTRO DE NÍVEIS -->
        <div class="d-flex flex-wrap gap-2 mb-5">
          <a 
            href="?view=<?= $view ?>&q=<?= $q ?>&nivel=0" 
            class="btn btn-sm <?= ($nivelSel === 0) ? 'btn-primary' : 'btn-outline-primary' ?>"
          >Todos</a>

          <?php 
            // se já tiver usado fetch_assoc antes, volte ponteiro:
            $listaNiveis->data_seek(0);
            while($n = $listaNiveis->fetch_assoc()):
              $idInt = (int)$n['id'];
          ?>
            <a
              href="?view=<?= $view ?>&q=<?= $q ?>&nivel=<?= $idInt ?>"
              class="btn btn-sm <?= ($nivelSel == $idInt) ? 'btn-primary' : 'btn-outline-primary' ?>"
            >
              <?= htmlspecialchars($n['descricao']) ?>
            </a>
          <?php endwhile; ?>
        </div>
        <!-- Nav de abas -->
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
      >Geral</button>
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
      >Individual</button>
    </li>
  </ul>

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
      list($equipe, $niveis) = explode('||', $grp);

      // agrupa para exibir as tabelas
      $tableGroups = [];
      foreach ($filtered as $key => $r) {
          list($okrId,) = explode('-', $key);
          if (! isset($tableGroups[$okrId])) {
              $tableGroups[$okrId] = [
                  'okr'   => $r['okr'],
                  'items' => []
              ];
          }
          $tableGroups[$okrId]['items'][] = $r;
      }
  ?>
  <div class="accordion mb-3" id="okrTableAccordion">
      <?php foreach($tableGroups as $okrId => $grpData): ?>
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
                      if ($r['menor_melhor']) {
                        $sum   = array_sum($r['real_seg']);
                        $cnt   = count($r['real_seg']);
                        $avg   = $cnt ? round($sum/$cnt) : 0;
                        $metaDisp = seg2time($r['meta_seg']);
                        $realDisp = $avg ? seg2time($avg) : '-';
                        $pct      = $avg ? round($r['meta_seg']/$avg*100,2) : 0;
                      } else {
                        $sum   = array_sum($r['real']);
                        $cnt   = count($r['real']);
                        $avg   = $cnt ? round($sum/$cnt,2) : 0;
                        $metaDisp = number_format($r['meta'],2,',','.') . ' %';
                        $realDisp = number_format($avg,2,',','.') . ' %';
                        $pct      = $r['meta'] ? round($avg/$r['meta']*100,2) : 0;
                      }
                      $cls = $pct>=100 ? 'success'
                           : ($pct>=90    ? 'warning'
                                          : 'danger');
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
      <div 
            class="tab-pane fade" 
            id="individual" 
            role="tabpanel" 
            aria-labelledby="individual-tab"
          >
          <div class="accordion" id="okrChartAccordion">
        <?php foreach($okrGroups as $okrId => $group): ?>
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
                    $ptMeses    = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
                    $labels     = array_map(fn($m)=>$ptMeses[$m-1], $months);
                    $valuesArr  = $item['isTime'] ? $item['real_seg'] : $item['real'];
                    $values_all = array_map(fn($m)=> $valuesArr[$m] ?? null, $months);
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
                            <div class="okr-status-circle me-4">
                              <div class="circle">
                                <strong>
                                  <?= $item['isTime']
                                      ? gmdate('H:i:s',$avg)
                                      : number_format($pct,2,',','.').' %' ?>
                                </strong>
                                <span>Status</span>
                              </div>
                            </div>
                            <div class="okr-chart flex-fill" style="height:200px;">
                              <canvas id="chart_<?= $krId ?>"></canvas>
                            </div>
                            <script>
                              (function(){
                                const ctx = document
                                  .getElementById('chart_<?= $krId ?>')
                                  .getContext('2d');
                                new Chart(ctx, {
                                  type: '<?= $chartType ?>',
                                  data: {
                                    labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>,
                                    datasets: [
                                      {
                                        label: <?= $item['isTime'] ? "'Tempo (mm:ss)'" : "'Check-in'" ?>,
                                        data: <?= json_encode($values_all, JSON_NUMERIC_CHECK) ?>,
                                        <?= $view==='quarter'
                                            ? "backgroundColor:'#007bff',"
                                            : "borderColor:'#007bff',tension:0.4,fill:false," ?>
                                      },
                                      {
                                        label: <?= $item['isTime'] ? "'Meta (mm:ss)'" : "'Meta (%)'" ?>,
                                        data: <?= json_encode($metaLine, JSON_NUMERIC_CHECK) ?>,
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
                                      x: { ticks: { autoSkip: false, maxRotation: 0, minRotation: 0 } },
                                      y: {
                                        <?php if($item['isTime']): ?>
                                        ticks: {
                                          callback: value => {
                                            const s = Math.floor(value),
                                                  h = Math.floor(s/3600),
                                                  m = Math.floor((s%3600)/60),
                                                  sec = s%60;
                                            return (h>0?(h+':').padStart(3,'0'):'')
                                                  +String(m).padStart(2,'0')
                                                  +':' +String(sec).padStart(2,'0');
                                          }
                                        }
                                        <?php else: ?>
                                        min:0, max:100,
                                        ticks: {
                                          callback: value => value.toFixed(2)+' %'
                                        }
                                        <?php endif; ?>
                                      }
                                    },
                                    plugins: {
                                      tooltip: {
                                        callbacks: {
                                          label: ctx => {
                                            const v = ctx.parsed.y;
                                            <?php if($item['isTime']): ?>
                                            const s = Math.floor(v),
                                                  h = Math.floor(s/3600),
                                                  m = Math.floor((s%3600)/60),
                                                  sec = s%60;
                                            return ctx.dataset.label+': '
                                                  +(h>0?(h+':').padStart(3,'0'):'')
                                                  +String(m).padStart(2,'0')
                                                  +':' +String(sec).padStart(2,'0');
                                            <?php else: ?>
                                            return ctx.dataset.label+': '+v.toFixed(2)+' %';
                                            <?php endif; ?>
                                          }
                                        }
                                      },
                                      <?php if(!$item['isTime']): ?>legend:{display:false},<?php endif; ?>
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
                                <?= $item['isTime']
                                    ? gmdate('H:i:s',$avg)
                                    : number_format($pct,2,',','.').' %' ?>
                              </p>
                              <p><strong>Meta:</strong>
                                <?= $item['isTime']
                                    ? gmdate('H:i:s',$item['target'])
                                    : number_format($item['target'],2,',','.').' %' ?>
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

  <script>
    // Pegamos o PHP e transformamos em JS
    window.metasByOkr = <?= json_encode($metaList, JSON_UNESCAPED_UNICODE) ?>;
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../Public/Js/okr.js"></script>
</body>
</html>
