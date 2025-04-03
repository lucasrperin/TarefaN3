<?php
session_start();

// Verifica se o usuário está logado; se não, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

require '../Config/Database.php';

// Se um usuário foi passado via GET, use-o; caso contrário, use o usuário logado
$usuario_id = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : $_SESSION['usuario_id'];

$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';

// Consulta para obter análises (incluindo o campo Nota) do usuário logado
$sql_analises = "SELECT
            a.Id,
            a.Descricao,
            a.Nota,
            a.numeroFicha,
            DATE_FORMAT(a.Hora_ini, '%d/%m %H:%i:%s') as Hora_ini,
            a.justificativa as justificativa,
            u.Nome as Usuario
         FROM TB_ANALISES a
         LEFT JOIN TB_USUARIO u ON u.Id = a.idUsuario
         WHERE a.idAtendente = ? AND a.idSituacao = 1 AND a.idStatus = 1";
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
$sql_ranking = "SELECT 
                  a.idAtendente, 
                  u.Nome as usuario_nome, 
                  AVG(a.Nota) as mediaNotas
                FROM TB_ANALISES a
                JOIN TB_USUARIO u ON a.idAtendente = u.Id
                WHERE a.idStatus = 1
                GROUP BY a.idAtendente, u.Nome
                ORDER BY mediaNotas DESC";
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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Minhas Análises e Fichas</title>
    <!-- Bootstrap CSS -->
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <!-- CSS personalizado para a área do usuário -->
    <link href="../Public/user.css" rel="stylesheet" />
    <!-- Ícones personalizados -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
    />
    <link rel="icon" href="../Public/Image/icone2.png" type="image/png" />
  </head>
  <body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark bg-dark">
      <div class="container d-flex justify-content-between align-items-center">
        <!-- Botão Hamburguer com Dropdown -->
          <div class="dropdown">
            <button class="navbar-toggler" type="button" id="menuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="navbar-toggler-icon"></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="menuDropdown">
              <?php if ($cargo === 'Admin' || $cargo === 'Conversor'  || $cargo === 'Viewer'): ?>
                <li><a class="dropdown-item" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a></li>
              <?php endif; ?>

              <?php if ($cargo === 'Admin'): ?>
                <li><a class="dropdown-item" href="escutas.php"><i class="fa-solid fa-headphones me-2"></i>Escutas</a></li>
              <?php endif; ?>

              <?php if ($cargo === 'Admin'): ?>
                <li><a class="dropdown-item" href="folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a></li>
              <?php endif; ?>

              <?php if ($cargo === 'Admin' || $cargo === 'Viewer'): ?>
                <li><a class="dropdown-item" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a></li>
              <?php endif; ?>

              <?php if ($cargo === 'Admin' || $cargo === 'Conversor' || $cargo === 'User'): ?>
                <li><a class="dropdown-item" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a></li>
              <?php endif; ?>

              <?php if ($cargo === 'Admin'): ?>
                <li><a class="dropdown-item" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a></li>
              <?php endif; ?>

              <?php if ($cargo === 'Admin'): ?>
                <li><a class="dropdown-item" href="dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a></li>
                <li><a class="dropdown-item" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a></li>
              <?php endif; ?>
              
            </ul>
          </div>

        <span class="text-white">
          Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!
        </span>
        <a href="menu.php" class="btn btn-danger">
          <i class="fa-solid fa-arrow-left me-2" style="font-size: 0.8em;"></i>Voltar
        </a>
      </div>
    </nav>

    <!-- Conteúdo Principal -->
    <div class="container user-container mt-4">
      <div class="row mb-4">
        <!-- Linha com Média de Notas -->
        <div class="col-lg-6 col-md-6 mb-3">
          <div class="card text-center border-secondary">
            <div class="card-body">
              <h5 class="card-title">Média das Notas</h5>
              <p class="card-text display-6 mt-4 <?php echo $classeMedia; ?>">
                <?php echo $mediaFormatada; ?>
              </p>
              <p class="<?php echo $classeMedia; ?>">
                <?php echo $textoMedia; ?>
              </p>
            </div>
          </div>
        </div>

        <!-- Ranking dos Melhores Usuários -->
        <div class="col-lg-6 mb-3">
          <div class="card text-center border-dark">
            <div class="card-body">
              <h5 class="card-title">Ranking</h5>
              <?php if (count($ranking) > 0): ?>
                <div class="ranking-scroll">
                  <!-- Área com scroll -->
                  <ul class="list-group">
                    <?php foreach ($ranking as $index => $rank): ?>
                      <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?php
                          if ($index == 0) {
                            echo "🥇  " . $rank['usuario_nome'];
                          } elseif ($index == 1) {
                            echo "🥈  " . $rank['usuario_nome'];
                          } elseif ($index == 2) {
                            echo "🥉  " . $rank['usuario_nome'];
                          } else {
                            echo ($index + 1) . "º  " . $rank['usuario_nome'];
                          }
                        ?>
                        <span class="badge bg-primary rounded-pill">
                          <?php echo number_format($rank['mediaNotas'], 2, ',', '.'); ?>
                        </span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php else: ?>
                <p>Nenhum ranking disponível.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Total de Análises -->
        <div class="col-lg-6 col-md-6 mb-3">
          <div class="card text-center border-primary">
            <div class="card-body">
              <h5 class="card-title text-primary">Total de Análises</h5>
              <p class="card-text display-6">
                <?php echo $totalAnalises; ?>
              </p>
            </div>
          </div>
        </div>

        <!-- Total de Fichas -->
        <div class="col-lg-6 col-md-6 mb-3">
          <div class="card text-center border-info">
            <div class="card-body">
              <h5 class="card-title text-info">Total de Fichas</h5>
              <p class="card-text display-6">
                <?php echo $totalFichas; ?>
              </p>
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
                <div class="table-responsive table-scroll">
                  <table class="table table-striped mt-0 ">
                    <thead>
                      <tr>
                        <th>Descrição</th>
                        <th>Nº da Ficha</th>
                        <th>Data</th>
                        <th>Nota</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($analises as $analise):
                        $nota = $analise['Nota'];
                        if ($nota == 5) {
                          $classeNota = 'nota-verde';
                        } elseif ($nota == 4) {
                          $classeNota = 'nota-teal';
                        } elseif ($nota == 3) {
                          $classeNota = 'nota-amarela';
                        } elseif ($nota == 2) {
                          $classeNota = 'nota-laranja';
                        } elseif ($nota == 1) {
                          $classeNota = 'nota-vermelha';
                        } elseif ($nota == 0) {
                          $classeNota = 'nota-neutra';
                        } else {
                          $classeNota = '';
                        }
                      ?>
                        <!-- Linha clicável para exibir a justificativa -->
                        <tr class="clickable"
                            data-justificativa="<?php echo htmlspecialchars($analise['justificativa'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-usuario="<?php echo htmlspecialchars($analise['Usuario'], ENT_QUOTES, 'UTF-8'); ?>"
                            onclick="mostrarJustificativaModal(this.getAttribute('data-justificativa'), this.getAttribute('data-usuario'))">
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
                <div class="alert alert-info">
                  Nenhuma análise cadastrada.
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Modal para exibir a Justificativa -->
        <div class="modal fade" id="justificativaModal" tabindex="-1" aria-labelledby="justificativaModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <!-- Cabeçalho do Modal -->
              <div class="modal-header">
                <div class="d-flex flex-column">
                  <h5 class="modal-title" id="justificativaModalLabel">
                    Justificativa da Nota
                  </h5>
                  <small class="text-muted">
                    Atribuído por: <span id="modalUsuario"></span>
                  </small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
              </div>
              <!-- Corpo do Modal -->
              <div class="modal-body" id="justificativaModalBody">
                <!-- Conteúdo da justificativa inserido dinamicamente -->
              </div>
              <!-- Rodapé do Modal -->
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
              </div>
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
                <div class="table-responsive table-scroll">
                  <table class="table table-striped mt-0">
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
                              <a href="https://zmap.zpos.com.br/#/detailsIncidente/<?php echo $ficha['numeroFicha']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                Acessar
                              </a>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="alert alert-info">
                  Nenhuma ficha cadastrada.
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bootstrap Bundle com Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      function mostrarJustificativaModal(justificativa, usuario) {
        // Insere a justificativa no corpo do modal
        document.getElementById("justificativaModalBody").innerText = justificativa;
        // Atualiza o campo de usuário no modal
        document.getElementById("modalUsuario").innerText = usuario;
        // Cria a instância do modal e exibe-o
        var modalElement = document.getElementById("justificativaModal");
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
      }
    </script>
  </body>
</html>
