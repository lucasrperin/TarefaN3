<?php
include '../Config/Database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

/****************************************************************
 * 1) Capturar Filtros (GET)
 ****************************************************************/
$dataInicial = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : '';
$dataFinal   = isset($_GET['data_final'])   ? $_GET['data_final']   : '';
$analistaID  = isset($_GET['analista_id'])  ? intval($_GET['analista_id']) : 0;

/****************************************************************
 * 2) Montar WHERE Dinâmico
 ****************************************************************/
$where = " WHERE 1=1 ";
if (!empty($dataInicial)) {
    $where .= " AND c.data_recebido >= '{$dataInicial} 00:00:00' ";
}
if (!empty($dataFinal)) {
    $where .= " AND c.data_recebido <= '{$dataFinal} 23:59:59' ";
}
if ($analistaID > 0) {
    $where .= " AND c.analista_id = {$analistaID} ";
}

/****************************************************************
 * 3) Dados do Gráfico (Mês x Analista)
 ****************************************************************/
$sqlGrafico = "
    SELECT 
        YEAR(c.data_recebido) AS ano,
        MONTH(c.data_recebido) AS mes,
        a.nome                AS analista_nome,
        COUNT(*)             AS total
      FROM TB_CONVERSOES c
      JOIN TB_ANALISTA_CONVER a ON c.analista_id = a.id
      $where
      GROUP BY YEAR(c.data_recebido), MONTH(c.data_recebido), c.analista_id
      ORDER BY ano, mes, analista_nome
";
$resGraf = $conn->query($sqlGrafico);

$dataPorMesAnalista = [];
$analistasDistinct  = [];
while ($rowG = $resGraf->fetch_assoc()) {
    $ano  = $rowG['ano'];
    $mes  = $rowG['mes'];
    $anal = $rowG['analista_nome'];
    $tot  = $rowG['total'];

    $rotuloMes = sprintf("%04d-%02d", $ano, $mes);
    if (!isset($dataPorMesAnalista[$rotuloMes])) {
        $dataPorMesAnalista[$rotuloMes] = [];
    }
    $dataPorMesAnalista[$rotuloMes][$anal] = $tot;
    $analistasDistinct[$anal] = true;
}

$labelsMes = array_keys($dataPorMesAnalista);
sort($labelsMes);

$listaAnalistas = array_keys($analistasDistinct);
sort($listaAnalistas);

// Montar datasets p/ Chart.js
$chartDatasets = [];
$cores = ["#d9534f","#5bc0de","#5cb85c","#f0ad4e","#0275d8","#292b2c","#7f7f7f"];
$corIndex = 0;
foreach ($listaAnalistas as $anal) {
    $dataVals = [];
    foreach ($labelsMes as $m) {
        $val = isset($dataPorMesAnalista[$m][$anal]) ? $dataPorMesAnalista[$m][$anal] : 0;
        $dataVals[] = $val;
    }
    $chartDatasets[] = [
        'label' => $anal,
        'backgroundColor' => $cores[$corIndex % count($cores)],
        'data' => $dataVals
    ];
    $corIndex++;
}

/****************************************************************
 * 4) TOTALIZADORES GERAIS (Quantidade, Tempo Médio)
 ****************************************************************/
$sqlQtd = "
    SELECT COUNT(*)
      FROM TB_CONVERSOES c
      $where
";
$total_conversoes = $conn->query($sqlQtd)->fetch_row()[0] ?? 0;

$sqlTempo = "
    SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(tempo_total)))
      FROM TB_CONVERSOES c
      $where
";
$tempo_medio = $conn->query($sqlTempo)->fetch_row()[0] ?? 'N/A';

/****************************************************************
 * 5) Totalizadores por Status
 ****************************************************************/
$sqlStatusTot = "
    SELECT st.descricao AS status_nome,
           COUNT(*)     AS total
      FROM TB_CONVERSOES c
      JOIN TB_STATUS_CONVER st ON c.status_id = st.id
      $where
      GROUP BY c.status_id
      ORDER BY st.descricao
";
$resStatusTot = $conn->query($sqlStatusTot);

/****************************************************************
 * 6) Totalizadores por Sistema
 ****************************************************************/
$sqlSistemaTot = "
    SELECT s.nome AS sistema_nome,
           COUNT(*) AS total
      FROM TB_CONVERSOES c
      JOIN TB_SISTEMA_CONVER s ON c.sistema_id = s.id
      $where
      GROUP BY c.sistema_id
      ORDER BY s.nome
";
$resSistemaTot = $conn->query($sqlSistemaTot);

/****************************************************************
 * 7) LISTA DE CONVERSOES
 ****************************************************************/
$sqlListar = "SELECT 
                c.id,
                c.id as Codigo,
                c.contato,
                c.serial,
                c.sistema_id,
                s.nome       AS sistema_nome,
                c.prazo_entrega,
                c.status_id,
                st.descricao AS status_nome,
                c.data_recebido,
                c.data_inicio,
                c.data_conclusao,
                c.analista_id,
                a.nome       AS analista_nome,
                c.retrabalho,
                c.observacao
            FROM TB_CONVERSOES c
            JOIN TB_SISTEMA_CONVER s  ON c.sistema_id  = s.id
            JOIN TB_STATUS_CONVER st  ON c.status_id   = st.id
            JOIN TB_ANALISTA_CONVER a ON c.analista_id = a.id
            $where
          ORDER BY c.data_recebido DESC";
$result = $conn->query($sqlListar);

/****************************************************************
 * 8) Listas p/ selects (Sistemas, Status, Analistas)
 ****************************************************************/
$sistemas  = $conn->query("SELECT * FROM TB_SISTEMA_CONVER ORDER BY nome");
$status    = $conn->query("SELECT * FROM TB_STATUS_CONVER ORDER BY descricao");
$analistas = $conn->query("SELECT * FROM TB_ANALISTA_CONVER ORDER BY nome");

// Para o filtro
$analistasFiltro = $conn->query("SELECT * FROM TB_ANALISTA_CONVER ORDER BY nome");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Gerenciar Conversões</title>
  <!-- CSS externo minimalista -->
  <link rel="stylesheet" href="../Public/conversao.css">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- Ícones personalizados -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

</head>
<body>

<!-- Container do Toast no canto superior direito -->
<div class="toast-container">
    <div id="toastSucesso" class="toast">
        <div class="toast-body">
            <i class="fa-solid fa-check-circle"></i> <span id="toastMensagem"></span>
        </div>
    </div>
</div>

<script>
//Toast para mensagem de sucesso
document.addEventListener("DOMContentLoaded", function () {
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get("success");

        if (success) {
            let mensagem = "";
            switch (success) {
                case "1":
                    mensagem = "Conversão cadastrada com sucesso!";
                    break;
                case "2":
                    mensagem = "Conversão editada com sucesso!";
                    break;
                case "3":
                    mensagem = "Conversão excluída com sucesso!";
                    break;
            }

            if (mensagem) {
                document.getElementById("toastMensagem").textContent = mensagem;
                var toastEl = document.getElementById("toastSucesso");
                var toast = new bootstrap.Toast(toastEl, { delay: 2200 });
                toast.show();
            }
        }
    });
</script> 


<div class="container mt-4">
  <h1 class="text-center mb-4">Gerenciar Conversões</h1>

  <!-- FILTRO GLOBAL -->
  <form method="GET" class="row gy-2 gx-2 mb-4">
    <div class="col-md-3">
      <label>Data Inicial</label>
      <input type="date" name="data_inicial" value="<?= htmlspecialchars($dataInicial) ?>" class="form-control">
    </div>
    <div class="col-md-3">
      <label>Data Final</label>
      <input type="date" name="data_final" value="<?= htmlspecialchars($dataFinal) ?>" class="form-control">
    </div>
    <div class="col-md-3">
      <label>Analista</label>
      <select name="analista_id" class="form-select">
        <option value="0">-- Todos --</option>
        <?php while ($anF = $analistasFiltro->fetch_assoc()): ?>
          <option value="<?= $anF['id'] ?>"
            <?= ($analistaID == $anF['id']) ? 'selected' : '' ?>>
            <?= $anF['nome'] ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button type="submit" class="btn btn-primary w-100">Filtrar</button>
    </div>
  </form>

  <!-- ROW: Grafico à esquerda (col-md-8), Lista de totalizadores (status/sistema) à direita (col-md-4) -->
  <div class="row mb-4">
    <div class="col-md-8">
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Conversões Mensais por Analista</h5>
          <canvas id="chartBarras" height="100"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <!-- Lista de totalizadores por status -->
      <div class="card mb-3">
        <div class="card-body">
          <h6 class="card-subtitle mb-2 text-muted">Conversões por Status</h6>
          <ul class="list-group">
            <?php while ($rowSt = $resStatusTot->fetch_assoc()): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <?= $rowSt['status_nome'] ?>
              <span class="badge bg-primary rounded-pill"><?= $rowSt['total'] ?></span>
            </li>
            <?php endwhile; ?>
          </ul>
        </div>
      </div>
      <!-- Lista de totalizadores por sistema -->
      <div class="card">
        <div class="card-body">
          <h6 class="card-subtitle mb-2 text-muted">Conversões por Sistema</h6>
          <ul class="list-group">
            <?php while ($rowSys = $resSistemaTot->fetch_assoc()): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <?= $rowSys['sistema_nome'] ?>
              <span class="badge bg-secondary rounded-pill"><?= $rowSys['total'] ?></span>
            </li>
            <?php endwhile; ?>
          </ul>
        </div>
      </div>
    </div>
  </div><!-- .row -->

  <!-- Totalizadores Gerais (Qtd, Tempo Médio) -->
  <div class="row g-3 mb-3 card-total">
    <div class="col-md-6">
      <div class="card text-white bg-primary">
        <div class="card-body text-center">
          <h5 class="card-title">Total de Conversões (Filtro)</h5>
          <h3 class="card-text"><?= $total_conversoes; ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card text-white bg-success">
        <div class="card-body text-center">
          <h5 class="card-title">Tempo Médio (Filtro)</h5>
          <h3 class="card-text"><?= $tempo_medio; ?></h3>
        </div>
      </div>
    </div>
  </div>

  <!-- Botão Cadastrar -->
  <div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" onclick="abrirModalCadastro()">Cadastrar</button>
  </div>

  <!-- Tabela de Conversões -->
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-bordered mb-0">
          <thead class="table-dark">
            <tr>
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
                <a class='btn btn-outline-primary btn-sm'
                  onclick="abrirModalEdicao(
                    '<?= $row['id'] ?>',
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
                  )"><i class='fa-sharp fa-solid fa-pen'></i>
                </a>
                <a href="javascript:void(0)" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalExclusao" onclick="excluirAnalise(<?= $row['id'] ?>)">
                  <i class="fa-sharp fa-solid fa-trash"></i>
                </a>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div><!-- .table-responsive -->
    </div><!-- .card-body -->
  </div><!-- .card -->
</div><!-- .container -->

  <!-- MODAL CADASTRO -->
  <div class="modal fade" id="modalCadastro" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content p-4">
        <h4 class="modal-title mb-3">Cadastrar Conversão</h4>
        <form id="formCadastro" action="cadastrar_conversao.php" method="POST">
          <div class="row mb-1">
            <div class="col-md-5">
              <div class="mb-3">
                <label class="form-label"><span>(Telefone/Email)</span></label>
                <input type="text" class="form-control" name="contato" required>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Serial / CNPJ:</label>
                <input type="text" class="form-control" name="serial" required>
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

            <div class="col-md-4">
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
          </div>

          <div class="row mb-3">
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Data Recebido:</label>
                <input type="datetime-local" class="form-control" name="data_recebido" required>
              </div>
            </div>
            
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Data Início:</label>
                <input type="datetime-local" class="form-control" name="data_inicio" required>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Data Conclusão:</label>
                <input type="datetime-local" class="form-control" name="data_conclusao">
              </div>
            </div>
          </div>

          <div class="row mb-3">
            

            <div class="col-md-12">
              <div class="mb-3">
                <label class="form-label">Observação:</label>
                <textarea name="observacao" class="form-control" rows="3"></textarea>
              </div>
            </div>
          </div>
                 
          <div class="text-end">
            <button type="submit" class="btn btn-success" onclick="exibirToast()">Salvar</button>
            <button type="submit" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
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
      <form id="formEdicao" action="editar_conversao.php" method="POST">
        <!-- Campo oculto para o ID da conversão -->
        <input type="hidden" id="edit_id" name="id">
        
        <div class="row mb-1">
          <div class="col-md-5">
            <div class="mb-3">
              <label class="form-label"><span>(Telefone/Email)</span></label>
              <input type="text" class="form-control" id="edit_contato" name="contato" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Serial / CNPJ:</label>
              <input type="text" class="form-control" id="edit_serial" name="serial" required>
            </div>
          </div>
          <div class="col-md-3">
            <div class="mb-3">
              <label class="form-label">Retrabalho:</label>
              <select name="retrabalho" class="form-select" id="edit_retrabalho">
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
              <select name="sistema_id" class="form-select" id="edit_sistema" required>
                <option value="">Selecione...</option>
                <?php
                mysqli_data_seek($sistemas, 0);
                while ($sisE = $sistemas->fetch_assoc()):
                ?>
                  <option value="<?= $sisE['id']; ?>"><?= $sisE['nome']; ?></option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>

          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Status:</label>
              <select name="status_id" class="form-select" id="edit_status" required>
                <option value="">Selecione...</option>
                <?php
                mysqli_data_seek($status, 0);
                while ($stE = $status->fetch_assoc()):
                ?>
                  <option value="<?= $stE['id']; ?>"><?= $stE['descricao']; ?></option>
                <?php endwhile; ?>
              </select>
            </div>
          </div> 

          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Analista:</label>
              <select name="analista_id" class="form-select" id="edit_analista" required>
                <option value="">Selecione...</option>
                <?php
                mysqli_data_seek($analistas, 0);
                while ($anE = $analistas->fetch_assoc()):
                ?>
                  <option value="<?= $anE['id']; ?>"><?= $anE['nome']; ?></option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Data Recebido:</label>
              <input type="datetime-local" class="form-control" name="data_recebido" id="edit_data_recebido" required>
            </div>
          </div>
          
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Data Início:</label>
              <input type="datetime-local" class="form-control" name="data_inicio" id="edit_data_inicio" required>
            </div>
          </div>

          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Data Conclusão:</label>
              <input type="datetime-local" class="form-control" name="data_conclusao" id="edit_data_conclusao">
            </div>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-12">
            <div class="mb-3">
              <label class="form-label">Observação:</label>
              <textarea name="observacao" class="form-control" id="edit_observacao" rows="3"></textarea>
            </div>
          </div>
        </div>
                 
        <div class="text-end">
          <button type="submit" class="btn btn-success">Salvar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal de Exclusão -->
<div class="modal fade" id="modalExclusao" tabindex="-1" aria-labelledby="modalExclusaoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
          <h5 class="modal-title" id="modalExclusaoLabel">Confirma a Exclusão da Análise?</h5>
      </div>
      <div class="modal-body">
        <form action="deletar_conversao.php" method="POST">
          <!-- Campo oculto para armazenar o ID da análise -->
          <input type="hidden" id="id_excluir" name="id_excluir">
          <div class="text-end">
            <button type="submit" class="btn btn-success">Sim</button>
            <button type="button" class="btn btn-danger" data-bs-dismiss="modal" aria-label="Close">Não</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS (para modal etc.) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
/*******************************************************
 * Renderizar Gráfico com Chart.js
 *******************************************************/
let labelsMes = <?= json_encode($labelsMes); ?>;
let chartDatasets = <?= json_encode($chartDatasets); ?>;

let ctx = document.getElementById('chartBarras').getContext('2d');
let chartBarras = new Chart(ctx, {
  type: 'bar',
  data: {
    labels: labelsMes,
    datasets: chartDatasets
  },
  options: {
    responsive: true,
    scales: {
      x: {
        title: { display: true, text: 'Mês (ano-mês)' }
      },
      y: {
        beginAtZero: true,
        title: { display: true, text: 'Quantidade' }
      }
    }
  }
});
</script>

<script>
    // Mostra modal de cadastro
    function abrirModalCadastro() {
      $("#modalCadastro").modal('show');
    }

    // Mostra modal de edição
    function abrirModalEdicao(
      id, contato, serial, retrabalho,
      sistemaID, prazoEntrega, statusID,
      dataRecebido, dataInicio, dataConclusao,
      analistaID, observacao
    ) {
      // Preenche campos do modal Edição
      $("#edit_id").val(id);
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
    // AJAX: Salvar Edição
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

    // Função para preencher o modal de exclusão
    function excluirAnalise(id) {
        document.getElementById("id_excluir").value = id;
    }
  </script>
</body>
</html>
