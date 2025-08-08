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
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- CSS local -->
  <link rel="stylesheet" href="../../Public/config_bot.css">
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
      <!-- Botão para gerar embeddings -->
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
        <div class="progress-bar bg-primary"
             role="progressbar"
             id="uploadProgressBar"
             style="width: 0%">
        </div>
      </div>

      <div id="logTreinamento" class="log-box mt-3" style="display:none;"></div>
    </div>
  </div>
</div>

  <!-- Scripts -->
  <script>

  // Theme toggle
  const themeBtn = document.getElementById('themeBtn');
  themeBtn.onclick = () => {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    document.documentElement.setAttribute('data-theme', isDark ? '' : 'dark');
    themeBtn.innerHTML = isDark ? '<i class="fa fa-moon"></i>' : '<i class="fa fa-sun"></i>';
  };

  // Execução das etapas (embeddings tradicionais)
  const logDiv = document.getElementById('log');
  const btnExec = document.getElementById('btnExecutar');
  btnExec.addEventListener('click', async () => {
    const etapas = ['backup', 'gerar', 'upload'];
    logDiv.innerHTML = '';
    logDiv.style.display = 'block';
    btnExec.disabled = true;

    for (let etapa of etapas) {
      const id = 'etapa-' + etapa;
      logDiv.innerHTML += `
        <div id="${id}">
          ⏳ Executando etapa: ${etapa}...
          <span class="spinner-border spinner-border-sm text-primary ms-1"></span>
        </div>
      `;
      const resp = await fetch('executar_etapas.php?etapa=' + etapa);
      const txt  = await resp.text();
      const container = document.getElementById(id);

      for (let etapa of etapas) {
        const id = 'etapa-' + etapa;
        logDiv.innerHTML += `
          <div id="${id}">
            ⏳ Executando etapa: ${etapa}...
            <span class="spinner-border spinner-border-sm text-primary ms-1"></span>
          </div>`;
        const resp = await fetch('executar_etapas.php?etapa=' + etapa);
        const txt  = await resp.text();
        const container = document.getElementById(id);

        if (!resp.ok || txt.startsWith('❌')) {
          container.innerHTML = `<span style="color:red;">${txt}</span> 🛑`;
          btnExec.disabled = false;
          return;
        }

        container.innerHTML = `<span style="color:green;">${txt}</span>`;
      }

      container.innerHTML = `<span style="color:green;">${txt}</span>`;
    }

    logDiv.innerHTML += "<b style='color:green;'>✅ Processo finalizado com sucesso.</b>";
    btnExec.disabled = false;
  });

  // Treinamento com barra de progresso real ou animada
  document.getElementById('formTreinamento').addEventListener('submit', function (e) {
    e.preventDefault();

    const logTreino = document.getElementById('logTreinamento');
    const progressContainer = document.getElementById('progressBarContainer');
    const progressBar = document.getElementById('uploadProgressBar');
    const form = e.target;
    const button = form.querySelector('button[type="submit"]');
    const originalText = button.innerHTML;

    const formData = new FormData();
    const file = document.getElementById('videoFile').files[0];
    const link = document.getElementById('videoLink').value.trim();

    if (file) formData.append('video', file);
    if (link) formData.append('link', link);

    // reset
    logTreino.innerHTML = '';
    logTreino.style.display = 'block';
    progressBar.style.width = file ? '0%' : '100%';
    progressBar.className = 'progress-bar bg-primary' + (file ? '' : ' progress-bar-striped progress-bar-animated');
    progressContainer.style.display = 'block';

    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Enviando...';

    const xhr = new XMLHttpRequest();

    xhr.upload.onprogress = function (e) {
      if (e.lengthComputable && file) {
        const percent = Math.round((e.loaded / e.total) * 100);
        progressBar.style.width = percent + '%';
      }
    };

    xhr.onload = function () {
      progressContainer.style.display = 'none';
      button.disabled = false;
      button.innerHTML = originalText;

      const responseText = xhr.responseText;
      if (xhr.status === 200) {
        logTreino.innerHTML = `<span style="color:green;">${responseText}</span>`;
      } else {
        logTreino.innerHTML = `<span style="color:red;">${responseText}</span>`;
      }
    };

    xhr.onerror = function () {
      progressContainer.style.display = 'none';
      button.disabled = false;
      button.innerHTML = originalText;
      logTreino.innerHTML = `<span style="color:red;">❌ Erro ao enviar o vídeo.</span>`;
    };

    xhr.open('POST', '/TarefaN3/ChatBot/scripts/chamar_processa_video.php', true);
    xhr.send(formData);
  });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
