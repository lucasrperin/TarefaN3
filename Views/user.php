<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../Login.php");
    exit();
}

require '../Config/Database.php';

$usuario_id = $_SESSION['usuario_id'];

// Consulta para obter análises
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

// Organizar fichas associadas
$fichas_por_numero = [];
while ($ficha = $resultado_fichas->fetch_assoc()) {
    $fichas_por_numero[$ficha['numeroFicha']][] = $ficha;
}

// Totalizadores
$totalAnalises = $resultado_analises->num_rows;
$totalFichas = 0;
foreach ($fichas_por_numero as $numeroFicha => $fichas) {
    $totalFichas += count($fichas);
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Minhas Análises e Fichas</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- CSS personalizado para a área do usuário -->
  <link rel="stylesheet" href="../Public/user.css">
  <style>
    /* Ajustes adicionais, se necessário */
    .table-responsive { margin-top: 15px; }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
  <div class="container d-flex align-items-center">
    <a class="navbar-brand" href="#">Painel do Usuário</a>
    <span class="text-white mx-auto">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
    <a href="logout.php" class="btn btn-danger">Sair</a>
  </div>
</nav>



  <!-- Conteúdo Principal -->
  <div class="container user-container mt-4">
    

    <!-- Totalizadores em Cards -->
    <div class="row mb-4">
      <div class="col-md-6 mb-3">
        <div class="card text-center border-primary">
          <div class="card-body">
            <h5 class="card-title text-primary">Total de Análises</h5>
            <p class="card-text display-6"><?php echo $totalAnalises; ?></p>
          </div>
        </div>
      </div>
      <div class="col-md-6 mb-3">
        <div class="card text-center border-info">
          <div class="card-body">
            <h5 class="card-title text-info">Total de Fichas</h5>
            <p class="card-text display-6"><?php echo $totalFichas; ?></p>
          </div>
        </div>
      </div>
    </div>

    <!-- Seções de Análises e Fichas -->
    <div class="row">
      <!-- Seção de Análises -->
      <div class="col-md-6 mb-4">
        <div class="card">
          <div class="card-header bg-primary text-white">
            Análises
          </div>
          <div class="card-body">
            <?php 
            // Armazenar os dados de análises para iteração
            $analises = [];
            while ($row = $resultado_analises->fetch_assoc()) {
                $analises[] = $row;
            }
            ?>
            <?php if ($totalAnalises > 0): ?>
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>Descrição</th>
                      <th>Nº da Ficha</th>
                      <th>Data</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($analises as $analise): ?>
                      <tr>
                        <td><?php echo $analise['Descricao']; ?></td>
                        <td><?php echo $analise['numeroFicha'] ?? '-'; ?></td>
                        <td><?php echo $analise['Hora_ini']; ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-info">Nenhuma análise cadastrada.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Seção de Fichas -->
      <div class="col-md-6 mb-4">
        <div class="card">
          <div class="card-header bg-info text-white">
            Fichas
          </div>
          <div class="card-body">
            <?php if ($totalFichas > 0): ?>
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>Nº da Ficha</th>
                      <th>Data</th>
                      <th>Ação</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($fichas_por_numero as $numeroFicha => $fichas): ?>
                      <?php foreach ($fichas as $ficha): ?>
                        <tr>
                          <td><?php echo $ficha['numeroFicha']; ?></td>
                          <td><?php echo $ficha['Hora_ini']; ?></td>
                          <td>
                            <a href="https://zmap.zpos.com.br/#/detailsIncidente/<?php echo $ficha['numeroFicha']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">Acessar</a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="alert alert-info">Nenhuma ficha cadastrada.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
