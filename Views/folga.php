<?php
// folga.php
include '../Config/Database.php';
session_start();

// Verifica se o usuário está logado; se não, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===================== RECEBE FILTROS (GET) =====================
$idEquipeFilter = isset($_GET['idEquipe']) ? $_GET['idEquipe'] : 'Todos';
$idNivelFilter = isset($_GET['idNivel']) ? $_GET['idNivel'] : 'Todos';

// ===================== CARREGA OPÇÕES DE FILTRO =====================
// Carrega as equipes
$sqlEquipes = "SELECT * FROM TB_EQUIPE ORDER BY descricao";
$resultEquipes = $conn->query($sqlEquipes);
if (!$resultEquipes) {
    die("Erro ao buscar equipes: " . $conn->error);
}
// Carrega os níveis
$sqlNiveis = "SELECT * FROM TB_NIVEL ORDER BY descricao";
$resultNiveis = $conn->query($sqlNiveis);
if (!$resultNiveis) {
    die("Erro ao buscar níveis: " . $conn->error);
}

// ===================== QUERY PRINCIPAL PARA O CALENDÁRIO (Aggregador) =====================
$sql = "SELECT f.id, u.Nome AS nome_colaborador, f.data_inicio, f.data_fim, f.tipo
        FROM TB_FOLGA f
        JOIN TB_USUARIO u ON f.usuario_id = u.Id";
$conditions = [];
// Se algum filtro for aplicado, faz join com TB_EQUIPE_NIVEL_ANALISTA
if ($idEquipeFilter != 'Todos' || $idNivelFilter != 'Todos') {
    $sql .= " JOIN TB_EQUIPE_NIVEL_ANALISTA eva ON u.Id = eva.idUsuario";
}
if ($idEquipeFilter != 'Todos') {
    $conditions[] = "eva.idEquipe = " . intval($idEquipeFilter);
}
if ($idNivelFilter != 'Todos') {
    $conditions[] = "eva.idNivel = " . intval($idNivelFilter);
}
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY f.data_inicio";
$result = $conn->query($sql);

$aggregator = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $start = strtotime($row['data_inicio']);
        $end   = strtotime($row['data_fim']);
        for ($d = $start; $d <= $end; $d += 86400) {
            $dayStr = date('Y-m-d', $d);
            $aggregator[$dayStr][] = [
                'nome' => $row['nome_colaborador'],
                'tipo' => $row['tipo']
            ];
        }
    }
}

// ===================== CONSULTA PARA COLABORADORES (para os selects) =====================
// Se houver filtro, retorna apenas os colaboradores que atendem à equipe e nível
if ($idEquipeFilter != 'Todos' || $idNivelFilter != 'Todos') {
    $sqlUsuarios = "SELECT u.Id, u.Nome FROM TB_USUARIO u
                     JOIN TB_EQUIPE_NIVEL_ANALISTA eva ON u.Id = eva.idUsuario
                     WHERE 1";
    if ($idEquipeFilter != 'Todos') {
        $sqlUsuarios .= " AND eva.idEquipe = " . intval($idEquipeFilter);
    }
    if ($idNivelFilter != 'Todos') {
        $sqlUsuarios .= " AND eva.idNivel = " . intval($idNivelFilter);
    }
    $sqlUsuarios .= " ORDER BY u.Nome";
} else {
    $sqlUsuarios = "SELECT Id, Nome FROM TB_USUARIO ORDER BY Nome";
}
$resultUsuarios = $conn->query($sqlUsuarios);
if (!$resultUsuarios) {
    die("Erro ao buscar usuários: " . $conn->error);
}

// ===================== PROCESSA CADASTRO (POST) =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cadastrar') {
    $usuario_id    = $_POST['usuario_id']    ?? '';
    $tipo          = $_POST['tipo']          ?? '';
    $data_inicio   = $_POST['data_inicio']   ?? '';
    $data_fim      = $_POST['data_fim']      ?? '';
    $justificativa = $_POST['justificativa'] ?? '';

    if (!empty($data_inicio) && !empty($data_fim)) {
        $dtInicio = strtotime($data_inicio);
        $dtFim    = strtotime($data_fim);
        if ($dtInicio !== false && $dtFim !== false && $dtFim >= $dtInicio) {
            $diffSegundos    = $dtFim - $dtInicio;
            $quantidade_dias = floor($diffSegundos / 86400) + 1;
        } else {
            $quantidade_dias = 0;
        }
        if ($quantidade_dias >= 1) {
            $sqlInsert = "INSERT INTO TB_FOLGA 
                            (usuario_id, tipo, data_inicio, data_fim, quantidade_dias, justificativa)
                          VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sqlInsert);
            if ($stmt) {
                $stmt->bind_param("isssis", 
                    $usuario_id, 
                    $tipo, 
                    $data_inicio, 
                    $data_fim, 
                    $quantidade_dias,
                    $justificativa
                );
                $stmt->execute();
                $stmt->close();
            } else {
                echo "Erro na preparação da query: " . $conn->error;
            }
        }
    }
    header("Location: folga.php");
    exit();
}

// ===================== CONSULTAS PARA LISTAGEM (Tabelas) =====================
// Para Ferias
$sqlListarFerias = "SELECT f.id, f.usuario_id, u.Nome AS nome_colaborador, f.data_inicio, f.data_fim, f.quantidade_dias
                    FROM TB_FOLGA f
                    JOIN TB_USUARIO u ON f.usuario_id = u.Id";
$conditionsFerias = ["f.tipo = 'Ferias'"];
if ($idEquipeFilter != 'Todos' || $idNivelFilter != 'Todos') {
    $sqlListarFerias .= " JOIN TB_EQUIPE_NIVEL_ANALISTA eva ON u.Id = eva.idUsuario";
}
if ($idEquipeFilter != 'Todos') {
    $conditionsFerias[] = "eva.idEquipe = " . intval($idEquipeFilter);
}
if ($idNivelFilter != 'Todos') {
    $conditionsFerias[] = "eva.idNivel = " . intval($idNivelFilter);
}
if (!empty($conditionsFerias)) {
    $sqlListarFerias .= " WHERE " . implode(" AND ", $conditionsFerias);
}
$sqlListarFerias .= " ORDER BY f.id DESC";
$resultFerias = $conn->query($sqlListarFerias);

// Para Folga
$sqlListarFolga = "SELECT f.id, f.usuario_id, u.Nome AS nome_colaborador, f.data_inicio, f.data_fim, f.quantidade_dias, f.justificativa
                   FROM TB_FOLGA f
                   JOIN TB_USUARIO u ON f.usuario_id = u.Id";
$conditionsFolga = ["f.tipo = 'Folga'"];
if ($idEquipeFilter != 'Todos' || $idNivelFilter != 'Todos') {
    $sqlListarFolga .= " JOIN TB_EQUIPE_NIVEL_ANALISTA eva ON u.Id = eva.idUsuario";
}
if ($idEquipeFilter != 'Todos') {
    $conditionsFolga[] = "eva.idEquipe = " . intval($idEquipeFilter);
}
if ($idNivelFilter != 'Todos') {
    $conditionsFolga[] = "eva.idNivel = " . intval($idNivelFilter);
}
if (!empty($conditionsFolga)) {
    $sqlListarFolga .= " WHERE " . implode(" AND ", $conditionsFolga);
}
$sqlListarFolga .= " ORDER BY f.id DESC";
$resultFolga = $conn->query($sqlListarFolga);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Controle de Férias e Folgas</title>
  <!-- Bootstrap 5 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
  <!-- FullCalendar CSS -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet" />
  <!-- Flatpickr CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- CSS customizado -->
  <link rel="stylesheet" href="../Public/folga.css">
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
  <div class="container d-flex justify-content-between align-items-center">
    <div class="dropdown">
      <button class="navbar-toggler" type="button" data-bs-toggle="dropdown">
        <span class="navbar-toggler-icon"></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-dark">
        <li><a class="dropdown-item" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a></li>
        <li><a class="dropdown-item" href="escutas.php"><i class="fa-solid fa-headphones me-2"></i>Escutas</a></li>
        <li><a class="dropdown-item" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a></li>
        <li><a class="dropdown-item" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-1"></i>Indicações</a></li>
        <li><a class="dropdown-item" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a></li>
        <li><a class="dropdown-item" href="dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a></li>
        <li><a class="dropdown-item" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a></li>
      </ul>
    </div>
    <span class="text-white">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
    <a href="menu.php" class="btn btn-danger">
      <i class="fa-solid fa-arrow-left me-2" style="font-size: 0.8em;"></i>Voltar
    </a>
  </div>
</nav>

<div class="container my-5">
  <!-- Formulário de filtro -->
  <form method="GET" class="filter mb-4">
  <div class="row g-3 justify-content-center">
    <div class="col-auto">
      <label for="idEquipe" class="form-label fw-semibold">Equipe:</label>
      <select class="form-select" name="idEquipe" id="idEquipe">
        <option value="Todos">Todos</option>
        <?php while($equipe = $resultEquipes->fetch_assoc()): ?>
          <option value="<?php echo $equipe['id']; ?>" <?php echo ($idEquipeFilter == $equipe['id']) ? 'selected' : ''; ?>>
            <?php echo $equipe['descricao']; ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="col-auto">
      <label for="idNivel" class="form-label fw-semibold">Nível:</label>
      <select class="form-select" name="idNivel" id="idNivel">
        <option value="Todos">Todos</option>
        <?php while($nivel = $resultNiveis->fetch_assoc()): ?>
          <option value="<?php echo $nivel['id']; ?>" <?php echo ($idNivelFilter == $nivel['id']) ? 'selected' : ''; ?>>
            <?php echo $nivel['descricao']; ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="col-auto align-self-end">
      <button type="submit" class="btn btn-primary">Filtrar</button>
    </div>
    <div class="col-auto align-self-end">
      <a href="folga.php" class="btn btn-secondary">Limpar Filtros</a>
    </div>
  </div>
</form>

  <!-- Linha que agrupa Calendário e Painel de Detalhes -->
  <div class="row calendario-detalhes mb-4">
    <div class="col-md-9">
      <div id="calendar" class="p-3 bg-light rounded shadow-sm"></div>
    </div>
    <div class="col-md-3">
      <div id="sidePanel" class="card shadow-sm border-0">
        <div class="card-header bg-secondary text-white rounded-top">
          <h5 class="mb-0">Detalhes do Dia</h5>
        </div>
        <div class="card-body" id="details">
          <!-- Conteúdo atualizado via JS -->
        </div>
      </div>
    </div>
  </div>

  <!-- Botão para abrir modal de cadastro -->
  <div class="d-flex justify-content-end mb-3">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastro">
      <i class="fa-solid fa-plus-circle me-2"></i> Cadastrar
    </button>
  </div>

  <!-- Modal de cadastro -->
  <div class="modal fade" id="modalCadastro" tabindex="-1" aria-labelledby="modalCadastroLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content shadow">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCadastroLabel">Cadastrar Folga/Ferias</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <form method="post" action="">
          <input type="hidden" name="acao" value="cadastrar">
          <div class="modal-body">
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="usuario_id" class="form-label fw-semibold">Colaborador:</label>
                <select class="form-select" name="usuario_id" id="usuario_id" required>
                  <option value="">Selecione</option>
                  <?php
                  $resultUsuarios->data_seek(0);
                  while($rowU = $resultUsuarios->fetch_assoc()):
                  ?>
                    <option value="<?php echo $rowU['Id']; ?>"><?php echo $rowU['Nome']; ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label for="tipo" class="form-label fw-semibold">Tipo:</label>
                <select class="form-select" name="tipo" id="tipo">
                  <option value="Ferias">Ferias</option>
                  <option value="Folga">Folga</option>
                </select>
              </div>
            </div>
            <!-- Calendário para seleção de período -->
            <div class="row mb-3">
              <div class="col text-center">
                <label class="form-label fw-semibold">Selecione o período:</label>
              </div>
            </div>
            <div class="row justify-content-center mb-3">
              <div class="col-auto">
                <div id="calendarioInline" class="border rounded p-2"></div>
              </div>
            </div>
            <!-- Inputs ocultos para datas -->
            <input type="hidden" name="data_inicio" id="data_inicio">
            <input type="hidden" name="data_fim" id="data_fim">
            <!-- Campo Justificativa (para Folga) -->
            <div class="row mb-3" id="justificativaGroup" style="display: none;">
              <label for="justificativa" class="form-label fw-semibold">Justificativa:</label>
              <textarea class="form-control" name="justificativa" id="justificativa" rows="3" placeholder="Descreva a justificativa da folga"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            <button type="submit" class="btn btn-primary">Salvar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal de Edição -->
  <div class="modal fade" id="modalEditar" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content shadow">
        <div class="modal-header">
          <h5 class="modal-title" id="modalEditarLabel">Editar Folga/Ferias</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <form method="post" action="editar_folga.php">
          <div class="modal-body">
            <input type="hidden" name="id" id="edit_id">
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="edit_usuario_id" class="form-label fw-semibold">Colaborador:</label>
                <?php
                  if ($idEquipeFilter != 'Todos' || $idNivelFilter != 'Todos') {
                      $sqlUsuarios2 = "SELECT u.Id, u.Nome FROM TB_USUARIO u
                                        JOIN TB_EQUIPE_NIVEL_ANALISTA eva ON u.Id = eva.idUsuario
                                        WHERE 1";
                      if ($idEquipeFilter != 'Todos') {
                          $sqlUsuarios2 .= " AND eva.idEquipe = " . intval($idEquipeFilter);
                      }
                      if ($idNivelFilter != 'Todos') {
                          $sqlUsuarios2 .= " AND eva.idNivel = " . intval($idNivelFilter);
                      }
                      $sqlUsuarios2 .= " ORDER BY u.Nome";
                  } else {
                      $sqlUsuarios2 = "SELECT Id, Nome FROM TB_USUARIO ORDER BY Nome";
                  }
                  $resultUsuarios2 = $conn->query($sqlUsuarios2);
                ?>
                <select class="form-select" name="usuario_id" id="edit_usuario_id" required>
                  <option value="">Selecione</option>
                  <?php while($user = $resultUsuarios2->fetch_assoc()): ?>
                    <option value="<?php echo $user['Id']; ?>"><?php echo $user['Nome']; ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label for="edit_tipo" class="form-label fw-semibold">Tipo:</label>
                <select class="form-select" name="tipo" id="edit_tipo" required>
                  <option value="Ferias">Ferias</option>
                  <option value="Folga">Folga</option>
                </select>
              </div>
            </div>
            <!-- Calendário de edição -->
            <div class="row mb-3">
              <div class="col text-center">
                <label class="form-label fw-semibold">Selecione o período:</label>
              </div>
            </div>
            <div class="row justify-content-center mb-3">
              <div class="col-auto">
                <div id="calendarioInlineEdit" class="border rounded p-2"></div>
              </div>
            </div>
            <input type="hidden" name="data_inicio" id="edit_data_inicio">
            <input type="hidden" name="data_fim" id="edit_data_fim">
            <!-- Campo Justificativa para edição -->
            <div class="row mb-3" id="justificativaGroupEdit" style="display: none;">
              <label for="edit_justificativa" class="form-label fw-semibold">Justificativa:</label>
              <textarea class="form-control" name="justificativa" id="edit_justificativa" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Listagem dos registros -->
  <div class="row g-4">
    <!-- Card de Ferias -->
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white">
          <h4 class="mb-0">Ferias</h4>
        </div>
        <div class="card-body p-0">
          <table class="table table-hover table-striped mb-0">
            <thead class="table-light">
              <tr>
                <th>Colaborador</th>
                <th>Data Início</th>
                <th>Data Fim</th>
                <th>Dias</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($resultFerias && $resultFerias->num_rows > 0): ?>
                <?php while($row = $resultFerias->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo $row['nome_colaborador']; ?></td>
                    <td><?php echo date("d/m/Y", strtotime($row['data_inicio'])); ?></td>
                    <td><?php echo date("d/m/Y", strtotime($row['data_fim'])); ?></td>
                    <td><?php echo $row['quantidade_dias']; ?></td>
                    <td>
                      <div class="d-flex flex-column align-items-start">
                        <button 
                          class="btn btn-sm btn-outline-primary editar-btn" 
                          data-bs-toggle="modal" 
                          data-bs-target="#modalEditar"
                          data-id="<?php echo $row['id']; ?>"
                          data-usuarioid="<?php echo $row['usuario_id']; ?>"  
                          data-tipo="Ferias"
                          data-inicio="<?php echo $row['data_inicio']; ?>"
                          data-fim="<?php echo $row['data_fim']; ?>"
                          data-justificativa="">
                          <i class="fa-solid fa-pen"></i>
                        </button>
                        <a 
                          href="deletar_folga.php?id=<?php echo $row['id']; ?>" 
                          class="btn btn-sm btn-outline-danger"
                          onclick="return confirm('Confirma a exclusão?');">
                          <i class="fa-solid fa-trash"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" class="text-center text-muted">Nenhum registro de Ferias encontrado.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <!-- Card de Folga -->
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white">
          <h4 class="mb-0">Folgas</h4>
        </div>
        <div class="card-body p-0">
          <table class="table table-hover table-striped mb-0">
            <thead class="table-light">
              <tr>
                <th>Colaborador</th>
                <th>Data Início</th>
                <th>Data Fim</th>
                <th>Dias</th>
                <th>Justificativa</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($resultFolga && $resultFolga->num_rows > 0): ?>
                <?php while($row = $resultFolga->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo $row['nome_colaborador']; ?></td>
                    <td><?php echo date("d/m/Y", strtotime($row['data_inicio'])); ?></td>
                    <td><?php echo date("d/m/Y", strtotime($row['data_fim'])); ?></td>
                    <td><?php echo $row['quantidade_dias']; ?></td>
                    <td><?php echo nl2br($row['justificativa'] ?? ''); ?></td>
                    <td>
                      <div class="d-flex flex-column align-items-start">
                        <button 
                          class="btn btn-sm btn-outline-primary editar-btn"
                          data-bs-toggle="modal" 
                          data-bs-target="#modalEditar"
                          data-id="<?php echo $row['id']; ?>"
                          data-usuarioid="<?php echo $row['usuario_id']; ?>" 
                          data-tipo="Folga"
                          data-inicio="<?php echo $row['data_inicio']; ?>"
                          data-fim="<?php echo $row['data_fim']; ?>"
                          data-justificativa="<?php echo $row['justificativa']; ?>">
                          <i class="fa-solid fa-pen"></i>
                        </button>
                        <a href="deletar_folga.php?id=<?php echo $row['id']; ?>" 
                           class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Confirma a exclusão?');">
                          <i class="fa-solid fa-trash"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" class="text-center text-muted">Nenhum registro de Folga encontrado.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- FullCalendar JS para versão 6 -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  // Função para formatar data de YYYY-MM-DD para DD/MM/YYYY
  function formatDate(dateStr) {
    var parts = dateStr.split('-');
    return parts[2] + '/' + parts[1] + '/' + parts[0];
  }

  // Atualiza o painel de detalhes para uma data específica
  function updateSidePanel(dayStr) {
    var details = document.getElementById('details');
    var formattedDate = formatDate(dayStr);
    details.innerHTML = '<h5>' + formattedDate + '</h5><hr>';
    if (aggregator[dayStr] && aggregator[dayStr].length > 0) {
      aggregator[dayStr].forEach(function(item) {
        details.innerHTML += '<p>' + item.nome + ' (' + item.tipo + ')</p>';
      });
    } else {
      details.innerHTML += '<p>Nenhum evento agendado.</p>';
    }
  }

  // Passa o array aggregator do PHP para o JavaScript
  var aggregator = <?php echo json_encode($aggregator); ?>;
  console.log('Aggregator:', aggregator);

  // Inicializa o FullCalendar com timeZone configurado para "local"
  document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    if (!calendarEl) {
      console.error("Elemento 'calendar' não encontrado!");
      return;
    }
    var calendar = new FullCalendar.Calendar(calendarEl, {
      locale: 'pt-br',
      timeZone: 'local',
      initialView: 'dayGridMonth',
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: ''
      },
      buttonText: {
        today: 'Hoje'
      },
      height: 350,
      expandRows: true,
      dayHeaderContent: function(arg) {
        return arg.text.toUpperCase().replace(/\./g, '');
      },
      dayCellDidMount: function(info) {
        var dayStr = info.date.toISOString().split('T')[0];
        var dayFrame = info.el.querySelector('.fc-daygrid-day-frame');
        if (dayFrame) {
          dayFrame.style.position = 'relative';
          if (aggregator[dayStr] && aggregator[dayStr].length > 0) {
            dayFrame.style.backgroundColor = '#E2F0D9';
            var badge = document.createElement('span');
            badge.classList.add('badge', 'bg-primary', 'badge-colab-center');
            badge.textContent = aggregator[dayStr].length;
            dayFrame.appendChild(badge);
          }
          dayFrame.addEventListener('click', function() {
            document.querySelectorAll('.fc-daygrid-day-frame.selected-day').forEach(function(cell) {
              cell.classList.remove('selected-day');
            });
            dayFrame.classList.add('selected-day');
            updateSidePanel(dayStr);
          });
        }
      }
    });
    calendar.render();

    var todayStr = new Date().toISOString().split('T')[0];
    updateSidePanel(todayStr);
  });

  let calendarInstance = null;
  let calendarEditInstance = null;

  // Inicializa o Flatpickr para o modal de cadastro
  const modalCadastro = document.getElementById('modalCadastro');
  modalCadastro.addEventListener('shown.bs.modal', function () {
    if (!calendarInstance) {
      calendarInstance = flatpickr('#calendarioInline', {
        mode: 'range',
        inline: true,
        dateFormat: 'Y-m-d',
        showMonths: 2,
        onDayCreate: function(dateObj, dateStr, instance, dayElem) {
          if (!dateObj || typeof dateObj.getFullYear !== 'function') {
            console.warn("onDayCreate: dateObj inválido:", dateObj);
            return;
          }
          let cleanDate = dateStr.slice(0, 10);
          if (aggregator[cleanDate] && aggregator[cleanDate].length > 0) {
            dayElem.classList.add("used-day");
          }
        },
        onChange: function(selectedDates, dateStr, instance) {
          let conflict = false;
          let conflictDays = [];
          if (selectedDates.length === 2) {
            let start = new Date(selectedDates[0]);
            let end = new Date(selectedDates[1]);
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
              let dayFormatted = instance.formatDate(d, 'Y-m-d');
              if (aggregator[dayFormatted] && aggregator[dayFormatted].length > 0) {
                conflict = true;
                conflictDays.push(dayFormatted);
              }
            }
          }
          let notificationElem = document.getElementById('conflictNotification');
          if (conflict) {
            if (!notificationElem) {
              notificationElem = document.createElement('div');
              notificationElem.id = 'conflictNotification';
              notificationElem.className = 'alert alert-warning mt-2';
              instance.calendarContainer.parentNode.insertBefore(notificationElem, instance.calendarContainer.nextSibling);
            }
            notificationElem.innerText = 'Já há colaboradores com folga/férias nos dias: ' + 
              conflictDays.map(function(day) { return day.split('-')[2]; }).join(', ');
          } else if (notificationElem) {
            notificationElem.parentNode.removeChild(notificationElem);
          }
          if (selectedDates.length === 2) {
            document.getElementById('data_inicio').value = instance.formatDate(selectedDates[0], 'Y-m-d');
            document.getElementById('data_fim').value = instance.formatDate(selectedDates[1], 'Y-m-d');
          }
        }
      });
    }
  });

  // Ouvinte para exibir/ocultar o campo de justificativa conforme o tipo selecionado no modal de cadastro
  const tipoSelect = document.getElementById('tipo');
  const justificativaGroup = document.getElementById('justificativaGroup');
  tipoSelect.addEventListener('change', function() {
    justificativaGroup.style.display = (tipoSelect.value === 'Folga') ? 'block' : 'none';
  });

  // Inicializa o Flatpickr para o modal de edição
  const modalEditar = document.getElementById('modalEditar');
  modalEditar.addEventListener('shown.bs.modal', function() {
    if (!calendarEditInstance) {
      calendarEditInstance = flatpickr('#calendarioInlineEdit', {
        mode: 'range',
        inline: true,
        dateFormat: 'Y-m-d',
        showMonths: 2,
        onChange: function(selectedDates, dateStr, instance) {
          let conflict = false;
          let conflictDays = [];
          if (selectedDates.length === 2) {
            let start = new Date(selectedDates[0]);
            let end = new Date(selectedDates[1]);
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
              let dayFormatted = instance.formatDate(d, 'Y-m-d');
              if (aggregator[dayFormatted] && aggregator[dayFormatted].length > 0) {
                conflict = true;
                conflictDays.push(dayFormatted);
              }
            }
          }
          let notificationElem = document.getElementById('editConflictNotification');
          if (conflict) {
            if (!notificationElem) {
              notificationElem = document.createElement('div');
              notificationElem.id = 'editConflictNotification';
              notificationElem.className = 'alert alert-warning mt-2';
              instance.calendarContainer.parentNode.insertBefore(notificationElem, instance.calendarContainer.nextSibling);
            }
            notificationElem.innerText = 'Já há colaboradores com folga/férias nos dias: ' +
              conflictDays.map(function(day) { return day.split('-')[2]; }).join(', ');
          } else if (notificationElem) {
            notificationElem.parentNode.removeChild(notificationElem);
          }
          if (selectedDates.length === 2) {
            document.getElementById('edit_data_inicio').value = instance.formatDate(selectedDates[0], 'Y-m-d');
            document.getElementById('edit_data_fim').value = instance.formatDate(selectedDates[1], 'Y-m-d');
          }
        }
      });
    } else {
      calendarEditInstance.redraw();
    }
  });

  // Preenche o modal de edição com os dados do evento selecionado e atualiza a visibilidade do campo justificativa
  document.querySelectorAll('.editar-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const usuarioId = this.getAttribute('data-usuarioid');
      const tipo = this.getAttribute('data-tipo');
      const dataInicio = this.getAttribute('data-inicio');
      const dataFim = this.getAttribute('data-fim');
      const justificativa = this.getAttribute('data-justificativa') || '';

      document.getElementById('edit_id').value = id;
      document.getElementById('edit_usuario_id').value = usuarioId;
      document.getElementById('edit_tipo').value = tipo;
      document.getElementById('edit_data_inicio').value = dataInicio;
      document.getElementById('edit_data_fim').value = dataFim;
      document.getElementById('edit_justificativa').value = justificativa;

      document.getElementById('justificativaGroupEdit').style.display = (tipo === 'Folga') ? 'block' : 'none';

      if (calendarEditInstance) {
        if (dataInicio && dataFim) {
          calendarEditInstance.setDate([dataInicio, dataFim], true);
        } else {
          calendarEditInstance.clear();
        }
      }
    });
  });
</script>
</body>
</html>
