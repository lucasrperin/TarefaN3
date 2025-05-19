// okr.js

document.addEventListener('DOMContentLoaded', () => {
  // ——— Função helper para mostrar toasts ———
  function showToast(message, type) {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => toast.classList.add("show"), 10);
    setTimeout(() => {
      toast.classList.remove("show");
      setTimeout(() => container.removeChild(toast), 300);
    }, 2000);
  }

  // ——— Reabre modal após submit ———
  const modalToOpen = sessionStorage.getItem('openModal');
  if (modalToOpen) {
    const el = document.getElementById(modalToOpen);
    if (el) new bootstrap.Modal(el).show();
    sessionStorage.removeItem('openModal');
  }
  document.querySelectorAll('.modal form').forEach(form => {
    form.addEventListener('submit', () => {
      const modal = form.closest('.modal');
      if (modal && modal.id) sessionStorage.setItem('openModal', modal.id);
    });
  });

  // ——— Toasts de resultado via query string ———
  const params  = new URLSearchParams(window.location.search);
  const success = params.get("success");
  const error   = params.get("error");
  if (success) {
    const msgs = {
      "1":"OKR Cadastrado!",
      "2":"OKR Editado!",
      "3":"OKR Excluído!",
      "4":"Meta Cadastrada!",
      "5":"Meta Editada!",
      "6":"Meta Excluído!",
      "7":"Valor Alcançado Cadastrado!"
    };
    if (msgs[success]) showToast(msgs[success], "success");
  }
  if (error) {
    const errs = {
      "1":"OKR já existe!",
      "2":"Não é possível excluir OKR que possui vínculos!",
      "3":"Não é possível excluir Meta que possui vínculos!"
    };
    if (errs[error]) showToast(errs[error], "error");
  }

  // ——— Modal Nova Meta: toggle campos valor/tempo ———
  const tipoMetaSel = document.getElementById('tipoMetaSel');
  if (tipoMetaSel) {
    tipoMetaSel.addEventListener('change', () => {
      document.getElementById('divValor').classList.toggle('d-none', tipoMetaSel.value === 'tempo');
      document.getElementById('divTempo').classList.toggle('d-none', tipoMetaSel.value === 'valor');
    });
  }

  // ——— Meta Dinâmica (modalNovaMeta) ———
  const selNivelMeta = document.getElementById('selNivelMeta');
  const selOkrMeta   = document.getElementById('selOkrMeta');
  if (selNivelMeta && selOkrMeta) {
    selNivelMeta.addEventListener('change', () => {
      const nivel = selNivelMeta.value;
      selOkrMeta.disabled = !nivel;
      selOkrMeta.value    = '';
      Array.from(selOkrMeta.options).forEach(opt => {
        if (!opt.value) return;
        const ids = (opt.dataset.niveisIds || '').split(',');
        opt.hidden = nivel ? !ids.includes(nivel) : false;
      });
    });
  }

  // ——— Modal de Edição & Exclusão ———
  const modalEdit = document.getElementById('modalEditarOKR');
  if (modalEdit) {
    modalEdit.addEventListener('show.bs.modal', e => {
      const btn  = e.relatedTarget;
      modalEdit.querySelector('#okr-id').value        = btn.dataset.id;
      modalEdit.querySelector('#okr-descricao').value = btn.dataset.descricao;
      modalEdit.querySelector('#okr-equipe').value    = btn.dataset.equipe;
      const nivs = (btn.dataset.niveis || '').split(',');
      modalEdit.querySelectorAll('input[name="niveis[]"]').forEach(chk => {
        chk.checked = nivs.includes(chk.value);
      });
    });
  }
  const modalExc = document.getElementById('modalExcluirOKR');
  if (modalExc) {
    modalExc.addEventListener('show.bs.modal', e => {
      const btn  = e.relatedTarget;
      modalExc.querySelector('#excluir_okr_id').value        = btn.dataset.id;
      modalExc.querySelector('#excluir_okr_nome').textContent = btn.dataset.nome;
      modalExc.querySelector('form').action                  = 'deletar_okr.php';
    });
  }
});

 document.addEventListener('DOMContentLoaded', () => {
  const selNivel = document.getElementById('selNivelLanc');
  const selOkr   = document.getElementById('selOkrLanc');
  const mesSelect = document.querySelector('#modalLancamento select[name="mes"]');
  const container = document.getElementById('divMetasList');
  const tbody     = document.getElementById('tbodyMetas');

  if (!selNivel || !selOkr) return;

  // 1) Filtra opções de OKR quando muda o Nível
  selNivel.addEventListener('change', () => {
    const nivel = selNivel.value;
    selOkr.disabled = !nivel;
    selOkr.value    = '';
    Array.from(selOkr.options).forEach(opt => {
      if (!opt.value) {
        opt.hidden = false;
      } else {
        const ids = (opt.dataset.niveisIds || '').split(',');
        opt.hidden = nivel ? !ids.includes(nivel) : false;
      }
    });
    // limpa a tabela de KRs
    tbody.innerHTML = '';
    container.classList.add('d-none');
  });

  // 2) Quando escolher o OKR, popula os KRs abaixo
  selOkr.addEventListener('change', () => {
    const okrId = selOkr.value;
    const metas = window.metasByOkr[okrId] || [];
    tbody.innerHTML = '';
    metas.forEach(m => {
      const tr = document.createElement('tr');
      const tdDesc = document.createElement('td');
      tdDesc.textContent = m.descricao;
      const hidden = document.createElement('input');
      hidden.type  = 'hidden';
      hidden.name  = 'idMeta[]';
      hidden.value = m.id;
      tdDesc.appendChild(hidden);
      const tdInput = document.createElement('td');
      const inputVal = document.createElement('input');
      inputVal.type        = 'text';
      inputVal.name        = 'realizado[]';
      inputVal.className   = 'form-control';
      inputVal.required    = true;
      inputVal.placeholder = m.menor_melhor ? '00:01:55' : '99.99';
      tdInput.appendChild(inputVal);
      tr.appendChild(tdDesc);
      tr.appendChild(tdInput);
      tbody.appendChild(tr);
    });
    container.classList.toggle('d-none', metas.length === 0);

    // opcional: esconde meses que já foram lançados
    const launched = window.atingsByOkr[okrId] || [];
    Array.from(mesSelect.options).forEach(opt => {
      if (!opt.value) return;
      opt.hidden = launched.includes(+opt.value);
    });
    mesSelect.value = '';
  });
});