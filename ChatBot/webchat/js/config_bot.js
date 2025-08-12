// ChatBot/webchat/js/config_bot.js
(() => {
  // ---------- Helpers ----------
  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => r.querySelectorAll(s);

  const body = document.body || document.documentElement;

  // Endpoints vindos via data-atributos do <body>
  const execBase = body?.dataset?.uploadVideoEmb || 'executar_etapas.php';
  const chamarProcessaVideo =
    body?.dataset?.chamarProcessaVideo || '/ChatBot/scripts/video/chamar_processa_video.php';

  // Toast helper (exige Bootstrap já carregado)
  const toastEl = $('#appToast');
  const toast   = toastEl ? new bootstrap.Toast(toastEl, { delay: 3000 }) : null;
  function showToast(message, variant = 'success') {
    if (!toastEl || !toast) return;
    toastEl.className =
      'toast align-items-center border-0 text-bg-' +
      (variant === 'error' ? 'danger' : variant === 'warn' ? 'warning' : 'success');
    $('#toastMsg').textContent = message;
    toast.show();
  }
  // disponibliza para outras funções
  window.showToast = showToast;

  // Toggle de tema
  const themeBtn = $('#themeBtn');
  if (themeBtn) {
    themeBtn.onclick = () => {
      const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      document.documentElement.setAttribute('data-theme', isDark ? '' : 'dark');
      themeBtn.innerHTML = isDark ? '<i class="fa fa-moon"></i>' : '<i class="fa fa-sun"></i>';
    };
  }

  // ---------- Embeddings Artigos (backup -> gerar -> upload) ----------
  const logDiv = $('#log');
  const btnExec = $('#btnExecutar');
  if (btnExec && logDiv) {
    btnExec.addEventListener('click', async () => {
      const etapas = ['backup', 'gerar', 'upload'];
      logDiv.innerHTML = '';
      logDiv.style.display = 'block';
      btnExec.disabled = true;

      for (const etapa of etapas) {
        const id = 'etapa-' + etapa;
        logDiv.insertAdjacentHTML(
          'beforeend',
          `<div id="${id}">
             ⏳ Executando etapa: ${etapa}...
             <span class="spinner-border spinner-border-sm text-primary ms-1"></span>
           </div>`
        );

        let txt = '';
        let ok  = false;
        try {
          const resp = await fetch(`${execBase}?etapa=${encodeURIComponent(etapa)}`);
          txt = await resp.text();
          ok = resp.ok && !txt.startsWith('❌');
        } catch (e) {
          txt = '❌ Erro de rede.';
        }

        const container = document.getElementById(id);
        if (!ok) {
          container.innerHTML = `<span style="color:red;">${txt}</span> 🛑`;
          btnExec.disabled = false;
          return;
        }
        container.innerHTML = `<span style="color:green;">${txt}</span>`;
      }

      logDiv.insertAdjacentHTML(
        'beforeend',
        "<b style='color:green;'>✅ Processo finalizado com sucesso.</b>"
      );
      btnExec.disabled = false;
    });
  }

  // ---------- Publicar Embeddings (VÍDEOS) ----------
const btnUploadVideos = document.querySelector('#btnUploadVideos');
if (btnUploadVideos) {
  // evita listeners duplicados
  const b = btnUploadVideos.cloneNode(true);
  btnUploadVideos.replaceWith(b);

  const statusEl = document.querySelector('#uploadVideosStatus');
  const ultimaEl = document.querySelector('#ultimaVideoText');
  const overlay  = document.querySelector('#busyOverlay');

  const originalHtml = '<i class="fa fa-cloud-arrow-up me-1"></i> Publicar Embeddings';
  const execBase = document.body?.dataset?.uploadVideoEmb || 'executar_etapas.php';

  function setBtn(html, variant, disabled) {
    b.className = 'btn btn-' + variant + ' btn-pill';
    b.innerHTML = html;
    b.disabled = !!disabled;
  }

  b.addEventListener('click', async () => {
    setBtn('<span class="spinner-border spinner-border-sm me-1"></span> Publicando...', 'primary', true);

    // LIGA o overlay durante TODO o processo
    overlay?.classList.add('show');
    document.body.classList.add('loading');

    try {
      // 1) BACKUP
      let resp = await fetch(`${execBase}?etapa=backup_videos`, { cache: 'no-store' });
      let txt  = await resp.text();
      if (!resp.ok || txt.startsWith('❌')) {
        throw new Error(txt || 'Falha ao gerar backup das transcrições.');
      }

      // 2) UPLOAD
      resp = await fetch(`${execBase}?etapa=upload_video`, { cache: 'no-store' });
      txt  = await resp.text();

      if (!(resp.ok && (txt.startsWith('✅') || /upload/i.test(txt)))) {
        throw new Error(txt || 'Erro ao publicar embeddings de vídeos.');
      }

      // Sucesso
      setBtn('Concluído ✅', 'success', true);

      // Atualiza a "Última geração"
      if (ultimaEl) {
        const agora = new Date();
        const formatado = new Intl.DateTimeFormat('pt-BR', {
          day: '2-digit', month: '2-digit', year: 'numeric',
          hour: '2-digit', minute: '2-digit', second: '2-digit'
        }).format(agora);
        ultimaEl.textContent = formatado;
      }

      // Volta para o estado original após 5s
      setTimeout(() => {
        setBtn(originalHtml, 'primary', false);
        // Se quiser ocultar o alerta verde depois, descomente:
        // statusEl?.classList.add('d-none');
      }, 5000);

    } catch (e) {
      // Erro
      if (statusEl) {
        statusEl.className = 'alert alert-danger py-2 px-3 mt-2';
        statusEl.textContent = e?.message || 'Falha no processo de backup/upload.';
      }
      if (typeof window.showToast === 'function') window.showToast('Falha no processo de backup/upload.', 'error');
      setBtn(originalHtml, 'primary', false);
    } finally {
      // DESLIGA o overlay SEMPRE
      overlay?.classList.remove('show');
      document.body.classList.remove('loading');
    }
  });
}

  // ---------- Dropzone (upload do vídeo para transcrição) ----------
  const dz = $('#dropzone');
  const fileInput = $('#videoFile');
  if (dz && fileInput) {
    dz.addEventListener('click', () => fileInput.click());
    ['dragenter','dragover'].forEach(evt =>
      dz.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); dz.classList.add('is-dragover'); })
    );
    ['dragleave','drop'].forEach(evt =>
      dz.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); dz.classList.remove('is-dragover'); })
    );
    dz.addEventListener('drop', e => {
      const f = e.dataTransfer.files && e.dataTransfer.files[0];
      if (f) {
        fileInput.files = e.dataTransfer.files;
        dz.querySelector('span').innerHTML = `<strong>Arquivo selecionado:</strong> ${f.name}`;
      }
    });
    fileInput.addEventListener('change', e => {
      const f = e.target.files && e.target.files[0];
      if (f) dz.querySelector('span').innerHTML = `<strong>Arquivo selecionado:</strong> ${f.name}`;
    });
  }

  // ---------- Formulário "Transcrever e Treinar" ----------
  const formTreino = $('#formTreinamento');
  if (formTreino) {
    formTreino.addEventListener('submit', function (e) {
      e.preventDefault();

      const logTreino = $('#logTreinamento');
      const progressContainer = $('#progressBarContainer');
      const progressBar = $('#uploadProgressBar');
      const button = this.querySelector('button[type="submit"]');
      const originalText = button.innerHTML;
      const overlay = $('#busyOverlay');
      const liveHint = $('#liveHint');

      const file  = $('#videoFile')?.files[0];
      const link  = $('#videoLink')?.value.trim();
      const title = $('#videoTitle')?.value.trim();

      if (!title) { alert('Informe um título para o treinamento.'); return; }
      if (!file && !link) { alert('Envie um arquivo ou informe um link.'); return; }
      if (file && link) { alert('Informe apenas arquivo OU link.'); return; }

      const formData = new FormData();
      formData.append('titulo', title);
      if (file) formData.append('video', file);
      if (link) formData.append('link', link);

      document.body.classList.add('loading');
      if (overlay) overlay.classList.add('show');

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

      function endUIReset() {
        document.body.classList.remove('loading');
        if (overlay) overlay.classList.remove('show');
        progressContainer.style.display = 'none';
        button.disabled = false;
        button.innerHTML = originalText;
        if (liveHint) liveHint.classList.add('d-none');
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
          if (respJson && respJson.id) openLogModal(respJson.id);
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

      xhr.open('POST', chamarProcessaVideo, true);
      xhr.send(formData);
    });
  }

  // ---------- Histórico ----------
  const btnReloadHist = $('#btnReloadHist');
  if (btnReloadHist) btnReloadHist.addEventListener('click', reloadHistorico);

  async function reloadHistorico() {
    try {
      const resp = await fetch(location.href, { headers: { 'X-Partial': 'historico' }});
      const html = await resp.text();
      const tmp  = document.createElement('div');
      tmp.innerHTML = html;
      const tbody = tmp.querySelector('#histBody');
      if (tbody) {
        $('#histBody').replaceWith(tbody);
      } else {
        location.reload();
      }
    } catch {
      location.reload();
    }
  }

  // ---------- Log Modal ----------
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-log');
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    if (id) openLogModal(id);
  });

  async function openLogModal(id) {
    try {
      const resp = await fetch(`${location.href}?id=${encodeURIComponent(id)}`, { headers: { 'X-Partial': 'log' }});
      const txt  = await resp.text();
      $('#logContent').textContent = txt || '(log vazio)';
      new bootstrap.Modal($('#logModal')).show();
    } catch {
      showToast('Não foi possível carregar o log.', 'error');
    }
  }
})();
