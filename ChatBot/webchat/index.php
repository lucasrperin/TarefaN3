<?php
// index.php (renomeado de index.html para index.php)
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
  <link rel="stylesheet" href="../../Public/chatbot.css">
  <!-- Parser Markdown -->
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <link rel="icon" href="../../Public/Image/LogoTituto.png" type="image/png">

  <script>
    // Expondo o ID do usuário ao JS
    window.USER_ID = <?= json_encode($usuario_id, JSON_NUMERIC_CHECK) ?>;
  </script>
</head>
<body>
  <div class="d-flex-wrapper">
    <?php include __DIR__ . '/../../components/sidebar_bot.php'; ?>

    <div class="w-100 flex-grow-1 d-flex flex-column">
      <div class="header d-flex justify-content-between align-items-center">
        <h3 class="mb-0"><i class="bi bi-robot me-2"></i>Chatbot IA</h3>
        <div class="user-info d-flex align-items-center gap-2">
          <span>Bem-vindo, <?= htmlspecialchars($usuario_nome, ENT_QUOTES, 'UTF-8') ?>!</span>
          <a href="../../Views/logout.php" class="btn btn-danger btn-sm">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
          </a>
          <button class="theme-toggle-btn btn btn-outline-secondary btn-sm" id="themeBtn" title="Alternar tema">
            <i class="fa fa-moon"></i>
          </button>
        </div>
      </div>

      <div class="chat-area access-scroll">
        <div id="avaliacoes-medias" class="p-2 mb-2 text-center" style="background:#f4f8fb; border-radius:6px; font-size:15px; border:1px solid #dde4ec;">
          <span><i class="fa fa-star text-warning"></i> Média geral: <b id="media-geral">-</b> <span style="color:#888;" id="total-geral"></span></span>
          &nbsp;|&nbsp;
          <span><i class="fa fa-calendar-day text-primary"></i> Média 7 dias: <b id="media-7dias">-</b> <span style="color:#888;" id="total-7dias"></span></span>
        </div>
        <div class="chat-header"><i class="bi bi-robot"></i>Agente Linha Clipp</div>
        <div class="chat-messages" id="msgs">
          <div id="historyLoading" class="text-center my-3">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Carregando...</span>
            </div>
            <div>Carregando histórico...</div>
          </div>
        </div>
        <form class="chat-input-row" id="form" autocomplete="off">
          <input type="text" id="input" class="chat-input" placeholder="Digite sua dúvida, cole uma imagem ou grave um áudio..." autocomplete="off" required />
          <input type="file" id="fileUpload" accept="image/*,audio/*" style="display:none;" />
          <button type="button" class="chat-send-btn btn-upload" title="Enviar arquivo/imagem" onclick="document.getElementById('fileUpload').click();">
            <i class="fa fa-paperclip"></i>
          </button>
          <!-- Botão do microfone -->
          <button type="button" class="chat-send-btn btn-mic" id="micBtn" title="Gravar áudio">
            <i class="fa fa-microphone"></i>
          </button>
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
      // — após renderizar, force todos os <a> a abrirem em nova guia —
      bubble.querySelectorAll('a').forEach(a => {
        a.setAttribute('target', '_blank');
        a.setAttribute('rel', 'noopener noreferrer');
      });
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

    // Função para enviar imagem colada/anexada
    async function enviarImagem(file) {
      const reader = new FileReader();
      reader.onload = function(e) {
        appendMsg('<img src="' + e.target.result + '" style="max-width:220px;max-height:140px;border-radius:8px;box-shadow:0 2px 8px #ccc;" />', 'user');
        showTyping();
      };
      reader.readAsDataURL(file);

      reader.onloadend = async function() {
        const formData = new FormData();
        formData.append("imagem", file);
        formData.append("user_id", window.USER_ID);

        try {
          const resp = await fetch('http://192.168.0.201:3310/upload-imagem?user_id=' + window.USER_ID, {
            method: 'POST',
            body: formData
          });

          const data = await resp.json();
          hideTyping();

          if (data.resposta) {
            const mostrarAvaliacaoAgora = data.resposta.includes("<<FINALIZADO>>");
            const respostaLimpa = data.resposta.replace("<<FINALIZADO>>", "").trim();
            appendMsg(respostaLimpa, 'bot');
            if (mostrarAvaliacaoAgora) mostrarAvaliacao();
          } else {
            appendMsg('Erro: ' + data.erro, 'bot');
          }
        } catch (err) {
          hideTyping();
          appendMsg('Erro ao enviar imagem: ' + err.message, 'bot');
        }
      };
    }

    // Função para enviar áudio colado/anexado ou gravado
    async function enviarAudio(file) {
      const url = URL.createObjectURL(file);
      appendMsg('<audio controls src="' + url + '" style="max-width:260px;outline:none;"></audio><br><span style="color:#888;font-size:12px;">Transcrevendo áudio...</span>', 'user');
      showTyping();

      const formData = new FormData();
      formData.append("audio", file);
      formData.append("user_id", window.USER_ID);

      try {
  const resp = await fetch('http://192.168.0.201:3310/upload-audio?user_id=' + window.USER_ID, {
          method: 'POST',
          body: formData
        });

        const data = await resp.json();
        hideTyping();

        if (data.resposta) {
          appendMsg(data.resposta, 'bot');
        } else {
          appendMsg('Erro: ' + data.erro, 'bot');
        }
      } catch (err) {
        hideTyping();
        appendMsg('Erro ao enviar áudio: ' + err.message, 'bot');
      }
    }

    // --- Gravação de áudio via microfone ---
    let isRecording = false;
    let mediaRecorder;
    let recordedChunks = [];

    const micBtn = document.getElementById('micBtn');
    micBtn.onclick = async () => {
      if (!isRecording) {
        // Iniciar gravação
        try {
          const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
          mediaRecorder = new MediaRecorder(stream);
          recordedChunks = [];
          mediaRecorder.ondataavailable = (e) => {
            if (e.data.size > 0) recordedChunks.push(e.data);
          };
          mediaRecorder.onstop = () => {
            const audioBlob = new Blob(recordedChunks, { type: 'audio/webm' });
            enviarAudio(audioBlob);
          };
          mediaRecorder.start();
          isRecording = true;
          micBtn.classList.add('recording');
          micBtn.innerHTML = '<i class="fa fa-stop"></i>';
          appendMsg('<span style="color:#0c4a6e;"><i class="fa fa-microphone"></i> Gravando... Clique para parar.</span>', 'user');
        } catch (err) {
          appendMsg('Não foi possível acessar o microfone: ' + err.message, 'bot');
        }
      } else {
        // Parar gravação
        mediaRecorder.stop();
        isRecording = false;
        micBtn.classList.remove('recording');
        micBtn.innerHTML = '<i class="fa fa-microphone"></i>';
      }
    };

    // --- RESTANTE DO SCRIPT: avaliações, envio de texto, histórico, etc. ---
    let avaliacaoId = 0;

    function isFinalizado(texto) {
      return texto.includes("<<FINALIZADO>>");
    }

    function limparFlagFinalizado(texto) {
      return texto.replace("<<FINALIZADO>>", "").trim();
    }

    function mostrarAvaliacao() {
      avaliacaoId++;
      const msgs = document.getElementById('msgs');
      const row = document.createElement('div');
      row.className = 'chat-row bot';
      row.innerHTML = `
        <span class="chat-avatar"><i class="bi bi-robot"></i></span>
        <div class="chat-bubble">
          <b>Por favor, avalie o atendimento:</b><br>
          <div id="avaliacao-botoes-${avaliacaoId}">
            ${[1,2,3,4,5].map(n => `
              <button onclick="enviarAvaliacao(${n}, ${avaliacaoId})" class="btn btn-sm btn-light m-1">${n} ⭐</button>
            `).join('')}
          </div>
        </div>
      `;
      msgs.appendChild(row);
      msgs.scrollTop = msgs.scrollHeight;
    }

    async function enviarAvaliacao(nota, id) {
  await fetch('http://192.168.0.201:3310/avaliacao', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_id: window.USER_ID,
          nota: nota
        })
      });
      const row = document.createElement('div');
      row.className = 'chat-row bot';
      row.innerHTML = `
        <span class="chat-avatar"><i class="bi bi-robot"></i></span>
        <div class="chat-bubble">Obrigado pela avaliação! 😊</div>
      `;
      sessionStorage.setItem('avaliado_' + window.USER_ID, 'true');
      document.getElementById('msgs').appendChild(row);
      document.getElementById('msgs').scrollTop = document.getElementById('msgs').scrollHeight;
      const botoes = document.getElementById(`avaliacao-botoes-${id}`);
      if (botoes) botoes.innerHTML = '';
      atualizarMedias();
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
  const resp = await fetch('http://192.168.0.201:3310/consultar', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        if (!resp.ok) throw new Error('Status ' + resp.status);
        const data = await resp.json();
        hideTyping();

        if (data.resposta) {
          const mostrarAvaliacaoAgora = isFinalizado(data.resposta);
          const respostaLimpa = limparFlagFinalizado(data.resposta);
          appendMsg(respostaLimpa, 'bot');
          if (mostrarAvaliacaoAgora) {
            mostrarAvaliacao();
          }
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

    async function atualizarMedias() {
      try {
  let resp = await fetch('http://192.168.0.201:3310/media-avaliacoes');
        let dados = await resp.json();
        document.getElementById('media-geral').textContent = dados.media ?? '-';
        document.getElementById('total-geral').textContent = dados.total ? `(${dados.total} avaliações)` : '';

  let resp7 = await fetch('http://192.168.0.201:3310/media-avaliacoes?dias=7');
        let dados7 = await resp7.json();
        document.getElementById('media-7dias').textContent = dados7.media ?? '-';
        document.getElementById('total-7dias').textContent = dados7.total ? `(${dados7.total} avaliações)` : '';
      } catch (e) {
        document.getElementById('media-geral').textContent = '-';
        document.getElementById('media-7dias').textContent = '-';
      }
    }

    async function loadHistory() {
      const loading = document.getElementById('historyLoading');
      // 1) mostra o loading
      loading.style.display = 'block';

      try {
        const resp = await fetch('./history.php', { credentials: 'include' });
        if (!resp.ok) throw new Error(await resp.text());
        const history = await resp.json();

        // 2) remove o loading antes de injetar as mensagens
        loading.remove();

        // 3) renderiza o histórico existente
        const humanIndices = history
          .map((m, idx) => m.type === 'human' ? idx : -1)
          .filter(idx => idx >= 0);

        let startIdx = 0;
        if (humanIndices.length > 15) {
          startIdx = humanIndices[humanIndices.length - 15];
        }
        for (let i = startIdx; i < history.length; i++) {
          const msg = history[i];
          appendMsg(msg.content, msg.type === 'human' ? 'user' : 'bot');
        }
      } catch (err) {
        // 4) em caso de erro, também remove o loading
        loading.remove();
        console.error('Erro ao carregar histórico:', err);
        appendMsg('Não foi possível carregar o histórico.', 'bot');
      }
    }


    document.addEventListener('DOMContentLoaded', () => {
      loadHistory().then(() => {
        atualizarMedias();
        setInterval(atualizarMedias, 60 * 1000);
      }).catch(err => {
        atualizarMedias();
        setInterval(atualizarMedias, 60 * 1000);
      });

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

      document.getElementById('input').addEventListener('paste', e => {
        const items = e.clipboardData.items;
        for (const item of items) {
          if (item.type.indexOf("image") === 0) {
            const file = item.getAsFile();
            enviarImagem(file);
            break;
          }
          if (item.type.indexOf("audio") === 0) {
            const file = item.getAsFile();
            enviarAudio(file);
            break;
          }
        }
      });

      document.getElementById('fileUpload').addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        if (file.type.startsWith("image/")) {
          enviarImagem(file);
        } else if (file.type.startsWith("audio/")) {
          enviarAudio(file);
        } else {
          appendMsg('Tipo de arquivo não suportado.', 'bot');
        }
        this.value = '';
      });
    });
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    .btn-mic.recording { background: #c4302b; color: #fff !important; }
  </style>
</body>
</html>
