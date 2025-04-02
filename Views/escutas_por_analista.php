<?php
include '../Config/Database.php';
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verifica se o usuário é Admin
$usuario_id = $_SESSION['usuario_id'] ?? null;
$cargo = $_SESSION['cargo'] ?? '';
if ($cargo !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

// Recebe o id do usuário (analista) via GET
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if ($user_id <= 0) {
    header("Location: escutas.php");
    exit;
}

// ------------------- Filtro por Período -------------------
$dataInicio = $_GET['data_inicio'] ?? '';
$dataFim    = $_GET['data_fim'] ?? '';
$dataFilterCondition = "";

// Monta condição do WHERE com base no período
if (!empty($dataInicio) && !empty($dataFim)) {
    $dataFilterCondition = " AND DATE(e.data_escuta) BETWEEN '".$conn->real_escape_string($dataInicio)."' 
                                                     AND '".$conn->real_escape_string($dataFim)."' ";
} else if (!empty($dataInicio)) {
    $dataFilterCondition = " AND DATE(e.data_escuta) >= '".$conn->real_escape_string($dataInicio)."' ";
} else if (!empty($dataFim)) {
    $dataFilterCondition = " AND DATE(e.data_escuta) <= '".$conn->real_escape_string($dataFim)."' ";
}

// ------------------- Nome do Analista -------------------
$stmt = $conn->prepare("SELECT nome FROM TB_USUARIO WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$analista = $result->fetch_assoc();
$stmt->close();
$usuario_nome = $analista ? $analista['nome'] : "Analista Desconhecido";

// Recupera o histórico de escutas para esse usuário (analista)
$query = "
          SELECT 
          e.*, 
          u.nome AS usuario_nome, 
          a.nome AS admin_nome,
          CASE
              WHEN c.descricao IS NOT NULL THEN c.descricao
              ELSE 'Sem Classificação'
          END AS classificacao
      FROM TB_ESCUTAS e
      JOIN TB_USUARIO u ON e.user_id = u.id 
      JOIN TB_USUARIO a ON e.admin_id = a.id
      LEFT JOIN TB_CLASSIFICACAO c ON e.classi_id = c.id 
      WHERE e.user_id = $user_id
      $dataFilterCondition
      ORDER BY e.data_escuta DESC
";
$resultEsc = $conn->query($query);
$escutas = [];
if ($resultEsc) {
    while ($row = $resultEsc->fetch_assoc()) {
        $escutas[] = $row;
    }
    $resultEsc->free();
}

// Recupera os usuários (para o select do modal de edição)
$users = [];
$queryUsers = "SELECT 
                u.id, 
                u.nome 
              FROM TB_USUARIO u
              WHERE (u.cargo = 'User' OR u.id IN (17, 18))
              AND u.id NOT IN (8)
              ORDER BY u.nome";
$resultUsers = $conn->query($queryUsers);
if ($resultUsers) {
    while ($row = $resultUsers->fetch_assoc()) {
        $users[] = $row;
    }
    $resultUsers->free();
}

// Recupera as classificações (para o select do modal de edição)
$classis = [];
$queryClassi = "SELECT id, descricao FROM TB_CLASSIFICACAO where id <> 1";
$resultClassi = $conn->query($queryClassi);
if ($resultClassi) {
    while ($row = $resultClassi->fetch_assoc()) {
        $classis[] = $row;
    }
    $resultClassi->free();
}

/* ------------------------------
   Totalizador: Classificações utilizadas
   Para o analista atual, conta quantas vezes cada classificação foi usada
------------------------------ */
$stmtClass = $conn->prepare("
    SELECT c.descricao, COUNT(e.id) AS total 
    FROM TB_ESCUTAS e 
    JOIN TB_CLASSIFICACAO c ON e.classi_id = c.id 
    WHERE e.user_id = ?
    $dataFilterCondition
    GROUP BY c.id 
    ORDER BY c.descricao
");
$stmtClass->bind_param("i", $user_id);
$stmtClass->execute();
$resultClass = $stmtClass->get_result();
$classificacaoTotalizadores = [];
while ($row = $resultClass->fetch_assoc()) {
    $classificacaoTotalizadores[] = $row;
}
$stmtClass->close();

/* ------------------------------
   Totalizador: Percentual de Avaliações (Positivas vs Negativas)
------------------------------ */
$stmtEval = $conn->prepare("
    SELECT 
        SUM(CASE WHEN P_N = 'Sim' THEN 1 ELSE 0 END) AS pos_count,
        SUM(CASE WHEN P_N = 'Nao' THEN 1 ELSE 0 END) AS neg_count,
        COUNT(*) AS total_count
    FROM TB_ESCUTAS e
    WHERE e.user_id = ?
    $dataFilterCondition
");
$stmtEval->bind_param("i", $user_id);
$stmtEval->execute();
$resultEval = $stmtEval->get_result();
$evaluation = $resultEval->fetch_assoc();
$stmtEval->close();

$pos_count = (int)$evaluation['pos_count'];
$neg_count = (int)$evaluation['neg_count'];
$total_count = (int)$evaluation['total_count'];
$percent_positive = $total_count > 0 ? ($pos_count / $total_count) * 100 : 0;
$percent_negative = $total_count > 0 ? ($neg_count / $total_count) * 100 : 0;
/* ------------------------------
   Totalizador: Percentual de Solicitação Avaliações (SIM x NAO)
------------------------------ */
$stmtEvalAva = $conn->prepare("
    SELECT 
        SUM(CASE WHEN solicitaAva = 'Sim' THEN 1 ELSE 0 END) AS pos_count_ava,
        SUM(CASE WHEN solicitaAva = 'Nao' THEN 1 ELSE 0 END) AS neg_count_ava,
        COUNT(*) AS total_count_ava
    FROM TB_ESCUTAS e
    WHERE e.user_id = ?
    $dataFilterCondition
");
$stmtEvalAva->bind_param("i", $user_id);
$stmtEvalAva->execute();
$resultEvalAva = $stmtEvalAva->get_result();
$evaluationAva = $resultEvalAva->fetch_assoc();
$stmtEvalAva->close();

$pos_count_ava = (int)$evaluationAva['pos_count_ava'];
$neg_count_ava = (int)$evaluationAva['neg_count_ava'];
$total_count_ava = (int)$evaluationAva['total_count_ava'];
$percent_positive_ava = $total_count_ava > 0 ? ($pos_count_ava / $total_count_ava) * 100 : 0;
$percent_negative_ava = $total_count_ava > 0 ? ($neg_count_ava / $total_count_ava) * 100 : 0;

// ------------------- Gráfico Mensal (Positivas x Negativas) -------------------
/*
   Agrupamos por ano-mês (YYYY-MM) e contamos quantas escutas foram 'Sim' e quantas foram 'Nao'
   Exemplo de query:
*/
$stmtGrafico = $conn->prepare("
    SELECT 
        DATE_FORMAT(e.data_escuta, '%Y-%m') AS mes,
        SUM(CASE WHEN e.P_N = 'Sim' THEN 1 ELSE 0 END) AS totalPos,
        SUM(CASE WHEN e.P_N = 'Nao' THEN 1 ELSE 0 END) AS totalNeg
    FROM TB_ESCUTAS e
    WHERE e.user_id = ?
    $dataFilterCondition
    GROUP BY DATE_FORMAT(e.data_escuta, '%Y-%m')
    ORDER BY mes
");
$stmtGrafico->bind_param("i", $user_id);
$stmtGrafico->execute();
$resultGraf = $stmtGrafico->get_result();

$meses = [];
$positivosMensais = [];
$negativosMensais = [];

while ($row = $resultGraf->fetch_assoc()) {
    $meses[] = $row['mes'];            // Ex.: 2023-08
    $positivosMensais[] = (int)$row['totalPos'];
    $negativosMensais[] = (int)$row['totalNeg'];
}
$stmtGrafico->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Escutas de <?php echo $usuario_nome; ?></title>
  <!-- Arquivo CSS personalizado -->
  <link href="../Public/escutas_por_analista.css" rel="stylesheet">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Ícones personalizados -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Chart.js (para o gráfico) -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  
  <link rel="icon" href="Public\Image\icone2.png" type="image/png">
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
  <div class="container d-flex justify-content-between align-items-center">
    <div class="dropdown">
      <button class="navbar-toggler" type="button" data-bs-toggle="dropdown">
        <span class="navbar-toggler-icon"></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-dark">
        <li><a class="dropdown-item" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversão</a></li>
        <li><a class="dropdown-item" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a></li>
        <li><a class="dropdown-item" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Painel</a></li>
        <li><a class="dropdown-item" href="dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a></li>
      </ul>
    </div>
    <span class="text-white">Escutas de <?php echo $usuario_nome; ?></span>
    <a href="escutas.php" class="btn btn-danger">
      <i class="fa-solid fa-arrow-left me-2"></i>Voltar
    </a>
  </div>
</nav>

<!-- Container do Toast no canto superior direito -->
<div class="toast-container">
    <div id="toastSucesso" class="toast">
        <div class="toast-body">
            <i class="fa-solid fa-check-circle"></i> <span id="toastMensagem"></span>
        </div>
    </div>
</div>

<!-- Script para exibir o toast -->
<script defer>
document.addEventListener("DOMContentLoaded", function () {
  const urlParams = new URLSearchParams(window.location.search);
  const success = urlParams.get("success");
  if (success) {
      let mensagem = "";
      switch (success) {
          case "3":
            mensagem = "Escuta editada com sucesso!";
            break;
          case "4":
            mensagem = "Escuta excluída com sucesso!";
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

<div class="container mt-4 ">
  <div class="row g-3 justify-content-center">
    <!-- Coluna 1: Classificações + Percentual -->
    <div class="col-md-3 d-flex flex-column">
      <!-- Card: Totalizador de Classificações -->
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Totalizador de Classificações</h5>
          <?php if(count($classificacaoTotalizadores) > 0): ?>
            <ul class="list-group scroll-container">
              <?php foreach($classificacaoTotalizadores as $item): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <?= htmlspecialchars($item['descricao']); ?>
                  <span class="badge bg-primary rounded-pill"><?= $item['total']; ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p>Nenhuma classificação utilizada neste período.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Card: Percentual de Avaliações -->
      <div class="card mt-3">
        <span data-bs-toggle="tooltip" data-bs-html="true" title="🟩 Sim <br>🟥 Não">
          <div class="card-body">
            <h5 class="card-title">Taxa de Avaliação Positiva</h5>
            <?php if($total_count > 0): ?>
              <div class="progress" style="height: 15px;">
                <div class="progress-bar bg-success" role="progressbar" 
                    style="width: <?= round($percent_positive); ?>%;" 
                    aria-valuenow="<?= round($percent_positive); ?>" aria-valuemin="0" aria-valuemax="100">
                  <?= round($percent_positive); ?>%
                </div>
                <div class="progress-bar bg-danger" role="progressbar" 
                    style="width: <?= round($percent_negative); ?>%;" 
                    aria-valuenow="<?= round($percent_negative); ?>" aria-valuemin="0" aria-valuemax="100">
                  <?= round($percent_negative); ?>%
                </div>
              </div>
            <?php else: ?>
              <p>Nenhuma avaliação registrada neste período.</p>
            <?php endif; ?>
          </div>
        </span>
      </div>
      <!-- Card: Percentual de Avaliações -->
      <div class="card mt-3">
        <span data-bs-toggle="tooltip" data-bs-html="true" title="🟩 Sim <br>🟥 Não">
          <div class="card-body">
            <h5 class="card-title">Taxa Solicitação de Avaliação</h5>
            <?php if($total_count_ava > 0): ?>
              <div class="progress" style="height: 15px;">
                <div class="progress-bar bg-success" role="progressbar" 
                    style="width: <?= round($percent_positive_ava); ?>%;" 
                    aria-valuenow="<?= round($percent_positive_ava); ?>" aria-valuemin="0" aria-valuemax="100">
                  <?= round($percent_positive_ava); ?>%
                </div>
                <div class="progress-bar bg-danger" role="progressbar" 
                    style="width: <?= round($percent_negative_ava); ?>%;" 
                    aria-valuenow="<?= round($percent_negative_ava); ?>" aria-valuemin="0" aria-valuemax="100">
                  <?= round($percent_negative_ava); ?>%
                </div>
              </div>
            <?php else: ?>
              <p>Nenhuma avaliação registrada neste período.</p>
            <?php endif; ?>
          </div>
        </span>
      </div>
    </div>
    <!-- Código para exibir e remover a mensagem/aviso -->
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl);
        });
      });
    </script>

    <!-- Coluna 2: Evolução Mensal (Gráfico) -->
    <div class="col-md-6 d-flex flex-column">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Evolução Mensal</h5>
          <?php if(count($meses) > 0): ?>
            <div style="position: relative; height: 300px;">
              <canvas id="chartMensal"></canvas>
            </div>
          <?php else: ?>
            <p>Nenhuma escuta registrada neste período.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Coluna 3: Filtro de Período -->
    <div class="col-md-2 d-flex flex-column">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Filtro de Período</h5>
          <form method="GET">
            <input type="hidden" name="user_id" value="<?= $user_id; ?>">
            <div class="mb-3">
              <label for="data_inicio" class="form-label">Data Início</label>
              <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($dataInicio); ?>">
            </div>
            <div class="mb-3">
              <label for="data_fim" class="form-label">Data Fim</label>
              <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= htmlspecialchars($dataFim); ?>">
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
              <a href="escutas_por_analista.php?user_id=<?= $user_id; ?>" class="btn btn-secondary btn-sm">Limpar</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div> <!-- fim row -->

  <!-- Histórico de Escutas (Tabela) -->
  <h3 class="mb-4 mt-4">Histórico de Escutas - <?php echo $usuario_nome; ?></h3>
  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
      <table class="table table-bordered tabelaEstilizada">
          <thead class="table-light align-items-center">
            <tr>
              <th width="7%">Data da Escuta</th>
              <th width="10%">Usuário</th>
              <th width="12%">Classificação</th>
              <th width="3%">Positivo</th>
              <th width="3%">Solic. Avaliação</th>
              <th width="17%">Transcrição</th>
              <th width="17%">Feedback</th>
              <th width="5%">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($escutas) > 0): ?>
              <?php foreach ($escutas as $escuta): ?>
                <tr>
                  <td><?php echo date('d/m/Y', strtotime($escuta['data_escuta'])); ?></td>
                  <td><?php echo $escuta['usuario_nome']; ?></td>
                  <td><?php echo $escuta['classificacao']; ?></td>
                  <td><?php echo $escuta['P_N']; ?></td>
                  <td><?php echo $escuta['solicitaAva']; ?></td>
                  <td class="sobrepor"><?php echo $escuta['transcricao']; ?></td>
                  <td class="sobrepor"><?php echo $escuta['feedback']; ?></td>
                  <td>
                    <!-- Botão Editar -->
                    <button class="btn btn-outline-primary btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#modalEditar"
                            onclick="preencherModalEditar(
                              <?php echo htmlspecialchars(json_encode($escuta['id']), ENT_QUOTES, 'UTF-8'); ?>,
                              <?php echo htmlspecialchars(json_encode($escuta['user_id']), ENT_QUOTES, 'UTF-8'); ?>,
                              <?php echo htmlspecialchars(json_encode($escuta['classi_id']), ENT_QUOTES, 'UTF-8'); ?>,
                              <?php echo htmlspecialchars(json_encode($escuta['P_N']), ENT_QUOTES, 'UTF-8'); ?>,
                              <?php echo htmlspecialchars(json_encode(date('Y-m-d', strtotime($escuta['data_escuta']))), ENT_QUOTES, 'UTF-8'); ?>,
                              <?php echo htmlspecialchars(json_encode($escuta['transcricao']), ENT_QUOTES, 'UTF-8'); ?>,
                              <?php echo htmlspecialchars(json_encode($escuta['feedback']), ENT_QUOTES, 'UTF-8'); ?>,
                              <?php echo htmlspecialchars(json_encode($escuta['solicitaAva']), ENT_QUOTES, 'UTF-8'); ?>
                            )">
                      <i class="fa-solid fa-pen"></i>
                    </button>
                    <!-- Botão Excluir -->
                    <button class="btn btn-outline-danger btn-sm" 
                            data-bs-toggle="modal" 
                            data-bs-target="#modalExcluir"
                            onclick="preencherModalExcluir('<?php echo $escuta['id']; ?>','<?php echo $escuta['user_id']; ?>')">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="7">Nenhuma escuta registrada para este analista.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal Editar Escuta -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content p-4">
      <h5 class="modal-title mb-3" id="modalEditarLabel">Editar Escuta</h5>
      <form method="POST" action="editar_escuta.php">
        <input type="hidden" name="id" id="edit_id">
        <div class="row mb-2">
          <div class="col-md-6 mb-3">
            <div class="mb-3">
              <label for="edit_user_id" class="form-label">Selecione o Usuário</label>
              <select name="user_id" id="edit_user_id" class="form-select" required>
                <option value="">Escolha o usuário</option>
                <?php foreach($users as $user): ?>
                  <option value="<?php echo $user['id']; ?>"><?php echo $user['nome']; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="mb-3">
              <label for="edit_cad_classi_id" class="form-label">Classificação</label>
              <select name="edit_classi_id" id="edit_cad_classi_id" class="form-select">
                <option value="1">Sem Classificação</option>
                <?php foreach($classis as $classi): ?>
                  <option value="<?php echo $classi['id']; ?>"><?php echo $classi['descricao']; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="row mb-2">
          <div class="col-md-4 mb-3">
            <div class="mb-3">
              <label for="edit_tipo_escuta" class="form-label">Escuta Positiva</label>
              <select name="edit_positivo" id="edit_tipo_escuta" class="form-select">
                <option value="">Selecione...</option>
                <option value="Sim">Sim</option>
                <option value="Nao">Nao</option>
              </select>
            </div>
          </div>
          <div class="col-md-4 mb-3">
            <label for="edit_solicita_ava" class="form-label">Solicitou Avaliação</label>
            <select name="edit_avaliacao" id="edit_solicita_ava" class="form-select" required>
              <option value="">Selecione...</option>
              <option value="Sim">Sim</option>
              <option value="Nao">Nao</option>
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <div class="mb-3">
              <label for="edit_data_escuta" class="form-label">Data da Escuta</label>
              <input type="date" name="data_escuta" id="edit_data_escuta" class="form-control" required>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label for="edit_transcricao" class="form-label">Transcrição da Ligação</label>
          <textarea name="transcricao" id="edit_transcricao" class="form-control" rows="4" required></textarea>
        </div>
        <div class="mb-3">
          <label for="edit_feedback" class="form-label">Feedback / Ajustes</label>
          <textarea name="feedback" id="edit_feedback" class="form-control" rows="2"></textarea>
        </div>
        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-primary">Salvar Alterações</button>
          <button type="button" class="btn btn-secondary ms-2" data-bs-dismiss="modal">Fechar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Excluir Escuta -->
<div class="modal fade" id="modalExcluir" tabindex="-1" aria-labelledby="modalExcluirLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content p-4">
      <h5 class="modal-title mb-3" id="modalExcluirLabel">Confirmar Exclusão</h5>
      <form method="POST" action="deletar_escuta.php">
        <input type="hidden" name="id" id="delete_id">
        <input type="hidden" name="user_id" id="delete_user_id">
        <p>Tem certeza que deseja excluir esta escuta?</p>
        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-danger">Sim, excluir</button>
          <button type="button" class="btn btn-secondary ms-2" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script para montar o gráfico Chart.js -->
<script>
  // Arrays com dados do PHP
  const meses = <?php echo json_encode($meses); ?>;               // ex: ["2023-01","2023-02"]
  const posMensal = <?php echo json_encode($positivosMensais); ?>; 
  const negMensal = <?php echo json_encode($negativosMensais); ?>;

  if (meses.length > 0) {
    const ctx = document.getElementById('chartMensal').getContext('2d');
    const myChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: meses, // rótulos no eixo X
        datasets: [
          {
            label: 'Positivas',
            data: posMensal,
            backgroundColor: 'rgba(40, 167, 69, 0.8)' // verde
          },
          {
            label: 'Negativas',
            data: negMensal,
            backgroundColor: 'rgba(220, 53, 69, 0.8)' // vermelho
          }
        ]
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            stepSize: 1
          }
        }
      }
    });
  }

  // Preenche Modal Editar
  function preencherModalEditar(id, user_id, classi_id, P_N, data_escuta, transcricao, feedback, solicitaAva) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_user_id').value = user_id;
    document.getElementById('edit_cad_classi_id').value = classi_id;
    document.getElementById('edit_tipo_escuta').value = P_N;
    document.getElementById('edit_data_escuta').value = data_escuta;
    document.getElementById('edit_transcricao').value = transcricao;
    document.getElementById('edit_feedback').value = feedback;
    document.getElementById('edit_solicita_ava').value = solicitaAva;
  }

  // Preenche Modal Excluir
  function preencherModalExcluir(id, user_id) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_user_id').value = user_id;
  }
</script>
</body>
</html>
