<?php
require_once __DIR__ . '/../../Includes/auth.php';
require_once __DIR__ . '/../../Config/Database.php';

$usuario_nome = $_SESSION['usuario_nome'] ?? '';

// busca última data de geração
$res = $conn->query("SELECT MAX(data_geracao) AS ultima FROM TB_EMBEDDINGS");
$row = $res->fetch_assoc();
$ultima = $row['ultima']
    ? date('d/m/Y H:i:s', strtotime($row['ultima']))
    : 'Nunca';

// busca histórico (últimos 50)
$hist = $conn->query("
  SELECT id, titulo, origem, link, arquivo_json, status, data_inicio, data_fim
    FROM TB_TREINAMENTOS_BOT
ORDER BY id DESC
   LIMIT 50
");
$historico = [];
if ($hist) while ($r = $hist->fetch_assoc()) $historico[] = $r;

// ====== Resposta parcial para atualização do histórico ======
if (isset($_SERVER['HTTP_X_PARTIAL']) && $_SERVER['HTTP_X_PARTIAL'] === 'historico') {
  $hist2 = $conn->query("
    SELECT id, titulo, origem, link, arquivo_json, status, data_inicio, data_fim
      FROM TB_TREINAMENTOS_BOT
  ORDER BY id DESC
     LIMIT 50
  ");
  echo '<tbody id="histBody">';
  if ($hist2 && $hist2->num_rows) {
    while ($h = $hist2->fetch_assoc()) {
      $badgeClass = $h['status'] === 'CONCLUIDO' ? 'badge-success' : ($h['status'] === 'ERRO' ? 'badge-danger' : 'badge-warning');
      echo '<tr class="row-hover">';
      echo '<td class="text-muted">'.(int)$h['id'].'</td>';
      echo '<td>'.htmlspecialchars($h['titulo'] ?? '').'</td>';
      echo '<td>'.($h['origem'] === 'url' ? '<span class="badge badge-soft-info">URL</span>' : '<span class="badge badge-soft-secondary">Upload</span>').'</td>';
      echo '<td>'.(!empty($h['link']) ? '<a href="'.htmlspecialchars($h['link']).'" target="_blank" rel="noopener" class="link-muted">abrir</a>' : '<span class="text-muted">—</span>').'</td>';
      echo '<td>'.(!empty($h['arquivo_json']) ? '<a href="'.htmlspecialchars($h['arquivo_json']).'" target="_blank" rel="noopener" class="link-muted">baixar</a>' : '<span class="text-muted">—</span>').'</td>';
      echo '<td><span class="badge '.$badgeClass.'">'.htmlspecialchars($h['status']).'</span></td>';
      echo '<td>'.($h['data_inicio'] ? date('d/m/Y H:i', strtotime($h['data_inicio'])) : '—').'</td>';
      echo '<td>'.($h['data_fim'] ? date('d/m/Y H:i', strtotime($h['data_fim'])) : '—').'</td>';
      echo '<td>';
      if ($h['status'] !== 'PROCESSANDO') {
        echo '<button type="button" class="btn btn-sm btn-outline-secondary btn-log" data-id="'.(int)$h['id'].'"><i class="bi bi-journal-text me-1"></i>Ver log</button>';
      } else {
        echo '<span class="text-muted d-inline-flex align-items-center"><span class="spinner-border spinner-border-sm me-2"></span>Aguardando</span>';
      }
      echo '</td>';
      echo '</tr>';
    }
  } else {
    echo '<tr><td colspan="9" class="text-muted">Sem registros.</td></tr>';
  }
  echo '</tbody>';
  exit;
}

// ====== Resposta parcial para obter o log de um ID ======
if (isset($_SERVER['HTTP_X_PARTIAL']) && $_SERVER['HTTP_X_PARTIAL'] === 'log') {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($id <= 0) { http_response_code(400); echo "ID inválido"; exit; }
  $q = $conn->prepare("SELECT log FROM TB_TREINAMENTOS_BOT WHERE id=?");
  $q->bind_param('i', $id);
  $q->execute();
  $res = $q->get_result();
  $log = '';
  if ($res && $row = $res->fetch_assoc()) $log = $row['log'] ?? '';
  header('Content-Type: text/plain; charset=utf-8');
  echo $log;
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Configuração do Chatbot</title>

  <!-- Font Awesome e Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <!-- CSS local -->
  <link rel="stylesheet" href="../../Public/config_bot.css">

  <!-- Tema novo (sem alterar header/menu) -->
  <style>
    :root{
      --bg-grad: radial-gradient(1200px 600px at 0% 0%, #eef2ff, transparent 60%),
                 radial-gradient(1000px 500px at 100% 0%, #ecfeff, transparent 55%);
      --card-bg: #fff;
      --card-bd: rgba(0,0,0,.06);
      --shadow: 0 10px 25px rgba(0,0,0,.08);
      --text-soft: #6b7280;
      --accent: #4f46e5;
    }
    [data-theme="dark"]{
      --bg-grad: radial-gradient(1200px 600px at 0% 0%, #111827, transparent 60%),
                 radial-gradient(1000px 500px at 100% 0%, #0b1220, transparent 55%);
      --card-bg: #0f172a;
      --card-bd: rgba(255,255,255,.06);
      --shadow: 0 8px 22px rgba(0,0,0,.55);
      --text-soft: #9ca3af;
      --accent: #60a5fa;
    }
    body{
      background:
        var(--bg-grad),
        linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,.03) 100%);
      min-height: 100vh;
    }
    .section{
      background: var(--card-bg);
      border: 1px solid var(--card-bd);
      border-radius: 18px;
      box-shadow: var(--shadow);
      padding: 1.25rem;
    }
    .section-title{
      display:flex; align-items:center; gap:.6rem; margin-bottom:1rem;
      font-weight: 700;
    }
    .section-title .ico{
      width:36px;height:36px;border-radius:10px;
      display:grid; place-items:center;
      background: rgba(79,70,229,.12); color: var(--accent);
    }
    .split{
      display:grid; grid-template-columns: 1fr; gap:1rem;
    }
    @media (min-width: 992px){
      .split{ grid-template-columns: 1fr 1fr; }
    }
    .stat{
      display:flex; align-items:center; justify-content:space-between; gap:1rem;
      background: linear-gradient(135deg, rgba(79,70,229,.08), rgba(14,165,233,.07));
      border:1px dashed rgba(79,70,229,.25);
      padding: .9rem 1rem; border-radius: 14px;
    }
    .btn-pill{ border-radius: 999px; }
    .dropzone{
      border:1.5px dashed rgba(0,0,0,.25);
      border-radius:14px;
      padding:1rem;
      display:flex; align-items:center; justify-content:center; gap:.75rem;
      color: var(--text-soft);
      transition:.15s ease;
      background: rgba(0,0,0,.02);
      cursor: pointer;
      text-align:center;
    }
    [data-theme="dark"] .dropzone{ background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.15); }
    .dropzone.is-dragover{ border-color: var(--accent); background: rgba(79,70,229,.08); color:#111; }
    .subtle{ color: var(--text-soft); font-size:.925rem; }
    .chip{
      display:inline-flex; align-items:center; gap:.5rem;
      background: rgba(79,70,229,.12); color: var(--accent);
      border:1px solid rgba(79,70,229,.22); border-radius:999px;
      padding:.25rem .65rem; font-weight:600;
    }
    /* Tabela moderna */
    .table-modern thead th{
      position:sticky; top:0; z-index:1; background:rgba(0,0,0,.04); backdrop-filter:saturate(140%) blur(2px);
    }
    [data-theme="dark"] .table-modern thead th{ background:rgba(255,255,255,.06); }
    .row-hover:hover{ background: rgba(79,70,229,.06); }
    .badge{ border-radius: 999px; font-weight:600; }
    .badge-success{ background: rgba(16,185,129,.2); color:#065f46; border:1px solid rgba(16,185,129,.35); }
    .badge-danger{ background: rgba(239,68,68,.2); color:#7f1d1d; border:1px solid rgba(239,68,68,.35); }
    .badge-warning{ background: rgba(245,158,11,.2); color:#7c2d12; border:1px solid rgba(245,158,11,.35); }
    .badge-soft-info{ background: rgba(59,130,246,.14); color:#1e3a8a; border:1px solid rgba(59,130,246,.28); }
    .badge-soft-secondary{ background: rgba(107,114,128,.18); color:#111827; border:1px solid rgba(107,114,128,.28); }
    .link-muted{ text-decoration: none; }
    .link-muted:hover{ text-decoration: underline; }
    /* Overlay forte */
    body.loading { overflow: hidden; }
    #busyOverlay{
      position: fixed; inset: 0; display:none; align-items:center; justify-content:center;
      background: rgba(0,0,0,.5); z-index: 2147483647;
    }
    #busyOverlay.show{ display:flex; }
  </style>
</head>
<body data-theme="">

<div class="d-flex-wrapper">
  <?php include __DIR__ . '/../../components/sidebar_bot.php'; ?>

  <div class="w-100 flex-grow-1 d-flex flex-column">
    <!-- Header (não alterado) -->
    <div class="header d-flex justify-content-between align-items-center p-3 border-bottom">
      <h3 class="mb-0"><i class="bi bi-gear me-2"></i>Configuração do Chatbot</h3>
      <div class="user-info d-flex align-items-center gap-2">
        <span>Bem-vindo, <strong><?= htmlspecialchars($usuario_nome, ENT_QUOTES, 'UTF-8') ?></strong>!</span>
        <a href="/TarefaN3/Views/logout.php" class="btn btn-danger btn-sm">
          <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
        </a>
        <button class="theme-toggle-btn btn btn-outline-secondary btn-sm" id="themeBtn" title="Alternar tema">
          <i class="fa fa-moon"></i>
        </button>
      </div>
    </div>

    <!-- Conteúdo principal (novo layout) -->
    <div class="page-content p-4">

      <div class="section mb-4">
        <div class="section-title">
          <div class="ico"><i class="fa fa-database"></i></div>
          <div>Embeddings</div>
        </div>
        <div class="stat mb-3">
          <div>
            <div class="subtle">Última geração de embeddings</div>
            <div class="fs-5 fw-bold"><?= htmlspecialchars($ultima) ?></div>
          </div>
          <button class="btn btn-primary btn-pill" id="btnExecutar">
            <i class="fa fa-bolt me-1"></i> Gerar Novos Embeddings
          </button>
        </div>
        <div id="log" class="log-box" style="display:none;"></div>
      </div>

      <div class="split mb-4">
        <div class="section">
          <div class="section-title">
            <div class="ico"><i class="bi bi-easel2"></i></div>
            <div>Treinamento por Vídeo</div>
          </div>

          <form id="formTreinamento" enctype="multipart/form-data">
            <div class="mb-3">
              <label for="videoTitle" class="form-label">Título do treinamento</label>
              <input class="form-control" type="text" name="titulo" id="videoTitle" maxlength="180" placeholder="Ex.: NT GO - DIFAL 2025-08-08" required>
              <div class="form-text">Será salvo dentro do JSON e também como nome do arquivo.</div>
            </div>

            <div class="mb-3">
              <div id="dropzone" class="dropzone">
                <i class="fa fa-cloud-arrow-up"></i>
                <span><strong>Arraste e solte</strong> um arquivo aqui ou <u>clique para selecionar</u> (.mp4/.mp3)</span>
              </div>
              <input class="form-control d-none" type="file" name="video" id="videoFile" accept="video/*,audio/*">
            </div>

            <div class="mb-3">
              <label for="videoLink" class="form-label">Ou informe um link de vídeo</label>
              <input class="form-control" type="url" name="link" id="videoLink" placeholder="https://...">
            </div>

            <div class="d-flex align-items-center gap-3">
              <button type="submit" class="btn btn-success btn-pill">
                <span class="me-1"><i class="fa fa-brain"></i></span> Transcrever e Treinar
              </button>
              <span id="liveHint" class="chip d-none">
                <span class="spinner-border spinner-border-sm"></span> Processando…
              </span>
            </div>

            <!-- Barra de progresso -->
            <div class="progress mt-3" id="progressBarContainer" style="height: 10px; display: none;">
              <div class="progress-bar bg-primary" role="progressbar" id="uploadProgressBar" style="width: 0%"></div>
            </div>

            <div id="logTreinamento" class="log-box mt-3" style="display:none;"></div>
          </form>
        </div>

        <div class="section">
          <div class="section-title">
            <div class="ico"><i class="bi bi-info-circle"></i></div>
            <div>Dicas rápidas</div>
          </div>
          <ul class="mb-0 subtle">
            <li>Use títulos claros — eles viram o <em>nome do arquivo JSON</em>.</li>
            <li>Envie <strong>arquivo OU link</strong>, não os dois ao mesmo tempo.</li>
            <li>Durante o processamento, acompanhe o status no histórico abaixo.</li>
          </ul>
        </div>
      </div>

      <div class="section">
        <div class="section-title">
          <div class="ico"><i class="bi bi-clock-history"></i></div>
          <div>Histórico de Treinamentos</div>
        </div>

        <div class="d-flex justify-content-end">
          <button class="btn btn-outline-secondary btn-sm btn-pill" id="btnReloadHist">
            <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
          </button>
        </div>

        <div class="table-responsive mt-3" id="histContainer">
          <table class="table table-modern table-sm align-middle">
            <thead>
              <tr>
                <th style="min-width:60px">#</th>
                <th style="min-width:240px">Título</th>
                <th>Origem</th>
                <th>Link</th>
                <th>JSON</th>
                <th>Status</th>
                <th>Início</th>
                <th>Fim</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody id="histBody">
            <?php if (!$historico): ?>
              <tr><td colspan="9" class="text-muted">Sem registros.</td></tr>
            <?php else: foreach ($historico as $h): ?>
              <tr class="row-hover">
                <td class="text-muted"><?= (int)$h['id'] ?></td>
                <td><?= htmlspecialchars($h['titulo'] ?? '') ?></td>
                <td><?= $h['origem'] === 'url' ? '<span class="badge badge-soft-info">URL</span>' : '<span class="badge badge-soft-secondary">Upload</span>' ?></td>
                <td><?= !empty($h['link']) ? '<a href="'.htmlspecialchars($h['link']).'" target="_blank" rel="noopener" class="link-muted">abrir</a>' : '<span class="text-muted">—</span>' ?></td>
                <td><?= !empty($h['arquivo_json']) ? '<a href="'.htmlspecialchars($h['arquivo_json']).'" target="_blank" rel="noopener" class="link-muted">baixar</a>' : '<span class="text-muted">—</span>' ?></td>
                <td>
                  <?php
                    $status = $h['status'];
                    $badgeClass = $status === 'CONCLUIDO' ? 'badge-success'
                               : ($status === 'ERRO' ? 'badge-danger' : 'badge-warning');
                  ?>
                  <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                </td>
                <td><?= $h['data_inicio'] ? date('d/m/Y H:i', strtotime($h['data_inicio'])) : '—' ?></td>
                <td><?= $h['data_fim'] ? date('d/m/Y H:i', strtotime($h['data_fim'])) : '—' ?></td>
                <td>
                  <?php if ($h['status'] !== 'PROCESSANDO'): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-log" data-id="<?= (int)$h['id'] ?>">
                      <i class="bi bi-journal-text me-1"></i>Ver log
                    </button>
                  <?php else: ?>
                    <span class="text-muted d-inline-flex align-items-center">
                      <span class="spinner-border spinner-border-sm me-2"></span>Aguardando
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Toast container -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1080">
  <div id="appToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body" id="toastMsg">OK</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<!-- Modal Log -->
<div class="modal fade" id="logModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-journal-text me-2"></i>Log do treinamento</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <pre id="logContent" class="mb-0" style="white-space:pre-wrap; font-size: .875rem;"></pre>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- Overlay "ocupado" -->
<div id="busyOverlay">
  <div style="text-align:center;color:#fff">
    <div class="spinner-border" role="status" aria-hidden="true"></div>
    <div style="margin-top:.75rem;font-weight:600">Processando o vídeo… isso pode levar alguns minutos</div>
  </div>
</div>

<!-- Bootstrap bundle antes do nosso script -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script -->
<script>
  // Theme toggle
  const themeBtn = document.getElementById('themeBtn');
  themeBtn.onclick = () => {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    document.documentElement.setAttribute('data-theme', isDark ? '' : 'dark');
    themeBtn.innerHTML = isDark ? '<i class="fa fa-moon"></i>' : '<i class="fa fa-sun"></i>';
  };

  // Toast helper
  let toastEl = document.getElementById('appToast');
  let toast = new bootstrap.Toast(toastEl, { delay: 3000 });
  function showToast(message, variant='success') {
    toastEl.className = 'toast align-items-center border-0 text-bg-' + (variant === 'error' ? 'danger' : (variant === 'warn' ? 'warning' : 'success'));
    document.getElementById('toastMsg').textContent = message;
    toast.show();
  }

  // Execução das etapas (embeddings tradicionais)
  const logDiv = document.getElementById('log');
  const btnExec = document.getElementById('btnExecutar');
  btnExec.addEventListener('click', async () => {
    const etapas = ['backup', 'gerar', 'upload'];
    logDiv.innerHTML = '';
    logDiv.style.display = 'block';
    btnExec.disabled = true;

    for (const etapa of etapas) {
      const id = 'etapa-' + etapa;
      logDiv.innerHTML += `
        <div id="${id}">
          ⏳ Executando etapa: ${etapa}...
          <span class="spinner-border spinner-border-sm text-primary ms-1"></span>
        </div>
      `;
      const resp = await fetch('executar_etapas.php?etapa=' + encodeURIComponent(etapa));
      const txt  = await resp.text();
      const container = document.getElementById(id);

      if (!resp.ok || txt.startsWith('❌')) {
        container.innerHTML = `<span style="color:red;">${txt}</span> 🛑`;
        btnExec.disabled = false;
        return;
      }
      container.innerHTML = `<span style="color:green;">${txt}</span>`;
    }

    logDiv.innerHTML += "<b style='color:green;'>✅ Processo finalizado com sucesso.</b>";
    btnExec.disabled = false;
  });

  // Dropzone (arraste e solte)
  const dz = document.getElementById('dropzone');
  const fileInput = document.getElementById('videoFile');
  dz.addEventListener('click', () => fileInput.click());
  ;['dragenter','dragover'].forEach(evt => dz.addEventListener(evt, e => {
    e.preventDefault(); e.stopPropagation(); dz.classList.add('is-dragover');
  }));
  ;['dragleave','drop'].forEach(evt => dz.addEventListener(evt, e => {
    e.preventDefault(); e.stopPropagation(); dz.classList.remove('is-dragover');
  }));
  dz.addEventListener('drop', e => {
    const f = e.dataTransfer.files && e.dataTransfer.files[0];
    if (f) {
      fileInput.files = e.dataTransfer.files; // aceita múltiplos, usaremos o primeiro
      dz.querySelector('span').innerHTML = `<strong>Arquivo selecionado:</strong> ${f.name}`;
    }
  });
  fileInput.addEventListener('change', e => {
    const f = e.target.files && e.target.files[0];
    if (f) dz.querySelector('span').innerHTML = `<strong>Arquivo selecionado:</strong> ${f.name}`;
  });

  // Treinamento com barra de progresso + overlay (interpreta JSON)
  document.getElementById('formTreinamento').addEventListener('submit', function (e) {
    e.preventDefault();

    const logTreino = document.getElementById('logTreinamento');
    const progressContainer = document.getElementById('progressBarContainer');
    const progressBar = document.getElementById('uploadProgressBar');
    const button = e.target.querySelector('button[type="submit"]');
    const originalText = button.innerHTML;
    const overlay = document.getElementById('busyOverlay');
    const liveHint = document.getElementById('liveHint');

    const file  = document.getElementById('videoFile').files[0];
    const link  = document.getElementById('videoLink').value.trim();
    const title = document.getElementById('videoTitle').value.trim();

    if (!title) { alert('Informe um título para o treinamento.'); return; }
    if (!file && !link) { alert('Envie um arquivo ou informe um link.'); return; }
    if (file && link) { alert('Informe apenas arquivo OU link.'); return; }

    const formData = new FormData();
    formData.append('titulo', title);
    if (file) formData.append('video', file);
    if (link) formData.append('link', link);

    // Visual: overlay + barra para upload
    document.body.classList.add('loading');
    overlay.classList.add('show');

    logTreino.innerHTML = '';
    logTreino.style.display = 'none';
    progressBar.style.width = file ? '0%' : '100%';
    progressBar.className = 'progress-bar bg-primary' + (file ? '' : ' progress-bar-striped progress-bar-animated');
    progressContainer.style.display = 'block';

    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Enviando...';

    const xhr = new XMLHttpRequest();
    xhr.timeout = 30 * 60 * 1000; // 30 minutos

    xhr.upload.onprogress = function (e) {
      if (e.lengthComputable && file) {
        const percent = Math.round((e.loaded / e.total) * 100);
        progressBar.style.width = percent + '%';
      }
    };

    function endUIReset(){
      document.body.classList.remove('loading');
      overlay.classList.remove('show');
      progressContainer.style.display = 'none';
      button.disabled = false;
      button.innerHTML = originalText;
      liveHint.classList.add('d-none');
    }

    xhr.onload = function () {
      endUIReset();

      let respJson = null;
      try { respJson = JSON.parse(xhr.responseText); } catch(e){}

      if (xhr.status === 200 && respJson && respJson.ok) {
        showToast(respJson.message || 'Treinado com sucesso!', 'success');
      } else {
        const msg = (respJson && respJson.message) ? respJson.message : 'Erro ao processar o vídeo.';
        showToast(msg, 'error');
        if (respJson && respJson.id) {
          openLogModal(respJson.id);
        }
      }
      reloadHistorico();
    };

    xhr.onerror = function () {
      endUIReset();
      showToast('Erro de rede ao enviar o vídeo.', 'error');
      reloadHistorico();
    };

    xhr.ontimeout = function () {
      endUIReset();
      showToast('Tempo excedido. O processamento pode ter continuado no servidor — verifique o histórico.', 'warn');
      reloadHistorico();
    };

    xhr.open('POST', '/TarefaN3/ChatBot/scripts/chamar_processa_video.php', true);
    xhr.send(formData);
  });

  // Botão atualizar histórico
  document.getElementById('btnReloadHist').addEventListener('click', reloadHistorico);

  async function reloadHistorico() {
    try {
      const resp = await fetch(location.href, { headers: { 'X-Partial': 'historico' }});
      const html = await resp.text();
      const tmp = document.createElement('div');
      tmp.innerHTML = html;
      const tbody = tmp.querySelector('#histBody');
      if (tbody) {
        document.querySelector('#histBody').replaceWith(tbody);
      } else {
        location.reload();
      }
    } catch {
      location.reload();
    }
  }

  // Ver log (delegação)
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.btn-log');
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    if (id) openLogModal(id);
  });

  async function openLogModal(id) {
    try {
      const resp = await fetch(location.href + '?id=' + encodeURIComponent(id), { headers: { 'X-Partial': 'log' }});
      const txt = await resp.text();
      document.getElementById('logContent').textContent = txt || '(log vazio)';
      const modal = new bootstrap.Modal(document.getElementById('logModal'));
      modal.show();
    } catch (e) {
      showToast('Não foi possível carregar o log.', 'error');
    }
  }
</script>
</body>
</html>
