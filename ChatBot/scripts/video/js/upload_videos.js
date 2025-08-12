// ChatBot/scripts/video/js/upload_videos.js
(function () {
  const btn = document.getElementById('btnUploadVideos');
  if (!btn) return;

  // remove qualquer listener prévio (clona o botão)
  const newBtn = btn.cloneNode(true);
  btn.replaceWith(newBtn);

  const b = newBtn;
  const statusEl = document.getElementById('uploadVideosStatus');
  const ultimaEl = document.getElementById('ultimaVideoText');
  const overlay = document.getElementById('busyOverlay');

  const originalHtml = '<i class="fa fa-cloud-arrow-up me-1"></i> Publicar Embeddings';
  const execBase = (window.ENDPOINTS && window.ENDPOINTS.uploadVideoEmb)
    ? window.ENDPOINTS.uploadVideoEmb
    : 'executar_etapas.php';

  function setBtn(html, variant, disabled) {
    b.className = 'btn btn-' + variant + ' btn-pill';
    b.innerHTML = html;
    b.disabled = !!disabled;
  }
  function toast(msg, type) {
    try { window.showToast(msg, type); } catch (_) {}
  }

  b.addEventListener('click', async () => {
    // Estado: publicando…
    setBtn('<span class="spinner-border spinner-border-sm me-1"></span> Publicando...', 'primary', true);
    if (overlay) { overlay.classList.add('show'); document.body.classList.add('loading'); }

    try {
      const resp = await fetch(execBase + '?etapa=upload_video');
      const txt  = await resp.text();

      if (overlay) { overlay.classList.remove('show'); document.body.classList.remove('loading'); }

      if (resp.ok && (txt.startsWith('✅') || /upload/i.test(txt))) {
        // sucesso
        // Botão fica "Concluído ✅" por 5s
        setBtn('Concluído ✅', 'success', true);

        // Atualiza o "Última geração de embeddings"
        if (ultimaEl) {
          try {
            const agora = new Date();
            const formatado = new Intl.DateTimeFormat('pt-BR', {
              day: '2-digit', month: '2-digit', year: 'numeric',
              hour: '2-digit', minute: '2-digit', second: '2-digit'
            }).format(agora);
            ultimaEl.textContent = formatado;
          } catch {}
        }

        // volta ao normal após 5s
        setTimeout(() => {
          setBtn(originalHtml, 'primary', false);
          // opcional: esconder a faixa verde depois
          // if (statusEl) statusEl.classList.add('d-none');
        }, 5000);

      } else {
        // erro
        if (statusEl) {
          statusEl.className = 'alert alert-danger py-2 px-3 mt-2';
          statusEl.textContent = txt || 'Erro ao publicar embeddings de vídeos.';
        }
        toast('Falha no upload de vídeos', 'error');
        setBtn(originalHtml, 'primary', false);
      }
    } catch (e) {
      if (overlay) { overlay.classList.remove('show'); document.body.classList.remove('loading'); }
      if (statusEl) {
        statusEl.className = 'alert alert-danger py-2 px-3 mt-2';
        statusEl.textContent = 'Erro de rede ao chamar o endpoint.';
      }
      toast('Erro de rede', 'error');
      setBtn(originalHtml, 'primary', false);
    }
  });
})();
