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
 * 7) 
 * Dividir a listagem em duas:
 *  - TABELA 1: status = 'Em fila'
 *  - TABELA 2: status != 'Em fila'
 * Precisamos saber qual ID ou descricao corresponde a Em fila.
 * Aqui, assumimos st.descricao = 'Em fila'.
 ****************************************************************/
// Tabela da esquerda: status = 'Em fila'
$sqlFila = "
  SELECT c.id,
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
         c.email_cliente,
         c.retrabalho,
         c.observacao
    FROM TB_CONVERSOES c
    JOIN TB_SISTEMA_CONVER s  ON c.sistema_id  = s.id
    JOIN TB_STATUS_CONVER st  ON c.status_id   = st.id
    JOIN TB_ANALISTA_CONVER a ON c.analista_id = a.id
    $where
      AND st.descricao = 'Em fila'
   ORDER BY c.data_recebido DESC
";
$resFila = $conn->query($sqlFila);

// Tabela da direita: status != 'Em fila'
$sqlOutros = "
  SELECT c.id,
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
         c.email_cliente,
         c.retrabalho,
         c.observacao
    FROM TB_CONVERSOES c
    JOIN TB_SISTEMA_CONVER s  ON c.sistema_id  = s.id
    JOIN TB_STATUS_CONVER st  ON c.status_id   = st.id
    JOIN TB_ANALISTA_CONVER a ON c.analista_id = a.id
    $where
      AND st.descricao <> 'Em fila'
   ORDER BY c.data_recebido DESC
";
$resOutros = $conn->query($sqlOutros);

/****************************************************************
 * 8) Carregar listas p/ selects
 ****************************************************************/
$sistemas  = $conn->query("SELECT * FROM TB_SISTEMA_CONVER ORDER BY nome");
$status    = $conn->query("SELECT * FROM TB_STATUS_CONVER ORDER BY descricao");
$analistas = $conn->query("SELECT * FROM TB_ANALISTA_CONVER ORDER BY nome");
// Para o filtro:
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
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
  <link rel="stylesheet" href="../Public/conversao.css"> <!-- Ajuste o caminho -->

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

  <!-- ROW: Grafico + Totalizadores Status/Sistema -->
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
  </div><!-- row -->

  <!-- TOTAlIZADORES GERAIS -->
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

  <!-- DUAS TABELAS: ESQUERDA = Fila, DIREITA = Outras -->
  <div class="row">
    <!-- TABELA 1: Em fila -->
    <div class="col-md-6 mb-3">
      <div class="card">
        <div class="card-header bg-warning text-dark">
          <strong>Conversões em Fila</strong> <!-- status='Em fila' -->
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0">
              <thead class="table-light">
                <tr>
                  <th>Contato</th>
                  <th>Serial/CNPJ</th>
                  <th>Sistema</th>
                  <th>Prazo</th>
                  <th>Recebido</th>
                  <th>Início</th>
                  <th>Conclusão</th>
                  <th>Analista</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($rowF = $resFila->fetch_assoc()): ?>
                <tr>
                  <td><?= $rowF['contato']; ?></td>
                  <td><?= $rowF['serial']; ?></td>
                  <td><?= $rowF['sistema_nome']; ?></td>
                  <td><?= $rowF['prazo_entrega']; ?></td>
                  <td><?= $rowF['data_recebido']; ?></td>
                  <td><?= $rowF['data_inicio']; ?></td>
                  <td><?= $rowF['data_conclusao']; ?></td>
                  <td><?= $rowF['analista_nome']; ?></td>
                  <td>
                    <button class="btn btn-sm btn-secondary"
                      onclick="abrirModalEdicao(
                        '<?= $rowF['id'] ?>',
                        '<?= $rowF['email_cliente'] ?>',
                        '<?= $rowF['contato'] ?>',
                        '<?= $rowF['serial'] ?>',
                        '<?= $rowF['retrabalho'] ?>',
                        '<?= $rowF['sistema_id'] ?>',
                        '<?= $rowF['prazo_entrega'] ?>',
                        '<?= $rowF['status_id'] ?>',
                        '<?= $rowF['data_recebido'] ?>',
                        '<?= $rowF['data_inicio'] ?>',
                        '<?= $rowF['data_conclusao'] ?>',
                        '<?= $rowF['analista_id'] ?>',
                        '<?= addslashes($rowF['observacao']) ?>'
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
    </div><!-- col-md-6 -->

    <!-- TABELA 2: Demais status (<> Em fila) -->
    <div class="col-md-6 mb-3">
      <div class="card">
        <div class="card-header bg-dark text-white">
          <strong>Outras Conversões</strong>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0">
              <thead class="table-light">
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
                <?php while ($rowO = $resOutros->fetch_assoc()): ?>
                <tr>
                  <td><?= $rowO['contato']; ?></td>
                  <td><?= $rowO['serial']; ?></td>
                  <td><?= $rowO['sistema_nome']; ?></td>
                  <td><?= $rowO['prazo_entrega']; ?></td>
                  <td><?= $rowO['status_nome']; ?></td>
                  <td><?= $rowO['data_recebido']; ?></td>
                  <td><?= $rowO['data_inicio']; ?></td>
                  <td><?= $rowO['data_conclusao']; ?></td>
                  <td><?= $rowO['analista_nome']; ?></td>
                  <td>
                    <button class="btn btn-sm btn-warning"
                      onclick="abrirModalEdicao(
                        '<?= $rowO['id'] ?>',
                        '<?= $rowO['email_cliente'] ?>',
                        '<?= $rowO['contato'] ?>',
                        '<?= $rowO['serial'] ?>',
                        '<?= $rowO['retrabalho'] ?>',
                        '<?= $rowO['sistema_id'] ?>',
                        '<?= $rowO['prazo_entrega'] ?>',
                        '<?= $rowO['status_id'] ?>',
                        '<?= $rowO['data_recebido'] ?>',
                        '<?= $rowO['data_inicio'] ?>',
                        '<?= $rowO['data_conclusao'] ?>',
                        '<?= $rowO['analista_id'] ?>',
                        '<?= addslashes($rowO['observacao']) ?>'
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
    </div><!-- col-md-6 -->
  </div><!-- row das duas tabelas -->
</div><!-- container -->

<!-- MODAL CADASTRO (id=modalCadastro) -->
<div class="modal fade" id="modalCadastro" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content p-4">
      <h4 class="modal-title mb-3">Cadastrar Conversão</h4>
      <form id="formCadastro">
        <input type="hidden" name="id">
        <!-- Campos ... [igual antes] -->
        <div class="mb-3">
          <label class="form-label">E-mail do Cliente:</label>
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
        <div class="mb-3">
          <label class="form-label">Data Recebido:</label>
          <input type="datetime-local" class="form-control" name="data_recebido" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Data Início:</label>
          <input type="datetime-local" class="form-control" name="data_inicio" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Data Conclusão:</label>
          <input type="datetime-local" class="form-control" name="data_conclusao">
        </div>
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

<!-- MODAL EDICAO (id=modalEdicao) -->
<div class="modal fade" id="modalEdicao" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content p-4">
      <h4 class="modal-title mb-3">Editar Conversão</h4>
      <form id="formEdicao">
        <input type="hidden" id="edit_id" name="id">
        <!-- Campos ... [igual antes] -->
        <div class="mb-3">
          <label class="form-label">E-mail do Cliente:</label>
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

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Chart.js
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
      x: { title: { display: true, text: 'Mês (ano-mês)' } },
      y: { beginAtZero: true, title: { display: true, text: 'Quantidade' } }
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
