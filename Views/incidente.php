<?php
include '../Config/Database.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ======== TOTALIZADORES E DADOS PARA O GRÁFICO ========

// Total por sistema
$systemTotalsSql = "SELECT sistema, COUNT(*) as total FROM tb_incidentes GROUP BY sistema";
$systemTotals = $conn->query($systemTotalsSql);

// Total por gravidade
$severityTotalsSql = "SELECT gravidade, COUNT(*) as total FROM tb_incidentes GROUP BY gravidade";
$severityTotals = $conn->query($severityTotalsSql);

// Dados mensais por gravidade para o gráfico
$sqlMonthly = "
    SELECT MONTH(hora_inicio) AS mes, gravidade, COUNT(*) AS total
    FROM tb_incidentes
    WHERE YEAR(hora_inicio) = YEAR(CURDATE())
    GROUP BY mes, gravidade
    ORDER BY mes
";
$monthlyResult = $conn->query($sqlMonthly);

// Inicializa arrays para os 12 meses (1..12)
$dataModerado   = array_fill(1, 12, 0);
$dataGrave      = array_fill(1, 12, 0);
$dataGravissimo = array_fill(1, 12, 0);

while($row = $monthlyResult->fetch_assoc()){
    $mes = (int)$row['mes'];
    if($row['gravidade'] == 'Moderado'){
        $dataModerado[$mes] = (int)$row['total'];
    } elseif($row['gravidade'] == 'Grave'){
        $dataGrave[$mes] = (int)$row['total'];
    } elseif($row['gravidade'] == 'Gravissimo'){
        $dataGravissimo[$mes] = (int)$row['total'];
    }
}
$labels = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

// ======== CONSULTAS PARA AS TABELAS DE INCIDENTES ========

// Incidentes Desktop (sistema ClippPRO)
$sqlDesktop = "SELECT * FROM tb_incidentes WHERE sistema = 'ClippPRO' ORDER BY hora_inicio DESC";
$resultDesktop = $conn->query($sqlDesktop);

// Incidentes Web (sistemas: ZWEB, Clipp360, ClippFácil, Conversor)
$sqlWeb = "
    SELECT * 
    FROM tb_incidentes 
    WHERE sistema IN ('ZWEB','Clipp360','ClippFácil','Conversor') 
    ORDER BY hora_inicio DESC
";
$resultWeb = $conn->query($sqlWeb);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Incidentes Registrados</title>
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    /* Badges para a coluna de gravidade */
    .badge-moderado {
      background-color: #ADD8E6; /* Azul claro */
      color: #000;
    }
    .badge-grave {
      background-color: #FFFF00; /* Amarelo */
      color: #000;
    }
    .badge-gravissimo {
      background-color: #FF0000; /* Vermelho */
      color: #FFF;
    }
  </style>
</head>
<body>
<div class="container my-5">
  <h2 class="mb-4">Incidentes Registrados</h2>

  <!-- Alerta de sucesso -->
  <?php if(isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
    <div class="alert alert-success" role="alert">
      Incidente registrado com sucesso!
    </div>
  <?php endif; ?>

  <!-- PAINEL COM TOTALIZADORES E GRÁFICO (altura fixa) -->
  <div class="row mb-4 align-items-stretch" style="height: 250px;">
    <!-- Coluna do Gráfico (8 colunas) -->
    <div class="col-md-8 h-100">
      <div class="card h-100" style="overflow: auto;">
        <div class="card-header bg-light">
          <strong>Incidentes por Mês (Ano Atual)</strong>
        </div>
        <!-- O card-body também pode ter overflow: auto se necessário -->
        <div class="card-body d-flex justify-content-center align-items-center">
          <!-- Ajuste max-height conforme desejar -->
          <canvas id="chartIncidents" style="max-height: 150px; width: 100%;"></canvas>
        </div>
      </div>
    </div>
    <!-- Coluna dos Totalizadores (4 colunas) -->
    <div class="col-md-4 d-flex flex-column h-100">
      <!-- Card: Total por Sistema -->
      <div class="card flex-fill mb-2" style="overflow: auto;">
        <div class="card-header bg-light">
          <strong>Total por Sistema</strong>
        </div>
        <div class="card-body">
          <?php if($systemTotals && $systemTotals->num_rows > 0): ?>
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Sistema</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php while($row = $systemTotals->fetch_assoc()): ?>
                  <tr>
                    <td><?= $row['sistema'] ?></td>
                    <td><?= $row['total'] ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="text-muted">Nenhum incidente cadastrado.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Card: Total por Gravidade -->
      <div class="card flex-fill" style="overflow: auto;">
        <div class="card-header bg-light">
          <strong>Total por Gravidade</strong>
        </div>
        <div class="card-body">
          <?php if($severityTotals && $severityTotals->num_rows > 0): ?>
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Gravidade</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php while($row = $severityTotals->fetch_assoc()): ?>
                  <tr>
                    <td><?= $row['gravidade'] ?></td>
                    <td><?= $row['total'] ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="text-muted">Nenhum incidente cadastrado.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- BOTÃO PARA ABRIR O MODAL DE REGISTRO -->
  <div class="mb-4">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastro">
      Registrar Incidente
    </button>
  </div>

  <!-- MODAL DE CADASTRO -->
  <div class="modal fade" id="modalCadastro" tabindex="-1" aria-labelledby="modalCadastroLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form action="processa_incidente.php" method="post">
          <div class="modal-header">
            <h5 class="modal-title" id="modalCadastroLabel">Cadastrar Incidente</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <!-- SISTEMA -->
            <div class="mb-3">
              <label for="sistema" class="form-label">Sistema</label>
              <select class="form-select" id="sistema" name="sistema" required>
                <option value="">Selecione o sistema</option>
                <option value="ClippPRO">ClippPRO (Desktop)</option>
                <option value="ZWEB">ZWEB (Web)</option>
                <option value="Clipp360">Clipp360 (Web)</option>
                <option value="ClippFácil">ClippFácil (Web)</option>
                <option value="Conversor">Conversor (Web)</option>
              </select>
            </div>
            <!-- GRAVIDADE -->
            <div class="mb-3">
              <label for="gravidade" class="form-label">Gravidade</label>
              <select class="form-select" id="gravidade" name="gravidade" required>
                <option value="">Selecione a gravidade</option>
                <option value="Moderado">Moderado</option>
                <option value="Grave">Grave</option>
                <option value="Gravissimo">Gravissimo</option>
              </select>
            </div>
            <!-- PROBLEMA -->
            <div class="mb-3">
              <label for="problema" class="form-label">Descrição do Problema</label>
              <textarea class="form-control" id="problema" name="problema" rows="3" required></textarea>
            </div>
            <!-- HORA INÍCIO -->
            <div class="mb-3">
              <label for="hora_inicio" class="form-label">Horário de Início</label>
              <input type="datetime-local" class="form-control" id="hora_inicio" name="hora_inicio" required>
            </div>
            <!-- HORA FIM -->
            <div class="mb-3">
              <label for="hora_fim" class="form-label">Horário de Término</label>
              <input type="datetime-local" class="form-control" id="hora_fim" name="hora_fim" required>
            </div>
            <!-- TEMPO TOTAL -->
            <div class="mb-3">
              <label for="tempo_total" class="form-label">Tempo Total (calculado)</label>
              <input type="text" class="form-control" id="tempo_total" name="tempo_total" readonly>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            <button type="submit" class="btn btn-primary">Gravar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- TABELAS DE INCIDENTES -->
  <div class="row">
    <!-- Incidentes Desktop -->
    <div class="col-md-6 mb-4">
      <div class="card">
        <div class="card-header bg-light">
          <strong>Incidentes Desktop</strong>
        </div>
        <div class="card-body">
          <?php if($resultDesktop && $resultDesktop->num_rows > 0): ?>
            <div class="table-responsive">
              <table class="table table-bordered align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Sistema</th>
                    <th>Gravidade</th>
                    <th>Problema</th>
                    <th>Hora Início</th>
                    <th>Hora Término</th>
                    <th>Tempo Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while($row = $resultDesktop->fetch_assoc()):
                    $gravidadeClass = '';
                    if($row['gravidade'] == 'Moderado'){
                      $gravidadeClass = 'badge-moderado';
                    } elseif($row['gravidade'] == 'Grave'){
                      $gravidadeClass = 'badge-grave';
                    } elseif($row['gravidade'] == 'Gravissimo'){
                      $gravidadeClass = 'badge-gravissimo';
                    }
                  ?>
                    <tr>
                      <td><?= $row['sistema'] ?></td>
                      <td>
                        <span class="badge <?= $gravidadeClass ?>">
                          <?= $row['gravidade'] ?>
                        </span>
                      </td>
                      <td><?= $row['problema'] ?></td>
                      <td><?= date('d/m/Y H:i:s', strtotime($row['hora_inicio'])) ?></td>
                      <td><?= date('d/m/Y H:i:s', strtotime($row['hora_fim'])) ?></td>
                      <td><?= $row['tempo_total'] ?></td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-muted">Nenhum incidente desktop registrado.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <!-- Incidentes Web -->
    <div class="col-md-6 mb-4">
      <div class="card">
        <div class="card-header bg-light">
          <strong>Incidentes Web</strong>
        </div>
        <div class="card-body">
          <?php if($resultWeb && $resultWeb->num_rows > 0): ?>
            <div class="table-responsive">
              <table class="table table-bordered align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Sistema</th>
                    <th>Gravidade</th>
                    <th>Problema</th>
                    <th>Hora Início</th>
                    <th>Hora Término</th>
                    <th>Tempo Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while($row = $resultWeb->fetch_assoc()):
                    $gravidadeClass = '';
                    if($row['gravidade'] == 'Moderado'){
                      $gravidadeClass = 'badge-moderado';
                    } elseif($row['gravidade'] == 'Grave'){
                      $gravidadeClass = 'badge-grave';
                    } elseif($row['gravidade'] == 'Gravissimo'){
                      $gravidadeClass = 'badge-gravissimo';
                    }
                  ?>
                    <tr>
                      <td><?= $row['sistema'] ?></td>
                      <td>
                        <span class="badge <?= $gravidadeClass ?>">
                          <?= $row['gravidade'] ?>
                        </span>
                      </td>
                      <td><?= $row['problema'] ?></td>
                      <td><?= date('d/m/Y H:i:s', strtotime($row['hora_inicio'])) ?></td>
                      <td><?= date('d/m/Y H:i:s', strtotime($row['hora_fim'])) ?></td>
                      <td><?= $row['tempo_total'] ?></td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-muted">Nenhum incidente web registrado.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Chart.js para o gráfico -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  // Dados para o gráfico (três conjuntos: Moderado, Grave e Gravissimo)
  const chartLabels = <?= json_encode($labels) ?>;
  const dataModerado = <?= json_encode(array_values($dataModerado)) ?>;
  const dataGrave = <?= json_encode(array_values($dataGrave)) ?>;
  const dataGravissimo = <?= json_encode(array_values($dataGravissimo)) ?>;
  
  const ctx = document.getElementById('chartIncidents').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: chartLabels,
      datasets: [
        {
          label: 'Moderado',
          data: dataModerado,
          backgroundColor: 'rgba(173,216,230,0.6)', // azul claro
          borderColor: 'rgba(173,216,230,1)',
          borderWidth: 1
        },
        {
          label: 'Grave',
          data: dataGrave,
          backgroundColor: 'rgba(255,255,0,0.6)', // amarelo
          borderColor: 'rgba(255,255,0,1)',
          borderWidth: 1
        },
        {
          label: 'Gravissimo',
          data: dataGravissimo,
          backgroundColor: 'rgba(255,0,0,0.6)', // vermelho
          borderColor: 'rgba(255,0,0,1)',
          borderWidth: 1
        }
      ]
    },
    options: {
      scales: {
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: 'Quantidade de Incidentes'
          }
        },
        x: {
          title: {
            display: true,
            text: 'Meses'
          }
        }
      }
    }
  });
  
  // Cálculo do Tempo Total no modal
  function calcularTempoTotal() {
    const inicio = document.getElementById('hora_inicio').value;
    const fim = document.getElementById('hora_fim').value;
    if (inicio && fim) {
      const dataInicio = new Date(inicio);
      const dataFim = new Date(fim);
      const diffMs = dataFim - dataInicio;
      if (diffMs < 0) {
        document.getElementById('tempo_total').value = 'Horário inválido';
        return;
      }
      let diffSegundos = Math.floor(diffMs / 1000);
      const horas = Math.floor(diffSegundos / 3600);
      diffSegundos %= 3600;
      const minutos = Math.floor(diffSegundos / 60);
      const segundos = diffSegundos % 60;
      document.getElementById('tempo_total').value =
        horas.toString().padStart(2, '0') + ':' +
        minutos.toString().padStart(2, '0') + ':' +
        segundos.toString().padStart(2, '0');
    }
  }
  document.getElementById('hora_inicio').addEventListener('change', calcularTempoTotal);
  document.getElementById('hora_fim').addEventListener('change', calcularTempoTotal);
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>
