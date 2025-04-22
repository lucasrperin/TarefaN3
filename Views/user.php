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
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Meu Painel</title>
  <!-- Fontes, Bootstrap, Font‑Awesome e CSS -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="../Public/user.css" rel="stylesheet">
  <link rel="icon" href="../Public/Image/icone2.png" type="image/png">
</head>
<body class="bg-light">
  <div class="d-flex-wrapper">
    <!-- SIDEBAR -->
    <div class="sidebar">
      <a class="light-logo mb-4" href="user.php">
        <img src="../Public/Image/zucchetti_blue.png" width="150" alt="Logo Zucchetti">
      </a>
      <nav class="nav flex-column">
        <a class="nav-link" href="menu.php"><i class="fa-solid fa-house me-2"></i>Home</a>
        <?php if(in_array($cargo,['Admin','Conversor','Viewer'])): ?>
          <a class="nav-link" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a>
        <?php endif;?>
        <?php if($cargo==='Admin'): ?>
          <a class="nav-link" href="escutas.php"><i class="fa-solid fa-headphones me-2"></i>Escutas</a>
          <a class="nav-link" href="folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a>
        <?php endif;?>
        <?php if(in_array($cargo,['Admin','Viewer'])): ?>
          <a class="nav-link" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a>
        <?php endif;?>
        <?php if(in_array($cargo,['Admin','Conversor','User'])): ?>
          <a class="nav-link" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a>
        <?php endif;?>
        <a class="nav-link active" href="user.php"><i class="fa-solid fa-users-rectangle me-2"></i>Meu Painel</a>
        <?php if($cargo==='Admin'): ?>
          <a class="nav-link" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a>
          <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-calculator me-2"></i>Totalizadores</a>
          <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a>
        <?php endif;?>
      </nav>
    </div>

  <!-- ÁREA PRINCIPAL -->
  <div class="w-100">
    <!-- HEADER (inalterado) -->
    <div class="header">
      <h3>Meu Painel</h3>
      <div class="user-info">
        <span>Bem‑vindo, <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>!</span>
        <a href="logout.php" class="btn btn-danger btn-sm">
          <i class="fa-solid fa-right-from-bracket"></i>
        </a>
      </div>
    </div>

    <div class="content">
      <!-- 1) Resumo Geral -->
      <div class="row summary-cards gx-4 mb-5 align-items-stretch">
      <!-- Média das Notas -->
      <div class="col-sm-6 col-lg-3">
        <div class="card summary-card modern h-100">
          <div class="card-body text-center p-4">
            <div class="icon-circle bg-primary mb-3">
              <i class="fa-solid fa-star text-white"></i>
            </div>
            <small class="label">Média das Notas</small>
            <div class="count <?php echo $classeMedia; ?>">
              <?php echo $mediaFormatada; ?>
            </div>
            <small class="text-<?php echo $classeMedia; ?>">
              <?php echo $textoMedia; ?>
            </small>
          </div>
        </div>
      </div>

      <!-- Ranking Completo -->
      <div class="col-sm-6 col-lg-3">
        <div class="card summary-card modern h-100">
          <div class="card-body text-center p-4">
            <div class="icon-circle bg-warning mb-3">
              <i class="fa-solid fa-trophy text-white"></i>
            </div>
            <small class="label">Ranking Completo</small>
            <ul class="ranking-scroll modern-scroll text-start small mb-0 ps-0">
              <?php if(count($ranking)>0): ?>
                <?php foreach($ranking as $i=>$r): ?>
                <li class="d-flex justify-content-between py-1 border-bottom">
                  <span>
                    <?php echo ($i<3?['🥇','🥈','🥉'][$i]:($i+1).'º') 
                              .' '.htmlspecialchars($r['usuario_nome']); ?>
                  </span>
                  <span><?php echo number_format($r['mediaNotas'],2,',','.'); ?></span>
                </li>
                <?php endforeach; ?>
              <?php else: ?>
                <li class="text-center text-muted py-2">Nenhum ranking</li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
      </div>

      <!-- Total de Análises -->
      <div class="col-sm-6 col-lg-3">
        <div class="card summary-card modern h-100">
          <div class="card-body text-center p-4">
            <div class="icon-circle bg-primary mb-3">
              <i class="fa-solid fa-chart-line text-white"></i>
            </div>
            <small class="label">Total de Análises</small>
            <div class="count">
              <?php echo number_format($totalAnalises,0,',','.'); ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Total de Fichas -->
      <div class="col-sm-6 col-lg-3">
        <div class="card summary-card modern h-100">
          <div class="card-body text-center p-4">
            <div class="icon-circle bg-info mb-3">
              <i class="fa-solid fa-clipboard-list text-white"></i>
            </div>
            <small class="label">Total de Fichas</small>
            <div class="count">
              <?php echo number_format($totalFichas,0,',','.'); ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>


  <div class="row gx-4 p-4">
    <div class="col-md-6">
      <!-- Seção Análises -->
      <div class="section-group">
        <div class="section-title">
          <i class="fa-solid fa-magnifying-glass-chart"></i>
          Análises
        </div>
        <div class="section-content">
          <div class="accordion" id="analisesAccordion">
            <?php foreach($analises as $i => $a): 
              
              $nota = $a['Nota'];
              if ($nota >= 4.5) {
                $notaClass = 'nota-verde';
              } elseif ($nota <= 2.99) {
                $notaClass = 'nota-vermelha';
              } else {
                $notaClass = 'nota-amarela';
              }

              ?>
              
            <div class="accordion-item">
              <h2 class="accordion-header" id="analiseHeading<?= $i ?>">
                <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#analiseCollapse<?= $i ?>"
                        aria-expanded="false"
                        aria-controls="analiseCollapse<?= $i ?>">
                  <span class="desc flex-fill text-truncate">
                    <?php echo htmlspecialchars($a['Descricao']); ?>
                  </span>
                  <span class="badge ms-auto nota-<?php echo $a['Nota']; ?>">
                    <?php echo $a['Nota']; ?> <i class="fa-solid fa-star text-white ms-1"></i>
                  </span>
                </button>
              </h2>
              <div id="analiseCollapse<?= $i ?>"
                  class="accordion-collapse collapse"
                  aria-labelledby="analiseHeading<?= $i ?>"
                  data-bs-parent="#analisesAccordion">
                <div class="accordion-body">
                  <div class="info-line">
                    <span class="info-label">Ficha:</span>
                    <span class="info-value"><?php echo $a['numeroFicha']?:'-'; ?></span>
                  </div>
                  <div class="info-line">
                    <span class="info-label">Data:</span>
                    <span class="info-value"><?php echo htmlspecialchars($a['Hora_ini']); ?></span>
                  </div>
                  <div class="info-line">
                    <span class="info-label">Justificativa:</span>
                    <span class="info-value"><?php echo htmlspecialchars($a['justificativa']); ?></span>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if(count($analises)===0): ?>
            <div class="text-center text-muted py-4">Nenhuma análise cadastrada.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <!-- Seção Fichas -->
      <div class="section-group">
        <div class="section-title">
          <i class="fa-solid fa-file-lines"></i>
          Fichas
        </div>
        <div class="section-content">
          <div class="accordion" id="fichasAccordion">
            <?php $idx=0; foreach($fichas_por_numero as $fs): foreach($fs as $f): $idx++; ?>
            <div class="accordion-item">
              <h2 class="accordion-header" id="fichaHeading<?= $idx ?>">
                <button class="accordion-button collapsed" type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#fichaCollapse<?= $idx ?>"
                        aria-expanded="false"
                        aria-controls="fichaCollapse<?= $idx ?>">
                  <span class="desc flex-fill text-truncate">
                    <?php echo htmlspecialchars($f['numeroFicha']); ?>
                  </span>
                  <small class="text-muted ms-auto"><?php echo htmlspecialchars($f['Hora_ini']); ?></small>
                </button>
              </h2>
              <div id="fichaCollapse<?= $idx ?>"
                  class="accordion-collapse collapse"
                  aria-labelledby="fichaHeading<?= $idx ?>"
                  data-bs-parent="#fichasAccordion">
                <div class="accordion-body d-flex justify-content-end">
                  <a href="https://zmap.zpos.com.br/#/detailsIncidente/<?php echo htmlspecialchars($f['numeroFicha']); ?>"
                    target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Abrir no ZMap
                  </a>
                </div>
              </div>
            </div>
            <?php endforeach; endforeach; ?>
            <?php if(count($fichas_por_numero)===0): ?>
            <div class="text-center text-muted py-4">Nenhuma ficha cadastrada.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
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
            <small class="text-muted" style="color: #fff">
              Atribuído por: <span id="modalUsuario"></span>
            </small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <!-- Corpo do Modal -->
        <div class="modal-body" style="overflow-wrap: break-word; " id="justificativaModalBody">
        <!-- Conteúdo da justificativa inserido dinamicamente -->
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
