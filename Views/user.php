<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../Login.php");
    exit();
}

require '../Config/Database.php'; // Ajuste conforme sua estrutura

$usuario_id = $_SESSION['usuario_id'];

// Consulta para obter as análises vinculadas ao usuário
$sql_analises = "SELECT * FROM TB_ANALISES WHERE idAtendente = ?";
$stmt_analises = $conn->prepare($sql_analises);
$stmt_analises->bind_param("i", $usuario_id);
$stmt_analises->execute();
$resultado_analises = $stmt_analises->get_result();

// Consulta para obter as fichas vinculadas ao usuário
$sql_fichas = "SELECT * FROM TB_FICHAS WHERE usuario_id = ?";
$stmt_fichas = $conn->prepare($sql_fichas);
$stmt_fichas->bind_param("i", $usuario_id);
$stmt_fichas->execute();
$resultado_fichas = $stmt_fichas->get_result();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Minhas Análises e Fichas</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
      <a class="navbar-brand" href="#">Painel do Usuário</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarUser" aria-controls="navbarUser" aria-expanded="false" aria-label="Alternar navegação">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarUser">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="../index.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="logout.php">Sair</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Conteúdo Principal -->
  <div class="container mt-5">
    <h1 class="mb-4">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?></h1>

    <!-- Seção de Análises -->
    <div class="mb-5">
      <h2>Análises</h2>
      <?php if ($resultado_analises->num_rows > 0): ?>
        <table class="table table-striped">
          <thead>
            <tr>
              <th>ID</th>
              <th>Título</th>
              <th>Data de Criação</th>
              <!-- Adicione outras colunas relevantes -->
            </tr>
          </thead>
          <tbody>
            <?php while ($analise = $resultado_analises->fetch_assoc()): ?>
              <tr>
                <td><?php echo $analise['id']; ?></td>
                <td><?php echo $analise['titulo']; ?></td>
                <td><?php echo $analise['data_criacao']; ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-muted">Você não possui análises cadastradas.</p>
      <?php endif; ?>
    </div>

    <!-- Seção de Fichas -->
    <div class="mb-5">
      <h2>Fichas</h2>
      <?php if ($resultado_fichas->num_rows > 0): ?>
        <table class="table table-striped">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nome</th>
              <th>Descrição</th>
              <th>Data de Criação</th>
              <!-- Adicione outras colunas relevantes -->
            </tr>
          </thead>
          <tbody>
            <?php while ($ficha = $resultado_fichas->fetch_assoc()): ?>
              <tr>
                <td><?php echo $ficha['id']; ?></td>
                <td><?php echo $ficha['nome']; ?></td>
                <td><?php echo $ficha['descricao']; ?></td>
                <td><?php echo $ficha['data_criacao']; ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-muted">Você não possui fichas cadastradas.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
