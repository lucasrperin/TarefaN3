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
</head>
<body>
  <div class="container user-container">
    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Painel do Usuário</h2>
      <a href="logout.php" class="btn btn-outline-danger">Sair</a>
    </div>

    <h3 class="user-header">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?></h3>
    
    <!-- Totalizadores -->
    <div class="row mb-4">
      <div class="col-md-6">
        <div class="alert alert-primary text-center" role="alert">
          Total de Análises: <strong><?php echo $totalAnalises; ?></strong>
        </div>
      </div>
      <div class="col-md-6">
        <div class="alert alert-info text-center" role="alert">
          Total de Fichas: <strong><?php echo $totalFichas; ?></strong>
        </div>
      </div>
    </div>
    
    <div class="row">
      <!-- Seção de Análises -->
      <div class="col-md-6">
        <h4 class="mb-3">Análises</h4>
        <?php 
        // Armazenar os dados de análises para iteração
        $analises = [];
        while ($row = $resultado_analises->fetch_assoc()) {
            $analises[] = $row;
        }
        ?>
        <?php if ($totalAnalises > 0): ?>
          <table class="table table-striped">
            <thead>
              <tr>
                <th>Descrição</th>
                <th>Número da Ficha</th>
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
        <?php else: ?>
          <div class="alert alert-info">Nenhuma análise cadastrada.</div>
        <?php endif; ?>
      </div>
      
      <!-- Seção de Fichas -->
      <div class="col-md-6">
        <h4 class="mb-3">Fichas</h4>
        <?php if ($totalFichas > 0): ?>
          <table class="table table-striped">
            <thead>
              <tr>
                <th>Número da Ficha</th>
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
        <?php else: ?>
          <div class="alert alert-info">Nenhuma ficha cadastrada.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
