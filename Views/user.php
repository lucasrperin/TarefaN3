<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../Login.php");
    exit();
}

require '../Config/Database.php';

$usuario_id = $_SESSION['usuario_id'];

// Consulta para obter análises (incluindo o campo Nota) do usuário logado
$sql_analises = "SELECT
            a.Id,
            a.Descricao,
            a.Nota,
            a.numeroFicha,
            DATE_FORMAT(a.Hora_ini, '%d/%m %H:%i:%s') as Hora_ini
        FROM TB_ANALISES a
        WHERE a.idAtendente = ? AND a.idSituacao = 1";
$stmt_analises = $conn->prepare($sql_analises);
$stmt_analises->bind_param("i", $usuario_id);
$stmt_analises->execute();
$resultado_analises = $stmt_analises->get_result();

// Armazenar análises em um array
$analises = [];
while ($row = $resultado_analises->fetch_assoc()) {
    $analises[] = $row;
}
$totalAnalises = count($analises);

// Calcular a média das notas do usuário logado
$somaNotas = 0;
foreach ($analises as $analise) {
    $somaNotas += $analise['Nota'];
}
$mediaValor = $totalAnalises > 0 ? $somaNotas / $totalAnalises : 0;
$mediaFormatada = number_format($mediaValor, 2, ',', '.');

// Definir a classe e o texto conforme a média do usuário logado
if ($mediaValor >= 4.5) {
    $classeMedia = 'nota-verde';
    $textoMedia = 'Acima do Esperado';
} elseif ($mediaValor <= 2.99) {
    $classeMedia = 'nota-vermelha';
    $textoMedia = 'Abaixo do Esperado';
} else {
    $classeMedia = 'nota-amarela';
    $textoMedia = 'Dentro do Esperado';
}

// Consulta para obter fichas do usuário logado
$sql_fichas = "SELECT
            a.Id,
            a.Descricao,
            a.numeroFicha,
            DATE_FORMAT(a.Hora_ini, '%d/%m %H:%i:%s') as Hora_ini
        FROM TB_ANALISES a
        WHERE a.idAtendente = ? AND a.idSituacao = 3";
$stmt_fichas = $conn->prepare($sql_fichas);
$stmt_fichas->bind_param("i", $usuario_id);
$stmt_fichas->execute();
$resultado_fichas = $stmt_fichas->get_result();

// Organizar fichas por número
$fichas_por_numero = [];
while ($ficha = $resultado_fichas->fetch_assoc()) {
    $fichas_por_numero[$ficha['numeroFicha']][] = $ficha;
}

// Calcular total de fichas
$totalFichas = 0;
foreach ($fichas_por_numero as $numeroFicha => $fichas) {
    $totalFichas += count($fichas);
}

// Consulta para ranking: top 5 usuários com maior média de notas
// A média é truncada para uma casa decimal: FLOOR(AVG(a.Nota)*10)/10
$sql_ranking = "SELECT a.idAtendente, u.Nome as usuario_nome, FLOOR(AVG(a.Nota)*10)/10 as mediaNotas
                FROM TB_ANALISES a
                JOIN TB_USUARIO u ON a.idAtendente = u.Id
                GROUP BY a.idAtendente, u.Nome
                ORDER BY mediaNotas DESC
                LIMIT 5";
$result_ranking = $conn->query($sql_ranking);
$ranking = [];
if ($result_ranking) {
    while ($row = $result_ranking->fetch_assoc()) {
         $ranking[] = $row;
    }
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
    <!-- Totalizadores e Ranking -->
    <div class="row mb-4">
      <!-- Total de Análises -->
      <div class="col-md-3 mb-3">
        <div class="card text-center border-primary">
          <div class="card-body">
            <h5 class="card-title text-primary">Total de Análises</h5>
            <p class="card-text display-6"><?php echo $totalAnalises; ?></p>
          </div>
        </div>
      </div>
      <!-- Total de Fichas -->
      <div class="col-md-3 mb-3">
        <div class="card text-center border-info">
          <div class="card-body">
            <h5 class="card-title text-info">Total de Fichas</h5>
            <p class="card-text display-6"><?php echo $totalFichas; ?></p>
          </div>
        </div>
      </div>
      <!-- Média das Notas do Usuário Logado -->
      <div class="col-md-3 mb-3">
        <div class="card text-center border-secondary">
          <div class="card-body">
            <h5 class="card-title">Média das Notas</h5>
            <p class="card-text display-6 <?php echo $classeMedia; ?>"><?php echo $mediaFormatada; ?></p>
            <p class="<?php echo $classeMedia; ?>"><?php echo $textoMedia; ?></p>
          </div>
        </div>
      </div>
      <!-- Ranking dos Melhores Usuários -->
      <div class="col-md-3 mb-3">
        <div class="card text-center border-dark">
          <div class="card-body">
            <h5 class="card-title">Ranking</h5>
            <?php if (count($ranking) > 0): ?>
              <ul class="list-group">
                <?php foreach ($ranking as $index => $rank): ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center">
                    <?php echo ($index + 1) . "º - " . $rank['usuario_nome']; ?>
                    <span class="badge bg-primary rounded-pill">
                      <?php echo number_format($rank['mediaNotas'], 2, ',', '.'); ?>
                    </span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p>Nenhum ranking disponível.</p>
            <?php endif; ?>
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
            <?php if ($totalAnalises > 0): ?>
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>Descrição</th>
                      <th>Nº da Ficha</th>
                      <th>Data</th>
                      <th>Nota</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($analises as $analise): ?>
                      <?php
                        $nota = $analise['Nota'];
                        if ($nota == 5) {
                            $classeNota = 'nota-verde';
                        } elseif ($nota == 4) {
                            $classeNota = 'nota-teal';
                        } elseif ($nota == 3) {
                            $classeNota = 'nota-amarela';
                        } elseif ($nota == 2) {
                            $classeNota = 'nota-laranja';
                        } elseif ($nota == 0 || $nota == 1) {
                            $classeNota = 'nota-vermelha';
                        } else {
                            $classeNota = '';
                        }
                      ?>
                      <tr>
                        <td><?php echo $analise['Descricao']; ?></td>
                        <td><?php echo $analise['numeroFicha'] ?? '-'; ?></td>
                        <td><?php echo $analise['Hora_ini']; ?></td>
                        <td class="<?php echo $classeNota; ?>"><?php echo $nota; ?></td>
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
    