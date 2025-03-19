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

// Consulta os usuários (analistas) que possuem escutas registradas
$query = "SELECT DISTINCT e.user_id, u.nome AS usuario_nome 
          FROM TB_ESCUTAS e
          JOIN TB_USUARIO u ON e.user_id = u.id 
          WHERE (u.cargo = 'User' OR u.id IN (17, 18))
          AND u.id NOT IN (8)
          ORDER BY u.nome";
$result = $conn->query($query);
$analistas = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $analistas[] = $row;
    }
    $result->free();
}

// Recupera os usuários com cargo "User" para preencher o select do modal de cadastro
$users = [];
$queryUsers = "SELECT u.id, u.nome FROM TB_USUARIO u WHERE (u.cargo = 'User' OR u.id IN (17, 18)) AND u.id NOT IN (8)";
$resultUsers = $conn->query($queryUsers);
if ($resultUsers) {
    while ($row = $resultUsers->fetch_assoc()) {
        $users[] = $row;
    }
    $resultUsers->free();
}

// Recupera os usuários com cargo "User" para preencher o select do modal de cadastro
$classis = [];
$queryClassi = "SELECT id, descricao FROM TB_CLASSIFICACAO";
$resultClassi = $conn->query($queryClassi);
if ($resultClassi) {
    while ($row = $resultClassi->fetch_assoc()) {
        $classis[] = $row;
    }
    $resultClassi->free();
}

// ----------------------------------------------------
// 1. Escutas por Analista (usuários com cargo 'User')
// ----------------------------------------------------
$sqlEscutasAnalista = "SELECT 
                        u.nome, 
                        COUNT(e.id) AS total
                      FROM TB_USUARIO u
                      JOIN TB_ESCUTAS e ON e.user_id = u.id
                      WHERE (u.cargo = 'User' OR u.id IN (17, 18))
                      AND u.id NOT IN (8)
                      GROUP BY u.id
                      ORDER BY u.nome
";
$resAnalista = $conn->query($sqlEscutasAnalista);
$escutasAnalista = [];
if ($resAnalista) {
    while ($row = $resAnalista->fetch_assoc()) {
        $escutasAnalista[] = $row;
    }
}

// ----------------------------------------------------
// 2. Escutas por Supervisor (usuários com cargo 'Supervisor')
// ----------------------------------------------------
$sqlEscutasSupervisor = "
    SELECT u.nome, COUNT(e.id) AS total
    FROM TB_USUARIO u
    JOIN TB_ESCUTAS e ON e.admin_id = u.id
    WHERE u.cargo = 'Admin'
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
// 3. Escutas Faltantes (para cada analista 'User', considerando meta de 5 escutas)
// --Usuarios que são do Conversor
// --Usuarios que são do Email
// ----------------------------------------------------
$sqlEscutasFaltantes = "SELECT 
                          u.nome, (5 - COUNT(e.id)) AS faltantes
                        FROM TB_USUARIO u
                        LEFT JOIN TB_ESCUTAS e ON e.user_id = u.id
                        WHERE (u.cargo = 'User' OR u.id IN (17, 18))
                          AND u.id NOT IN (8)
                        GROUP BY u.id
                        HAVING faltantes > 0
                        ORDER BY u.nome
";
$resFaltantes = $conn->query($sqlEscutasFaltantes);
$escutasFaltantes = [];
if ($resFaltantes) {
    while ($row = $resFaltantes->fetch_assoc()) {
        $escutasFaltantes[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tarefas N3</title>
    <!-- Arquivo CSS personalizado -->
    <link href="../Public/escutas.css" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
   
    <!-- Ícones personalizados -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <link rel="icon" href="Public\Image\icone2.png" type="image/png">
    
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
  <div class="container d-flex justify-content-between align-items-center">
    <!-- Dropdown de navegação -->
    <div class="dropdown">
      <button class="navbar-toggler" type="button" data-bs-toggle="dropdown">
        <span class="navbar-toggler-icon"></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-dark">
        <li><a class="dropdown-item" href="conversao.php">Conversão</a></li>
        <li><a class="dropdown-item" href="../index.php">Painel</a></li>
        <li><a class="dropdown-item" href="dashboard.php">Totalizadores</a></li>
      </ul>
    </div>
    <span class="text-white">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
    <a href="../index.php" class="btn btn-danger">
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
<script dref>
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

<div class="container mt-3">
  <!-- Linha dos Totalizadores -->
  <div class="row justify-content-center mb-4">
    <!-- Escutas por Analista -->
    <div class="col-md-3">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Escutas por Analista</h5>
          <?php if (count($escutasAnalista) > 0): ?>
            <ul class="list-group scroll-container">
                <!-- Supondo que $escutasAnalista seja um array com nome e total de escutas -->
                <?php foreach($escutasAnalista as $analista): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <?= htmlspecialchars($analista['nome']); ?>
                  <span class="badge bg-info rounded-pill"><?= $analista['total']; ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p>Nenhum registro exibido</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Escutas por Supervisor -->
    <div class="col-md-3">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Escutas por Supervisor</h5>
          <?php if (count($escutasSupervisor) > 0): ?>
            <ul class="list-group scroll-container">
                <!-- Supondo que $escutasSupervisor seja um array com nome e total de escutas de supervisores -->
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

    <!-- Escutas Faltantes -->
    <div class="col-md-3">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Escutas Faltantes</h5>
          <?php if (count($escutasFaltantes) > 0): ?>
          <ul class="list-group scroll-container">
              <!-- Para cada analista com cargo 'User', calcular quantas escutas faltam (5 - total) -->
              <?php foreach($escutasFaltantes as $analista): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <span class="analista-name"><?= htmlspecialchars($analista['nome']); ?></span>
                  <span class="analista-total badge bg-danger rounded-pill"><?= $analista['faltantes']; ?></span>
                </li>
              <?php endforeach; ?>
          </ul>
          <?php else: ?>
            <p>Nenhum registro exibido</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>



<div class="container mt-5">
  <div class="row mb-2">
    <div class="col-md-12 mb-3">
      <!-- Botão para abrir modal de cadastro -->
      <div class="d-flex justify-content-end mb-4 gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastrarUser">
          Cadastrar Usuário
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastrar">
          Cadastrar Nova Escuta
        </button>
      </div>
    </div>

    <div class="card">
  <div class="card-body">
    <h5 class="card-title">Escutas por Analista</h5>
    <?php if (count($escutasAnalista) > 0): ?>
      <ul class="list-group">
        <?php foreach($escutasAnalista as $analista): ?>
          <?php 
            // Total de escutas para o analista
            $totalEscutas = $analista['total'];
            // Calcula o percentual com base na meta de 5 escutas
            $percentual = ($totalEscutas * 100) / 5;
            // Limita o percentual a 100%
            if ($percentual > 100) {
              $percentual = 100;
            }
          ?>
          <li class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
              <span><?= htmlspecialchars($analista['nome']); ?></span>
              <span><?= $totalEscutas; ?> escutas</span>
            </div>
            <div class="progress mt-2">
              <div class="progress-bar" role="progressbar" style="width: <?= $percentual; ?>%;" aria-valuenow="<?= $percentual; ?>" aria-valuemin="0" aria-valuemax="100">
                <?= round($percentual); ?>%
              </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p>Nenhum registro exibido</p>
    <?php endif; ?>
  </div>
</div>

  </div>

  <h3 class="mb-4">Escutas por Analista</h3>
  <div class="row">
    <?php if (count($analistas) > 0): ?>
      <?php foreach ($analistas as $analista): ?>
        <div class="col-md-4 mb-3">
          <div class="card h-100">
            <div class="card-body text-center">
              <h5 class="card-title"><?php echo $analista['usuario_nome']; ?></h5>
              <a href="escutas_por_analista.php?user_id=<?php echo $analista['user_id']; ?>" class="btn btn-primary">
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
            <div class="mb-3">
              <label for="cad_user_id" class="form-label">Selecione o Usuário</label>
              <select name="user_id" id="cad_user_id" class="form-select" required>
                <option value="">Escolha o usuário</option>
                <?php foreach($users as $user): ?>
                  <option value="<?php echo $user['id']; ?>"><?php echo $user['nome']; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="mb-3">
              <label for="cad_classi_id" class="form-label">Classificação</label>
              <select name="classi_id" id="cad_classi_id" class="form-select" required>
                <option value="">Escolha a classificação</option>
                <?php foreach($classis as $classi): ?>
                  <option value="<?php echo $classi['id']; ?>"><?php echo $classi['descricao']; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="row mb-2">
          <div class="col-md-6 mb-3">
            <div class="mb-3">
              <label for="tipo_escuta" class="form-label">Escuta Positiva</label>
              <select name="positivo" id="tipo_escuta" class="form-select">
                <option value="">Selecione...</option>
                <option value="Sim">Sim</option>
                <option value="Nao">Nao</option>
              </select>
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="mb-3">
              <label for="cad_data_escuta" class="form-label">Data da Escuta</label>
              <input type="date" name="data_escuta" id="cad_data_escuta" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label for="cad_transcricao" class="form-label">Transcrição da Ligação</label>
          <textarea name="transcricao" id="cad_transcricao" class="form-control" rows="4" required></textarea>
        </div>
        <div class="mb-3">
          <label for="cad_feedback" class="form-label">Feedback / Ajustes</label>
          <textarea name="feedback" id="cad_feedback" class="form-control" rows="2" required></textarea>
        </div>
        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-primary">Registrar Escuta</button>
          <button type="button" class="btn btn-secondary ms-2" data-bs-dismiss="modal">Fechar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Cadastrar Usuário -->
<div class="modal fade" id="modalCadastrarUser" tabindex="-1" aria-labelledby="modalCadastrarLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content p-4">
      <h5 class="modal-title mb-3" id="modalCadastrarLabel">Cadastrar Novo Usuário</h5>
      <form method="POST" action="cadastrar_usuario.php">
        <div class="row mb-2">
          <div class="col-md-6 mb-3">
            <div class="mb-3">
              <label for="cad_new_user_id" class="form-label">E-mail</label>
              <input type="email" class="form-control" id="email" name="email" required>
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="mb-3">
              <label for="user_name" class="form-label">Nome</label>
              <input type="text" class="form-control" id="name" name="nome" maxlength="50" required>
            </div>
          </div>
        </div>

        <div class="row mb-2">
          <div class="col-md-6 mb-3">
            <div class="mb-3">
              <label for="tipo_escuta" class="form-label">Senha</label>
              <input type="password" class="form-control" id="senha" name="senha" required>
            </div>
          </div>
        </div>
        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-primary">Cadastrar</button>
          <button type="button" class="btn btn-secondary ms-2" data-bs-dismiss="modal">Fechar</button>
        </div>
      </form>
    </div>
  </div>
</div>
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</html>
