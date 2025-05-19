<!-- MODAL NOVO OKR -->
<div class="modal fade" id="modalNovoOKR" tabindex="-1" aria-labelledby="modalNovoOKRLabel" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form action="cadastrar_okr.php" method="post">
      <div class="modal-header">
        <h5 class="modal-title" id="modalNovoOKRLabel">Cadastrar OKR</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Descrição</label>
          <input type="text" class="form-control" name="descricao" required>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Equipe</label>
            <select class="form-select" name="idEquipe" required>
              <?php $e=$conn->query("SELECT id,descricao FROM TB_EQUIPE"); while($r=$e->fetch_assoc()): ?>
                <option value="<?=$r['id']?>"><?=$r['descricao']?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Níveis (um ou mais)</label>
            <div class="border rounded p-2" style="max-height:120px;overflow:auto">
              <?php $niv=$conn->query("SELECT id,descricao FROM TB_NIVEL"); while($n=$niv->fetch_assoc()): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="idNivel[]" value="<?=$n['id']?>" id="niv<?=$n['id']?>">
                  <label class="form-check-label" for="niv<?=$n['id']?>"><?=$n['descricao']?></label>
                </div>
              <?php endwhile; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-custom" type="submit">Salvar</button>
      </div>
    </form>
  </div></div>
</div>

<!-- MODAL NOVA META -->
<div class="modal fade" id="modalNovaMeta" tabindex="-1" aria-labelledby="modalNovaMetaLabel" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form action="cadastrar_meta.php" method="post">
      <div class="modal-header">
        <h5 class="modal-title" id="modalNovaMetaLabel">Cadastrar Meta Anual</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-3">
          <div class="col-md-6">
            <!-- Equipe -->
            <label class="form-label">Equipe</label>
            <select id="selEquipeMeta" name="idEquipe" class="form-select" required>
              <?php
                $eqs = $conn->query("SELECT id, descricao FROM TB_EQUIPE ORDER BY descricao");
                while($e = $eqs->fetch_assoc()):
              ?>
                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['descricao']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <!-- 1) Seletor de Nível -->
          <div class="col-md-6">
            <label class="form-label">Nível</label>
            <select id="selNivelMeta" class="form-select" required>
              <option value="">Selecione um nível</option>
              <?php
                $resN = $conn->query("
                  SELECT 
                    ni.id,
                    ni.descricao,
                    equ.id            AS idEquipe,
                    RIGHT(equ.descricao,5) AS equipe
                  FROM TB_NIVEL ni
                  JOIN TB_OKR_NIVEL okn ON okn.idNivel = ni.id
                  JOIN TB_OKR      okr ON okr.id      = okn.idOkr
                  JOIN TB_EQUIPE   equ ON equ.id      = okr.idEquipe
                  
                  GROUP BY equ.id, ni.id
                  ORDER BY equ.descricao, ni.descricao
                ");
                while($n = $resN->fetch_assoc()):
              ?>
                <option value="<?= $n['id'] ?>" data-equipe-id="<?= $n['idEquipe'] ?>">
                  <?= htmlspecialchars($n['descricao']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>

        <!-- 2) Seletor de OKR (inicialmente desabilitado) -->
        <div class="mb-3">
          <label class="form-label">OKR</label>
          <select
            id="selOkrMeta"
            name="idOkr"
            class="form-select"
            required
            disabled
          >
            <option value="">Selecione um OKR</option>
            <?php
              $o = $conn->query("
                SELECT
                  o.id,
                  o.descricao,
                  o.idEquipe,
                  GROUP_CONCAT(onl.idNivel) AS niveis_ids
                FROM TB_OKR o
                LEFT JOIN TB_OKR_NIVEL onl ON onl.idOkr = o.id
                GROUP BY o.id, o.descricao, o.idEquipe
                ORDER BY o.descricao
              ");
              while($r = $o->fetch_assoc()):
            ?>
              <option
                value="<?= $r['id'] ?>"
                data-niveis-ids="<?= $r['niveis_ids'] ?>"
                data-equipe-id="<?= $r['idEquipe'] ?>"
              >
                <?= htmlspecialchars($r['descricao']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Ano</label>
            <input type="number" class="form-control" name="ano" value="<?=$anoAtual?>" required>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Tipo</label>
            <select name="tipo_meta" id="tipoMetaSel" class="form-select" onchange="toggleTipoMeta()" required>
              <option value="valor" selected>Valor (%)</option>
              <option value="tempo">Tempo (HH:MM:SS)</option>
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">Prazo</label>
            <input type="date" class="form-control" name="dt_prazo" id="dtPrazoMeta"  required>
          </div>
        </div>
        <div class="row">
          <div id="divValor" class="col-md-4 mb-3">
            <label class="form-label">Meta (%)</label>
            <input type="number" step="0.01" class="form-control" name="meta_valor">
          </div>
          <div id="divTempo" class="col-md-4 mb-3 d-none">
            <label class="form-label">Meta (HH:MM:SS)</label>
            <input type="text" class="form-control" name="meta_tempo" placeholder="00:01:10">
          </div>
        
          <div class="col-md-8">
            <label class="form-label">Descrição do KR</label>
            <input type="text" class="form-control" name="descricao">
          </div>
        </div>
        <div id="divKRLancados" class="mb-1 d-none">
          <strong>KRs já cadastrados neste OKR:</strong>
          <ul class="list-group list-group-flush" id="ulKRLancados"></ul>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-custom" type="submit">Salvar</button>
      </div>
    </form>
  </div></div>
</div>

<!-- MODAL LANÇAR REALIZADO -->
<div class="modal fade" id="modalLancamento" …>
  <div class="modal-dialog"><div class="modal-content">
    <form action="cadastrar_atingimento.php" method="post">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLancamentoLabel">Registrar Realizado Mensal</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-3">
          <!-- Equipe -->
          <div class="col-md-6">
            <label class="form-label">Equipe</label>
            <select id="selEquipeLanc" name="idEquipe" class="form-select" required>
              <?php
                $eqs = $conn->query("SELECT id, descricao FROM TB_EQUIPE ORDER BY descricao");
                while($e = $eqs->fetch_assoc()):
              ?>
                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['descricao']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <!-- 1) Seletor de Nível -->
          <div class="col-md-6">
            <label class="form-label">Nível</label>
            <select id="selNivelLanc" name="idNivel" class="form-select" required>
              <option value="">Selecione um nível</option>
              <?php
                $resN = $conn->query("
                  SELECT 
                    ni.id,
                    ni.descricao,
                    equ.id            AS idEquipe,
                    RIGHT(equ.descricao,5) AS equipe
                  FROM TB_NIVEL ni
                  JOIN TB_OKR_NIVEL okn ON okn.idNivel = ni.id
                  JOIN TB_OKR      okr ON okr.id      = okn.idOkr
                  JOIN TB_EQUIPE   equ ON equ.id      = okr.idEquipe
                  WHERE equ.id <> 3
                  GROUP BY equ.id, ni.id
                  ORDER BY equ.descricao, ni.descricao
                ");
                while($n = $resN->fetch_assoc()):
              ?>
                <option
                  value="<?= $n['id'] ?>"
                  data-equipe-id="<?= $n['idEquipe'] ?>"
                >
                  <?= htmlspecialchars($n['descricao']) ?> – <?= htmlspecialchars($n['equipe']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>

        <!-- 2) Seletor de OKR -->
        <div class="mb-3">
          <label class="form-label">OKR</label>
          <select id="selOkrLanc" name="idOkr" class="form-select" required disabled>
            <option value="">Selecione o OKR</option>
            <?php
              $okrOptions = $conn->query("
                SELECT
                  o.id,
                  o.descricao,
                  o.idEquipe,
                  GROUP_CONCAT(onl.idNivel) AS niveis_ids
                FROM TB_OKR o
                LEFT JOIN TB_OKR_NIVEL onl ON onl.idOkr = o.id
                GROUP BY o.id, o.descricao, o.idEquipe
                ORDER BY o.descricao
              ");
              while($ok = $okrOptions->fetch_assoc()):
            ?>
              <option
                value="<?= $ok['id'] ?>"
                data-niveis-ids="<?= $ok['niveis_ids'] ?>"
                data-equipe-id="<?= $ok['idEquipe'] ?>"
              >
                <?= htmlspecialchars($ok['descricao']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <!-- restante do modal -->
        <div class="mb-3">
          <label class="form-label">Mês</label>
          <select class="form-select" name="mes" required>
            <?php for($i=1;$i<=12;$i++): ?>
              <option value="<?=$i?>"><?=str_pad($i,2,'0',STR_PAD_LEFT)?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div id="divMetasList" class="d-none">
          <table class="table">
            <thead>
              <tr>
                <th>KR / Meta</th>
                <th>Realizado</th>
              </tr>
            </thead>
            <tbody id="tbodyMetas"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-custom" type="submit">Salvar</button>
      </div>
    </form>
  </div>
</div>

