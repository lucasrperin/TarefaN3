<?php require_once __DIR__ . '/../Public/Php/okr_php.php';?>

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
    <aside class="sidebar">
      <a class="light-logo mb-4 d-block" href="menu.php">
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
          <a class="nav-link active" href="okr.php"><img src="../Public/Image/benchmarkbranco.png" width="27" height="27" class="me-1" alt="Benchmark">OKRs</a>
          <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-calculator me-2"></i>Totalizadores</a>
          <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a>
        <?php endif; ?>
        <?php if (in_array($cargo, ['Admin','Comercial','Treinamento'])): ?>
          <a class="nav-link" href="treinamento.php"><i class="fa-solid fa-calendar-check me-2"></i>Treinamentos</a>
        <?php endif; ?>
      </nav>
    </aside>

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
      <section class="content container-fluid mt-3">

        <!-- VIEW + ACTION BUTTONS -->
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <a href="?view=year" class="btn btn-sm <?= $view==='year' ? 'btn-primary' : 'btn-outline-primary' ?>">
              Visão Anual
            </a>

            <?php for($i=1; $i<=4; $i++): ?>
              <a
                href="?view=quarter&q=<?=$i?>"
                class="btn btn-sm <?= $view==='quarter' && $q===$i ? 'btn-primary' : 'btn-outline-primary' ?>"
              >Q<?=$i?></a>
            <?php endfor; ?>
          </div>

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
        </div>

        <!-- FILTRO DE NÍVEIS -->
        <div class="d-flex flex-wrap gap-2 mb-5">
          <a
            href="okr.php?view=<?= $view ?>&q=<?= $q ?>"
            class="btn btn-sm <?= $nivelSel===0 ? 'btn-primary' : 'btn-outline-primary' ?>"
          >Todos</a>

          <?php while($n = $listaNiveis->fetch_assoc()): ?>
            <a
              href="okr.php?view=<?= $view ?>&q=<?= $q ?>&nivel=<?= $n['id'] ?>"
              class="btn btn-sm <?= $nivelSel===$n['id'] ? 'btn-primary' : 'btn-outline-primary' ?>"
            ><?= htmlspecialchars($n['descricao']) ?></a>
          <?php endwhile; ?>
        </div>

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

    // agrupa por OKR
    $okrGroups = [];
    foreach ($filtered as $key => $r) {
        list($okrId,) = explode('-', $key);
        $okrGroups[$okrId]['okr']     = $r['okr'];
        $okrGroups[$okrId]['items'][] = $r;
    }
?>
  <div class="mb-5">
    <div class="card border-0 shadow-sm rounded">
      <div class="card-header bg-transparent px-3 py-2">
        <h5 class="mb-0"><?= htmlspecialchars($equipe) ?> &mdash; <?= htmlspecialchars($niveis) ?></h5>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive px-3 pb-3">
          <table class="table table-modern mb-0">
            <thead>
              <tr>
     
                <th width="33%">KR / Indicador</th>
                <th class="text-center" width="17%">Meta</th>
                <th class="text-center" width="17%">Realizado</th>
                <th class="text-center" width="17%">% Ating.</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($okrGroups as $okrId => $grpData):
                  $collapseId = "okrCollapse_{$okrId}";
                  $items      = $grpData['items'];
              ?>
                <!-- linha principal do OKR, clicável -->
                <tr class="clickable" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#<?= $collapseId ?>" 
                    aria-expanded="false">
                  <td colspan="5">
                    <strong><?= htmlspecialchars($grpData['okr']) ?></strong>
                    <i class="fa fa-chevron-down float-end"></i>
                  </td>
                </tr>
                <!-- linhas de KR, escondidas até expandir -->
                <tr class="collapse" id="<?= $collapseId ?>">
                  <td colspan="4.5" class="p-0">
                    <table class="table table-borderless mb-0">
                      <tbody>
                        <?php foreach ($items as $r):
                          // calcula média e %
                          if ($r['menor_melhor']) {
                            $sum   = array_sum($r['real_seg'] ?? []);
                            $cnt   = count($r['real_seg'] ?? []);
                            $avg   = $cnt ? round($sum/$cnt) : 0;
                            $metaDisp = seg2time($r['meta_seg']);
                            $realDisp = $avg ? seg2time($avg) : '-';
                            $pct      = $avg ? round($r['meta_seg']/$avg*100,2) : 0;
                          } else {
                            $sum   = array_sum($r['real'] ?? []);
                            $cnt   = count($r['real'] ?? []);
                            $avg   = $cnt ? round($sum/$cnt,2) : 0;
                            $metaDisp = number_format($r['meta'],2,',','.') . ' %';
                            $realDisp = number_format($avg,2,',','.') . ' %';
                            $pct      = $r['meta'] ? round($avg/$r['meta']*100,2) : 0;
                          }
                          $cls = $pct>=100?'success':($pct>=90?'warning':'danger');
                        ?>
                          <tr>
                            <td width="33%"><?= htmlspecialchars($r['kr']) ?></td>
                            <td class="text-center" width="17%"><?= $metaDisp ?></td>
                            <td class="text-center" width="17%"><?= $realDisp ?></td>
                            <td class="text-center" width="17%">
                              <span class="badge bg-<?= $cls ?> text-dark"><?= number_format($pct,2,',','.') ?>%</span>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
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

        <!-- ACORDION DE DETALHES DAS METAS -->
        <?php
          $cardsData = [];
          $seenMeta  = [];

          foreach ($data as $grp => $metas) {
            list($equipe,$niveisStr) = explode('||',$grp);
            foreach ($metas as $key => $metaData) {
              list(,$metaId) = explode('-',$key);
              if ($nivelSel && ! in_array($nivelSel, $metaData['niveis_ids'])) continue;
              if (isset($seenMeta[$metaId])) continue;

              $seenMeta[$metaId] = true;
              $cardsData[] = [
                'key'      => $key,
                'metaData' => $metaData,
                'niveis'   => $niveisStr,
                'equipe'   => $equipe
              ];
              $data[$grp][$key]['realizado']     = array_sum($metaData['real']    ?? []);
              $data[$grp][$key]['realizado_seg'] = array_sum($metaData['real_seg'] ?? []);
            }
          }
        ?>

        <div class="accordion" id="okrAccordion">
          <?php foreach ($cardsData as $card):
            $r    = $card['metaData'];
            list($okrId,$metaId) = explode('-', $card['key']);
            $id   = str_replace('-', '_', $card['key']);
            $okr  = $r['okr'];
            $kr   = $r['kr'];
            $equipe = $card['equipe'];
            $niveis = $card['niveis'];
            $isTime = $r['menor_melhor']==1;

            // Dados para o gráfico
            $valuesArr = $isTime ? ($r['real_seg'] ?? []) : ($r['real'] ?? []);
            $target    = $isTime ? $r['meta_seg'] : $r['meta'];
            $labels    = array_map(
              fn($m)=>strftime('%b',mktime(0,0,0,$m,1)), range(1,12)
            );
            $values_all = array_map(
              fn($v)=> $v===null ? null : round((float)$v, 2),
              array_values($valuesArr)
            );

            $metaLine = array_fill(0, count($labels), round((float)$target, 2));
            // contar elementos existentes
            $cnt = $isTime 
            ? count(array_filter($values_all, fn($v)=>$v!==null)) 
            : count($valuesArr);

            // média só se houver dados
            if ($cnt) {
            $sum = $isTime 
              ? array_sum($values_all) 
              : array_sum($valuesArr);
            $avg = round($sum / $cnt, 2);
            } else {
            $avg = 0;
            }

            // percentual de atingimento (evita divisão por zero)
            if ($avg && $target) {
            $pct = round(
            $isTime 
              ? ($target / $avg * 100) 
              : ($avg / $target * 100),
            2
            );
            } else {
            $pct = 0;
            }

            $headerId   = "heading_{$id}";
            $collapseId = "collapse_{$id}";
          ?>
          <div class="accordion-item">
            <h2 class="accordion-header" id="<?= $headerId ?>">
              <button
                class="accordion-button collapsed"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#<?= $collapseId ?>"
                aria-expanded="false"
                aria-controls="<?= $collapseId ?>"
              >
                <?= htmlspecialchars($okr) ?> — <?= htmlspecialchars($kr) ?>
              </button>
            </h2>

            <div
              id="<?= $collapseId ?>"
              class="accordion-collapse collapse"
              aria-labelledby="<?= $headerId ?>"
              data-bs-parent="#okrAccordion"
            >
              <div class="accordion-body">
                <div class="card-okr mb-4">

                  <header class="okr-header mb-3">
                    <p>
                      <small>
                        <strong>KR:</strong> <?= htmlspecialchars($kr) ?>
                        · <?= htmlspecialchars($equipe) ?> / <?= htmlspecialchars($niveis) ?>
                      </small>
                    </p>
                  </header>

                  <div class="okr-body d-flex justify-content-between">
                    <div class="okr-status-circle">
                      <div class="circle">
                        <strong><?= number_format($pct,2,',','.') ?>%</strong>
                        <span>Status</span>
                      </div>
                    </div>

                    <div class="okr-chart" style="position: relative; width: 100%; height: 250px;">
                      <canvas id="chart_<?= $id ?>"></canvas>
                    </div>

                    <div class="okr-info">
                      <p><strong>
                        <?= $view==='quarter' ? "Q$q - $anoAtual" : "Visão Anual" ?>
                      </strong></p>

                      <p>
                        <strong>Atingido:</strong>
                        <?= $isTime
                            ? gmdate('H:i:s',$avg)
                            : number_format($pct,2,',','.').' %' ?>
                      </p>

                      <p>
                        <strong>Meta:</strong>
                        <?= $isTime
                            ? gmdate('H:i:s',$target)
                            : number_format($target,2,',','.').' %' ?>
                      </p>
                    </div>
                  </div>

                  <footer class="okr-footer d-flex align-items-center">
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

                </div> <!-- /.card-okr -->
              </div> <!-- /.accordion-body -->
            </div> <!-- /.collapse -->
          </div> <!-- /.accordion-item -->

          <script>
const ctx_<?= $id ?> = document.getElementById('chart_<?= $id ?>').getContext('2d');
new Chart(ctx_<?= $id ?>, {
  type: <?= $view==='quarter' ? "'bar'" : "'line'" ?>,
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [
      {
        label: <?= $isTime ? "'Tempo (mm:ss)'" : "'Check-in'" ?>,
        data: <?= json_encode($values_all, JSON_NUMERIC_CHECK) ?>,
        <?= $view==='quarter'
          ? "backgroundColor:'#007bff',"
          : "borderColor:'#007bff',tension:0.4,fill:false," ?>
      },
      {
        label: <?= $isTime ? "'Meta (mm:ss)'" : "'Meta (%)'" ?>,
        data: <?= json_encode($metaLine, JSON_NUMERIC_CHECK) ?>,
        <?= $view==='quarter'
          ? "backgroundColor:'#ccc',"
          : "borderColor:'#ccc',borderDash:[5,5],tension:0.4,fill:false," ?>
      }
    ]
  },
  options: {
    responsive:true,
    maintainAspectRatio:false,
    scales:{
      x:{ ticks:{ autoSkip:false, maxRotation:0, minRotation:0 } },
      y:{
        <?php if($isTime): ?>
        ticks:{
          callback:value=>{
            const s=Math.floor(value),
                  h=Math.floor(s/3600),
                  m=Math.floor((s%3600)/60),
                  sec=s%60;
            return (h>0?(h+':').padStart(3,'0'):'')+
                   String(m).padStart(2,'0')+':' +
                   String(sec).padStart(2,'0');
          }
        }
        <?php else: ?>
        ticks:{
          callback:value=>value.toFixed(2)+' %'
        },
        min:0,max:100
        <?php endif; ?>
      }
    },
    plugins:{
      tooltip:{
        callbacks:{
          label:ctx=>{
            const v=ctx.parsed.y;
            <?php if($isTime): ?>
            const s=Math.floor(v),
                  h=Math.floor(s/3600),
                  m=Math.floor((s%3600)/60),
                  sec=s%60;
            return ctx.dataset.label+': '+
                   (h>0?(h+':').padStart(3,'0'):'')+
                   String(m).padStart(2,'0')+':' +
                   String(sec).padStart(2,'0');
            <?php else: ?>
            return ctx.dataset.label+': '+v.toFixed(2)+' %';
            <?php endif; ?>
          }
        }
      },
      <?php if(!$isTime): ?>legend:{display:false},<?php endif; ?>
    }
  }
});
</script>


        <?php endforeach; ?> <!-- /.accordion -->

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
