<?php
require_once __DIR__ . '/../../Includes/auth.php';
$usuario_nome = $_SESSION['usuario_nome'] ?? '';
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
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Seus CSS locais -->
  <link rel="stylesheet" href="../../Public/config_bot.css">

</head>
<body data-theme="">

  <div class="d-flex-wrapper">
    <?php include __DIR__ . '/../../components/sidebar_bot.php'; ?>

    <div class="w-100 flex-grow-1 d-flex flex-column">
      <!-- header igual ao das outras telas -->
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

      <!-- conteúdo principal -->
      <div class="page-content">
        <button class="btn btn-primary mb-3" id="btnExecutar">
          <i class="fa fa-bolt me-1"></i> Gerar Novos Embeddings
        </button>
        <div id="log" class="log-box" style="display:none;"></div>
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

    // Lógica de execução das etapas
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
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
