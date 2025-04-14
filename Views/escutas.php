<?php
include '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

// Verifica se o usuário logado é Admin
$usuario_id = $_SESSION['usuario_id'];
$cargo = $_SESSION['cargo'] ?? '';
if ($cargo !== 'Admin') {
  header("Location: ../login.php");
  exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ----------- Filtro de Período (Data Início e Data Fim) ------------
$dataInicio = $_GET['data_inicio'] ?? '';
$dataFim    = $_GET['data_fim']    ?? '';

if (empty($dataInicio) && empty($dataFim)) {
    // Sem filtro, usa mês/ano atual
    $dataCondition = "MONTH(e.data_escuta) = MONTH(CURRENT_DATE()) AND YEAR(e.data_escuta) = YEAR(CURRENT_DATE())";
} else {
    if (!empty($dataInicio) && empty($dataFim)) {
        $dataCondition = "DATE(e.data_escuta) >= '" . $conn->real_escape_string($dataInicio) . "'";
    } elseif (empty($dataInicio) && !empty($dataFim)) {
        $dataCondition = "DATE(e.data_escuta) <= '" . $conn->real_escape_string($dataFim) . "'";
    } else {
        $dataCondition = "DATE(e.data_escuta) BETWEEN '" . $conn->real_escape_string($dataInicio) . "' 
                                              AND '" . $conn->real_escape_string($dataFim) . "'";
    }
}
$dataFilterCondition = " AND $dataCondition";

// ----------- Consultas Principais -----------

// 1) Analistas (usuários) que possuem escutas registradas
$sqlAnalistas = "
    SELECT DISTINCT e.user_id, u.nome AS usuario_nome 
    FROM TB_ESCUTAS e
    JOIN TB_USUARIO u ON e.user_id = u.id 
    WHERE (u.cargo = 'User' OR u.id IN (17, 18))
      AND u.id NOT IN (8)
      $dataFilterCondition
    ORDER BY u.nome
";
$resAnalistas = $conn->query($sqlAnalistas);
$analistas = [];
if ($resAnalistas) {
  while ($row = $resAnalistas->fetch_assoc()) {
    $analistas[] = $row;
  }
  $resAnalistas->free();
}

// 2) Usuários (apenas cargo='User') para meta geral
$users = [];
$sqlUsers = "
    SELECT u.nome
    FROM TB_USUARIO u
    WHERE (u.cargo = 'User' OR u.id IN (17, 18))
      AND u.id NOT IN (8)
    ORDER BY u.nome
";
$resUsers = $conn->query($sqlUsers);
if ($resUsers) {
  while ($row = $resUsers->fetch_assoc()) {
    $users[] = $row;
  }
  $resUsers->free();
}

// 3) Usuários para modal de cadastro (que não possuem 5 análises no período)
$userscadastro = [];
$sqlUsersCad = "
    SELECT 
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
    ORDER BY u.nome
";
$resUsersCad = $conn->query($sqlUsersCad);
if ($resUsersCad) {
  while ($row = $resUsersCad->fetch_assoc()) {
    $userscadastro[] = $row;
  }
  $resUsersCad->free();
}

// 4) Classificações (para o modal)
$classis = [];
$sqlClassi = "SELECT id, descricao FROM TB_CLASSIFICACAO WHERE id <> 1";
$resClassi = $conn->query($sqlClassi);
if ($resClassi) {
  while ($row = $resClassi->fetch_assoc()) {
    $classis[] = $row;
  }
  $resClassi->free();
}

// 5) Escutas Faltantes
$sqlEscutasFaltantes = "
    SELECT 
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
    ORDER BY u.nome
";
$resFaltantes = $conn->query($sqlEscutasFaltantes);
$escutasFaltantes = [];
if ($resFaltantes) {
  while ($row = $resFaltantes->fetch_assoc()) {
    $escutasFaltantes[] = $row;
  }
}

// 6) Escutas por Supervisor
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

// 7) Meta Geral de Escutas (cada analista tem meta de 5)
$totalAnalistas = count($users);
$metaGeral = $totalAnalistas * 5;
$sqlTotalEscutas = "
    SELECT COUNT(e.id) as total 
    FROM TB_ESCUTAS e
    JOIN TB_USUARIO u ON e.user_id = u.id
    WHERE (u.cargo = 'User' OR u.id IN (17, 18))
      AND u.id NOT IN (8)
      $dataFilterCondition
";
$resTotal = $conn->query($sqlTotalEscutas);
$totalEscutasRealizadas = 0;
if ($resTotal) {
  $rowTotal = $resTotal->fetch_assoc();
  $totalEscutasRealizadas = $rowTotal['total'];
}
$percentMetaGeral = ($metaGeral > 0) ? ($totalEscutasRealizadas * 100 / $metaGeral) : 0;
if ($percentMetaGeral > 100) $percentMetaGeral = 100;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Painel N3 - Escutas</title>

  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">

  <!-- Seus CSS externos (ordem: usuários.css depois escutas.css) -->
 
  <link rel="stylesheet" href="../Public/escutas.css">

  <link rel="icon" href="Public/Image/icone2.png" type="image/png">
</head>
<body class="bg-light">
<div class="d-flex-wrapper">
  <!-- Sidebar -->
  <div class="sidebar">
    <a class="light-logo" href="usuarios.php">
      <img src="../Public/Image/zucchetti_blue.png" width="150" alt="Logo Zucchetti">
    </a>
    <nav class="nav flex-column">
        <a class="nav-link" href="menu.php"><i class="fa-solid fa-house me-2"></i>Home</a>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="destaque.php"><i class="fa-solid fa-ranking-star me-2"></i>Destaques</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link active" href="escutas.php"><i class="fa-solid fa-headphones me-2"></i>Escutas</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'User'): ?>
         <a class="nav-link" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
         <a class="nav-link" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-calculator me-2"></i>Totalizadores</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
         <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'Treinamento'): ?>
          <a class="nav-link" href="treinamento.php"><i class="fa-solid fa-calendar-check me-2"></i>Treinamentos</a>
        <?php endif; ?>
      </nav>
  </div>
  <!-- Main Content -->
  <div class="w-100">
    <!-- Header -->
    <div class="header">
      <h3>Controle de Escutas</h3>
      <div class="user-info">
        <span>Bem-vindo(a), <?php echo htmlspecialchars($_SESSION['usuario_nome'], ENT_QUOTES, 'UTF-8'); ?>!</span>
        <a href="logout.php" class="btn btn-danger">
          <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
        </a>
      </div>
    </div>
    
    <!-- Conteúdo principal -->
    <div class="content container-fluid">

      <!-- Toast de Sucesso (para feedback de ações) -->
      <div class="toast-container">
        <div id="toastSucesso" class="toast">
          <div class="toast-body">
            <i class="fa-solid fa-check-circle"></i> <span id="toastMensagem"></span>
          </div>
        </div>
      </div>

      <!-- Script para exibir mensagens via Toast -->
      <script>
      document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get("success");
        if (success) {
          let mensagem = "";
          switch (success) {
            case "1": mensagem = "Usuário cadastrado com sucesso!"; break;
            case "2": mensagem = "Escuta cadastrada com sucesso!"; break;
            case "5": mensagem = "Classificação cadastrada com sucesso!"; break;
            case "6": mensagem = "Usuário já cadastrado!"; break;
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

    <!-- Filtros e Informações Principais (Escutas Faltantes, Meta Geral + Supervisor, Filtro) -->
<div class="row g-3 align-items-stretch">
  <!-- Coluna 1: Escutas Faltantes -->
  <div class="col-md-4 d-flex flex-column">
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
                    <?= (int)$analista['faltantes']; ?>
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

  <!-- Coluna 2: Meta Geral + Escutas por Supervisor -->
  <div class="col-md-4 d-flex flex-column">
    <!-- Card: Meta Geral -->
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

  <!-- Coluna 3: Filtro de Período -->
  <div class="col-md-4 d-flex flex-column">
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
</div>


      <!-- Escutas por Analista -->
      <div class="mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h3 class="mb-0">Escutas por Analista</h3>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastrar">
            <i class="fa-solid fa-plus-circle me-1"></i>Cadastrar
          </button>
        </div>

        <div class="row">
          <?php if (count($analistas) > 0): ?>
            <?php foreach ($analistas as $analista): ?>
              <div class="col-md-3 mb-3">
                <div class="card h-100">
                  <div class="card-body text-center">
                    <h5 class="card-title"><?= htmlspecialchars($analista['usuario_nome']); ?></h5>
                    <!-- link com passagem de filtros para escutas_por_analista -->
                    <a href="escutas_por_analista.php?user_id=<?= (int)$analista['user_id']; ?>
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

    </div> <!-- Fim de .content container-fluid -->
  </div> <!-- Fim de .w-100 (Main Content) -->
</div> <!-- Fim de .d-flex-wrapper -->

<!-- Modal Cadastrar Nova Escuta -->
<div class="modal fade" id="modalCadastrar" tabindex="-1" aria-labelledby="modalCadastrarLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCadastrarLabel">Nova Escuta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <form method="POST" action="cadastrar_escuta.php">
        <div class="modal-body">
          <div class="row mb-2 mt-2">
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
              <div class="input-group mt-0">
                <select name="classi_id" id="cad_classi_id" class="form-control">
                  <option value="1">Sem Classificação</option>
                  <?php foreach($classis as $classi): ?>
                    <option value="<?= $classi['id']; ?>"><?= htmlspecialchars($classi['descricao']); ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-secondary" type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#novoClassiCollapse"
                        aria-expanded="false"
                        aria-controls="novoClassiCollapse">
                  <i class="fa-solid fa-plus"></i>
                </button>
              </div>
              <span id="msgClassificacao" class="d-none" style="color: green; margin-top: 5px;">
                <i class="fa-solid fa-check"></i> Classificação cadastrada com sucesso!
              </span>
            </div>
          </div>

          <div class="collapse mb-3" id="novoClassiCollapse">
            <div class="card card-body">
              <div class="form-group">
                <label for="novo_classificacao">Nova Classificação</label>
                <input type="text" class="form-control" id="novo_classificacao" placeholder="Informe a nova classificação">
              </div>
              <button type="button" class="btn btn-primary mt-1" id="btnCadastrarClassificacao">Salvar Classificação</button>
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
              <select name="avaliacao" id="solicita_ava" class="form-select" required>
                <option value="">Selecione...</option>
                <option value="Sim">Sim</option>
                <option value="Nao">Nao</option>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label for="cad_data_escuta" class="form-label">Data da Escuta</label>
              <input type="date" name="data_escuta" id="cad_data_escuta" class="form-control" required
                     value="<?= date('Y-m-d'); ?>">
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
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Cadastrar Classificação -->
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
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Script AJAX para cadastrar Classificação -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
  $('#btnCadastrarClassificacao').click(function(){
    var novaClassificacao = $('#novo_classificacao').val().trim();
    if(novaClassificacao === ''){
      $('#msgClassificacao').html('<span style="color:red;"><i class="fa-solid fa-xmark"></i> Informe a nova classificação.</span>').removeClass('d-none');
      setTimeout(function(){
        $('#msgClassificacao').addClass('d-none');
      }, 2500);
      return;
    }
    $.ajax({
      url: 'cadastrar_classificacao.php',
      type: 'POST',
      data: { descricao: novaClassificacao },
      dataType: 'json',
      success: function(resp){
        if(resp.duplicate === true) {
          $('#msgClassificacao').html('<span style="color:orange;"><i class="fa-solid fa-exclamation-triangle"></i> ' + resp.message + '</span>').removeClass('d-none');
          $('#cad_classi_id').val(resp.id);
        } else if(resp.id) {
          $('#cad_classi_id').append('<option value="' + resp.id + '">' + resp.descricao + '</option>');
          $('#cad_classi_id').val(resp.id);
          $('#msgClassificacao').html('<i class="fa-solid fa-check"></i> Classificação cadastrada com sucesso!').removeClass('d-none');
        } else {
          $('#msgClassificacao').html('<span style="color:red;"><i class="fa-solid fa-xmark"></i> Erro: ' + resp.message + '</span>').removeClass('d-none');
        }
        setTimeout(function(){
          $('#msgClassificacao').addClass('d-none');
        }, 2500);
        $('#novo_classificacao').val('');
        $('#novoClassiCollapse').collapse('hide');
      },
      error: function(jqXHR, textStatus, errorThrown){
        $('#msgClassificacao').html('<span style="color:red;"><i class="fa-solid fa-xmark"></i> Erro na requisição: ' + errorThrown + '</span>').removeClass('d-none');
        setTimeout(function(){
          $('#msgClassificacao').addClass('d-none');
        }, 2500);
      }
    });
  });
});
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>
