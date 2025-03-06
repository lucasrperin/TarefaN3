<?php
/*********************************************************
 * conversao.php
 * Página principal para Gerenciar Conversões
 * Usa TB_CONVERSOES com chaves estrangeiras em:
 *   - TB_SISTEMA_CONVER
 *   - TB_STATUS_CONVER
 *   - TB_ANALISTA_CONVER
 *********************************************************/
include '../Config/Database.php'; // Ajuste conforme seu caminho

// (Opcional) Debug de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

/*******************************************************
 * 1) TOTALIZAÇÕES
 *******************************************************/
// Quantidade de conversões no mês
$sqlQuantidade = "
    SELECT COUNT(*) 
      FROM TB_CONVERSOES
     WHERE MONTH(data_recebido) = MONTH(NOW())
";
$total_conversoes = $conn->query($sqlQuantidade)->fetch_row()[0] ?? 0;

// Tempo médio no mês
$sqlTempoMedio = "
    SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(tempo_total))) 
      FROM TB_CONVERSOES
     WHERE MONTH(data_recebido) = MONTH(NOW())
";
$tempo_medio = $conn->query($sqlTempoMedio)->fetch_row()[0] ?? 'N/A';

/*******************************************************
 * 2) LISTAGEM DAS CONVERSÕES (JOIN nas FK)
 *******************************************************/
$sqlListar = "
    SELECT c.id,
           c.email_cliente,
           c.contato,
           c.serial,
           c.retrabalho,
           c.sistema_id,
           s.nome           AS sistema_nome,
           c.prazo_entrega,
           c.status_id,
           st.descricao     AS status_nome,
           c.data_recebido,
           c.data_inicio,
           c.data_conclusao,
           c.analista_id,
           a.nome           AS analista_nome,
           c.observacao
      FROM TB_CONVERSOES c
      JOIN TB_SISTEMA_CONVER s  ON c.sistema_id  = s.id
      JOIN TB_STATUS_CONVER st  ON c.status_id   = st.id
      JOIN TB_ANALISTA_CONVER a ON c.analista_id = a.id
     ORDER BY c.data_recebido DESC
";
$result = $conn->query($sqlListar);

/*******************************************************
 * 3) CARREGAR LISTAS (SISTEMA, STATUS, ANALISTAS)
 *    para preencher os <select> nos modais
 *******************************************************/
$sistemas = $conn->query("SELECT * FROM TB_SISTEMA_CONVER ORDER BY nome");
$status  = $conn->query("SELECT * FROM TB_STATUS_CONVER ORDER BY descricao");
$analistas = $conn->query("SELECT * FROM TB_ANALISTA_CONVER ORDER BY nome");

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>Gerenciar Conversões</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <script>
    // Abre o modal de cadastro
    function abrirModalCadastro() {
      $("#modalCadastro").modal('show');
    }

    // Abre o modal de edição, passando ID e DEMAIS CAMPOS
    // Ao final, definimos os selects para o ID correto
    function abrirModalEdicao(
      id, email, contato, serial, retrabalho,
      sistemaID, prazoEntrega, statusID,
      dataRecebido, dataInicio, dataConclusao,
      analistaID, observacao
    ) {
      $("#edit_id").val(id);
      $("#edit_email_cliente").val(email);
      $("#edit_contato").val(contato);
      $("#edit_serial").val(serial);
      $("#edit_retrabalho").val(retrabalho);
      $("#edit_sistema").val(sistemaID);
      $("#edit_prazo_entrega").val(prazoEntrega);
      $("#edit_status").val(statusID);
      $("#edit_data_recebido").val(dataRecebido);
      $("#edit_data_inicio").val(dataInicio);
      $("#edit_data_conclusao").val(dataConclusao);
      $("#edit_analista").val(analistaID);
      $("#edit_observacao").val(observacao);
      $("#modalEdicao").modal('show');
    }

    // Salvar Cadastro via AJAX
    function salvarCadastro() {
      $.post("cadastrar_conversao.php",
             $("#formCadastro").serialize(),
             function(response) {
               if (response.trim() === "success") {
                 location.reload();
               } else {
                 alert("Erro ao cadastrar: " + response);
               }
             }
      ).fail(function(jqXHR, textStatus, errorThrown) {
        alert("Erro AJAX [cadastro]: " + textStatus + " - " + errorThrown);
      });
    }

    // Salvar Edição via AJAX
    function salvarEdicao() {
      $.post("editar_conversao.php",
             $("#formEdicao").serialize(),
             function(response) {
               if (response.trim() === "success") {
                 location.reload();
               } else {
                 alert("Erro ao editar: " + response);
               }
             }
      ).fail(function(jqXHR, textStatus, errorThrown) {
        alert("Erro AJAX [edição]: " + textStatus + " - " + errorThrown);
      });
    }
  </script>
</head>
<body class="container mt-4">

  <!-- Título e Totalizadores -->
  <h1 class="text-center mb-4">Gerenciar Conversões</h1>

  <div class="row mb-3">
    <div class="col-md-6">
      <div class="card p-3 text-center bg-primary text-white">
        <h5 class="fw-bold">Total de Conversões (Mês)</h5>
        <h3><?= $total_conversoes; ?></h3>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card p-3 text-center bg-success text-white">
        <h5 class="fw-bold">Tempo Médio (Mês)</h5>
        <h3><?= $tempo_medio; ?></h3>
      </div>
    </div>
  </div>

  <!-- Botão para abrir modal de cadastro -->
  <button class="btn btn-primary mb-3" onclick="abrirModalCadastro()">Cadastrar Nova Conversão</button>

  <!-- Tabela de conversões -->
  <table class="table table-bordered table-striped">
    <thead class="table-dark">
      <tr>
        <th>ID</th>
        <th>Email</th>
        <th>Contato</th>
        <th>Serial/CNPJ</th>
        <th>Retrabalho</th>
        <th>Sistema</th>
        <th>Prazo Entrega</th>
        <th>Status</th>
        <th>Data Recebido</th>
        <th>Data Início</th>
        <th>Data Conclusão</th>
        <th>Analista</th>
        <th>Observação</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $row['id']; ?></td>
        <td><?= $row['email_cliente']; ?></td>
        <td><?= $row['contato']; ?></td>
        <td><?= $row['serial']; ?></td>
        <td><?= $row['retrabalho']; ?></td>
        <td><?= $row['sistema_nome']; ?></td>
        <td><?= $row['prazo_entrega']; ?></td>
        <td><?= $row['status_nome']; ?></td>
        <td><?= $row['data_recebido']; ?></td>
        <td><?= $row['data_inicio']; ?></td>
        <td><?= $row['data_conclusao']; ?></td>
        <td><?= $row['analista_nome']; ?></td>
        <td><?= $row['observacao']; ?></td>
        <td>
          <button class="btn btn-warning"
            onclick="abrirModalEdicao(
              '<?= $row['id'] ?>',
              '<?= $row['email_cliente'] ?>',
              '<?= $row['contato'] ?>',
              '<?= $row['serial'] ?>',
              '<?= $row['retrabalho'] ?>',
              '<?= $row['sistema_id'] ?>',      /* ID real do sistema */
              '<?= $row['prazo_entrega'] ?>',
              '<?= $row['status_id'] ?>',       /* ID real do status */
              '<?= $row['data_recebido'] ?>',
              '<?= $row['data_inicio'] ?>',
              '<?= $row['data_conclusao'] ?>',
              '<?= $row['analista_id'] ?>',     /* ID real do analista */
              '<?= addslashes($row['observacao']) ?>'
            )">
            Editar
          </button>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <!-- MODAL CADASTRO -->
  <div class="modal fade" id="modalCadastro" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content p-4">
        <h4 class="modal-title mb-3">Cadastrar Conversão</h4>
        <form id="formCadastro">
          <div class="row mb-3">
            <div class="col-md-5">
              <div class="mb-3">
                <label class="form-label"><span>(Telefone/Email)</span></label>
                <input type="text" class="form-control" name="contato" required>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Serial / CNPJ:</label>
                <input type="text" class="form-control" name="serial">
              </div>
            </div>

            <div class="col-md-3">
              <div class="mb-3">
                <label class="form-label">Retrabalho:</label>
                <select name="retrabalho" class="form-select">
                  <option value="Sim">Sim</option>
                  <option value="Não" selected>Não</option>
                </select>
              </div>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Sistema:</label>
                <select name="sistema_id" class="form-select" required>
                  <option value="">Selecione...</option>
                  <?php
                  // Reposiciona o ponteiro para listar sistemas de novo
                  mysqli_data_seek($sistemas, 0);
                  while ($sis = $sistemas->fetch_assoc()):
                  ?>
                    <option value="<?= $sis['id']; ?>"><?= $sis['nome']; ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>

            <div class="col-md-3">
              <div class="mb-3">
                <label class="form-label">Prazo Entrega:</label>
                <input type="datetime-local" class="form-control" name="prazo_entrega" required>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Status:</label>
                <select name="status_id" class="form-select" required>
                  <option value="">Selecione...</option>
                  <?php
                  mysqli_data_seek($status, 0);
                  while ($st = $status->fetch_assoc()):
                  ?>
                    <option value="<?= $st['id']; ?>"><?= $st['descricao']; ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-3">
              <div class="mb-3">
                <label class="form-label">Data Recebido:</label>
                <input type="datetime-local" class="form-control" name="data_recebido" required>
              </div>
            </div>
            
            <div class="col-md-3">
              <div class="mb-3">
                <label class="form-label">Data Início:</label>
                <input type="datetime-local" class="form-control" name="data_inicio" required>
              </div>
            </div>

            <div class="col-md-3">
              <div class="mb-3">
                <label class="form-label">Data Conclusão:</label>
                <input type="datetime-local" class="form-control" name="data_conclusao">
              </div>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-3">
              <div class="mb-3">
                <label class="form-label">Analista:</label>
                <select name="analista_id" class="form-select" required>
                  <option value="">Selecione...</option>
                  <?php
                  mysqli_data_seek($analistas, 0);
                  while ($an = $analistas->fetch_assoc()):
                  ?>
                    <option value="<?= $an['id']; ?>"><?= $an['nome']; ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Observação:</label>
                <textarea name="observacao" class="form-control" rows="3"></textarea>
              </div>
            </div>
          </div>
                 
          <div class="text-end">
            <button type="button" class="btn btn-success" onclick="salvarCadastro()">Salvar</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL EDIÇÃO -->
  <div class="modal fade" id="modalEdicao" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content p-4">
        <h4 class="modal-title mb-3">Editar Conversão</h4>
        <form id="formEdicao">
          <input type="hidden" id="edit_id" name="id">

          <div class="mb-3">
            <label class="form-label">Email do Cliente:</label>
            <input type="email" class="form-control" id="edit_email_cliente" name="email_cliente" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Contato (telefone ou email):</label>
            <input type="text" class="form-control" id="edit_contato" name="contato" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Serial / CNPJ:</label>
            <input type="text" class="form-control" id="edit_serial" name="serial">
          </div>

          <div class="mb-3">
            <label class="form-label">Retrabalho:</label>
            <select id="edit_retrabalho" name="retrabalho" class="form-select">
              <option value="Sim">Sim</option>
              <option value="Não">Não</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Sistema:</label>
            <select id="edit_sistema" name="sistema_id" class="form-select" required>
              <option value="">Selecione...</option>
              <?php
              // Precisamos reposicionar ponteiro de $sistemas novamente
              mysqli_data_seek($sistemas, 0);
              while ($sisE = $sistemas->fetch_assoc()):
              ?>
                <option value="<?= $sisE['id']; ?>"><?= $sisE['nome']; ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Prazo Entrega:</label>
            <input type="datetime-local" class="form-control" id="edit_prazo_entrega" name="prazo_entrega" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Status:</label>
            <select id="edit_status" name="status_id" class="form-select" required>
              <option value="">Selecione...</option>
              <?php
              mysqli_data_seek($status, 0);
              while ($stE = $status->fetch_assoc()):
              ?>
                <option value="<?= $stE['id']; ?>"><?= $stE['descricao']; ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Data Recebido:</label>
            <input type="datetime-local" class="form-control" id="edit_data_recebido" name="data_recebido" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Data Início:</label>
            <input type="datetime-local" class="form-control" id="edit_data_inicio" name="data_inicio" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Data Conclusão:</label>
            <input type="datetime-local" class="form-control" id="edit_data_conclusao" name="data_conclusao">
          </div>

          <div class="mb-3">
            <label class="form-label">Analista:</label>
            <select id="edit_analista" name="analista_id" class="form-select" required>
              <option value="">Selecione...</option>
              <?php
              mysqli_data_seek($analistas, 0);
              while ($anE = $analistas->fetch_assoc()):
              ?>
                <option value="<?= $anE['id']; ?>"><?= $anE['nome']; ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Observação:</label>
            <textarea id="edit_observacao" class="form-control" name="observacao" rows="3"></textarea>
          </div>

          <div class="text-end">
            <button type="button" class="btn btn-success" onclick="salvarEdicao()">Salvar</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
