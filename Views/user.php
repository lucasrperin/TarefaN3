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
  <link rel="icon" href="../Public/Image/LogoTituto.png" type="image/png">
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

      <!-- CONTENT (Layout 4) -->
      <div class="content">
        <div class="row gy-4">
          <!-- COLUNA ESQUERDA: MÉTRICAS -->
          <div class="col-lg-4 d-flex flex-column gap-4">
            <!-- Média das Notas -->
            <div class="card border-start border-4 border-secondary shadow-sm bg-tint-secondary">
              <div class="card-body d-flex align-items-center">
                <div class="bg-secondary text-white rounded-circle icon-circle me-3">
                  <i class="fa-solid fa-star"></i>
                </div>
                <div>
                  <small class="text-muted">Média das Notas</small>
                  <h5 class="<?php echo $classeMedia; ?>"><?php echo $mediaFormatada; ?></h5>
                  <small class="<?php echo $classeMedia; ?>"><?php echo $textoMedia; ?></small>
                </div>
              </div>
            </div>

            <!-- Ranking -->
            <div class="card border-start border-4 border-warning shadow-sm bg-tint-warning">
              <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center mb-2">
                  <div class="bg-warning text-white rounded-circle icon-circle me-2">
                    <i class="fa-solid fa-trophy"></i>
                  </div>
                  <h6 class="mb-0">Ranking</h6>
                </div>
                <?php if(count($ranking)>0): ?>
                  <ul class="list-unstyled small  ranking-scroll mb-0">
                    <?php foreach($ranking as $i=>$r): ?>
                      <li class="d-flex justify-content-between py-2 border-bottom">
                        <span>
                          <?php 
                            echo ($i<3? ['🥇','🥈','🥉'][$i] : ($i+1).'º')
                              .' '.htmlspecialchars($r['usuario_nome']);
                          ?>
                        </span>
                        <span class="badge bg-secondary rounded-pill">
                          <?php echo number_format($r['mediaNotas'],2,',','.'); ?>
                        </span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php else: ?>
                  <small class="text-muted">Nenhum ranking disponível</small>
                <?php endif; ?>
              </div>
            </div>

            <!-- Total de Análises -->
            <div class="card border-start border-4 border-primary shadow-sm bg-tint-primary">
              <div class="card-body d-flex align-items-center">
                <div class="bg-primary text-white rounded-circle icon-circle me-3">
                  <i class="fa-solid fa-chart-line"></i>
                </div>
                <div>
                  <small class="text-muted">Total de Análises</small>
                  <h4 class="mb-0"><?php echo $totalAnalises; ?></h4>
                </div>
              </div>
            </div>

            <!-- Total de Fichas -->
            <div class="card border-start border-4 border-info shadow-sm bg-tint-info">
              <div class="card-body d-flex align-items-center">
                <div class="bg-info text-white rounded-circle icon-circle me-3">
                  <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <div>
                  <small class="text-muted">Total de Fichas</small>
                  <h4 class="mb-0"><?php echo $totalFichas; ?></h4>
                </div>
              </div>
            </div>
          </div>

          <!-- COLUNA DIREITA: TABELAS -->
          <div class="col-lg-8 d-flex flex-column gap-4">
            <!-- Análises Recentes -->
            <div class="card border-0 shadow-sm h-100">
              <div class="card-header bg-transparent border-bottom-0 d-flex align-items-center">
                <i class="fa-solid fa-magnifying-glass-chart text-primary fa-lg me-2"></i>
                <h6 class="mb-0">Análises Recentes</h6>
              </div>
              <div class="table-responsive table-scroll" style="max-height:350px; overflow:auto;">
                <div class="grid-table">
                  <!-- header sem # -->
                  <div class="grid-header">
                    <div><i class="fa-solid fa-align-left me-1"></i>Descrição</div>
                    <div><i class="fa-solid fa-hashtag me-1"></i>Ficha</div>
                    <div><i class="fa-solid fa-calendar-day me-1"></i>Data</div>
                    <div><i class="fa-solid fa-star me-1"></i>Nota</div>
                  </div>
                  <!-- linhas -->
                  <?php foreach($analises as $a): ?>
                  <div class="grid-row clickable nota-<?php echo $a['Nota']; ?>"
                      data-justificativa="<?php echo htmlspecialchars($a['justificativa'],ENT_QUOTES); ?>"
                      data-usuario="<?php echo htmlspecialchars($a['Usuario'],ENT_QUOTES); ?>"
                      onclick="mostrarJustificativaModal(this.dataset.justificativa,this.dataset.usuario)">
                    <div class="sobrepor"><?php echo htmlspecialchars($a['Descricao']); ?></div>
                    <div><?php echo $a['numeroFicha']?: '-'; ?></div>
                    <div><?php echo htmlspecialchars($a['Hora_ini']); ?></div>
                    <div class="nota"><?php echo $a['Nota']; ?> <i class="fa-solid fa-star text-warning ms-1"></i></div>
                  </div>
                  <?php endforeach; ?>
                  <?php if(empty($analises)): ?>
                  <div class="grid-row">
                    <div colspan="4" class="text-center text-muted">Nenhuma análise cadastrada.</div>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Fichas Recentes -->
            <div class="card border-0 shadow-sm h-100 mt-4">
              <div class="card-header bg-transparent border-bottom-0 d-flex align-items-center">
                <i class="fa-solid fa-file-lines text-info fa-lg me-2"></i>
                <h6 class="mb-0">Fichas Recentes</h6>
              </div>
              <div class="table-responsive table-scroll" style="max-height:350px; overflow:auto;">
                <div class="grid-table">
                  <!-- header sem # -->
                  <div class="grid-header">
                    <div><i class="fa-solid fa-hashtag me-1"></i>Ficha</div>
                    <div><i class="fa-solid fa-calendar-day me-1"></i>Data</div>
                    <div style="justify-content:center;"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Ação</div>
                  </div>
                  <!-- linhas -->
                  <?php foreach($fichas_por_numero as $fs): foreach($fs as $f): ?>
                  <div class="grid-row">
                    <div><?php echo htmlspecialchars($f['numeroFicha']); ?></div>
                    <div><?php echo htmlspecialchars($f['Hora_ini']); ?></div>
                    <div>
                      <a href="https://zmap.zpos.com.br/#/detailsIncidente/<?php echo htmlspecialchars($f['numeroFicha']); ?>"
                        target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> ZMap
                      </a>
                    </div>
                  </div>
                  <?php endforeach; endforeach; ?>
                  <?php if(empty($fichas_por_numero)): ?>
                  <div class="grid-row">
                    <div colspan="3" class="text-center text-muted">Nenhuma ficha cadastrada.</div>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
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
