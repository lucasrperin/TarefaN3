<?php
include '../Config/Database.php';
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verifica se o usuário logado é Admin
$usuario_id = $_SESSION['usuario_id'];
$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';
if ($cargo !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

// ----------- Filtro de Período (Data Início e Data Fim) ------------
$dataInicio = "";
$dataFim = "";

if (isset($_GET['data_inicio']) && !empty($_GET['data_inicio'])) {
    $dataInicio = $_GET['data_inicio'];
}
if (isset($_GET['data_fim']) && !empty($_GET['data_fim'])) {
    $dataFim = $_GET['data_fim'];
}

// Cria uma condição de data que será utilizada em todas as consultas
if (empty($dataInicio) && empty($dataFim)) {
    // Sem filtro, usa mês/ano atual
    $dataCondition = "MONTH(e.data_escuta) = MONTH(CURRENT_DATE()) AND YEAR(e.data_escuta) = YEAR(CURRENT_DATE())";
} else {
    if (!empty($dataInicio) && empty($dataFim)) {
        $dataCondition = "DATE(e.data_escuta) >= '" . $conn->real_escape_string($dataInicio) . "'";
    } else if (empty($dataInicio) && !empty($dataFim)) {
        $dataCondition = "DATE(e.data_escuta) <= '" . $conn->real_escape_string($dataFim) . "'";
    } else { // ambos preenchidos
        $dataCondition = "DATE(e.data_escuta) BETWEEN '" . $conn->real_escape_string($dataInicio) . "' AND '" . $conn->real_escape_string($dataFim) . "'";
    }
}

$dataFilterCondition = " AND " . $dataCondition;

// -------------------------------------------------------------------
// Consulta os usuários (analistas) que possuem escutas registradas
$query = "SELECT DISTINCT e.user_id, u.nome AS usuario_nome 
          FROM TB_ESCUTAS e
          JOIN TB_USUARIO u ON e.user_id = u.id 
          WHERE (u.cargo = 'User' OR u.id IN (17, 18))
            AND u.id NOT IN (8)
            $dataFilterCondition
          ORDER BY u.nome";
$result = $conn->query($query);
$analistas = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $analistas[] = $row;
    }
    $result->free();
}

// Recupera os usuários com cargo "User" (para meta Geral)
$users = [];
$queryUsers = "SELECT 
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

// Recupera os usuários com cargo "User" (para modal de cadastro) que não possuem 5 análises
$userscadastro = [];
$queryUsersCad = "SELECT 
                    u.id,
                    u.nome,
                    (5 - COUNT(e.id)) AS faltantes
                  FROM TB_USUARIO u
                  LEFT JOIN TB_ESCUTAS e 
                    ON e.user_id = u.id 
                    AND ($dataCondition)
                  WHERE (u.cargo = 'User' OR u.id IN (17, 18))
                    AND u.id NOT IN (8)
                  GROUP BY u.id
                  HAVING faltantes > 0
                  ORDER BY u.nome";
$resultUsersCad = $conn->query($queryUsersCad);
if ($resultUsersCad) {
    while ($row = $resultUsersCad->fetch_assoc()) {
        $userscadastro[] = $row;
    }
    $resultUsersCad->free();
}

// Recupera as classificações (para modal de cadastro)
$classis = [];
$queryClassi = "SELECT id, descricao FROM TB_CLASSIFICACAO WHERE id <> 1";
$resultClassi = $conn->query($queryClassi);
if ($resultClassi) {
    while ($row = $resultClassi->fetch_assoc()) {
        $classis[] = $row;
    }
    $resultClassi->free();
}

// ----------------------------------------------------
// 1. Escutas por Analista (usuários com cargo 'User')
$sqlEscutasAnalista = "SELECT 
                        u.nome, 
                        COUNT(e.id) AS total
                      FROM TB_USUARIO u
                      JOIN TB_ESCUTAS e ON e.user_id = u.id
                      WHERE (u.cargo = 'User' OR u.id IN (17, 18))
                        AND u.id NOT IN (8)
                        $dataFilterCondition
                      GROUP BY u.id
                      ORDER BY u.nome";
$resAnalista = $conn->query($sqlEscutasAnalista);
$escutasAnalista = [];
if ($resAnalista) {
    while ($row = $resAnalista->fetch_assoc()) {
        $escutasAnalista[] = $row;
    }
}

// ----------------------------------------------------
// 2. Escutas por Supervisor (usuários com cargo 'Admin')
$sqlEscutasSupervisor = "
    SELECT u.nome, COUNT(e.id) AS total
    FROM TB_USUARIO u
    JOIN TB_ESCUTAS e ON e.admin_id = u.id
    WHERE u.cargo = 'Admin'
    $dataFilterCondition
    GROUP BY u.id
    ORDER BY u.nome
";
$resSupervisor = $conn->query($sqlEscutasSupervisor);
$escutasSupervisor = [];
if ($resSupervisor) {
    while ($row = $resSupervisor->fetch_assoc()) {
        $escutasSupervisor[] = $row;
    }
}

// ----------------------------------------------------
// 3. Escutas Faltantes (para cada analista 'User', meta de 5 escutas)
$sqlEscutasFaltantes = "SELECT 
    u.nome,
    COUNT(e.id) AS totalEscutas,
    (5 - COUNT(e.id)) AS faltantes
FROM TB_USUARIO u
LEFT JOIN TB_ESCUTAS e 
  ON e.user_id = u.id 
  AND ($dataCondition)
WHERE (u.cargo = 'User' OR u.id IN (17, 18))
  AND u.id NOT IN (8)
GROUP BY u.id
ORDER BY u.nome";
$resFaltantes = $conn->query($sqlEscutasFaltantes);
$escutasFaltantes = [];
if ($resFaltantes) {
    while ($row = $resFaltantes->fetch_assoc()) {
        $escutasFaltantes[] = $row;
    }
}

// ----------------------------------------------------
// 4. Meta Geral de Escutas
$totalAnalistas = count($users);
$metaGeral = $totalAnalistas * 5;
$sqlTotalEscutas = "SELECT COUNT(e.id) as total 
                    FROM TB_ESCUTAS e
                    JOIN TB_USUARIO u ON e.user_id = u.id
                    WHERE (u.cargo = 'User' OR u.id IN (17, 18))
                      AND u.id NOT IN (8)
                      $dataFilterCondition";
$resultTotal = $conn->query($sqlTotalEscutas);
$totalEscutasRealizadas = 0;
if ($resultTotal) {
    $rowTotal = $resultTotal->fetch_assoc();
    $totalEscutasRealizadas = $rowTotal['total'];
}
$percentMetaGeral = ($metaGeral > 0) ? ($totalEscutasRealizadas * 100 / $metaGeral) : 0;
if ($percentMetaGeral > 100) {
    $percentMetaGeral = 100;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tarefas N3</title>
    <!-- CSS personalizado -->
    <link href="../Public/escutas.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Ícones Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="icon" href="Public/Image/icone2.png" type="image/png">
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
  <div class="container d-flex justify-content-between align-items-center">
    <div class="dropdown">
      <button class="navbar-toggler" type="button" data-bs-toggle="dropdown">
        <span class="navbar-toggler-icon"></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-dark">
        <li><a class="dropdown-item" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a></li>
        <li><a class="dropdown-item" href="folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a></li>
        <li><a class="dropdown-item" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a></li>
        <li><a class="dropdown-item" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-1"></i>Indicações</a></li>
        <li><a class="dropdown-item" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a></li>
        <li><a class="dropdown-item" href="dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a></li>
        <li><a class="dropdown-item" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a></li>
      </ul>
    </div>
    <span class="text-white">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
    <a href="menu.php" class="btn btn-danger">
      <i class="fa-solid fa-arrow-left me-2" style="font-size: 0.8em;"></i>Voltar
    </a>
  </div>
</nav>

<!-- Container do Toast -->
<div class="toast-container">
  <div id="toastSucesso" class="toast">
      <div class="toast-body">
        <i class="fa-solid fa-check-circle"></i> <span id="toastMensagem"></span>
      </div>
  </div>
</div>

<!-- Script para exibir o Toast -->
<script defer>
document.addEventListener("DOMContentLoaded", function () {
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get("success");

    if (success) {
        let mensagem = "";
        switch (success) {
            case "1":
                mensagem = "Usuário cadastrado com sucesso!";
                break;
            case "2":
                mensagem = "Escuta cadastrada com sucesso!";
                break;
            case "5":
                mensagem = "Classificação cadastrada com sucesso!";
                break;
            case "6":
                mensagem = "Usuário já cadastrado!";
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

<!-- Container principal para o layout -->
<div class="container mt-4">
  <!-- .row com align-items-stretch e g-3 para espaçamento -->
  <div class="row justify-content-center align-items-stretch g-3">
    <!-- Coluna 1: Escutas Faltantes -->
    <div class="col-md-3 d-flex flex-column">
      <div class="card flex-fill">
        <div class="card-body">
          <h5 class="card-title">Escutas Faltantes</h5>
          <?php if (count($escutasFaltantes) > 0): ?>
            <ul class="list-group scroll-container">
            <?php foreach($escutasFaltantes as $analista): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <span class="analista-name"><?= htmlspecialchars($analista['nome']); ?></span>
                <?php if ($analista['faltantes'] <= 0): ?>
                  <span class="analista-total badge bg-success rounded-pill">
                    <i class="fa-solid fa-check-circle"></i>
                  </span>
                <?php else: ?>
                  <span class="analista-total badge bg-danger rounded-pill">
                    <?= $analista['faltantes']; ?>
                  </span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>

            </ul>
          <?php else: ?>
            <p>Nenhum registro exibido</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Coluna 2: Meta Geral e Escutas por Supervisor -->
    <div class="col-md-3 d-flex flex-column">
      <!-- Card: Meta Geral (ocupa o espaço vertical restante) -->
      <div class="card flex-fill mb-3">
        <div class="card-body">
          <h5 class="card-title mb-2">Meta Geral de Escutas</h5>
          <p class="mb-1"><strong>Meta Geral:</strong> <?= $metaGeral; ?> escutas</p>
          <p><strong>Escutas Realizadas:</strong> <?= $totalEscutasRealizadas; ?></p>
          <div class="progress mt-2">
            <div class="progress-bar" role="progressbar"
                 style="width: <?= $percentMetaGeral; ?>%;"
                 aria-valuenow="<?= $percentMetaGeral; ?>"
                 aria-valuemin="0" aria-valuemax="100">
              <?= round($percentMetaGeral); ?>%
            </div>
          </div>
        </div>
      </div>
      <!-- Card: Escutas por Supervisor -->
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Escutas por Supervisor</h5>
          <?php if (count($escutasSupervisor) > 0): ?>
            <ul class="list-group scroll-container">
              <?php foreach($escutasSupervisor as $supervisor): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <?= htmlspecialchars($supervisor['nome']); ?>
                  <span class="badge bg-primary rounded-pill"><?= $supervisor['total']; ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p>Nenhum registro exibido</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Coluna 3: Filtro de Período (também se estica) -->
    <div class="col-md-3 d-flex flex-column">
      <div class="card flex-fill">
        <div class="card-body">
          <h5 class="card-title">Filtrar Período</h5>
          <form method="GET">
            <div class="mb-3 mt-4">
              <label for="data_inicio" class="form-label">Data início</label>
              <input type="date" class="form-control" id="data_inicio" name="data_inicio" 
                     value="<?= htmlspecialchars($dataInicio); ?>">
            </div>
            <div class="mb-4">
              <label for="data_fim" class="form-label">Data fim</label>
              <input type="date" class="form-control" id="data_fim" name="data_fim"
                     value="<?= htmlspecialchars($dataFim); ?>">
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
              <a href="escutas.php" class="btn btn-secondary btn-sm">Limpar Filtro</a>
            </div>
          </form>
        </div>
      </div>
    </div>

  </div> <!-- fim row -->
</div> <!-- fim container -->

<!-- Seção: Escutas por Analista -->
<div class="container mt-4">
  <!-- Título + Botões -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Escutas por Analista</h3>
    <div class="d-flex justify-content-between align-items-center gap-2">
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastrar">Inserir Escuta</button>
        <div class="dropdown dropEstilizado">
          <a class="btn btn-primary"  data-bs-toggle="dropdown">
              Cadastrar<i class="fa-solid fa-caret-down ms-2"></i>
            </a>
          <ul class="dropdown-menu dropdown-menu-dark">
            <li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalCadastrarClassi">Classificação</a></li>
          </ul>
        </div>
      </div>
  </div>

  <div class="row">
    <?php if (count($analistas) > 0): ?>
      <?php foreach ($analistas as $analista): ?>
        <div class="col-md-3 mb-3">
          <div class="card h-100">
            <div class="card-body text-center">
              <h5 class="card-title"><?= htmlspecialchars($analista['usuario_nome']); ?></h5>
              <!-- Ao clicar, propaga data_inicio e data_fim para escutas_por_analista.php -->
              <a href="escutas_por_analista.php?user_id=<?= $analista['user_id']; ?>
                 <?php
                   if (!empty($dataInicio)) {
                       echo '&data_inicio=' . urlencode($dataInicio);
                   }
                   if (!empty($dataFim)) {
                       echo '&data_fim=' . urlencode($dataFim);
                   }
                 ?>"
                 class="btn btn-primary">
                Ver Escutas
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p>Nenhum analista com escutas registrado.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Modal Cadastrar Nova Escuta -->
<div class="modal fade" id="modalCadastrar" tabindex="-1" aria-labelledby="modalCadastrarLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content p-4">
      <h5 class="modal-title mb-3" id="modalCadastrarLabel">Cadastrar Nova Escuta</h5>
      <form method="POST" action="cadastrar_escuta.php">
        <div class="row mb-2">
          <div class="col-md-6 mb-3">
            <label for="cad_user_id" class="form-label">Selecione o Usuário</label>
            <select name="user_id" id="cad_user_id" class="form-select" required>
              <option value="">Escolha o usuário</option>
              <?php foreach($userscadastro as $usercad): ?>
                <option value="<?= $usercad['id']; ?>"><?= htmlspecialchars($usercad['nome']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label for="cad_classi_id" class="form-label">Classificação</label>
            <select name="classi_id" id="cad_classi_id" class="form-select">
              <option value="1">Sem Classificação</option>
              <?php foreach($classis as $classi): ?>
                <option value="<?= $classi['id']; ?>"><?= htmlspecialchars($classi['descricao']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row mb-2">
          <div class="col-md-4 mb-3">
            <label for="tipo_escuta" class="form-label">Escuta Positiva</label>
            <select name="positivo" id="tipo_escuta" class="form-select">
              <option value="">Selecione...</option>
              <option value="Sim">Sim</option>
              <option value="Nao">Nao</option>
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label for="solicita_ava" class="form-label">Solicitou Avaliação</label>
            <select name="avaliacao" id="solicita_ava" class="form-select">
              <option value="">Selecione...</option>
              <option value="Sim">Sim</option>
              <option value="Nao">Nao</option>
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label for="cad_data_escuta" class="form-label">Data da Escuta</label>
            <input type="date" name="data_escuta" id="cad_data_escuta" class="form-control" required value="<?= date('Y-m-d'); ?>">
          </div>
        </div>

        <div class="mb-3">
          <label for="cad_transcricao" class="form-label">Transcrição da Ligação</label>
          <textarea name="transcricao" id="cad_transcricao" class="form-control" rows="4" required></textarea>
        </div>
        <div class="mb-3">
          <label for="cad_feedback" class="form-label">Feedback / Ajustes</label>
          <textarea name="feedback" id="cad_feedback" class="form-control" rows="2"></textarea>
        </div>
        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-primary">Registrar Escuta</button>
          <button type="button" class="btn btn-secondary ms-2" data-bs-dismiss="modal">Fechar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Cadastrar CLassificação -->
<div class="modal fade" id="modalCadastrarClassi" tabindex="-1" aria-labelledby="modalCadastrarLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content p-4">
      <h5 class="modal-title mb-3" id="modalCadastrarLabel">Cadastrar Classificação</h5>
      <form method="POST" action="cadastrar_classificacao.php">
        <div class="row mb-2">
          <div class="col-md-12 mb-3">
            <label for="descricao" class="form-label">Classificação</label>
            <input type="text" class="form-control" id="descricao" name="descricao" required>
          </div>
        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-primary">Cadastrar</button>
          <button type="button" class="btn btn-secondary ms-2" data-bs-dismiss="modal">Fechar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
