<?php
// index.php (renomeado de index.html para index.php)
// Ajuste o caminho para o seu Includes/auth.php
require_once __DIR__ . '/../../Includes/auth.php';

// Variáveis de sessão
$usuario_id   = $_SESSION['usuario_id'] ?? null;
$usuario_nome = $_SESSION['usuario_nome'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>IA Workspace Chat</title>

  <!-- Font Awesome e Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">

  <!-- Seus CSS locais -->
  <link rel="stylesheet" href="/TarefaN3/ChatBot/webchat/chatbot.css">

  <!-- Parser Markdown -->
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

  <script>
    // Expondo o ID do usuário ao JS
    window.USER_ID = <?= json_encode($usuario_id, JSON_NUMERIC_CHECK) ?>;
  </script>
</head>
<body >
  <div class="d-flex-wrapper">
    <?php include __DIR__ . '/../../components/sidebar_bot.php'; ?>

    <!-- Aqui fazemos o “right pane” crescer e ser um flex-column -->
    <div class="w-100 flex-grow-1 d-flex flex-column">
      <div class="header d-flex justify-content-between align-items-center">
        <h3 class="mb-0"><i class="bi bi-robot me-2"></i>Chatbot IA</h3>
        <div class="user-info d-flex align-items-center gap-2">
          <span>Bem-vindo, <?= htmlspecialchars($usuario_nome, ENT_QUOTES, 'UTF-8') ?>!</span>
          <a href="/TarefaN3/Views/logout.php" class="btn btn-danger btn-sm">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
          </a>
          <button class="theme-toggle-btn btn btn-outline-secondary btn-sm" id="themeBtn" title="Alternar tema">
            <i class="fa fa-moon"></i>
          </button>
        </div>
      </div>

      <div class="chat-area">
        <div class="chat-header"><i class="bi bi-robot"></i> Suporte Linha Clipp</div>
        <div class="chat-messages" id="msgs"></div>
        <form class="chat-input-row" id="form" autocomplete="off">
          <input type="text" id="input" class="chat-input" placeholder="Digite sua dúvida..." autocomplete="off" required />
          <button type="submit" class="chat-send-btn" aria-label="Enviar">
            <i class="fa fa-paper-plane"></i>
          </button>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Theme toggle
    const themeBtn = document.getElementById('themeBtn');
    themeBtn.onclick = () => {
      const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      document.documentElement.setAttribute('data-theme', isDark ? '' : 'dark');
      themeBtn.innerHTML = isDark ? '<i class="fa fa-moon"></i>' : '<i class="fa fa-sun"></i>';
    };

    function appendMsg(text, who) {
      const msgs = document.getElementById('msgs');
      const row = document.createElement('div');
      row.className = 'chat-row ' + (who === 'user' ? 'user' : 'bot');
      const avatar = document.createElement('span');
      avatar.className = 'chat-avatar';
      avatar.innerHTML = who === 'user'
        ? '<i class="bi bi-person"></i>'
        : '<i class="bi bi-robot"></i>';
      const bubble = document.createElement('div');
      bubble.className = 'chat-bubble';
      bubble.innerHTML = who === 'bot' ? marked.parse(text) : text;
      row.appendChild(avatar);
      row.appendChild(bubble);
      msgs.appendChild(row);
      msgs.scrollTop = msgs.scrollHeight;
    }

    function showTyping() {
      const msgs = document.getElementById('msgs');
      const row = document.createElement('div');
      row.className = 'chat-typing-row typing-row';
      row.innerHTML = `
        <span class="chat-typing-dot"></span>
        <span class="chat-typing-dot"></span>
        <span class="chat-typing-dot"></span>
        <span style="margin-left:8px;">Digitando...</span>
      `;
      msgs.appendChild(row);
      msgs.scrollTop = msgs.scrollHeight;
    }

    function hideTyping() {
      document.querySelectorAll('.typing-row').forEach(e => e.remove());
    }

    async function enviar(pergunta) {
      appendMsg(pergunta, 'user');
      showTyping();

      const user_id = window.USER_ID;
      if (!user_id) {
        hideTyping();
        appendMsg('Erro: usuário não autenticado.', 'bot');
        return;
      }

      const payload = { pergunta, user_id };

      try {
        const resp = await fetch('http://localhost:8000/consultar', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        if (!resp.ok) throw new Error('Status ' + resp.status);
        const data = await resp.json();
        hideTyping();

        if (data.resposta) {
          appendMsg(data.resposta, 'bot');
        } else if (data.erro) {
          appendMsg('Erro: ' + data.erro, 'bot');
        } else {
          appendMsg('Resposta inesperada do servidor.', 'bot');
        }
      } catch (err) {
        hideTyping();
        appendMsg('Erro na comunicação: ' + err.message, 'bot');
      }
    }

    document.getElementById('form').onsubmit = e => {
      e.preventDefault();
      const txt = document.getElementById('input').value.trim();
      if (txt) enviar(txt);
      document.getElementById('input').value = '';
      document.getElementById('input').focus();
    };

    document.getElementById('input').addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('form').dispatchEvent(new Event('submit'));
      }
    });
  </script>
</body>
</html>
