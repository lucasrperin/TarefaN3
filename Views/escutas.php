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
$queryUsers = "SELECT id, nome FROM TB_USUARIO WHERE cargo = 'User'";
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
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Painel de Escutas - Por Analista</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Seus CSS customizados -->
  <link rel="stylesheet" href="../css/index.css">
  <link rel="stylesheet" href="../css/dashboard.css">
  <link rel="stylesheet" href="../css/user.css">
  <!-- Ícones -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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

<div class="container mt-5">
  <!-- Botão para abrir modal de cadastro -->
  <div class="d-flex justify-content-end mb-4">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastrar">
      Cadastrar Nova Escuta
    </button>
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
          <div class="col-md-4 mb-3">
            <div class="mb-3">
              <label for="tipo_escuta" class="form-label">Escuta Positiva</label>
              <select name="positivo" id="tipo_escuta" class="form-select">
                <option value="">Selecione...</option>
                <option value="Sim">Sim</option>
                <option value="Nao">Nao</option>
              </select>
            </div>
          </div>
          <div class="col-md-4 mb-3">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
