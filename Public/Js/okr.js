// okr.js

// alterna campos modal Nova Meta (continua fora se usado em okr.php)
function toggleTipoMeta(){
  const sel = document.getElementById('tipoMetaSel').value;
  document.getElementById('divValor').classList.toggle('d-none', sel==='tempo');
  document.getElementById('divTempo').classList.toggle('d-none', sel==='valor');
}

// metas agrupadas por OKR
const metasByOkr = window.metasByOkr || {};

document.addEventListener('DOMContentLoaded', () => {
  // ——— Listener para #selOkr (somente em okr.php) ———
  const selOkr = document.getElementById('selOkr');
  if (selOkr) {
    selOkr.addEventListener('change', function(){
      const metas     = metasByOkr[this.value] || [];
      const container = document.getElementById('divMetasList');
      const tbody     = document.getElementById('tbodyMetas');
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
    });
  }

  // ——— Modal de Edição ———
  const modalEdit = document.getElementById('modalEditarOKR');
  if (modalEdit) {
    modalEdit.addEventListener('show.bs.modal', e => {
      const btn  = e.relatedTarget;
      const id   = btn.dataset.id;
      const desc = btn.dataset.descricao;
      const eq   = btn.dataset.equipe;
      const nivs = (btn.dataset.niveis || '').split(',');
      modalEdit.querySelector('#okr-id').value        = id;
      modalEdit.querySelector('#okr-descricao').value = desc;
      modalEdit.querySelector('#okr-equipe').value    = eq;
      modalEdit.querySelectorAll('input[name="niveis[]"]').forEach(chk => {
        chk.checked = nivs.includes(chk.value);
      });
    });
  }

  // ——— Modal de Exclusão ———
  const modalExc = document.getElementById('modalExcluirOKR');
  if (modalExc) {
    modalExc.addEventListener('show.bs.modal', e => {
      const btn  = e.relatedTarget;
      const id   = btn.dataset.id;
      const nome = btn.dataset.nome;
      modalExc.querySelector('#excluir_okr_id').value        = id;
      modalExc.querySelector('#excluir_okr_nome').textContent = nome;
      modalExc.querySelector('form').action                  = 'deletar_okr.php';
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
  // 1️⃣   Se havia um modal pra reabrir, faz isso agora:
  const modalToOpen = sessionStorage.getItem('openModal');
  if (modalToOpen) {
    const el = document.getElementById(modalToOpen);
    if (el) new bootstrap.Modal(el).show();
    sessionStorage.removeItem('openModal');
  }

  // 2️⃣   Antes de qualquer form SUBMIT dentro de um modal, memoriza o id:
  document.querySelectorAll('.modal form').forEach(form => {
    form.addEventListener('submit', () => {
      const modal = form.closest('.modal');
      if (modal && modal.id) {
        sessionStorage.setItem('openModal', modal.id);
      }
    });
  });
});


  // ——— Toasts de Mensagem ———
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

  const params  = new URLSearchParams(window.location.search);
  const success = params.get("success");
  const error   = params.get("error");

  if (success) {
    let msg = "";
    if (success === "1") msg = "OKR Cadastrado!";
    else if (success === "2") msg = "OKR Editado!";
    else if (success === "3") msg = "OKR Excluído!";
    else if (success === "4") msg = "Meta Cadastrada!";
    else if (success === "5") msg = "Meta Editada!";
    else if (success === "6") msg = "Meta Excluído!";
    else if (success === "7") msg = "Valor Alcançado Cadastrado!";
    if (msg) showToast(msg, "success");
  }
  if (error) {
    let msg = "";
    if (error === "1") msg = "OKR já existe!";
    else if (error === "2") msg = "Não é possível excluir OKR que possui vínculos!";
    else if (error === "3") msg = "Não é possível excluir Meta que possui vínculos!";
    if (msg) showToast(msg, "error");
  }
});

document.addEventListener('DOMContentLoaded', () => {
  const selNivel = document.getElementById('selNivelMeta');
  const selOkr   = document.getElementById('selOkrMeta');

  if (!selNivel || !selOkr) return;

  selNivel.addEventListener('change', function() {
    const nivel = this.value;
    // ativa/desativa o seletor de OKR
    selOkr.disabled = !nivel;

    // limpa seleção anterior
    selOkr.value = '';

    // para cada option de OKR, esconde se não tiver o nível
    Array.from(selOkr.options).forEach(opt => {
      if (!opt.value) {
        // opção placeholder sempre visível
        opt.hidden = false;
      } else {
        const ids = (opt.dataset.niveisIds || '').split(',');
        opt.hidden = nivel ? !ids.includes(nivel) : false;
      }
    });
  });
});

document.addEventListener('DOMContentLoaded', () => {
  const selNivel = document.getElementById('selNivelLanc');
  const selOkr   = document.getElementById('selOkr');

  if (selNivel && selOkr) {
    selNivel.addEventListener('change', function() {
      const nivel = this.value;
      selOkr.disabled = !nivel;
      selOkr.value    = '';
      Array.from(selOkr.options).forEach(opt => {
        if (!opt.value) {
          opt.hidden = false; // placeholder
        } else {
          const ids = (opt.dataset.niveisIds || '').split(',');
          opt.hidden = nivel ? !ids.includes(nivel) : false;
        }
      });
    });
  }
});
