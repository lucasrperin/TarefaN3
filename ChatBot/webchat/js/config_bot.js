// ChatBot/webchat/js/config_bot.js
(() => {
  // ---------- Helpers ----------
  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => r.querySelectorAll(s);
  const body = document.body || document.documentElement;

  // Endpoints do <body>
  const execBase            = body?.dataset?.uploadVideoEmb || 'executar_etapas.php';
  const chamarProcessaVideo = body?.dataset?.chamarProcessaVideo || '/ChatBot/scripts/video/chamar_processa_video.php';
  const chamarProcessaSite  = body?.dataset?.chamarProcessaSite  || '/ChatBot/scripts/website/chamar_processa_site.php';
  const execSiteBase        = body?.dataset?.uploadSiteEmb || execBase;

  // Toast
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
  window.showToast = showToast;

  // Tema
  const themeBtn = $('#themeBtn');
  if (themeBtn) {
    themeBtn.onclick = () => {
      const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      document.documentElement.setAttribute('data-theme', isDark ? '' : 'dark');
      themeBtn.innerHTML = isDark ? '<i class="fa fa-moon"></i>' : '<i class="fa fa-sun"></i>';
    };
  }

  // ---------- ARTIGOS: backup->gerar->upload ----------
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
          const resp = await fetch(`${execBase}?etapa=${encodeURIComponent(etapa)}`, { cache: 'no-store' });
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

  // ---------- VÍDEOS: Publicar embeddings ----------
  const btnUploadVideos = $('#btnUploadVideos');
  if (btnUploadVideos) {
    const b = btnUploadVideos.cloneNode(true); btnUploadVideos.replaceWith(b);
    const statusEl = $('#uploadVideosStatus');
    const ultimaEl = $('#ultimaVideoText');
    const overlay  = $('#busyOverlay');
    const originalHtml = '<i class="fa fa-cloud-arrow-up me-1"></i> Publicar';

    function setBtn(html, variant, disabled) {
      b.className = 'btn btn-' + variant + ' btn-pill';
      b.innerHTML = html;
      b.disabled = !!disabled;
    }

    b.addEventListener('click', async () => {
      setBtn('<span class="spinner-border spinner-border-sm me-1"></span> Publicando...', 'primary', true);
      overlay?.classList.add('show'); document.body.classList.add('loading');
      try {
        // BACKUP das transcrições
        let resp = await fetch(`${execBase}?etapa=backup_videos`, { cache: 'no-store' });
        let txt  = await resp.text();
        if (!resp.ok || txt.startsWith('❌')) throw new Error(txt || 'Falha no backup das transcrições.');

        // UPLOAD
        resp = await fetch(`${execBase}?etapa=upload_video`, { cache: 'no-store' });
        txt  = await resp.text();
        if (!(resp.ok && (txt.startsWith('✅') || /upload/i.test(txt)))) {
          throw new Error(txt || 'Erro ao publicar embeddings de vídeos.');
        }

        setBtn('Concluído ✅', 'success', true);
        if (ultimaEl) {
          const agora = new Date();
          const f = new Intl.DateTimeFormat('pt-BR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit', second:'2-digit' }).format(agora);
          ultimaEl.textContent = f;
        }
        setTimeout(() => setBtn(originalHtml, 'primary', false), 5000);
      } catch (e) {
        statusEl && (statusEl.className = 'alert alert-danger py-2 px-3 mt-2', statusEl.textContent = e?.message || 'Erro no processo.');
        showToast('Falha no processo de backup/upload.', 'error');
        setBtn(originalHtml, 'primary', false);
      } finally {
        overlay?.classList.remove('show'); document.body.classList.remove('loading');
      }
    });
  }

  // ---------- WEBSITE: Publicar embeddings ----------
  const btnUploadSites = $('#btnUploadSites');
  if (btnUploadSites) {
    const b = btnUploadSites.cloneNode(true); btnUploadSites.replaceWith(b);
    const statusEl = $('#uploadSitesStatus');
    const ultimaEl = $('#ultimaWebsiteText');
    const overlay  = $('#busyOverlay');
    const originalHtml = '<i class="fa fa-cloud-arrow-up me-1"></i> Publicar';

    function setBtn(html, variant, disabled) {
      b.className = 'btn btn-' + variant + ' btn-pill';
      b.innerHTML = html;
      b.disabled = !!disabled;
    }

    b.addEventListener('click', async () => {
      setBtn('<span class="spinner-border spinner-border-sm me-1"></span> Publicando...', 'primary', true);
      overlay?.classList.add('show'); document.body.classList.add('loading');
      try {
        // BACKUP dos JSONs de sites
        let resp = await fetch(`${execSiteBase}?etapa=backup_sites`, { cache: 'no-store' });
        let txt  = await resp.text();
        if (!resp.ok || txt.startsWith('❌')) throw new Error(txt || 'Falha no backup dos sites.');

        // UPLOAD para Supabase
        resp = await fetch(`${execSiteBase}?etapa=upload_site`, { cache: 'no-store' });
        txt  = await resp.text();
        if (!(resp.ok && (txt.startsWith('✅') || /upload/i.test(txt)))) {
          throw new Error(txt || 'Erro ao publicar embeddings de websites.');
        }

        setBtn('Concluído ✅', 'success', true);
        if (ultimaEl) {
          const agora = new Date();
          const f = new Intl.DateTimeFormat('pt-BR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit', second:'2-digit' }).format(agora);
          ultimaEl.textContent = f;
        }
        setTimeout(() => setBtn(originalHtml, 'primary', false), 5000);
      } catch (e) {
        statusEl && (statusEl.className = 'alert alert-danger py-2 px-3 mt-2', statusEl.textContent = e?.message || 'Erro no processo.');
        showToast('Falha no processo de backup/upload (websites).', 'error');
        setBtn(originalHtml, 'primary', false);
      } finally {
        overlay?.classList.remove('show'); document.body.classList.remove('loading');
      }
    });
  }

  // ---------- VÍDEO: Form envio ----------
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

      document.body.classList.add('loading'); overlay?.classList.add('show');
      logTreino.innerHTML = ''; logTreino.style.display = 'none';
      progressBar.style.width = file ? '0%' : '100%';
      progressBar.className = 'progress-bar bg-primary' + (file ? '' : ' progress-bar-striped progress-bar-animated');
      progressContainer.style.display = 'block';

      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Enviando...';

      const xhr = new XMLHttpRequest();
      xhr.timeout = 30 * 60 * 1000; // 30 min

      xhr.upload.onprogress = function (e) {
        if (e.lengthComputable && file) {
          const percent = Math.round((e.loaded / e.total) * 100);
          progressBar.style.width = percent + '%';
        }
      };

      function endUIReset() {
        document.body.classList.remove('loading'); overlay?.classList.remove('show');
        progressContainer.style.display = 'none';
        button.disabled = false; button.innerHTML = originalText;
        liveHint?.classList.add('d-none');
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

  // ---------- WEBSITE: Form envio ----------
  const formSite = $('#formTreinamentoSite');
  if (formSite) {
    formSite.addEventListener('submit', function (e) {
      e.preventDefault();

      const url      = $('#siteUrl')?.value.trim();
      const maxPages = parseInt($('#siteMaxPages')?.value || '10', 10);
      const sameDom  = $('#siteSameDomain')?.checked ? '1' : '0';
      const useMap   = $('#siteUseSitemap')?.checked ? '1' : '0';
      const overlay  = $('#busyOverlay');
      const liveHint = $('#liveHintSite');
      const btn      = this.querySelector('button[type="submit"]');
      const original = btn.innerHTML;

      if (!url) { alert('Informe a URL inicial.'); return; }

      const fd = new FormData();
      fd.append('url', url);
      fd.append('max_pages', String(Math.max(1, Math.min(100, isNaN(maxPages) ? 10 : maxPages))));
      fd.append('same_domain', sameDom);
      fd.append('use_sitemap', useMap);

      document.body.classList.add('loading'); overlay?.classList.add('show');
      liveHint?.classList.remove('d-none');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Iniciando...';

      fetch(chamarProcessaSite, { method:'POST', body: fd })
        .then(async r => {
          const txt = await r.text();
          let j = null; try { j = JSON.parse(txt); } catch {}
          if (r.ok && j && j.ok) {
            showToast(j.message || 'Coleta iniciada!', 'success');
            if (j.id) openLogModal(j.id);
          } else {
            showToast((j&&j.message)||'Falha ao iniciar o treinamento por website.', 'error');
          }
          reloadHistorico();
        })
        .catch(() => { showToast('Erro de rede ao iniciar o treinamento de website.', 'error'); })
        .finally(() => {
          document.body.classList.remove('loading'); overlay?.classList.remove('show');
          liveHint?.classList.add('d-none');
          btn.disabled = false; btn.innerHTML = original;
        });
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
      if (tbody) $('#histBody').replaceWith(tbody); else location.reload();
    } catch { location.reload(); }
  }

  // ---------- Modal Log (com restauração de foco) ----------
  const logModalEl = $('#logModal');
  const logModal   = logModalEl ? new bootstrap.Modal(logModalEl) : null;
  let lastFocusBeforeLog = null; // botão "Ver log" clicado

  // Captura o botão clicado para restaurar depois
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-log');
    if (!btn) return;
    lastFocusBeforeLog = btn;
    const id = btn.getAttribute('data-id');
    if (id) openLogModal(id);
  });

  async function openLogModal(id) {
    try {
      const resp = await fetch(`${location.href}?id=${encodeURIComponent(id)}`, { headers: { 'X-Partial': 'log' }});
      const txt  = await resp.text();
      $('#logContent').textContent = txt || '(log vazio)';
      if (!logModal || !logModalEl) return;

      // Foco ao abrir
      logModalEl.addEventListener('shown.bs.modal', () => {
        logModalEl.querySelector('.btn-close')?.focus();
      }, { once: true });

      // Restaurar foco ao fechar
      logModalEl.addEventListener('hidden.bs.modal', () => {
        if (lastFocusBeforeLog && document.contains(lastFocusBeforeLog)) {
          lastFocusBeforeLog.focus();
        } else {
          // fallback para o botão "Atualizar" do histórico
          $('#btnReloadHist')?.focus();
        }
      }, { once: true });

      logModal.show();
    } catch {
      showToast('Não foi possível carregar o log.', 'error');
    }
  }
})();
