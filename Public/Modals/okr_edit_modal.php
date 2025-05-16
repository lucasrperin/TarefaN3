<!-- MODAL DE EDIÇÃO -->
<div class="modal fade" id="modalEditarOKR" tabindex="-1" aria-labelledby="modalEditarOKRLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form action="editar_okr.php" method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalEditarOKRLabel">Editar OKR</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="okr-id">
        <div class="mb-3">
          <label for="okr-descricao" class="form-label">Descrição</label>
          <input type="text" class="form-control" name="descricao" id="okr-descricao" required>
        </div>
        <div class="mb-3">
          <label for="okr-equipe" class="form-label">Equipe</label>
          <select name="idEquipe" id="okr-equipe" class="form-select" required>
            <?php
              $eqs = $conn->query("SELECT id, descricao FROM TB_EQUIPE ORDER BY descricao");
              while($e = $eqs->fetch_assoc()):
            ?>
              <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['descricao']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Níveis</label>
          <?php
            $niv = $conn->query("SELECT id, descricao FROM TB_NIVEL ORDER BY descricao");
            while($n = $niv->fetch_assoc()):
          ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="niveis[]" value="<?= $n['id'] ?>" id="niv-<?= $n['id'] ?>">
              <label class="form-check-label" for="niv-<?= $n['id'] ?>">
                <?= htmlspecialchars($n['descricao']) ?>
              </label>
            </div>
          <?php endwhile; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL DE EXCLUSÃO -->
<div class="modal fade" id="modalExcluirOKR" tabindex="-1" aria-labelledby="modalExcluirOKRLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalExcluirOKRLabel">Excluir OKR</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <form action="deletar_okr.php" method="post">
        <div class="modal-body">
          <input type="hidden" name="id" id="excluir_okr_id">
          <p>Tem certeza que deseja excluir o OKR <strong id="excluir_okr_nome"></strong>?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger">Excluir</button>
        </div>
      </form>
    </div>
  </div>
</div>