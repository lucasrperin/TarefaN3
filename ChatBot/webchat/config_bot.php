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
      $badgeClass = $h['status'] === 'CONCLUIDO' ? 'text-bg-success' : ($h['status'] === 'ERRO' ? 'text-bg-danger' : 'text-bg-warning');
      echo '<tr>';
      echo '<td>'.(int)$h['id'].'</td>';
      echo '<td>'.htmlspecialchars($h['titulo'] ?? '').'</td>';
      echo '<td>'.($h['origem'] === 'url' ? '<span class="badge text-bg-info">URL</span>' : '<span class="badge text-bg-secondary">Upload</span>').'</td>';
      echo '<td>'.(!empty($h['link']) ? '<a href="'.htmlspecialchars($h['link']).'" target="_blank" rel="noopener">abrir</a>' : '<span class="text-muted">—</span>').'</td>';
      echo '<td>'.(!empty($h['arquivo_json']) ? '<a href="'.htmlspecialchars($h['arquivo_json']).'" target="_blank" rel="noopener">baixar</a>' : '<span class="text-muted">—</span>').'</td>';
      echo '<td><span class="badge '.$badgeClass.'">'.htmlspecialchars($h['status']).'</span></td>';
      echo '<td>'.($h['data_inicio'] ? date('d/m/Y H:i', strtotime($h['data_inicio'])) : '—').'</td>';
      echo '<td>'.($h['data_fim'] ? date('d/m/Y H:i', strtotime($h['data_fim'])) : '—').'</td>';
      echo '<td>';
      if ($h['status'] !== 'PROCESSANDO') {
        echo '<button type="button" class="btn btn-sm btn-outline-secondary btn-log" data-id="'.(int)$h['id'].'"><i class="bi bi-journal-text me-1"></i>Ver log</button>';
      } else {
        echo '<button type="button" class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-hourglass-split me-1"></i>Aguardando</button>';
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

  <!-- Overlay CSS forte -->
  <style>
    body.loading { overflow: hidden; }
    #busyOverlay{
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(0,0,0,.45);
      z-index: 2147483647;
    }
    #busyOverlay.show{ display:flex; }
  </style>
</head>
<body data-theme="">

<div class="d-flex-wrapper">
  <?php include __DIR__ . '/../../components/sidebar_bot.php'; ?>

  <div class="w-100 flex-grow-1 d-flex flex-column">
    <!-- Header -->
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

    <!-- Conteúdo principal -->
    <div class="page-content p-4">
      <p><strong>Última geração de embeddings:</strong> <?= htmlspecialchars($ultima) ?></p>
      <button class="btn btn-primary mb-3" id="btnExecutar">
        <i class="fa fa-bolt me-1"></i> Gerar Novos Embeddings
      </button>
      <div id="log" class="log-box" style="display:none;"></div>
      <hr class="my-4">

      <!-- Treinamento por vídeo -->
      <h5><i class="bi bi-easel2 me-2"></i>Treinamento por Vídeo</h5>
      <form id="formTreinamento" enctype="multipart/form-data">
        <div class="mb-3">
          <label for="videoTitle" class="form-label">Título do treinamento</label>
          <input class="form-control" type="text" name="titulo" id="videoTitle" maxlength="180" placeholder="Ex.: NT GO - DIFAL 2025-08-08" required>
          <div class="form-text">Será salvo dentro do JSON e também como nome do arquivo.</div>
        </div>
        <div class="mb-3">
          <label for="videoFile" class="form-label">Enviar vídeo (.mp4/.mp3):</label>
          <input class="form-control" type="file" name="video" id="videoFile" accept="video/*,audio/*">
        </div>
        <div class="mb-3">
          <label for="videoLink" class="form-label">Ou informe um link de vídeo:</label>
          <input class="form-control" type="url" name="link" id="videoLink" placeholder="https://...">
        </div>
        <button type="submit" class="btn btn-success">
          <i class="fa fa-brain me-1"></i> Transcrever e Treinar
        </button>
      </form>

      <!-- Barra de progresso -->
      <div class="progress mt-2" id="progressBarContainer" style="height: 10px; display: none;">
        <div class="progress-bar bg-primary" role="progressbar" id="uploadProgressBar" style="width: 0%"></div>
      </div>

      <div id="logTreinamento" class="log-box mt-3" style="display:none;"></div>

      <hr class="my-4">

      <!-- Histórico de Treinamentos -->
      <div class="d-flex align-items-center justify-content-between">
        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Histórico de Treinamentos</h5>
        <button class="btn btn-outline-secondary btn-sm" id="btnReloadHist">
          <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
        </button>
      </div>
      <div class="table-responsive mt-3" id="histContainer">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Título</th>
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
            <tr>
              <td><?= (int)$h['id'] ?></td>
              <td><?= htmlspecialchars($h['titulo'] ?? '') ?></td>
              <td><?= $h['origem'] === 'url' ? '<span class="badge text-bg-info">URL</span>' : '<span class="badge text-bg-secondary">Upload</span>' ?></td>
              <td><?= !empty($h['link']) ? '<a href="'.htmlspecialchars($h['link']).'" target="_blank" rel="noopener">abrir</a>' : '<span class="text-muted">—</span>' ?></td>
              <td><?= !empty($h['arquivo_json']) ? '<a href="'.htmlspecialchars($h['arquivo_json']).'" target="_blank" rel="noopener">baixar</a>' : '<span class="text-muted">—</span>' ?></td>
              <td>
                <?php
                  $status = $h['status'];
                  $badgeClass = $status === 'CONCLUIDO' ? 'text-bg-success'
                             : ($status === 'ERRO' ? 'text-bg-danger' : 'text-bg-warning');
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
                  <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                    <i class="bi bi-hourglass-split me-1"></i>Aguardando
                  </button>
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

<!-- Carrega Bootstrap bundle ANTES do nosso script -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Nosso script -->
<script>
  // Theme toggle
  const themeBtn = document.getElementById('themeBtn');
  themeBtn.onclick = () => {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    document.documentElement.setAttribute('data-theme', isDark ? '' : 'dark');
    themeBtn.innerHTML = isDark ? '<i class="fa fa-moon"></i>' : '<i class="fa fa-sun"></i>';
  };

  // Toast helper (agora bootstrap já está carregado)
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

  // Treinamento com barra de progresso + overlay (interpreta JSON)
  document.getElementById('formTreinamento').addEventListener('submit', function (e) {
    e.preventDefault();

    const logTreino = document.getElementById('logTreinamento');
    const progressContainer = document.getElementById('progressBarContainer');
    const progressBar = document.getElementById('uploadProgressBar');
    const button = e.target.querySelector('button[type="submit"]');
    const originalText = button.innerHTML;
    const overlay = document.getElementById('busyOverlay');

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

    // Visual: overlay + barra indeterminada para link
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
        } else {
          reloadHistorico();
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

  // Ver log
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
