<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../Login.php");
    exit();
}

require '../Config/Database.php';

$usuario_id = $_SESSION['usuario_id'];

// Consulta para obter análises com ou sem fichas associadas
$sql_analises = "SELECT
            tas.Id as Codigo,
            tas.Descricao as Descricao,
            tas.numeroFicha,
            DATE_FORMAT(tas.Hora_ini, '%d/%m %H:%i:%s') as Hora_ini
        FROM TB_ANALISES tas
        WHERE tas.idAtendente = ? AND idSituacao = 1";
$stmt_analises = $conn->prepare($sql_analises);
$stmt_analises->bind_param("i", $usuario_id);
$stmt_analises->execute();
$resultado_analises = $stmt_analises->get_result();

// Consulta para obter fichas
$sql_fichas = "SELECT
            tas.Id as Codigo,
            tas.Descricao as Descricao,
            tas.numeroFicha,
            DATE_FORMAT(tas.Hora_ini, '%d/%m %H:%i:%s') as Hora_ini
        FROM TB_ANALISES tas
        WHERE tas.idAtendente = ? AND idSituacao = 3";
$stmt_fichas = $conn->prepare($sql_fichas);
$stmt_fichas->bind_param("i", $usuario_id);
$stmt_fichas->execute();
$resultado_fichas = $stmt_fichas->get_result();

// Organizar fichas associadas às análises
$fichas_por_numero = [];
while ($ficha = $resultado_fichas->fetch_assoc()) {
    $fichas_por_numero[$ficha['numeroFicha']][] = $ficha;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Minhas Análises e Fichas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
      <a class="navbar-brand" href="#">Painel do Usuário</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarUser">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarUser">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="logout.php">Sair</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="container mt-5">
    <h1 class="mb-4">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?></h1>

    <!-- Seção de Análises -->
    <div class="mb-5">
      <h2>Análises</h2>
      <?php if ($resultado_analises->num_rows > 0): ?>
        <table class="table table-striped">
          <thead>
            <tr>
              <th>Descrição</th>
              <th>Número da Ficha</th>
              <th>Data de Criação</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($analise = $resultado_analises->fetch_assoc()): ?>
              <tr>
                <td><?php echo $analise['Descricao']; ?></td>
                <td><?php echo $analise['numeroFicha'] ?? '-'; ?></td>
                <td><?php echo $analise['Hora_ini']; ?></td>
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
      <?php if (!empty($fichas_por_numero)): ?>
        <table class="table table-striped">
          <thead>
            <tr>
              <th>Descrição</th>
              <th>Número da Ficha</th>
              <th>Data de Criação</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($fichas_por_numero as $numeroFicha => $fichas): ?>
              <?php foreach ($fichas as $ficha): ?>
                <tr>
                  <td><?php echo $ficha['Descricao']; ?></td>
                  <td><?php echo $ficha['numeroFicha']; ?></td>
                  <td><?php echo $ficha['Hora_ini']; ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-muted">Você não possui fichas cadastradas.</p>
      <?php endif; ?>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
