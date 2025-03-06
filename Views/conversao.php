<?php
include '../Config/Database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1) TOTALIZAÇÕES
$sqlQuantidade = "
  SELECT COUNT(*) 
    FROM TB_CONVERSOES
   WHERE MONTH(data_recebido) = MONTH(NOW())
";
$total_conversoes = $conn->query($sqlQuantidade)->fetch_row()[0] ?? 0;

$sqlTempoMedio = "
  SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(tempo_total)))
    FROM TB_CONVERSOES
   WHERE MONTH(data_recebido) = MONTH(NOW())
";
$tempo_medio = $conn->query($sqlTempoMedio)->fetch_row()[0] ?? 'N/A';

// 2) LISTAGEM (JOIN para obter nomes de Sistema, Status, Analista)
$sqlListar = "
  SELECT c.id,
         c.contato,
         c.serial,
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
         c.email_cliente,
         c.retrabalho,
         c.observacao
    FROM TB_CONVERSOES c
    JOIN TB_SISTEMA_CONVER s  ON c.sistema_id  = s.id
    JOIN TB_STATUS_CONVER st  ON c.status_id   = st.id
    JOIN TB_ANALISTA_CONVER a ON c.analista_id = a.id
   ORDER BY c.data_recebido DESC
";
$result = $conn->query($sqlListar);

// 3) Carregar listas (Sistemas/Status/Analistas) para os selects
$sistemas  = $conn->query("SELECT * FROM TB_SISTEMA_CONVER ORDER BY nome");
$status    = $conn->query("SELECT * FROM TB_STATUS_CONVER ORDER BY descricao");
$analistas = $conn->query("SELECT * FROM TB_ANALISTA_CONVER ORDER BY nome");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Gerenciar Conversões</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <link rel="stylesheet" href="../Public/conversao.css">
  
  <script>
    function abrirModalCadastro() {
      $("#modalCadastro").modal('show');
    }

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
<body>
  <div class="container mt-4">
    <h1 class="text-center mb-4">Gerenciar Conversões</h1>

    <!-- Row de totalizadores -->
    <div class="row g-3 mb-3 card-total">
      <div class="col-md-6">
        <div class="card text-white bg-primary">
          <div class="card-body text-center">
            <h5 class="card-title">Total de Conversões (Mês)</h5>
            <h3 class="card-text"><?= $total_conversoes; ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card text-white bg-success">
          <div class="card-body text-center">
            <h5 class="card-title">Tempo Médio (Mês)</h5>
            <h3 class="card-text"><?= $tempo_medio; ?></h3>
          </div>
        </div>
      </div>
    </div>

    <!-- Botão de cadastro -->
    <div class="d-flex justify-content-end mb-3">
      <button class="btn btn-primary" onclick="abrirModalCadastro()">Cadastrar</button>
    </div>

    <!-- Card para a tabela -->
    <div class="card">
    
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped table-bordered mb-0">
            <thead class="table-dark">
              <tr>
                <!-- APENAS as colunas que o usuário solicitou -->
                <th>Contato</th>
                <th>Serial/CNPJ</th>
                <th>Sistema</th>
                <th>Prazo</th>
                <th>Status</th>
                <th>Recebido</th>
                <th>Início</th>
                <th>Conclusão</th>
                <th>Analista</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= $row['contato']; ?></td>
                <td><?= $row['serial']; ?></td>
                <td><?= $row['sistema_nome']; ?></td>
                <td><?= $row['prazo_entrega']; ?></td>
                <td><?= $row['status_nome']; ?></td>
                <td><?= $row['data_recebido']; ?></td>
                <td><?= $row['data_inicio']; ?></td>
                <td><?= $row['data_conclusao']; ?></td>
                <td><?= $row['analista_nome']; ?></td>
                <td>
                  <button class="btn btn-warning btn-sm"
                    onclick="abrirModalEdicao(
                      '<?= $row['id'] ?>',
                      '<?= $row['email_cliente'] ?>',
                      '<?= $row['contato'] ?>',
                      '<?= $row['serial'] ?>',
                      '<?= $row['retrabalho'] ?>',
                      '<?= $row['sistema_id'] ?>',
                      '<?= $row['prazo_entrega'] ?>',
                      '<?= $row['status_id'] ?>',
                      '<?= $row['data_recebido'] ?>',
                      '<?= $row['data_inicio'] ?>',
                      '<?= $row['data_conclusao'] ?>',
                      '<?= $row['analista_id'] ?>',
                      '<?= addslashes($row['observacao']) ?>'
                    )">
                    Editar
                  </button>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div><!-- table-responsive -->
      </div><!-- card-body -->
    </div><!-- card -->
  </div><!-- container -->

  <!-- MODAL CADASTRO (Mesmos campos de antes) -->
  <div class="modal fade" id="modalCadastro" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content p-4">
        <h4 class="modal-title mb-3">Cadastrar Conversão</h4>
        <form id="formCadastro">
          <input type="hidden" name="id">
          <!-- E-mail (não exibido na lista mas editamos/cadastramos normalmente) -->
          <div class="mb-3">
            <label class="form-label">Email do Cliente:</label>
            <input type="email" class="form-control" name="email_cliente" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Contato:</label>
            <input type="text" class="form-control" name="contato" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Serial / CNPJ:</label>
            <input type="text" class="form-control" name="serial">
          </div>
          <div class="mb-3">
            <label class="form-label">Retrabalho:</label>
            <select name="retrabalho" class="form-select">
              <option value="Sim">Sim</option>
              <option value="Não" selected>Não</option>
            </select>
          </div>
          <!-- SISTEMA -->
          <div class="mb-3">
            <label class="form-label">Sistema:</label>
            <select name="sistema_id" class="form-select" required>
              <option value="">Selecione...</option>
              <?php
              mysqli_data_seek($sistemas, 0);
              while ($sis = $sistemas->fetch_assoc()):
              ?>
                <option value="<?= $sis['id']; ?>"><?= $sis['nome']; ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Prazo Entrega:</label>
            <input type="datetime-local" class="form-control" name="prazo_entrega" required>
          </div>
          <!-- STATUS -->
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
          <!-- DATA RECEBIDO -->
          <div class="mb-3">
            <label class="form-label">Data Recebido:</label>
            <input type="datetime-local" class="form-control" name="data_recebido" required>
          </div>
          <!-- DATA INICIO -->
          <div class="mb-3">
            <label class="form-label">Data Início:</label>
            <input type="datetime-local" class="form-control" name="data_inicio" required>
          </div>
          <!-- DATA CONCLUSAO -->
          <div class="mb-3">
            <label class="form-label">Data Conclusão:</label>
            <input type="datetime-local" class="form-control" name="data_conclusao">
          </div>
          <!-- ANALISTA -->
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
          <!-- OBSERVACAO -->
          <div class="mb-3">
            <label class="form-label">Observação:</label>
            <textarea name="observacao" class="form-control" rows="3"></textarea>
          </div>
          <div class="text-end">
            <button type="button" class="btn btn-success" onclick="salvarCadastro()">Salvar</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL EDICAO (Mesmos campos - Email, Retrabalho etc. - Mas não exibidos na tabela) -->
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
            <label class="form-label">Contato:</label>
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

  <!-- Bootstrap JS (modal etc.) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
