<?php
include '../Config/Database.php';
session_start();
// Define aba ativa com query param
$activeTab = $_GET['tab'] ?? 'incidentes';
// Determina aba ativa de recorrentes
$rec_success = isset($_GET['msg']) && $_GET['msg'] === 'rec_success';

// Verifica se o usuário está logado; se não, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

// Define o cargo do usuário (supondo que ele esteja armazenado na sessão, com a chave "cargo")
$usuario_id = $_SESSION['usuario_id'];
$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// pendentes (resolvido=0) primeiro, depois os já resolvidos, e sempre os mais recentes acima
$resultRec = $conn->query("
    SELECT *
      FROM TB_RECORRENTES
  ORDER BY resolvido ASC, created_at DESC
");
// Função para mapear o nome do sistema para o caminho do ícone correspondente
function getIconPath($system) {
    switch($system) {
        case 'ClippPRO':
            return '../Public/Image/ClippPro.png';
        case 'ZWEB':
            return '../Public/Image/ZWeb.png';
        case 'Clipp360':
            return '../Public/Image/Clipp360.png';
        case 'ClippFácil':
        case 'ClippFacil': // Atende tanto com acento quanto sem
            return '../Public/Image/ClippFacil.png';
        case 'Conversor':
            return '../Public/Image/Conversor.png';
        default:
            return '../Public/Image/default.png';
    }
}

// ======== TOTALIZADORES E DADOS PARA O GRÁFICO ========

// Total por sistema
$systemTotalsSql = "SELECT sistema, COUNT(*) as total FROM TB_INCIDENTES GROUP BY sistema";
$systemTotals = $conn->query($systemTotalsSql);

// Total por gravidade
$severityTotalsSql = "SELECT gravidade, COUNT(*) as total FROM TB_INCIDENTES GROUP BY gravidade";
$severityTotals = $conn->query($severityTotalsSql);

// Dados mensais por gravidade para o gráfico
$sqlMonthly = "
    SELECT MONTH(hora_inicio) AS mes, gravidade, COUNT(*) AS total
    FROM TB_INCIDENTES
    WHERE YEAR(hora_inicio) = YEAR(CURDATE())
    GROUP BY mes, gravidade
    ORDER BY mes
";
$monthlyResult = $conn->query($sqlMonthly);

// Inicializa arrays para os 12 meses (índices de 1 a 12)
$dataModerado   = array_fill(1, 12, 0);
$dataGrave      = array_fill(1, 12, 0);
$dataGravissimo = array_fill(1, 12, 0);

while($row = $monthlyResult->fetch_assoc()){
    $mes = (int)$row['mes'];
    if($row['gravidade'] == 'Moderado'){
        $dataModerado[$mes] = (int)$row['total'];
    } elseif($row['gravidade'] == 'Grave'){
        $dataGrave[$mes] = (int)$row['total'];
    } elseif($row['gravidade'] == 'Gravissimo'){
        $dataGravissimo[$mes] = (int)$row['total'];
    }
}
$labels = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

// ======== CONSULTAS PARA AS TABELAS DE INCIDENTES ========

// Incidentes Desktop (sistema ClippPRO)
$sqlDesktop = "SELECT * FROM TB_INCIDENTES WHERE sistema = 'ClippPRO' ORDER BY hora_inicio DESC";
$resultDesktop = $conn->query($sqlDesktop);

// Incidentes Web (sistemas: ZWEB, Clipp360, ClippFácil, Conversor)
$sqlWeb = "
    SELECT * 
    FROM TB_INCIDENTES 
    WHERE sistema IN ('ZWEB','Clipp360','ClippFácil','Conversor') 
    ORDER BY hora_inicio DESC
";
$resultWeb = $conn->query($sqlWeb);

$rec_success = (isset($_GET['msg']) && $_GET['msg']==='rec_success');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Incidentes Registrados - Painel N3</title>
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <!-- CSS externos -->
  <link rel="icon" href="../Public/Image/LogoTituto.png" type="image/png">
  <link rel="stylesheet" href="../Public/incidentes.css">
  <link rel="stylesheet" href="../Public/usuarios.css">
</head>
<body class="bg-light">
  <div class="d-flex-wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
      <a class="light-logo" href="usuarios.php">
        <img src="../Public/Image/zucchetti_blue.png" width="150" alt="Logo Zucchetti">
      </a>
      <nav class="nav flex-column">
      <a class="nav-link" href="menu.php"><i class="fa-solid fa-house me-2"></i>Home</a>
        <?php if ($cargo === 'Admin' || $cargo === 'Conversor'): ?>
          <a class="nav-link" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="destaque.php"><i class="fa-solid fa-ranking-star me-2"></i>Destaques</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="escutas.php"><i class="fa-solid fa-headphones me-2"></i>Escutas</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link active" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'User' || $cargo === 'Conversor'): ?>
          <a class="nav-link" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin' || $cargo === 'Viewer' || $cargo === 'User' || $cargo === 'Conversor'): ?>
          <a class="nav-link" href="user.php"><i class="fa-solid fa-users-rectangle me-2"></i>Meu Painel</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'Treinamento'): ?>
          <a class="nav-link" href="treinamento.php"><i class="fa-solid fa-calendar-check me-2"></i>Treinamentos</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a>
        <?php endif; ?>
      </nav>
    </div>
    <!-- Main Content -->
    <div class="w-100">
      <!-- Header -->
      <div class="header">
        <h3>Controle de Incidentes</h3>
        <div class="user-info">
          <span>Bem-vindo(a), <?php echo htmlspecialchars($_SESSION['usuario_nome'], ENT_QUOTES, 'UTF-8'); ?>!</span>
          <a href="logout.php" class="btn btn-danger">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
          </a>
        </div>
      </div>
      <!-- Conteúdo principal -->
      <div class="content container-fluid">


<ul class="nav nav-tabs mb-4" id="mainTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link <?php echo $activeTab==='incidentes'?'active':''; ?>" 
            id="tab-incidentes" 
            data-bs-toggle="tab" 
            data-bs-target="#pane-incidentes" 
            type="button">
      Incidentes
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?php echo $activeTab==='recorrentes'?'active':''; ?>" 
            id="tab-recorrentes" 
            data-bs-toggle="tab" 
            data-bs-target="#pane-recorrentes" 
            type="button">
      Recorrentes
    </button>
  </li>
</ul>
<div class="tab-content" id="mainTabsContent">
  <div class="tab-pane fade <?php echo $activeTab==='incidentes'?'show active':''; ?>" 
       id="pane-incidentes" 
       role="tabpanel"> 

        
        <!-- Alerta de sucesso -->
        <?php if (isset($_GET['msg'])): ?>
          <?php if ($_GET['msg'] == 'success'): ?>
              <div class="alert alert-success" role="alert" id="alert-msg">
                  Incidente registrado com sucesso!
              </div>
        <div class="tab-pane fade <?php if ($rec_success) echo 'show active'; ?>" id="pane-recorrentes" role="tabpanel">
          <h2 class="mb-4">Recorrentes Registrados</h2>
          <!-- Botão de cadastro -->
          <div class="mb-4">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastroRecorrente">
              Registrar Recorrente
            </button>
          </div>
          <!-- Tabela de recorrentes -->
          <div class="card">
            <div class="card-body p-0">
              <?php if(isset($resultRec) && $resultRec->num_rows): ?>
                <table class="table table-striped mb-0">
                  <thead>
                    <tr>
                      <th>ID</th><th>Situação</th><th>Cards</th><th>Cadastrado em</th><th>Status</th><th>Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php while($r = $resultRec->fetch_assoc()):
                      $cardsRes = $conn->query("SELECT card_num FROM TB_RECORRENTES_CARDS WHERE recorrente_id={$r['id']}");
                      $cards = [];
                      while($c = $cardsRes->fetch_assoc()) $cards[] = $c['card_num'];
                    ?>
                    <tr>
                      <td><?= $r['id'] ?></td>
                      <td><?= htmlspecialchars($r['situacao']) ?></td>
                      <td>
                        <?php foreach($cards as $n): ?>
                          <a href="https://zmap.zpos.com.br/#/detailsIncidente/<?= $n ?>" target="_blank"><?= $n ?></a><br>
                        <?php endforeach; ?>
                      </td>
                      <td><?= $r['created_at'] ?></td>
                      <td>
                        <?php if($r['resolvido']): ?>
                          <span class="badge bg-success">Resolvido</span>
                        <?php else: ?>
                          <span class="badge bg-warning text-dark">Pendente</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <a href="resolver_recorrente.php?id=<?= $r['id'] ?>"
                           class="btn btn-sm <?= $r['resolvido'] ? 'btn-secondary' : 'btn-success' ?>"
                           onclick="return confirm('Tem certeza?');">
                          <?= $r['resolvido'] ? 'Reabrir' : 'Marcar resolvido' ?>
                        </a>
                      </td>
                    </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>
              <?php else: ?>
                <p class="m-3 text-muted">Nenhum caso recorrente cadastrado.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
    </div> <!-- fim mainTabsContent -->

          <?php elseif ($_GET['msg'] == 'edit_success'): ?>
              <div class="alert alert-success" role="alert" id="alert-msg">
                  Incidente atualizado com sucesso!
              </div>
          <?php elseif ($_GET['msg'] == 'delete_success'): ?>
              <div class="alert alert-success" role="alert" id="alert-msg">
                  Incidente excluído com sucesso!
              </div>
          <?php endif; ?>
          <script>
            setTimeout(function() {
                var alertElement = document.getElementById('alert-msg');
                if (alertElement) {
                    alertElement.style.display = 'none';
                }
            }, 3000);
          </script>
        <?php endif; ?>

        <!-- Accordion para o Gráfico -->
        <div class="accordion mb-4" id="accordionGraph">
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingGraph">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGraph" aria-expanded="false" aria-controls="collapseGraph">
                Exibir Gráfico
              </button>
            </h2>
            <div id="collapseGraph" class="accordion-collapse collapse" aria-labelledby="headingGraph">
              <div class="accordion-body">
                <!-- Linha para o Gráfico com 500px de altura -->
                <div class="row mb-4" style="height: 500px;">
                  <div class="col-12">
                    <div class="card" style="height: 100%; overflow: hidden;">
                      <div class="card-header bg-light">
                        <strong>Incidentes por Mês (Ano Atual)</strong>
                      </div>
                      <div class="card-body d-flex justify-content-center align-items-center" style="height: calc(100% - 56px);">
                        <canvas id="chartIncidents" style="width: 100%;"></canvas>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Accordion para os Totalizadores -->
        <div class="accordion mb-4" id="accordionTotalizadores">
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingTotalizadores">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTotalizadores" aria-expanded="false" aria-controls="collapseTotalizadores">
                Exibir Totalizadores
              </button>
            </h2>
            <div id="collapseTotalizadores" class="accordion-collapse collapse" aria-labelledby="headingTotalizadores">
              <div class="accordion-body">
                <div class="row">
                  <!-- Total por Sistema -->
                  <div class="col-md-6 mb-2">
                    <div class="card" style="overflow: auto;">
                      <div class="card-header bg-light">
                        <strong>Total por Sistema</strong>
                      </div>
                      <div class="card-body">
                        <?php if($systemTotals && $systemTotals->num_rows > 0): ?>
                          <table class="table table-sm">
                            <thead>
                              <tr>
                                <th>Sistema</th>
                                <th>Total</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php while($row = $systemTotals->fetch_assoc()): ?>
                                <tr>
                                  <td>
                                    <img src="<?= getIconPath($row['sistema']) ?>" alt="<?= $row['sistema'] ?>" style="height:30px; vertical-align: middle;">
                                    <span style="margin-left: 8px; vertical-align: middle;"><?= $row['sistema'] ?></span>
                                  </td>
                                  <td><?= $row['total'] ?></td>
                                </tr>
                              <?php endwhile; ?>
                            </tbody>
                          </table>
                        <?php else: ?>
                          <p class="text-muted">Nenhum incidente cadastrado.</p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  <!-- Total por Gravidade -->
                  <div class="col-md-6 mb-2">
                    <div class="card" style="overflow: auto;">
                      <div class="card-header bg-light">
                        <strong>Total por Gravidade</strong>
                      </div>
                      <div class="card-body">
                        <?php if($severityTotals && $severityTotals->num_rows > 0): ?>
                          <table class="table table-sm">
                            <thead>
                              <tr>
                                <th>Gravidade</th>
                                <th>Total</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php while($row = $severityTotals->fetch_assoc()): ?>
                                <tr>
                                  <td><?= $row['gravidade'] ?></td>
                                  <td><?= $row['total'] ?></td>
                                </tr>
                              <?php endwhile; ?>
                            </tbody>
                          </table>
                        <?php else: ?>
                          <p class="text-muted">Nenhum incidente cadastrado.</p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div> <!-- Fim dos Totalizadores -->
              </div>
            </div>
          </div>
        </div>

        <!-- BOTÃO PARA ABRIR O MODAL DE CADASTRO -->
        <div class="mb-4">
          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastro">
            Registrar Incidente
          </button>
        </div>

        <!-- Modal de Cadastro -->
        <div class="modal fade" id="modalCadastro" tabindex="-1" aria-labelledby="modalCadastroLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <form action="cadastrar_incidente.php" method="post">
                <div class="modal-header">
                  <h5 class="modal-title" id="modalCadastroLabel">Cadastrar Incidente</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <label for="sistema" class="form-label">Sistema</label>
                      <select class="form-select" id="sistema" name="sistema" required>
                        <option value="">Selecione o sistema</option>
                        <option value="ClippPRO">ClippPRO (Desktop)</option>
                        <option value="ZWEB">ZWEB (Web)</option>
                        <option value="Clipp360">Clipp360 (Web)</option>
                        <option value="ClippFácil">ClippFácil (Web)</option>
                        <option value="Conversor">Conversor (Web)</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label for="gravidade" class="form-label">Gravidade</label>
                      <select class="form-select" id="gravidade" name="gravidade" required>
                        <option value="">Selecione a gravidade</option>
                        <option value="Moderado">Moderado</option>
                        <option value="Grave">Grave</option>
                        <option value="Gravissimo">Gravissimo</option>
                      </select>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <div class="col-12">
                      <label for="problema" class="form-label">Descrição do Problema</label>
                      <textarea class="form-control" id="problema" name="problema" rows="3" required></textarea>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <label for="hora_inicio" class="form-label">Horário de Início</label>
                      <input type="datetime-local" class="form-control" id="hora_inicio" name="hora_inicio" required>
                    </div>
                    <div class="col-md-6">
                      <label for="hora_fim" class="form-label">Horário de Término</label>
                      <input type="datetime-local" class="form-control" id="hora_fim" name="hora_fim" required>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <label for="tempo_total" class="form-label">Tempo Total (calculado)</label>
                      <input type="text" class="form-control" id="tempo_total" name="tempo_total" readonly>
                    </div>
                    <div class="col-md-6">
                      <label for="indisponibilidade" class="form-label">Tipo de Indisponibilidade</label>
                      <select class="form-select" id="indisponibilidade" name="indisponibilidade" required>
                        <option value="">Selecione</option>
                        <option value="Total">Total</option>
                        <option value="Parcial">Parcial</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                  <button type="submit" class="btn btn-primary">Gravar</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Modal de Edição -->
        <div class="modal fade" id="modalEdicao" tabindex="-1" aria-labelledby="modalEdicaoLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <form action="editar_incidente.php" method="post" id="formEdicao">
                <div class="modal-header">
                  <h5 class="modal-title" id="modalEdicaoLabel">Editar Incidente</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                  <input type="hidden" name="id" id="edit_id">
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <label for="edit_sistema" class="form-label">Sistema</label>
                      <select class="form-select" name="sistema" id="edit_sistema" required>
                        <option value="">Selecione o sistema</option>
                        <option value="ClippPRO">ClippPRO (Desktop)</option>
                        <option value="ZWEB">ZWEB (Web)</option>
                        <option value="Clipp360">Clipp360 (Web)</option>
                        <option value="ClippFácil">ClippFácil (Web)</option>
                        <option value="Conversor">Conversor (Web)</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label for="edit_gravidade" class="form-label">Gravidade</label>
                      <select class="form-select" name="gravidade" id="edit_gravidade" required>
                        <option value="">Selecione a gravidade</option>
                        <option value="Moderado">Moderado</option>
                        <option value="Grave">Grave</option>
                        <option value="Gravissimo">Gravissimo</option>
                      </select>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <div class="col-12">
                      <label for="edit_problema" class="form-label">Descrição do Problema</label>
                      <textarea class="form-control" name="problema" id="edit_problema" rows="3" required></textarea>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <label for="edit_hora_inicio" class="form-label">Horário de Início</label>
                      <input type="datetime-local" class="form-control" name="hora_inicio" id="edit_hora_inicio" required>
                    </div>
                    <div class="col-md-6">
                      <label for="edit_hora_fim" class="form-label">Horário de Término</label>
                      <input type="datetime-local" class="form-control" name="hora_fim" id="edit_hora_fim" required>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <label for="edit_tempo_total" class="form-label">Tempo Total (calculado)</label>
                      <input type="text" class="form-control" name="tempo_total" id="edit_tempo_total" readonly>
                    </div>
                    <div class="col-md-6">
                      <label for="edit_indisponibilidade" class="form-label">Tipo de Indisponibilidade</label>
                      <select class="form-select" name="indisponibilidade" id="edit_indisponibilidade" required>
                        <option value="">Selecione</option>
                        <option value="Total">Total</option>
                        <option value="Parcial">Parcial</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                  <button type="submit" class="btn btn-primary">Atualizar</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Tabelas de Incidentes -->
        <div class="row">
          <!-- Incidentes Desktop -->
          <div class="col-md-6 mb-4">
            <div class="card">
              <div class="card-header bg-light">
                <strong>Incidentes Desktop</strong>
              </div>
              <div class="card-body">
                <?php if($resultDesktop && $resultDesktop->num_rows > 0): ?>
                  <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                      <thead class="table-light">
                        <tr>
                          <th>Sistema</th>
                          <th>Gravidade</th>                          
                          <th>Tempo Total</th>
                          <th>Ações</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php while($row = $resultDesktop->fetch_assoc()):
                          $gravidadeClass = '';
                          if($row['gravidade'] == 'Moderado'){
                              $gravidadeClass = 'badge-moderado';
                          } elseif($row['gravidade'] == 'Grave'){
                              $gravidadeClass = 'badge-grave';
                          } elseif($row['gravidade'] == 'Gravissimo'){
                              $gravidadeClass = 'badge-gravissimo';
                          }
                        ?>
                          <tr>
                            <td>
                              <img src="<?= getIconPath($row['sistema']) ?>" alt="<?= $row['sistema'] ?>" style="height:30px; vertical-align: middle;">
                              <span style="margin-left: 8px; vertical-align: middle;"><?= $row['sistema'] ?></span>
                            </td>
                            <td>
                              <span class="badge <?= $gravidadeClass ?>">
                                <?= $row['gravidade'] ?>
                              </span>
                            </td>                                             
                            <td><?= $row['tempo_total'] ?></td>
                            <td>
                              <a href="javascript:void(0);" 
                                class="btn btn-sm btn-primary me-1"
                                onclick="openEditModal(
                                  '<?= $row['id'] ?>',
                                  '<?= $row['sistema'] ?>',
                                  '<?= $row['gravidade'] ?>',
                                  '<?= htmlspecialchars($row['problema'], ENT_QUOTES) ?>',
                                  '<?= date('Y-m-d\TH:i', strtotime($row['hora_inicio'])) ?>',
                                  '<?= date('Y-m-d\TH:i', strtotime($row['hora_fim'])) ?>',
                                  '<?= $row['tempo_total'] ?>',
                                  '<?= $row['indisponibilidade'] ?>'
                                );">
                                <i class="fa-solid fa-pen"></i>
                              </a>
                              <a href="deletar_incidente.php?id=<?= $row['id'] ?>"
                                class="btn btn-sm btn-danger"
                                onclick="return confirm('Tem certeza que deseja excluir este incidente?');">
                                <i class="fa-solid fa-trash"></i>
                              </a>
                            </td>
                          </tr>
                        <?php endwhile; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <p class="text-muted">Nenhum incidente desktop registrado.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <!-- Incidentes Web -->
          <div class="col-md-6 mb-4">
            <div class="card">
              <div class="card-header bg-light">
                <strong>Incidentes Web</strong>
              </div>
              <div class="card-body">
                <?php if($resultWeb && $resultWeb->num_rows > 0): ?>
                  <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                      <thead class="table-light">
                        <tr>
                          <th>Sistema</th>
                          <th>Gravidade</th>
                          <th>Tempo Total</th>
                          <th>Ações</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php while($row = $resultWeb->fetch_assoc()):
                          $gravidadeClass = '';
                          if($row['gravidade'] == 'Moderado'){
                            $gravidadeClass = 'badge-moderado';
                          } elseif($row['gravidade'] == 'Grave'){
                            $gravidadeClass = 'badge-grave';
                          } elseif($row['gravidade'] == 'Gravissimo'){
                            $gravidadeClass = 'badge-gravissimo';
                          }
                        ?>
                          <tr>
                            <td>
                              <img src="<?= getIconPath($row['sistema']) ?>" alt="<?= $row['sistema'] ?>" style="height:30px; vertical-align: middle;">
                              <span style="margin-left: 8px; vertical-align: middle;"><?= $row['sistema'] ?></span>
                            </td>
                            <td>
                              <span class="badge <?= $gravidadeClass ?>">
                                <?= $row['gravidade'] ?>
                              </span>
                            </td>
                            <td><?= $row['tempo_total'] ?></td>
                            <td>
                              <a href="javascript:void(0);" 
                                class="btn btn-sm btn-primary me-1"
                                onclick="openEditModal(
                                  '<?= $row['id'] ?>',
                                  '<?= $row['sistema'] ?>',
                                  '<?= $row['gravidade'] ?>',
                                  '<?= htmlspecialchars($row['problema'], ENT_QUOTES) ?>',
                                  '<?= date('Y-m-d\TH:i', strtotime($row['hora_inicio'])) ?>',
                                  '<?= date('Y-m-d\TH:i', strtotime($row['hora_fim'])) ?>',
                                  '<?= $row['tempo_total'] ?>',
                                  '<?= $row['indisponibilidade'] ?>'
                                );">
                                <i class="fa-solid fa-pen"></i>
                              </a>
                              <a href="deletar_incidente.php?id=<?= $row['id'] ?>"
                                class="btn btn-sm btn-danger"
                                onclick="return confirm('Tem certeza que deseja excluir este incidente?');">
                                <i class="fa-solid fa-trash"></i>
                              </a>
                            </td>
                          </tr>
                        <?php endwhile; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <p class="text-muted">Nenhum incidente web registrado.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div> <!-- Fim das Tabelas de Incidentes -->
      </div> <!-- Fim do Conteúdo principal -->
    </div> <!-- Fim do Main Content -->
  </div> <!-- Fim do d-flex-wrapper -->

  <?php include 'recorrentes.php'; ?>
  <!-- Scripts JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // Dados para o gráfico
    const chartLabels = <?= json_encode($labels) ?>;
    const dataModerado = <?= json_encode(array_values($dataModerado)) ?>;
    const dataGrave = <?= json_encode(array_values($dataGrave)) ?>;
    const dataGravissimo = <?= json_encode(array_values($dataGravissimo)) ?>;
    
    const ctx = document.getElementById('chartIncidents').getContext('2d');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: chartLabels,
        datasets: [
          {
            label: 'Moderado',
            data: dataModerado,
            backgroundColor: 'rgba(173,216,230,0.6)',
            borderColor: 'rgba(173,216,230,1)',
            borderWidth: 1
          },
          {
            label: 'Grave',
            data: dataGrave,
            backgroundColor: 'rgba(255,255,0,0.6)',
            borderColor: 'rgba(255,255,0,1)',
            borderWidth: 1
          },
          {
            label: 'Gravissimo',
            data: dataGravissimo,
            backgroundColor: 'rgba(255,0,0,0.6)',
            borderColor: 'rgba(255,0,0,1)',
            borderWidth: 1
          }
        ]
      },
      options: {
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: 'Quantidade de Incidentes'
            }
          },
          x: {
            title: {
              display: true,
              text: 'Meses'
            }
          }
        },
        plugins: {
          legend: {
            position: 'bottom'
          }
        }
      }
    });
    
    function openEditModal(
      id,
      sistema,
      gravidade,
      problema,
      hora_inicio,
      hora_fim,
      tempo_total,
      indisponibilidade
    ) {
      document.getElementById('edit_id').value = id;
      document.getElementById('edit_sistema').value = sistema;
      document.getElementById('edit_gravidade').value = gravidade;
      document.getElementById('edit_problema').value = problema;
      document.getElementById('edit_hora_inicio').value = hora_inicio;
      document.getElementById('edit_hora_fim').value = hora_fim;
      document.getElementById('edit_tempo_total').value = tempo_total;
      document.getElementById('edit_indisponibilidade').value = indisponibilidade;
      
      new bootstrap.Modal(document.getElementById('modalEdicao')).show();
    }
    
    function calcularTempoTotalEdicao() {
      const inicio = document.getElementById('edit_hora_inicio').value;
      const fim = document.getElementById('edit_hora_fim').value;
      if (inicio && fim) {
        const dataInicio = new Date(inicio);
        const dataFim = new Date(fim);
        const diffMs = dataFim - dataInicio;
        if (diffMs < 0) {
          document.getElementById('edit_tempo_total').value = 'Horário inválido';
          return;
        }
        let diffSegundos = Math.floor(diffMs / 1000);
        const horas = Math.floor(diffSegundos / 3600);
        diffSegundos %= 3600;
        const minutos = Math.floor(diffSegundos / 60);
        const segundos = diffSegundos % 60;
        document.getElementById('edit_tempo_total').value =
          horas.toString().padStart(2, '0') + ':' +
          minutos.toString().padStart(2, '0') + ':' +
          segundos.toString().padStart(2, '0');
      }
    }
    
    document.getElementById('edit_hora_inicio').addEventListener('change', calcularTempoTotalEdicao);
    document.getElementById('edit_hora_fim').addEventListener('change', calcularTempoTotalEdicao);
    
    function calcularTempoTotal() {
      const inicio = document.getElementById('hora_inicio').value;
      const fim = document.getElementById('hora_fim').value;
      if (inicio && fim) {
        const dataInicio = new Date(inicio);
        const dataFim = new Date(fim);
        const diffMs = dataFim - dataInicio;
        if (diffMs < 0) {
          document.getElementById('tempo_total').value = 'Horário inválido';
          return;
        }
        let diffSegundos = Math.floor(diffMs / 1000);
        const horas = Math.floor(diffSegundos / 3600);
        diffSegundos %= 3600;
        const minutos = Math.floor(diffSegundos / 60);
        const segundos = diffSegundos % 60;
        document.getElementById('tempo_total').value =
          horas.toString().padStart(2, '0') + ':' +
          minutos.toString().padStart(2, '0') + ':' +
          segundos.toString().padStart(2, '0');
      }
    }
    
    document.getElementById('hora_inicio').addEventListener('change', calcularTempoTotal);
    document.getElementById('hora_fim').addEventListener('change', calcularTempoTotal);
  </script>

<!-- Modal Cadastro Recorrente -->
<div class="modal fade" id="modalCadastroRecorrente" tabindex="-1" aria-labelledby="modalCadastroRecorrenteLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="cadastrar_recorrente.php" method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCadastroRecorrenteLabel">Cadastrar Recorrente</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="situacao_rec" class="form-label">Situação</label>
            <select class="form-select" id="situacao_rec" name="situacao" required>
              <option value="">Selecione</option>
              <option value="Movimentações">Movimentações</option>
              <option value="Comissões">Comissões</option>
              <option value="Notas que não geram receitas">Notas que não geram receitas</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="card_nums_rec" class="form-label">Números de Card (um por linha)</label>
            <textarea class="form-control" id="card_nums_rec" name="card_nums" rows="6" placeholder="22640&#10;22023&#10;21988" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
          <button type="submit" class="btn btn-primary">Gravar</button>
        </div>
      </form>
    </div>
  </div>
</div>

</body>
</html>
<?php
$conn->close();
?>
