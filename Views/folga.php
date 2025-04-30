<?php
// folga.php
include '../Config/Database.php';
session_start();

// Verifica se o usuário está logado; se não, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}
// Variáveis de sessão
$usuario_id   = $_SESSION['usuario_id'];
$cargo        = $_SESSION['cargo'] ?? '';
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

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
$sql = "SELECT
          f.id,
          f.usuario_id,
          u.Nome AS nome_colaborador,
          f.data_inicio,
          f.data_fim,
          f.tipo,
          COALESCE(f.justificativa,'') AS justificativa
        FROM TB_FOLGA f
        JOIN TB_USUARIO u ON f.usuario_id = u.Id";

$conditions = [];

/* se aplicar filtro de equipe / nível faz o JOIN extra */
if ($idEquipeFilter != 'Todos' || $idNivelFilter != 'Todos') {
    $sql .= " JOIN TB_EQUIPE_NIVEL_ANALISTA eva ON u.Id = eva.idUsuario";
}
if ($idEquipeFilter != 'Todos') {
    $conditions[] = "eva.idEquipe = " . intval($idEquipeFilter);
}
if ($idNivelFilter != 'Todos') {
    $conditions[] = "eva.idNivel = " . intval($idNivelFilter);
}

/* ▼ NOVO: traz apenas períodos ainda vigentes ou futuros */
$conditions[] = "f.data_fim >= CURDATE()";
/* ▲ NOVO --------------------------------------------------- */

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}

$sql .= " ORDER BY f.data_inicio";
$result = $conn->query($sql);

/* ------------------------- monta o $aggregator ------------------------- */
$aggregator = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $start = strtotime($row['data_inicio']);
        $end   = strtotime($row['data_fim']);            // já ≥ hoje
        for ($d = $start; $d <= $end; $d += 86400) {
            $dayStr = date('Y-m-d', $d);
            $aggregator[$dayStr][] = [
                'id'            => $row['id'],
                'usuarioId'     => $row['usuario_id'],
                'nome'          => $row['nome_colaborador'],
                'tipo'          => $row['tipo'],
                'inicio'        => $row['data_inicio'],
                'fim'           => $row['data_fim'],
                'justificativa' => $row['justificativa']
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
$conditionsFerias = ["f.tipo = 'Ferias'",
                     "f.data_fim >= CURDATE()" ];
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
$sqlListarFerias .= " ORDER BY f.data_inicio ASC";
$resultFerias = $conn->query($sqlListarFerias);

// Para Folga
$sqlListarFolga = "SELECT f.id, f.usuario_id, u.Nome AS nome_colaborador, f.data_inicio, f.data_fim, f.quantidade_dias, f.justificativa
                   FROM TB_FOLGA f
                   JOIN TB_USUARIO u ON f.usuario_id = u.Id";
$conditionsFolga = ["f.tipo = 'Folga'",
                    "f.data_fim >= CURDATE()"];
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
$sqlListarFolga .= " ORDER BY f.data_inicio ASC";
$resultFolga = $conn->query($sqlListarFolga);

// 1) Carrega e ordena FÉRIAS
$feriasList = [];
if ($resultFerias) {
  while ($row = $resultFerias->fetch_assoc()) {
    $feriasList[] = $row;
  }
  usort($feriasList, function($a, $b){
    return strtotime($a['data_inicio']) - strtotime($b['data_inicio']);
  });
}

// 2) Carrega e ordena FOLGAS
$folgasList = [];
if ($resultFolga) {
  while ($row = $resultFolga->fetch_assoc()) {
    $folgasList[] = $row;
  }
  usort($folgasList, function($a, $b){
    return strtotime($a['data_inicio']) - strtotime($b['data_inicio']);
  });
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Controle de Férias e Folgas</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../Public/folga.css">
  <!-- FullCalendar CSS -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet" />
  <!-- Flatpickr CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <!-- CSS customizado -->
  <link rel="icon" href="../Public/Image/LogoTituto.png" type="image/png">
  
</head>
<body>
  <!-- Início do layout unificado: Sidebar e Cabeçalho -->
  <div class="d-flex-wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
      <a class="light-logo" href="menu.php">
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
          <a class="nav-link active" href="folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a>
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
      <!-- Cabeçalho -->
      <div class="header">
        <h3>Controle de Férias e Folgas</h3>
        <div class="user-info">
          <span>Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
          <a href="logout.php" class="btn btn-danger">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
          </a>
        </div>
      </div>

<div class="container my-5">
  <!-- Linha que agrupa Calendário e Painel de Detalhes -->
  <div class="row calendario-detalhes mb-4">
    <div class="col-md-9">
      <div id="calendar" class="p-3 bg-light rounded shadow-sm">
        <!-- Conteúdo atualizado via JS -->
      </div>
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
    <!-- Botão para abrir o modal de filtro -->
    <button type="button" class="btn btn-outline-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#filterModal">
      <i class="fa-solid fa-filter"></i>
    </button>
    <input type="text" id="searchFolgasFerias" class="form-control me-2" placeholder="Pesquisar..." style="max-width: 150px;">
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

  <!-- Modal de Exclusão de Folga/Férias -->
  <div class="modal fade" id="modalExcluirFolga" tabindex="-1" aria-labelledby="modalExcluirFolgaLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header text-white">
          <h5 class="modal-title" id="modalExcluirFolgaLabel">Excluir Agendamento</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <form action="deletar_folga.php" method="post">
          <div class="modal-body">
            <input type="hidden" name="id" id="excluir_folga_id">
            <p>Tem certeza que deseja excluir a(s) <strong id="excluir_folga_tipo"></strong> de <strong id="excluir_folga_nome"></strong>?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-danger">Excluir</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal de Filtro -->
  <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
      <div class="modal-content">
        <form method="GET" action="folga.php">
          <div class="modal-header">
            <h5 class="modal-title" id="filterModalLabel">Filtro</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <!-- Escolha de tipo -->
            <div class="mb-3">
              <div class="mb-3">
                <label for="filterType" class="form-label">Filtrar por:</label>
                <select class="form-select" id="filterType">
                  <option value="equipe">Equipe</option>
                  <option value="nivel">Nível</option>
                </select>
              </div>
            </div>
            
              <!-- Campo Equipe -->
              <div class="mb-3">
                <div class="mb-3" id="equipeField">
                  <label for="modal_idEquipe" class="form-label">Selecione a Equipe:</label>
                  <select class="form-select" name="idEquipe" id="modal_idEquipe" required>
                    <option value="Todos">Todos</option>
                    <?php 
                      $resultEquipes->data_seek(0);
                      while($equipe = $resultEquipes->fetch_assoc()): 
                    ?>
                      <option value="<?= $equipe['id'] ?>" <?= ($idEquipeFilter == $equipe['id'] ? 'selected' : '') ?>>
                        <?= $equipe['descricao'] ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>
              </div>
              <!-- Campo Nível -->
              <div class="mb-3">
                <div class="mb-3" id="nivelField" style="display: none;">
                  <label for="modal_idNivel" class="form-label">Selecione o Nível:</label>
                  <select class="form-select" name="idNivel" id="modal_idNivel" required>
                    <option value="Todos">Todos</option>
                    <?php 
                      $resultNiveis->data_seek(0);
                      while($nivel = $resultNiveis->fetch_assoc()): 
                    ?>
                      <option value="<?= $nivel['id'] ?>" <?= ($idNivelFilter == $nivel['id'] ? 'selected' : '') ?>>
                        <?= $nivel['descricao'] ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>
              </div>
          </div>
          <div class="modal-footer">
            <a href="folga.php" class="btn btn-secondary">Limpar Filtros</a>
            <button type="submit" class="btn btn-primary">Filtrar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const filterType  = document.getElementById('filterType');
      const equipeField = document.getElementById('equipeField');
      const nivelField  = document.getElementById('nivelField');

      function toggleFields() {
        if (filterType.value === 'equipe') {
          equipeField.style.display = '';
          nivelField.style.display  = 'none';
        } else {
          equipeField.style.display = 'none';
          nivelField.style.display  = '';
        }
      }

      filterType.addEventListener('change', toggleFields);
      toggleFields();
    });
  </script>


  <!-- Listagem dos registros -->
  <div class="row g-4">
    <!-- ==== FÉRIAS ==== -->
    <div class="col-md-6">
      <h5 class="mb-3">
        <i class="fa-solid fa-umbrella-beach text-primary me-2"></i>Férias
      </h5>
      <?php if (count($feriasList)): ?>
        <div class="table-scroll">
          <ul class="timeline">
            <?php foreach ($feriasList as $idx => $f):
              $isNext     = $idx === 0;
              $iconBg     = $isNext ? 'bg-warning text-dark' : 'bg-primary';
              $badgeClass = $isNext ? 'bg-warning text-dark' : 'bg-primary';
            ?>
              <li class="timeline-event">
                <div class="timeline-icon <?= $iconBg ?>">
                  <i class="fa-solid fa-umbrella-beach"></i>
                </div>
                <div class="timeline-content">
                  <?php if ($isNext): ?>
                    <div class="ribbon-label"><b>Próximas Férias</b></div>
                  <?php endif; ?>

                  <h6 class="mb-1">
                    <?= htmlspecialchars($f['nome_colaborador']) ?>
                    <span class="badge <?= $badgeClass ?> ms-2">
                      <?= $f['quantidade_dias'] ?>d
                    </span>
                  </h6>
                  <small class="text-muted d-block mb-1">
                    <i class="fa-regular fa-calendar me-1"></i>
                    <?= date("d/m/Y", strtotime($f['data_inicio'])) ?>
                    →
                    <?= date("d/m/Y", strtotime($f['data_fim'])) ?>
                  </small>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php else: ?>
        <div class="text-muted">Nenhum registro de Férias encontrado.</div>
      <?php endif; ?>
    </div>

    <!-- ==== FOLGAS ==== -->
    <div class="col-md-6">
      <h5 class="mb-3">
        <i class="fa-solid fa-calendar-day text-primary me-2"></i>Folgas
      </h5>
      <?php if (count($folgasList)): ?>
        <div class="table-scroll">
          <ul class="timeline">
            <?php foreach ($folgasList as $idx => $f):
              $isNext     = $idx === 0;
              $iconBg     = $isNext ? 'bg-warning text-dark' : 'bg-primary text-white';
              $badgeClass = $isNext ? 'bg-warning text-dark' : 'bg-primary text-white';
            ?>
              <li class="timeline-event">
                <div class="timeline-icon <?= $iconBg ?>">
                  <i class="fa-solid fa-calendar-day"></i>
                </div>
                <div class="timeline-content">
                  <?php if ($isNext): ?>
                    <div class="ribbon-label" ><b>Próxima Folga</b></div>
                  <?php endif; ?>

                  <h6 class="mb-1">
                    <?= htmlspecialchars($f['nome_colaborador']) ?>
                    <span class="badge <?= $badgeClass ?> ms-2">
                      <?= $f['quantidade_dias'] ?>d
                    </span>
                  </h6>
                  <small class="text-muted d-block mb-1">
                    <i class="fa-regular fa-calendar me-1"></i>
                    <?= date("d/m/Y", strtotime($f['data_inicio'])) ?>
                    →
                    <?= date("d/m/Y", strtotime($f['data_fim'])) ?>
                  </small>
                  <?php if (trim($f['justificativa'])): ?>
                    <p class="justificativa-folga small text-muted mb-0">
                      <i class="fa-solid fa-comment-dots me-1"></i>
                      <?= nl2br(htmlspecialchars($f['justificativa'])) ?>
                    </p>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php else: ?>
        <div class="text-muted">Nenhum registro de Folga encontrado.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- FullCalendar JS para versão 6 -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  // Função para formatar data de YYYY‑MM‑DD para DD/MM/YYYY
  function formatDate(dateStr) {
    var parts = dateStr.split('-');
    return parts[2] + '/' + parts[1] + '/' + parts[0];
  }

  // ← NOVO: guarda o último dia clicado no calendário principal
  var selectedDay = null;

  function filtrarListasPeloDia(diaSelecionado) {
  
  // Formato YYYY-MM-DD para comparação direta
  document.querySelectorAll('.timeline').forEach(timeline => {
    let algumVisivel = false;

    timeline.querySelectorAll('.timeline-event').forEach(evento => {
      const periodoTexto = evento.querySelector('small').innerText;
      const [inicio, fim] = periodoTexto.split('→').map(data => {
        return data.trim().split('/').reverse().join('-');
      });

      // Exibe apenas se a data estiver dentro do intervalo
      if (diaSelecionado >= inicio && diaSelecionado <= fim) {
        evento.style.display = '';
        algumVisivel = true;
      } else {
        evento.style.display = 'none';
      }
    });

    // Remove mensagem existente antes de inserir novamente
    timeline.querySelectorAll('.no-result-message').forEach(el => el.remove());

    // Se nada visível, insere a mensagem
    if (!algumVisivel) {
      const mensagem = document.createElement('li');
      mensagem.className = 'text-muted no-result-message p-2';
      const titulo = timeline.closest('.col-md-6').querySelector('h5').innerText.trim();
      mensagem.innerText = `Não há ${titulo} para o período selecionado.`;
      timeline.appendChild(mensagem);
    }
  });
}




  // Atualiza o painel de detalhes para uma data específica
  function updateSidePanel(dayStr) {
    const details = document.getElementById('details');
    const items   = aggregator[dayStr] || [];
    const ferias  = items.filter(i => i.tipo === 'Ferias');
    const folgas  = items.filter(i => i.tipo === 'Folga');

    // monta as tabs com contador
    let html =
      `<ul class="nav nav-tabs mb-3" id="detailsTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="title-aba nav-link active" id="ferias-tab"
                  data-bs-toggle="tab" data-bs-target="#ferias-pane"
                  type="button" role="tab" aria-controls="ferias-pane"
                  aria-selected="true">
            Férias 
            <span class="badge rounded-pill bg-primary ms-1">${ferias.length}</span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="title-aba nav-link" id="folgas-tab"
                  data-bs-toggle="tab" data-bs-target="#folgas-pane"
                  type="button" role="tab" aria-controls="folgas-pane"
                  aria-selected="false">
            Folgas 
            <span class="badge rounded-pill bg-warning text-dark ms-1">${folgas.length}</span>
          </button>
        </li>
      </ul>
      <div class="tab-content" id="detailsTabContent">
        <div class="tab-pane fade show active" id="ferias-pane"
              role="tabpanel" aria-labelledby="ferias-tab">`;

    if (ferias.length) {
      ferias.forEach(item => {
        const just = item.justificativa.replace(/"/g,'&quot;');
        html += `
          <div class="detail-item d-flex justify-content-between align-items-center mb-2">
            <span data-bs-toggle="tooltip" title="${just}">
              ${item.nome}
            </span>
            <div class="d-flex gap-1">
              <button type="button" class="detail-btn edit-side-btn"
                      data-id="${item.id}" data-usuarioid="${item.usuarioId}"
                      data-tipo="${item.tipo}" data-inicio="${item.inicio}"
                      data-fim="${item.fim}" data-justificativa="${just}"
                      title="Editar">
                <i class="fa-solid fa-pen"></i>
              </button>
              <button type="button" class="detail-btn remove-side-btn"
                      data-id="${item.id}" data-nome="${item.nome}"
                      data-tipo="${item.tipo}"
                      title="Remover">
                <i class="fa-solid fa-trash"></i>
              </button>
            </div>
          </div>`;
      });
    } else {
      html += `<p class="text-muted">Sem férias neste dia.</p>`;
    }

    html += `
        </div>
        <div class="tab-pane fade" id="folgas-pane"
              role="tabpanel" aria-labelledby="folgas-tab">`;

    if (folgas.length) {
      folgas.forEach(item => {
        const just = item.justificativa.replace(/"/g,'&quot;');
        html += `
          <div class="detail-item d-flex justify-content-between align-items-center mb-2">
            <span data-bs-toggle="tooltip" title="${just}">
              ${item.nome}
            </span>
            <div class="d-flex gap-1">
              <button type="button" class="detail-btn edit-side-btn"
                      data-id="${item.id}" data-usuarioid="${item.usuarioId}"
                      data-tipo="${item.tipo}" data-inicio="${item.inicio}"
                      data-fim="${item.fim}" data-justificativa="${just}"
                      title="Editar">
                <i class="fa-solid fa-pen"></i>
              </button>
              <button type="button" class="detail-btn remove-side-btn"
                      data-id="${item.id}" data-nome="${item.nome}"
                      data-tipo="${item.tipo}"
                      title="Remover">
                <i class="fa-solid fa-trash"></i>
              </button>
            </div>
          </div>`;
      });
    } else {
      html += `<p class="text-muted">Sem folgas neste dia.</p>`;
    }

    html += `</div></div>`;

    details.innerHTML = html;

    // inicializa tooltips nos spans
    details.querySelectorAll('[data-bs-toggle="tooltip"]')
          .forEach(el => new bootstrap.Tooltip(el));


    // liga exclusão para abrir modal
    details.querySelectorAll('.remove-side-btn')
          .forEach(btn => {
            btn.addEventListener('click', function() {
              const id   = this.dataset.id;
              const nome = this.dataset.nome;
              const tipo = this.dataset.tipo;
              document.getElementById('excluir_folga_id').value     = id;
              document.getElementById('excluir_folga_nome').textContent = nome;
              document.getElementById('excluir_folga_tipo').textContent = tipo;
              new bootstrap.Modal(document.getElementById('modalExcluirFolga')).show();
            });
          });
  }

  document.getElementById('details').addEventListener('click', function (e) {
    var btn = e.target.closest('.remove-side-btn');
    if (!btn) return; // clicou em outra área

    document.getElementById('excluir_folga_id').value     = id;
    document.getElementById('excluir_folga_nome').textContent = nome;
    document.getElementById('excluir_folga_tipo').textContent = tipo;

    if (typeof calendarEditInstance !== 'undefined' && calendarEditInstance) {
      calendarEditInstance.setDate([btn.dataset.inicio, btn.dataset.fim], true);
    }
    new bootstrap.Modal(document.getElementById('modalExcluirFolga')).show();
  });

  /* -------- delegação de clique para os ícones criados dinamicamente */
  document.getElementById('details').addEventListener('click', function (e) {
    var btn = e.target.closest('.edit-side-btn');
    if (!btn) return; // clicou em outra área

    document.getElementById('edit_id').value            = btn.dataset.id;
    document.getElementById('edit_usuario_id').value    = btn.dataset.usuarioid;
    document.getElementById('edit_tipo').value          = btn.dataset.tipo;
    document.getElementById('edit_data_inicio').value   = btn.dataset.inicio;
    document.getElementById('edit_data_fim').value      = btn.dataset.fim;
    document.getElementById('edit_justificativa').value = btn.dataset.justificativa;
    document.getElementById('justificativaGroupEdit').style.display =
      (btn.dataset.tipo === 'Folga') ? 'block' : 'none';

    if (typeof calendarEditInstance !== 'undefined' && calendarEditInstance) {
      calendarEditInstance.setDate([btn.dataset.inicio, btn.dataset.fim], true);
    }
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
  });
  /* ----------------------------------------------------------------------- */

  // Mantém os botões de edição estáticos das tabelas
  document.querySelectorAll('.editar-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('edit_id').value            = this.dataset.id;
      document.getElementById('edit_usuario_id').value    = this.dataset.usuarioid;
      document.getElementById('edit_tipo').value          = this.dataset.tipo;
      document.getElementById('edit_data_inicio').value   = this.dataset.inicio;
      document.getElementById('edit_data_fim').value      = this.dataset.fim;
      document.getElementById('edit_justificativa').value = this.dataset.justificativa || '';
      document.getElementById('justificativaGroupEdit').style.display =
        (this.dataset.tipo === 'Folga') ? 'block' : 'none';

      if (calendarEditInstance) {
        calendarEditInstance.setDate([this.dataset.inicio, this.dataset.fim], true);
      }
      new bootstrap.Modal(document.getElementById('modalEditar')).show();
    });
  });

  // Passa o array aggregator do PHP para o JavaScript
  var aggregator = <?php echo json_encode($aggregator); ?>;
  console.log('Aggregator:', aggregator);

  // helper: verifica se existe folga de OUTRO colaborador (ignora o próprio e o evento em edição)
  function hasForeignEvent(dayKey, uid, currId) {
    if (!aggregator[dayKey]) return false;
    return aggregator[dayKey].some(ev =>
      String(ev.usuarioId) !== String(uid) &&
      (currId === null || String(ev.id) !== String(currId))
    );
  }

  // Inicializa o FullCalendar com timeZone configurado para "local"
  document.addEventListener('DOMContentLoaded', function () {
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
      buttonText: { today: 'Hoje' },
      height: 350,
      expandRows: true,
      dayHeaderContent: function (arg) {
        return arg.text.toUpperCase().replace(/\./g, '');
      },
      dayCellDidMount: function (info) {
        var dayStr   = info.date.toISOString().split('T')[0];
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
          dayFrame.addEventListener('click', function () {
            document.querySelectorAll('.fc-daygrid-day-frame.selected-day')
                    .forEach(cell => cell.classList.remove('selected-day'));

            dayFrame.classList.add('selected-day');
            selectedDay = dayStr;
            updateSidePanel(dayStr);
            filtrarListasPeloDia(dayStr);  // ← certifique-se disso aqui
          });
        }
      }
    });
    calendar.render();

    var todayStr = new Date().toISOString().split('T')[0];
    updateSidePanel(todayStr);
  });

  let calendarInstance     = null;
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
        onDayCreate: function (dateObj, dateStr, instance, dayElem) {
          if (!dateObj || typeof dateObj.getFullYear !== 'function') return;
          let cleanDate = dateStr.slice(0, 10);
          if (aggregator[cleanDate] && aggregator[cleanDate].length > 0) {
            dayElem.classList.add("used-day");
          }
        },
        onChange: function (selectedDates, _, instance) {
          let conflict = false;
          let conflictDays = [];
          if (selectedDates.length === 2) {
            let start = new Date(selectedDates[0]);
            let end   = new Date(selectedDates[1]);
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
              let key = instance.formatDate(d, 'Y-m-d');
              if (aggregator[key] && aggregator[key].length > 0) {
                conflict = true;
                conflictDays.push(key);
              }
            }
          }
          let notificationElem = document.getElementById('conflictNotification');
          if (conflict) {
            if (!notificationElem) {
              notificationElem = document.createElement('div');
              notificationElem.id = 'conflictNotification';
              notificationElem.className = 'alert alert-warning mt-2';
              instance.calendarContainer.parentNode.insertBefore(
                notificationElem, instance.calendarContainer.nextSibling
              );
            }
            notificationElem.innerText =
              'Já há colaboradores com folga/férias nos dias: ' +
              conflictDays.map(function (day) { return day.split('-')[2]; }).join(', ');
          } else if (notificationElem) {
            notificationElem.parentNode.removeChild(notificationElem);
          }
          if (selectedDates.length === 2) {
            document.getElementById('data_inicio').value = instance.formatDate(selectedDates[0], 'Y-m-d');
            document.getElementById('data_fim').value    = instance.formatDate(selectedDates[1], 'Y-m-d');
          }
        }
      });
    }

    // se o usuário já escolheu um dia no principal, destaca aqui
    if (selectedDay && calendarInstance) {
      calendarInstance.clear();
      calendarInstance.setDate(selectedDay, true);
      document.getElementById('data_inicio').value = selectedDay;
      document.getElementById('data_fim').value    = '';
    }
  });

  // Ouvinte para exibir/ocultar o campo de justificativa no cadastro
  const tipoSelect         = document.getElementById('tipo');
  const justificativaGroup = document.getElementById('justificativaGroup');
  tipoSelect.addEventListener('change', function () {
    justificativaGroup.style.display = (tipoSelect.value === 'Folga') ? 'block' : 'none';
  });

  /* --------- Flatpickr – modal de EDIÇÃO (ajustado) --------- */
  const modalEditarElem = document.getElementById('modalEditar');
  modalEditarElem.addEventListener('shown.bs.modal', function () {
    if (!calendarEditInstance) {
      calendarEditInstance = flatpickr('#calendarioInlineEdit', {
        mode: 'range',
        inline: true,
        dateFormat: 'Y-m-d',
        showMonths: 2,
        onChange: function (selectedDates, _, instance) {
          const uid    = document.getElementById('edit_usuario_id').value; // ★
          const currId = document.getElementById('edit_id').value;        // ★
          let conflict = false, days = [];

          if (selectedDates.length === 2) {
            for (let d = new Date(selectedDates[0]); d <= selectedDates[1]; d.setDate(d.getDate() + 1)) {
              const key = instance.formatDate(d, 'Y-m-d');
              if (hasForeignEvent(key, uid, currId)) { // ★
                conflict = true;
                days.push(key);
              }
            }
          }

          let warn = document.getElementById('editConflictNotification');
          if (conflict) {
            if (!warn) {
              warn = document.createElement('div');
              warn.id = 'editConflictNotification';
              warn.className = 'alert alert-warning mt-2';
              instance.calendarContainer.parentNode.insertBefore(
                warn, instance.calendarContainer.nextSibling
              );
            }
            warn.textContent =
              'Há outros colaboradores com folga/férias nos dias: ' +
              days.map(k => k.split('-')[2]).join(', ');
          } else if (warn) {
            warn.remove();
          }

          if (selectedDates.length === 2) {
            document.getElementById('edit_data_inicio').value =
              instance.formatDate(selectedDates[0], 'Y-m-d');
            document.getElementById('edit_data_fim').value    =
              instance.formatDate(selectedDates[1], 'Y-m-d');
          }
        }
      });
    } else {
      calendarEditInstance.redraw();
    }

    // mantém o período atual já aparecido
    var di = document.getElementById('edit_data_inicio').value;
    var df = document.getElementById('edit_data_fim').value;
    if (di && df && calendarEditInstance) {
      calendarEditInstance.setDate([di, df], true);
    }
  });

  // força reload da página após fechar o modal de edição
  modalEditarElem.addEventListener('hidden.bs.modal', () => location.reload());
</script>
<script>
  document
    .getElementById('searchFolgasFerias')
    .addEventListener('input', function() {
      const termo = this.value.toLowerCase();
      document
        .querySelectorAll('.timeline-event')
        .forEach(item => {
          const texto = item.textContent.toLowerCase();
          item.style.display = texto.includes(termo) ? '' : 'none';
        });
    });
</script>
</body>
</html>
