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
    const divValor = document.getElementById('divValor');
    const divTempo = document.getElementById('divTempo');
    const divMoeda = document.getElementById('divMoeda');
    const divQuantidade = document.getElementById('divQuantidade');

    function toggleTipoMeta() {
      const tipo = tipoMetaSel.value;
      divValor.classList.toggle('d-none', tipo !== 'valor');
      divTempo.classList.toggle('d-none', tipo !== 'tempo');
      divMoeda.classList.toggle('d-none', tipo !== 'moeda');
      divQuantidade.classList.toggle('d-none', tipo !== 'quantidade');
    }

    tipoMetaSel.addEventListener('change', toggleTipoMeta);

    // chama uma vez no load pra garantir o estado inicial
    toggleTipoMeta();
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
      const tr     = document.createElement('tr');
      const tdDesc = document.createElement('td');
      tdDesc.textContent = m.descricao;
      // input hidden com idMeta[]
      const hid = document.createElement('input');
      hid.type  = 'hidden';
      hid.name  = 'idMeta[]';
      hid.value = m.id;
      tdDesc.appendChild(hid);

      const tdIn  = document.createElement('td');
      const inp   = document.createElement('input');
      inp.name      = 'realizado[]';
      inp.className = 'form-control';
      inp.required  = true;

      // Se for tempo
      if (m.unidade === 's') {
        inp.type        = 'text';
        inp.placeholder = '00:01:55';
        inp.pattern     = '\\d{2}:\\d{2}:\\d{2}';

      // Se for número (%, R$ ou unidades)
      } else {
        inp.type = 'number';
        // passo 1 para uni, 0.01 para % e R$
        inp.step = (m.unidade === 'unidades') ? '1' : '0.01';  

        if (m.unidade === '%') {
          inp.placeholder = '99.99';
        } else if (m.unidade === 'R$') {
          inp.placeholder = '1234.56';
        } else if (m.unidade === 'unidades') {
          inp.placeholder = '10';
        }
      }

      tdIn.appendChild(inp);
      tr.appendChild(tdDesc);
      tr.appendChild(tdIn);
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

//Função para no modal de META somente mostrar os OKR do nivel e equipe selecionado
document.addEventListener('DOMContentLoaded', () => {
  const selNivel = document.getElementById('selNivelMeta');
  const selOkr   = document.getElementById('selOkrMeta');
  // guarda o HTML original do <select> de OKR (placeholder + todas opções)
  const originalOptions = selOkr.innerHTML;

  selNivel.addEventListener('change', () => {
    const nivelId  = selNivel.value;
    const equipeId = selNivel.selectedOptions[0]?.dataset.equipeId;
    // 1) recarrega tudo
    selOkr.innerHTML = originalOptions;
    // 2) remove as opções que não pertencem ao nível **e** equipe selecionados
    Array.from(selOkr.options).forEach(opt => {
      if (!opt.value) return; // pula placeholder
      const niveis = (opt.dataset.niveisIds||'').split(',');
      if (!niveis.includes(nivelId) || opt.dataset.equipeId !== equipeId) {
        opt.remove();
      }
    });
    // 3) habilita se houver pelo menos uma opção válida
    selOkr.disabled = selOkr.options.length <= 1;
  });
}); 

// Função para no modal de LANÇAR REALIZADO somente mostrar os OKR do nivel e equipe selecionado
document.addEventListener('DOMContentLoaded', () => {
  const selNivel = document.getElementById('selNivelLanc');
  const selOkr   = document.getElementById('selOkrLanc');
  // guarda o HTML completo de todas as options (incluindo a placeholder)
  const originalOptions = selOkr.innerHTML;

  selNivel.addEventListener('change', () => {
    const nivelId  = selNivel.value;
    const equipeId = selNivel.selectedOptions[0]?.dataset.equipeId;
    // 1) recarrega tudo
    selOkr.innerHTML = originalOptions;
    // 2) percorre e remove o que não tiver o nível ou não for da equipe
    Array.from(selOkr.options).forEach(opt => {
      if (!opt.value) return; // pula placeholder
      const niveis = opt.dataset.niveisIds.split(',');
      if (!niveis.includes(nivelId) || opt.dataset.equipeId !== equipeId) {
        opt.remove();
      }
    });
    // 3) habilita o select só se restar >1 opção
    selOkr.disabled = selOkr.options.length <= 1;
  });
});

// Função para preecher o campo PRAZO com o último dia do ano
document.addEventListener('DOMContentLoaded', () => {
  // calcula último dia do ano atual
  const ano   = new Date().getFullYear();
  const data  = `${ano}-12-31`;           // formato ISO que o <input type="date"> aceita
  const campo = document.getElementById('dtPrazoMeta');
  if (campo) campo.value = data;
});

// ——— Lista KRs já lançados no Modal Nova Meta ———
document.addEventListener('DOMContentLoaded', () => {
  const selOkrMeta     = document.getElementById('selOkrMeta');
  const divKRLancados  = document.getElementById('divKRLancados');
  const ulKRLancados   = document.getElementById('ulKRLancados');
  const anoAtual       = new Date().getFullYear();

  if (!selOkrMeta) return;

  selOkrMeta.addEventListener('change', () => {
    const okrId = selOkrMeta.value;
    // limpa lista e esconde se nada selecionado
    ulKRLancados.innerHTML = '';
    divKRLancados.classList.add('d-none');
    if (!okrId) return;

    fetch(`../Ajax/get_kr_lancado.php?okr_id=${okrId}&ano=${anoAtual}`)
      .then(res => res.json())
      .then(list => {
        if (list.length) {
          ulKRLancados.innerHTML = list
            .map(desc => `<li class="list-group-item py-1">${desc}</li>`)
            .join('');
          divKRLancados.classList.remove('d-none');
        }
      })
      .catch(err => console.error('Erro ao buscar KRs:', err));
  });
});

// Função para trazer somente os níveis da equipe selecionado no modal de META 
document.addEventListener('DOMContentLoaded', () => {
  const selEquipe = document.getElementById('selEquipeMeta');
  const selNivel  = document.getElementById('selNivelMeta');
  const selOkr    = document.getElementById('selOkrMeta');

  // guarda os HTML originais
  const originalNivelHTML = selNivel.innerHTML;
  const originalOkrHTML   = selOkr.innerHTML;

  // 1) Filtra Níveis quando muda Em equipe
   function filtraNiveis() {
    const eqId = selEquipe.value;
    selNivel.innerHTML = originalNivelHTML;
    selNivel.value     = '';
    Array.from(selNivel.options).forEach(opt => {
      if (!opt.value) return;
      opt.hidden = eqId !== '0' && opt.dataset.equipeId !== eqId;
    });
    // também resetamos OKR
    selOkr.innerHTML = originalOkrHTML;
    selOkr.disabled  = true;
  }

  selEquipe.addEventListener('change', filtraNiveis);

  // **DISPARA O FILTRO ASSIM QUE A PÁGINA É CARREGADA**
  filtraNiveis();

  // 2) Filtra OKRs quando muda Nível
  selNivel.addEventListener('change', () => {
    const nivelId = selNivel.value;
    const eqId    = selEquipe.value;
    // repopula OKR e limpa seleção
    selOkr.innerHTML = originalOkrHTML;
    selOkr.value     = '';
    // filtra
    Array.from(selOkr.options).forEach(opt => {
      if (!opt.value) return;
      const niveis = (opt.dataset.niveisIds || '').split(',');
      const optEq   = opt.dataset.equipeId;
      if (
        !niveis.includes(nivelId) ||
        (eqId !== '0' && optEq !== eqId)
      ) {
        opt.remove();
      }
    });
    selOkr.disabled = selOkr.options.length <= 1;
  });
});

// Função para trazer somente os níveis da equipe selecionado no modal de LANCAR REALIZADO
document.addEventListener('DOMContentLoaded', () => {
  const selEquipe = document.getElementById('selEquipeLanc');
  const selNivel  = document.getElementById('selNivelLanc');
  const selOkr    = document.getElementById('selOkrLanc');

  if (!selEquipe) return;

  // guarda originais
  const origNivel = selNivel.innerHTML;
  const origOkr   = selOkr.innerHTML;

  // 1) quando mudar Equipe, filtra Nível e reseta OKR
  selEquipe.addEventListener('change', () => {
    const eq = selEquipe.value;
    selNivel.innerHTML = origNivel;
    selNivel.value     = '';
    Array.from(selNivel.options).forEach(opt => {
      if (!opt.value) return;
      opt.hidden = eq !== '0' && opt.dataset.equipeId !== eq;
    });
    // reset OKR
    selOkr.innerHTML = origOkr;
    selOkr.disabled  = true;
  });

  // 2) quando mudar Nível, filtra OKR
  selNivel.addEventListener('change', () => {
    const nv = selNivel.value;
    const eq = selEquipe.value;
    selOkr.innerHTML = origOkr;
    selOkr.value     = '';
    Array.from(selOkr.options).forEach(opt => {
      if (!opt.value) return;
      const nivs = (opt.dataset.niveisIds||'').split(',');
      const eqOk = opt.dataset.equipeId;
      if (!nivs.includes(nv) || (eq !== '0' && eqOk !== eq)) {
        opt.remove();
      }
    });
    selOkr.disabled = selOkr.options.length <= 1;
  });

  // 3) dispara filtro inicial ao abrir o modal
  const modal = document.getElementById('modalLancamento');
  modal.addEventListener('show.bs.modal', () => {
    selEquipe.dispatchEvent(new Event('change'));
  });
});
