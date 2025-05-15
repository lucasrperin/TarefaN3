<?php include '../Config/Database.php';

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
        okr_n.niveis_ids   AS niveis_ids,
        o.descricao        AS okr,
        m.id               AS idMeta,
        COALESCE(m.descricao,'-') AS kr,
        m.menor_melhor,
        m.meta,
        m.meta_seg,
        ai.mes            AS mes,
        COALESCE(ai.realizado,     0) AS realizado,
        COALESCE(ai.realizado_seg, 0) AS realizado_seg
      FROM TB_OKR o
      JOIN TB_EQUIPE e ON e.id = o.idEquipe

      LEFT JOIN (
        SELECT
          onl.idOkr       AS okr_id,
          GROUP_CONCAT(n.descricao SEPARATOR ', ') AS niveis,
          GROUP_CONCAT(onl.idNivel)                AS niveis_ids
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
                'niveis_ids'    => array_map('intval', explode(',', $r['niveis_ids'] ?? '')),
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
        okr_n.niveis_ids   AS niveis_ids,
        o.descricao        AS okr,
        m.id               AS idMeta,
        COALESCE(m.descricao,'-') AS kr,
        m.menor_melhor,
        m.meta,
        m.meta_seg,
        ai.mes            AS mes,
        COALESCE(ai.realizado,     0) AS realizado,
        COALESCE(ai.realizado_seg, 0) AS realizado_seg
      FROM TB_OKR o
      JOIN TB_EQUIPE e ON e.id = o.idEquipe

      LEFT JOIN (
        SELECT
          onl.idOkr       AS okr_id,
          GROUP_CONCAT(n.descricao SEPARATOR ', ') AS niveis,
          GROUP_CONCAT(onl.idNivel)                AS niveis_ids
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
                'niveis_ids'    => array_map('intval', explode(',', $r['niveis_ids'] ?? '')),
                'menor_melhor'  => $r['menor_melhor'],
                'meta'          => $r['meta'],
                'meta_seg'      => $r['meta_seg'],
                'real'          => [],
                'real_seg'      => []
            ];
        }

        // usa null coalescing para evitar warnings
        $mes           = $r['mes'] ?? null;
        $realizado     = $r['realizado']     ?? 0;
        $realizado_seg = $r['realizado_seg'] ?? 0;

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

function imprimeColsYear(array $r) {
    // pega arrays de realizado por mês
    $realArr    = $r['real']     ?? [];
    $realSegArr = $r['real_seg'] ?? [];

    // conta quantos meses têm dado lançado
    $cntPct  = count($realArr);
    $cntTime = count($realSegArr);

    // soma total de realizado
    $sumPct  = array_sum($realArr);
    $sumTime = array_sum($realSegArr);

    if ($r['menor_melhor']) {
        // metas de tempo: faz média de segundos
        $avgSec = $cntTime ? $sumTime / $cntTime : 0;
        $meta   = seg2time($r['meta_seg']);
        $real   = $avgSec ? seg2time(round($avgSec)) : '-';
        $pct    = $avgSec
                ? round($r['meta_seg'] / $avgSec * 100, 2)
                : 0;
    } else {
        // metas percentuais: faz média dos %
        $avgPct = $cntPct ? $sumPct / $cntPct : 0;
        $meta   = number_format($r['meta'], 2, ',', '.') . ' %';
        $real   = number_format($avgPct, 2, ',', '.') . ' %';
        $pct    = $r['meta']
                ? round($avgPct / $r['meta'] * 100, 2)
                : 0;
    }

    // classe de cor
    $cls = $pct >= 100
         ? 'bg-success text-dark'
         : ($pct >= 90
            ? 'bg-warning text-dark'
            : 'bg-danger text-light');

    echo "<td class='text-center'>{$meta}</td>";
    echo "<td class='text-center'>{$real}</td>";
    echo "<td class='text-center {$cls}'>". number_format($pct,2,',','.') ." %</td>";
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

// 1.1) Recebe o nível selecionado (via GET)
$nivelSel = isset($_GET['nivel']) ? intval($_GET['nivel']) : 0;
$nivelDesc = '';
if ($nivelSel) {
    $stmtN = $conn->prepare("SELECT descricao FROM TB_NIVEL WHERE id = ?");
    $stmtN->bind_param('i', $nivelSel);
    $stmtN->execute();
    $stmtN->bind_result($nivelDesc);
    $stmtN->fetch();
    $stmtN->close();
}

// 1.2) Lista de todos os níveis (para renderizar os cards de filtro)
$listaNiveis = $conn->query(" SELECT 
                                ni.id, 
                                  ni.descricao 
                              FROM TB_NIVEL ni
                              INNER JOIN TB_OKR_NIVEL okn 
                                ON okn.idNivel = ni.id
                              GROUP BY ni.id
                              ORDER BY ni.descricao");

// --- 1) ACHATAR $data EM $cardsData ---
$cardsData = []; $seen = [];
foreach($data as $grp=>$metas){
  list($equipe,$niveisStr)=explode('||',$grp);
  foreach($metas as $key=>$m){
    if($nivelSel && !in_array($nivelSel,$m['niveis_ids'])) continue;
    if(isset($seen[$key])) continue;
    $seen[$key]=true;
    $cardsData[]=[
      'key'=>$key,
      'metaData'=>$m,
      'equipe'=>$equipe,
      'niveis'=>$niveisStr
    ];
  }
}

// agrupa por OKR já definindo isTime e target
$okrGroups = [];
foreach($cardsData as $card){
  list($okrId,$metaId)=explode('-',$card['key']);
  if(!isset($okrGroups[$okrId])){
    $okrGroups[$okrId]=[
      'okr'=>$card['metaData']['okr'],
      'niveis' => $card['niveis'],
      'items'=>[]
    ];
  }
  $m = $card['metaData'];
  $okrGroups[$okrId]['items'][$metaId]=[
    'kr'=>$m['kr'],
    'isTime'=>($m['menor_melhor']==1),
    'real'=>$m['real']     ?? [],
    'real_seg'=>$m['real_seg'] ?? [],
    'target'=>($m['menor_melhor']==1 ? $m['meta_seg'] : $m['meta']),
    'equipe'=>$card['equipe'],
    'niveis'=>$card['niveis'],
  ];
}

?>