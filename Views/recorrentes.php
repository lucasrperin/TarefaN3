<?php
// Fragmento completo para a aba "Recorrentes" em incidente.php
// Requer: $conn, $resultRec, $activeTab

// Formata datetime: dd/mm/yyyy HH:ii
function formatDateTime($dt) {
    return date('d/m/Y H:i', strtotime($dt));
}
?>
<div class="tab-pane fade <?= $activeTab==='recorrentes'?'show active':'' ?>" id="pane-recorrentes" role="tabpanel">
  <div class="container py-4" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="h4 mb-0">Casos Recorrentes</h2>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroRecorrente">
        <i class="fa-solid fa-plus me-2"></i> Novo
      </button>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
      <?php if ($resultRec->num_rows): ?>
        <?php while ($r = $resultRec->fetch_assoc()): ?>
          <?php
            $cards = [];
            $cardsRes = $conn->query("SELECT card_num FROM TB_RECORRENTES_CARDS WHERE recorrente_id={$r['id']}");
            while ($c = $cardsRes->fetch_assoc()) {
                $cards[] = $c['card_num'];
            }
            $createdRaw = $r['created_at'];
            $completedRaw = $r['completed_at'];
            $fmt_created = formatDateTime($createdRaw);
            $fmt_completed = $completedRaw ? formatDateTime($completedRaw) : '';
            $intervalText = '';
            if ($completedRaw) {
                $diff = (new DateTime($createdRaw))->diff(new DateTime($completedRaw));
                $parts = [];
                if ($diff->d) $parts[] = "{$diff->d}d";
                if ($diff->h) $parts[] = "{$diff->h}h";
                if ($diff->i) $parts[] = "{$diff->i}m";
                $intervalText = implode(' ', $parts);
            }
            $cardsCsv = implode(',', $cards);
          ?>
        <div class="col">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <h5 class="card-title text-truncate mb-0"><?= htmlspecialchars($r['situacao']) ?></h5>
                <span class="badge <?= $r['resolvido']?'bg-success':'bg-warning text-dark' ?>">
                  <?= $r['resolvido']?'Resolvido':'Pendente' ?>
                </span>
              </div>
              <div class="mb-3">
                <?php foreach ($cards as $n): ?>
                  <span class="badge bg-light text-dark me-1 mb-1">
                    <a href="https://zmap.zpos.com.br/#/detailsIncidente/<?= $n ?>" target="_blank" class="text-decoration-none text-dark">
                      <?= $n ?>
                    </a>
                  </span>
                <?php endforeach; ?>
              </div>
              <?php if ($completedRaw): ?>
              <div class="mb-3 small text-muted">
                <i class="fa-regular fa-calendar-check me-1"></i> <?= $fmt_completed ?><br>
                <i class="fa-regular fa-hourglass-half me-1"></i> <?= $intervalText ?><br>
                <i class="fa-solid fa-info-circle me-1"></i>
                <a href="#" data-bs-toggle="modal" data-bs-target="#modalInfoRecorrente"
                   data-situacao="<?= htmlspecialchars($r['situacao'], ENT_QUOTES) ?>"
                   data-created="<?= $fmt_created ?>"
                   data-completed="<?= $fmt_completed ?>"
                   data-interval="<?= $intervalText ?>"
                   data-resposta="<?= htmlspecialchars($r['resposta'], ENT_QUOTES) ?>"
                   class="text-decoration-none">Ver Detalhes</a>
              </div>
              <?php endif; ?>
              <div class="mt-auto d-flex justify-content-between align-items-center pt-2 border-top">
                <small class="text-muted"><i class="fa-regular fa-clock me-1"></i> <?= $fmt_created ?></small>
                <div>
                  <?php if(!$r['resolvido']): ?>
                    <button type="button" class="btn btn-sm btn-outline-success me-1"
                            data-bs-toggle="modal" data-bs-target="#modalConcluirRecorrente"
                            data-id="<?= $r['id'] ?>">
                      <i class="fa-solid fa-check"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary me-1"
                            data-bs-toggle="modal" data-bs-target="#modalEditarRecorrente"
                            data-id="<?= $r['id'] ?>"
                            data-situacao="<?= htmlspecialchars($r['situacao'], ENT_QUOTES) ?>"
                            data-cards="<?= htmlspecialchars($cardsCsv, ENT_QUOTES) ?>">
                      <i class="fa-solid fa-pen"></i>
                    </button>
                  <?php else: ?>
                    <a href="resolver_recorrente.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary">
                      <i class="fa-solid fa-rotate-left"></i>
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="col-12 text-center text-muted py-5">Nenhum caso recorrente cadastrado.</div>
      <?php endif; ?>
    </div>

    <!-- Modal Cadastro Recorrente -->
    <div class="modal fade" id="modalCadastroRecorrente" tabindex="-1" aria-labelledby="modalCadastroRecorrenteLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form action="cadastrar_recorrente.php" method="post">
            <div class="modal-header">
              <h5 class="modal-title" id="modalCadastroRecorrenteLabel">Cadastrar Recorrente</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <label for="situacao_rec" class="form-label">Situação</label>
              <select id="situacao_rec" name="situacao" class="form-select mb-3" required>
                <option value="">Selecione</option>
                <option value="Movimentações">Movimentações</option>
                <option value="Comissões">Comissões</option>
                <option value="Notas que não geram receitas">Notas sem receita</option>
              </select>
              <label for="card_nums_rec" class="form-label">Cards (um por linha)</label>
              <textarea id="card_nums_rec" name="card_nums" class="form-control" rows="5" required></textarea>
            </div>
            <div class="modal-footer">
              <button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
              <button class="btn btn-primary">Gravar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal Concluir Recorrente -->
    <div class="modal fade" id="modalConcluirRecorrente" tabindex="-1" aria-labelledby="modalConcluirRecorrenteLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form action="resolver_recorrente.php" method="post">
            <input type="hidden" name="id" id="concluir_recorrente_id">
            <div class="modal-header">
              <h5 class="modal-title" id="modalConcluirRecorrenteLabel">Concluir Recorrente</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <label for="resposta_rec" class="form-label">Resposta do Desenvolvimento</label>
              <textarea id="resposta_rec" name="resposta" class="form-control" rows="4" required></textarea>
            </div>
            <div class="modal-footer">
              <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button class="btn btn-success">Concluir</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal Editar Recorrente -->
    <div class="modal fade" id="modalEditarRecorrente" tabindex="-1" aria-labelledby="modalEditarRecorrenteLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form action="editar_recorrente.php" method="post">
            <input type="hidden" name="id" id="edit_recorrente_id">
            <div class="modal-header">
              <h5 class="modal-title" id="modalEditarRecorrenteLabel">Editar Recorrente</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <label for="edit_situacao_rec" class="form-label">Situação</label>
              <select id="edit_situacao_rec" name="situacao" class="form-select mb-3" required>
                <option value="">Selecione</option>
                <option value="Movimentações">Movimentações</option>
                <option value="Comissões">Comissões</option>
                <option value="Notas que não geram receitas">Notas sem receita</option>
              </select>
              <label for="edit_card_nums_rec" class="form-label">Cards (um por linha)</label>
              <textarea id="edit_card_nums_rec" name="card_nums" class="form-control" rows="5" required></textarea>
            </div>
            <div class="modal-footer">
              <button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
              <button class="btn btn-primary">Atualizar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal Info Recorrente -->
    <div class="modal fade" id="modalInfoRecorrente" tabindex="-1" aria-labelledby="modalInfoRecorrenteLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalInfoRecorrenteLabel">Detalhes do Recorrente</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p><strong>Situação:</strong> <span id="info_situacao"></span></p>
            <p><i class="fa-regular fa-clock me-1"></i> <strong>Criado em:</strong> <span id="info_created"></span></p>
            <p><i class="fa-regular fa-calendar-check me-1"></i> <strong>Concluído em:</strong> <span id="info_completed"></span></p>
            <p><i class="fa-solid fa-hourglass-half me-1"></i> <strong>Tempo:</strong> <span id="info_interval"></span></p>
            <p><i class="fa-solid fa-comments me-1"></i> <strong>Resposta:</strong><br><span id="info_resposta"></span></p>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
// Popula modais dinamicamente
var concluirModal = document.getElementById('modalConcluirRecorrente');
concluirModal.addEventListener('show.bs.modal', function(event) {
  document.getElementById('concluir_recorrente_id').value = event.relatedTarget.getAttribute('data-id');
});

var editarModal = document.getElementById('modalEditarRecorrente');
editarModal.addEventListener('show.bs.modal', function(event) {
  var btn = event.relatedTarget;
  document.getElementById('edit_recorrente_id').value = btn.getAttribute('data-id');
  document.getElementById('edit_situacao_rec').value = btn.getAttribute('data-situacao');
  document.getElementById('edit_card_nums_rec').value = btn.getAttribute('data-cards').split(',').join('\n');
});

var infoModal = document.getElementById('modalInfoRecorrente');
infoModal.addEventListener('show.bs.modal', function(event) {
  var btn = event.relatedTarget;
  document.getElementById('info_situacao').innerText = btn.getAttribute('data-situacao');
  document.getElementById('info_created').innerText = btn.getAttribute('data-created');
  document.getElementById('info_completed').innerText = btn.getAttribute('data-completed');
  document.getElementById('info_interval').innerText = btn.getAttribute('data-interval');
  document.getElementById('info_resposta').innerText = btn.getAttribute('data-resposta');
});
</script>