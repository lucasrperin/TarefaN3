// alterna campos modal Nova Meta
function toggleTipoMeta(){
  const sel = document.getElementById('tipoMetaSel').value;
  document.getElementById('divValor').classList.toggle('d-none', sel==='tempo');
  document.getElementById('divTempo').classList.toggle('d-none', sel==='valor');
}
  // JSON gerado no PHP: metas agrupadas por OKR
  // retirado: const metasByOkr = json_encode($metaList, JSON_UNESCAPED_UNICODE);
// agora:
const metasByOkr = window.metasByOkr;

document
  .getElementById('selOkr')
  .addEventListener('change', function(){
    const metas     = metasByOkr[this.value] || [];
    const container = document.getElementById('divMetasList');
    const tbody     = document.getElementById('tbodyMetas');

    tbody.innerHTML = '';
    metas.forEach(m => {
      const tr     = document.createElement('tr');
      const tdDesc = document.createElement('td');
      tdDesc.textContent = m.descricao;

      // campo oculto para enviar idMeta[]
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

