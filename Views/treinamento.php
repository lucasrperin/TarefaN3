<?php
include '../Config/Database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

// Variáveis de sessão
$usuario_id   = $_SESSION['usuario_id'];
$cargo        = $_SESSION['cargo'] ?? '';
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

// ------------------------------------------------------------------
// Carregar lista de SISTEMAS
// ------------------------------------------------------------------
$sistemaQuery  = "SELECT Id, Descricao FROM TB_SISTEMA ORDER BY Descricao";
$sistemaResult = mysqli_query($conn, $sistemaQuery);
$sistemas      = [];
while ($row = mysqli_fetch_assoc($sistemaResult)) {
  $sistemas[] = $row;
}

// ------------------------------------------------------------------
// Carregar lista de USUÁRIOS (consultores)
// ------------------------------------------------------------------
$consultorQuery  = "SELECT Id, Nome FROM TB_USUARIO ORDER BY Nome";
$consultorResult = mysqli_query($conn, $consultorQuery);
$consultores     = [];
while ($row = mysqli_fetch_assoc($consultorResult)) {
  $consultores[] = $row;
}

// Consulta para buscar treinamento em andamento (dt_ini preenchido, dt_fim nulo e status PENDENTE)
$queryInProgress = "SELECT t.id, t.dt_ini, t.dt_fim, t.tipo, t.sistema, t.status, c.cliente, t.dt_ini 
                    FROM TB_TREINAMENTOS t
                    JOIN TB_CLIENTES c ON t.cliente_id = c.id 
                    WHERE t.dt_ini IS NOT NULL 
                      AND t.dt_fim IS NULL 
                      AND t.status = 'PENDENTE'
                    ORDER BY t.dt_ini DESC LIMIT 1";
$resultInProgress = mysqli_query($conn, $queryInProgress);
$treinamentoEmAndamento = mysqli_fetch_assoc($resultInProgress);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Painel N3 - Treinamentos</title>
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Bootstrap Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <!-- Seu CSS -->
  <link rel="stylesheet" href="../Public/treinamento.css">
  <!-- FullCalendar CSS -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet" />
  <!-- FullCalendar tradução -->
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales-all.global.min.js"></script>
</head>
<body>
  <div class="d-flex-wrapper page-container">
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
          <a class="nav-link" href="folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a>
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
          <a class="nav-link active" href="treinamento.php"><i class="fa-solid fa-calendar-check me-2"></i>Treinamentos</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a>
        <?php endif; ?>
      </nav>
    </div>

    <!-- Minimalist Modern Toast Layout -->
    <div id="toast-container" class="toast-container">
      <div id="toastSucesso" class="toast toast-success">
        <i class="fa-solid fa-check-circle"></i>
        <span id="toastMensagem"><i class="fa-solid fa-check-circle"></i></span>
      </div>
      <div id="toastErro" class="toast toast-error">
        <i class="fa-solid fa-exclamation-triangle"></i>
        <span id="toastMensagemErro"></span>
      </div>
    </div>
    <script>
      function showToast(message, type) {
        const container = document.getElementById("toast-container");
        const toast = document.createElement("div");
        toast.className = "toast " + type;
        toast.textContent = message;
        container.appendChild(toast);
        // Trigger the CSS animation
        setTimeout(() => {
          toast.classList.add("show");
        }, 10);
        // Hide after 2 seconds and remove from DOM
        setTimeout(() => {
          toast.classList.remove("show");
          setTimeout(() => {
            container.removeChild(toast);
          }, 300);
        }, 2000);
      }

      document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get("success");
        const error = urlParams.get("error");

        if (success) {
          let msg = "";
          switch (success) {
            case "1":
              msg = "Treinamento Cadastrado!";
              break;
            case "2":
              msg = "Treinamento Editado!";
              break;
            case "3":
              msg = "Treinamento Excluído!";
              break;
            case "4":
              msg = "Treinamento Iniciado!";
              break;
            case "5":
              msg = "Treinamento Finalizado!";
              break;  
            case "6":
              msg = "Erro ao cadastrar!";
              break;
          }
          if (msg) showToast(msg, "success");
        }

        if (error) {
          let msg = "";
          switch (error) {
            case "1":
              msg = "Erro ao finalizar treinamento!";
              break;
            case "2":
              msg = "Erro ao excluir treinamento!";
              break;
          }
          if (msg) showToast(msg, "error");
        }
      });
    </script>

    <!-- Toast Container -->
    <div id="toast-container" class="toast-container">
      <div id="toastSucesso" class="toast toast-success">
        <i class="fa-solid fa-check-circle"></i>
        <span id="toastMensagem"></span>
      </div>
      <div id="toastErro" class="toast toast-error">
        <i class="fa-solid fa-exclamation-triangle"></i>
        <span id="toastMensagemErro"></span>
      </div>
    </div>

    <!-- Toast para agendamentos próximos -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050;">
      <div id="toastAgendamento" class="toast text-bg-primary border-0" role="alert">
        <div class="toast-body">
          <div id="toastAgendamentoMensagem"></div>
          <div class="mt-2 pt-2 border-top text-end">
            <button id="btnReagendarToast" class="btn btn-light btn-sm">Reagendar</button>
            <button type="button" class="btn btn-close btn-close-white btn-sm" data-bs-dismiss="toast"></button>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content --> 
    <div class="w-100">
      <!-- Header -->
      <div class="header">
        <h3>Agendamentos</h3>
        <div class="user-info">
          <span>Bem-vindo(a), <?php echo htmlspecialchars($usuario_nome, ENT_QUOTES, 'UTF-8'); ?>!</span>
          <a href="logout.php" class="btn btn-danger">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
          </a>
        </div>
      </div>

      <!-- Conteúdo -->
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <h4>Agenda</h4>
          <small>Visualize e gerencie as Instalações, Treinamentos ou ambos</small>
        </div>
        <div class="d-flex">
          <a href="clientes.php" class="btn btn-custom me-2">
            <i class="fa-solid fa-users me-1"></i> Clientes
          </a>
          <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#modalCadastroTreinamento">
            <i class="fa-solid fa-plus me-1"></i> Novo Agendamento
          </button>
        </div>
      </div>

      <!-- Exemplo de card estilizado -->
      <div class="container my-3">
        <?php if (!empty($treinamentoEmAndamento)): ?>
          <div class="d-flex align-items-center justify-content-between bg-light border rounded p-3 shadow-sm" style="max-width: 500px; margin: auto;">
            <div>
              <i class="fa-solid fa-clock me-2"></i><strong>Treinamento em Andamento</strong>
              <div class="mt-1">
                <span><?= htmlspecialchars($treinamentoEmAndamento['cliente'], ENT_QUOTES) ?> - <?= htmlspecialchars($treinamentoEmAndamento['sistema'], ENT_QUOTES) ?></span><br>
                <small>
                  Tipo: <?= htmlspecialchars($treinamentoEmAndamento['tipo'], ENT_QUOTES) ?><br>
                  Início: <?= date("d/m H:i:s", strtotime($treinamentoEmAndamento['dt_ini'])) ?>
                </small>
              </div>
            </div>
            <div>
              <!-- Formulário oculto para encerrar o treinamento -->
              <form id="finalizeForm" action="editar_treinamento.php" method="post" style="display: none;">
                <input type="hidden" name="id" value="<?= htmlspecialchars($treinamentoEmAndamento['id'], ENT_QUOTES) ?>">
                <input type="hidden" name="action" value="encerrar">
              </form>
              <!-- Ícone encapsulado em um link; ao clicar, submete o formulário oculto -->
              <a href="javascript:void(0);" onclick="document.getElementById('finalizeForm').submit();">
                <i id="iconFinalizar" class="fa-regular fa-circle-pause text-danger" style="font-size: 1.75em; cursor: pointer;" 
                  data-bs-toggle="tooltip" data-bs-placement="top" title="Finalizar treinamento"></i>
              </a>
            </div>
          </div>
        <?php endif; ?>
      </div>


      <div class="card-body">
        <!-- Calendário -->
        <div id="calendar"></div>
      </div>
    </div>
  </div>

  <!-- Modal: Cadastro de Agendamento -->
  <div class="modal fade" id="modalCadastroTreinamento" tabindex="-1" aria-labelledby="modalCadastroLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg"> 
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCadastroLabel">Novo Agendamento</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <form action="cadastrar_treinamento.php" method="post">
          <div class="modal-body">
            <!-- Linha 1: Data | Hora | Tipo -->
            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="data_treino" class="form-label">Data</label>
                <input type="date" name="data" id="data_treino" class="form-control" required>
              </div>
              <div class="col-md-4 mb-3">
                  <label for="hora_treino" class="form-label">Hora</label>
                  <select name="hora" id="hora_treino" class="form-select" required>
                    <option value="">Selecione um horário</option>
                  </select>
                </div>
              <div class="col-md-4 mb-3">
                <label for="tipo_treino" class="form-label">Tipo</label>
                <select name="tipo" id="tipo_treino" class="form-select" required>
                  <option value="TREINAMENTO">Treinamento</option>
                  <option value="INSTALACAO">Instalação</option>
                  <option value="AMBOS">Instalação + Treinamento</option>
                </select>
              </div>
            </div>

            <!-- Linha 2: Cliente (Pesquisa), Sistema, Consultor -->
            <div class="row">
              <div class="col-md-4 mb-3" style="position: relative;">
                <label for="cliente_treino" class="form-label">Cliente</label>
                <input type="text" name="cliente_nome" id="cliente_treino" class="form-control" placeholder="Pesquise por nome, CNPJ/CPF ou Serial" autocomplete="off" required>
                <input type="hidden" name="cliente_id" id="cliente_id">
                <div id="cliente_suggestions" class="list-group" style="position: absolute; width: 100%; z-index: 1000;"></div>
              </div>
              <div class="col-md-4 mb-3">
                <label for="sistema_treino" class="form-label">Sistema</label>
                <select name="sistema" id="sistema_treino" class="form-select" required>
                  <option value="">-- Selecione --</option>
                  <?php foreach($sistemas as $sis): ?>
                    <option value="<?= htmlspecialchars($sis['Descricao'], ENT_QUOTES) ?>">
                      <?= htmlspecialchars($sis['Descricao'], ENT_QUOTES) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4 mb-3">
                <label for="consultor_treino" class="form-label">Consultor</label>
                <select name="consultor" id="consultor_treino" class="form-select" required>
                  <option value="">-- Selecione --</option>
                  <?php foreach($consultores as $cons): ?>
                    <option value="<?= htmlspecialchars($cons['Nome'], ENT_QUOTES) ?>">
                      <?= htmlspecialchars($cons['Nome'], ENT_QUOTES) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Linha 3: Status -->
            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="status_treino" class="form-label">Status</label>
                <select name="status" id="status_treino" class="form-select">
                  <option value="PENDENTE">Pendente</option>
                  <option value="CONCLUIDO">Concluído</option>
                  <option value="CANCELADO">Cancelado</option>
                </select>
              </div>
            </div>

            <!-- Linha 4: Observações -->
            <div class="row">
              <div class="col-12 mb-3">
                <label for="observacoes_treino" class="form-label">Observações</label>
                <textarea name="observacoes" id="observacoes_treino" class="form-control" rows="3"></textarea>
              </div>
            </div>

            <!-- Linha 5: Duração (minutos) -->
            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="duracao_treino" class="form-label">Duração (minutos)</label>
                <input type="number" name="duracao" id="duracao_treino" class="form-control" required value="30">
              </div>
            </div>
          </div><!-- modal-body -->
          <div class="modal-footer">
            <button type="submit" class="btn btn-custom">Cadastrar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Edição de Agendamento -->
  <div class="modal fade" id="modalEditarTreinamento" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalEditarLabel">Editar Agendamento</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <form action="editar_treinamento.php" method="post">
          <div class="modal-body">
            <input type="hidden" name="id" id="edit_id">
            <!-- Linha 1: Data | Hora | Tipo -->
            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="edit_data" class="form-label">Data</label>
                <input type="date" name="data" id="edit_data" class="form-control" required>
              </div>
              <div class="col-md-4 mb-3">
              <label for="edit_hora" class="form-label">Hora</label>
              <select name="hora" id="edit_hora" class="form-select" required>
                <option value="">Selecione um horário</option>
              </select>
            </div>
              <div class="col-md-4 mb-3">
                <label for="edit_tipo" class="form-label">Tipo</label>
                <select name="tipo" id="edit_tipo" class="form-select" required>
                  <option value="TREINAMENTO">Treinamento</option>
                  <option value="INSTALACAO">Instalação</option>
                  <option value="AMBOS">Instalação + Treinamento</option>
                </select>
              </div>
            </div>
            <!-- Hidden para dt_ini e dt_fim -->
            <input type="hidden" name="dt_ini" id="edit_dt_ini" value="">
            <input type="hidden" name="dt_fim" id="edit_dt_fim" value="">
            <!-- Linha 2: Cliente (Pesquisa), Sistema, Consultor -->
            <div class="row">
              <div class="col-md-4 mb-3" style="position: relative;">
                <label for="edit_cliente" class="form-label">Cliente</label>
                <input type="text" name="cliente_nome" id="edit_cliente" class="form-control" placeholder="Pesquise por nome, CNPJ/CPF ou Serial" autocomplete="off" required>
                <input type="hidden" name="cliente_id" id="edit_cliente_id">
                <div id="edit_cliente_suggestions" class="list-group" style="position: absolute; width: 100%; z-index: 1000;"></div>
              </div>
              <div class="col-md-4 mb-3">
                <label for="edit_sistema" class="form-label">Sistema</label>
                <select name="sistema" id="edit_sistema" class="form-select" required>
                  <?php foreach($sistemas as $sis): ?>
                    <option value="<?= htmlspecialchars($sis['Descricao'], ENT_QUOTES) ?>">
                      <?= htmlspecialchars($sis['Descricao'], ENT_QUOTES) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4 mb-3">
                <label for="edit_consultor" class="form-label">Consultor</label>
                <select name="consultor" id="edit_consultor" class="form-select" required>
                  <option value="">-- Selecione --</option>
                  <?php foreach($consultores as $cons): ?>
                    <option value="<?= htmlspecialchars($cons['Nome'], ENT_QUOTES) ?>">
                      <?= htmlspecialchars($cons['Nome'], ENT_QUOTES) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <!-- Linha 3: Status -->
            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="edit_status" class="form-label">Status</label>
                <select name="status" id="edit_status" class="form-select">
                  <option value="PENDENTE">Pendente</option>
                  <option value="CONCLUIDO">Concluído</option>
                  <option value="CANCELADO">Cancelado</option>
                </select>
              </div>
            </div>
            <!-- Linha 4: Observações -->
            <div class="row">
              <div class="col-12 mb-3">
                <label for="edit_observacoes" class="form-label">Observações</label>
                <textarea name="observacoes" id="edit_observacoes" class="form-control" rows="3"></textarea>
              </div>
            </div>
            <!-- Linha 5: Duração (minutos) -->
            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="edit_duracao" class="form-label">Duração (minutos)</label>
                <input type="number" name="duracao" id="edit_duracao" class="form-control" required value="30">
              </div>
            </div>
          </div><!-- modal-body -->
          <div class="modal-footer">
            <div class="d-flex w-100">
              <!-- Ambos os botões iniciam com display:none; serão exibidos via JavaScript conforme o valor de dt_ini/dt_fim -->
              <?php if ($cargo === 'Treinamento' || $cargo === 'Admin'): ?>
                <button type="button" class="btn btn-success me-2" id="btnIniciarTreinamento" style="display: none;">
                  <i class="fa-solid fa-circle-play me-2"></i>Iniciar
                </button>
                <button type="button" class="btn btn-danger me-2" id="btnEncerrarTreinamento" style="display: none;">
                  <i class="fa-solid fa-circle-pause me-2"></i>Encerrar
                </button>
              <?php endif; ?>
              <!-- Ícone de lixeira no modal de edição -->
              <div class="ms-auto d-flex align-items-center gap-2">
                <a onclick="modalExcluir(document.getElementById('edit_id').value, document.getElementById('edit_cliente').value)" title="Excluir">
                  <i class="fa-regular fa-trash-alt text-danger" style="font-size: 1.75em; cursor: pointer;"></i>
                </a>
                <button type="submit" name="action" value="salvar" class="btn btn-custom">Salvar</button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Exclusão de Agendamento -->
  <div class="modal fade" id="modalExcluirTreinamento" tabindex="-1" aria-labelledby="modalExcluirLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalExcluirLabel">Excluir Agendamento</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <form action="deletar_treinamento.php" method="post">
          <div class="modal-body">
            <input type="hidden" name="id" id="excluir_id">
            <p>Tem certeza que deseja excluir o agendamento <strong id="excluir_cliente"></strong>?</p>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-danger">Excluir</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Excedeu Horas Contratadas (usado para cadastro e edição) -->
  <div class="modal fade" id="modalExceeded" tabindex="-1" aria-labelledby="modalExceededLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalExceededLabel">Limite de Horas Excedido</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <p id="exceededMessage"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" id="btnRedirectClients">Sim, Registrar Mais Horas</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </div>
    </div>
  </div>

<!-- Scripts JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Inicializa todos os tooltips do Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  });

  document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      locale: 'pt-br',
      nowIndicator: true,
      dayHeaderContent: function(arg) {
        return arg.text.toUpperCase();
      },
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay'
      },
      buttonText: {
        today: 'Hoje',
        month: 'Mês',
        week: 'Semana',
        day: 'Dia'
      },
      allDayText: 'Dia inteiro',
      events: 'fetch_treinamentos.php',
      eventClick: function(info) {
          const eventObj = info.event;
          document.getElementById('edit_id').value = eventObj.id;

          let startDate = new Date(eventObj.start);
          let year  = startDate.getFullYear();
          let month = String(startDate.getMonth() + 1).padStart(2, '0');
          let day   = String(startDate.getDate()).padStart(2, '0');
          let hours = String(startDate.getHours()).padStart(2, '0');
          let mins  = String(startDate.getMinutes()).padStart(2, '0');

          const dataSelecionada = `${year}-${month}-${day}`;
          const horaSelecionada = `${hours}:${mins}`;
          const duracao = eventObj.extendedProps.duracao || 30;

          document.getElementById('edit_data').value = dataSelecionada;

          // Carregar horários disponíveis via AJAX
          $.ajax({
            url: 'horarios_disponiveis.php',
            method: 'GET',
            data: { data: dataSelecionada, duracao: duracao },
            dataType: 'json',
            success: function(response) {
              let html = '<option value="">Selecione um horário</option>';
              let encontrouHorario = false;

              $.each(response, function(index, horario) {
                const selected = horario.trim() === horaSelecionada.trim() ? 'selected' : '';
                if (selected) encontrouHorario = true;
                html += `<option value="${horario}" ${selected}>${horario}</option>`;
              });

              // Caso o horário agendado não esteja disponível, adiciona manualmente
              if (!encontrouHorario) {
                html += `<option value="${horaSelecionada}" selected>${horaSelecionada} (atual)</option>`;
              }

              $('#edit_hora').html(html);
            },
            error: function(){
              alert('Erro ao buscar horários disponíveis.');
            }
          });

          // Preenche os demais campos
          document.getElementById('edit_cliente').value = eventObj.extendedProps.cliente;
          document.getElementById('edit_cliente_id').value = eventObj.extendedProps.cliente_id;
          $('#edit_cliente').data('original-client-id', eventObj.extendedProps.cliente_id);
          $('#edit_cliente').data('original-client-nome', eventObj.extendedProps.cliente);

          document.getElementById('edit_sistema').value = eventObj.extendedProps.sistema;
          document.getElementById('edit_consultor').value = eventObj.extendedProps.consultor;
          document.getElementById('edit_status').value = eventObj.extendedProps.status;
          document.getElementById('edit_tipo').value = eventObj.extendedProps.tipo || 'TREINAMENTO';
          document.getElementById('edit_observacoes').value = eventObj.extendedProps.observacoes || '';
          document.getElementById('edit_duracao').value = duracao;

          var dtIni = eventObj.extendedProps.dt_ini;
          var dtFim = eventObj.extendedProps.dt_fim;

          if (dtIni === "0000-00-00 00:00:00") dtIni = "";
          if (dtFim === "0000-00-00 00:00:00") dtFim = "";

          document.getElementById('edit_dt_ini').value = dtIni || "";
          document.getElementById('edit_dt_fim').value = dtFim || "";

          if (!dtIni) {
            $('#btnIniciarTreinamento').show();
            $('#btnEncerrarTreinamento').hide();
          } else if (dtIni && !dtFim) {
            $('#btnIniciarTreinamento').hide();
            $('#btnEncerrarTreinamento').show();
          } else {
            $('#btnIniciarTreinamento, #btnEncerrarTreinamento').hide();
          }

          $('#modalEditarTreinamento form input[name="action"]').remove();
          const editModal = new bootstrap.Modal(document.getElementById('modalEditarTreinamento'));
          editModal.show();
        }

    });
    calendar.render();
  });

  $(document).ready(function(){
    $('#modalCadastroTreinamento form').on('submit', function(e){
        e.preventDefault(); // impede o envio imediato
        var form = $(this);
        $.ajax({
            url: 'check_hours.php',
            method: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if(response.status === 'exceeded'){
                    // Exibe o modal de aviso de horas excedidas
                    var formattedMsg = response.message.replace(/\n/g, "<br>");
                    $('#exceededMessage').html(formattedMsg);
                    var modalExceeded = new bootstrap.Modal(document.getElementById('modalExceeded'));
                    modalExceeded.show();
                } else if(response.status === 'ok'){
                    // Remove o handler e submete o formulário normalmente
                    form.off('submit').submit();
                } else {
                    alert(response.message);
                }
            },
            error: function(){
                alert('Erro na comunicação com o servidor.');
            }
        });
    });
  });

  // Botões de ação no modal
  $(document).ready(function(){  
    // Exemplo: Botão para redirecionar para a aba de clientes (se necessário)
    $('#btnRedirectClients').on('click', function(){
        window.location.href = 'clientes.php';
    });
    // Botão para redirecionar para a aba de clientes
    $('#btnRedirectClients').on('click', function(){
        window.location.href = 'clientes.php';
    });

    // Ao clicar no botão para redirecionar para a aba de clientes para registrar mais horas
    $('#btnRedirectClients').on('click', function(){
        window.location.href = 'clientes.php';
    });
  
    // Botão Iniciar
    $('#btnIniciarTreinamento').click(function(e) {
      e.preventDefault();
      $('#modalEditarTreinamento form input[name="action"]').remove();
      $('<input>').attr({
        type: 'hidden',
        name: 'action',
        value: 'iniciar'
      }).appendTo('#modalEditarTreinamento form');
      $('#modalEditarTreinamento form').submit();
    });
    
    // Botão Encerrar
    $('#btnEncerrarTreinamento').click(function(e) {
      e.preventDefault();
      $('#modalEditarTreinamento form input[name="action"]').remove();
      $('<input>').attr({
        type: 'hidden',
        name: 'action',
        value: 'encerrar'
      }).appendTo('#modalEditarTreinamento form');
      $('#modalEditarTreinamento form').submit();
    });
  });

  function verificaHoras() {
    var clienteId = $("#cliente_id").val();
    var duracao = parseInt($("#duracao_treino").val());
    if (!clienteId || isNaN(duracao)) return;
    
    $.ajax({
        url: 'check_hours.php',
        method: 'POST',
        data: { cliente_id: clienteId, duracao: duracao },
        dataType: 'json',
        success: function(response) {
            if(response.status === 'exceeded'){
                var formattedMsg = response.message.replace(/\n/g, "<br>");
                $('#exceededMessage').html(formattedMsg);
                var modalExceeded = new bootstrap.Modal(document.getElementById('modalExceeded'));
                modalExceeded.show();
            }
        },
        error: function(){
            console.log("Falha na verificação das horas.");
        }
    });
  }


  $(document).ready(function(){
    // Se desejar que a verificação ocorra quando o campo de duração mudar
    $("#duracao_treinamento").on("change", verificaHoras);
  });


  $(document).ready(function(){
    // Sempre que o valor de duração mudar, dispara a verificação
    $("#duracao_treino").on("change", verificaHoras);
  });


  document.addEventListener('DOMContentLoaded', function() {
    // Inicializa os tooltips do Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Ao clicar no ícone de finalizar, submete o formulário
    document.getElementById('iconFinalizar').addEventListener('click', function() {
      document.getElementById('finalizeForm').submit();
    });
  });

  
  function modalExcluir(id, cliente) {
    document.getElementById('excluir_id').value = id;
    document.getElementById('excluir_cliente').textContent = cliente;
    new bootstrap.Modal(document.getElementById('modalExcluirTreinamento')).show();
  }
    
  // ALERTA DE PROXIMIDADE
  let eventoParaReagendar = null;
  function mostrarToastAgendamento(evento) {
    eventoParaReagendar = evento;
    const toastElement = document.getElementById('toastAgendamento');
    const toastBody = document.getElementById('toastAgendamentoMensagem');
    toastBody.innerHTML = `
      <strong>📅 Próximo agendamento:</strong><br>
      ${evento.title}<br>
      <strong>Horário:</strong> ${new Date(evento.start).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
    `;
    const toast = new bootstrap.Toast(toastElement, { delay: 10000 });
    toast.show();
  }
  
  document.getElementById('btnReagendarToast').addEventListener('click', function() {
    if (!eventoParaReagendar) return;
    const evento = eventoParaReagendar;
    
    // Preencher os campos de edição com os dados do evento reagendado
    $('#edit_id').val(evento.id);
    const dataEvento = new Date(evento.start);
    const ano = dataEvento.getFullYear();
    const mes = String(dataEvento.getMonth() + 1).padStart(2, '0');
    const dia = String(dataEvento.getDate()).padStart(2, '0');
    const hora = String(dataEvento.getHours()).padStart(2, '0');
    const minuto = String(dataEvento.getMinutes()).padStart(2, '0');
    const dataSelecionada = `${ano}-${mes}-${dia}`;
    const horaSelecionada = `${hora}:${minuto}`;
    const duracao = evento.extendedProps.duracao || 30;

    $('#edit_data').val(dataSelecionada);
    // Atualiza o select de horários no modal de edição (incluindo o parâmetro "ignore")
    $.ajax({
        url: 'horarios_disponiveis.php',
        method: 'GET',
        data: { data: dataSelecionada, duracao: duracao, ignore: evento.id },
        dataType: 'json',
        success: function(response) {
            let html = '<option value="">Selecione um horário</option>';
            let encontrouHorario = false;
            $.each(response, function(index, horario) {
                const selected = horario.trim() === horaSelecionada.trim() ? 'selected' : '';
                if (selected) encontrouHorario = true;
                html += `<option value="${horario}" ${selected}>${horario}</option>`;
            });
            // Se o horário atual não estiver na lista, adiciona manualmente
            if (!encontrouHorario) {
                html += `<option value="${horaSelecionada}" selected>${horaSelecionada} (atual)</option>`;
            }
            $('#edit_hora').html(html);
        },
        error: function(){
            alert('Erro ao buscar horários disponíveis.');
        }
    });
    
    // Preenche os demais campos da edição
    $('#edit_cliente').val(evento.extendedProps.cliente);
    $('#edit_cliente_id').val(evento.extendedProps.cliente_id);
    $('#edit_sistema').val(evento.extendedProps.sistema);
    $('#edit_consultor').val(evento.extendedProps.consultor);
    $('#edit_status').val(evento.extendedProps.status);
    $('#edit_tipo').val(evento.extendedProps.tipo || 'TREINAMENTO');
    $('#edit_observacoes').val(evento.extendedProps.observacoes || '');
    $('#edit_duracao').val(duracao);

    // Caso haja campos ocultos de dt_ini e dt_fim, mantenha-os atualizados
    const dtIni = evento.extendedProps.dt_ini === "0000-00-00 00:00:00" ? "" : evento.extendedProps.dt_ini;
    const dtFim = evento.extendedProps.dt_fim === "0000-00-00 00:00:00" ? "" : evento.extendedProps.dt_fim;
    $('#edit_dt_ini').val(dtIni);
    $('#edit_dt_fim').val(dtFim);
    
    // Exibe o modal de edição (que também será usado para reagendamento)
    const editModal = new bootstrap.Modal(document.getElementById('modalEditarTreinamento'));
    editModal.show();
});

  
  function verificarProximidadeAgendamento(eventos) {
    const agora = new Date();
    const alertaAntecedenciaMinutos = 15;
    eventos.forEach(evento => {
      const dataEvento = new Date(evento.start);
      const diferencaMinutos = (dataEvento - agora) / (1000 * 60);
      if (diferencaMinutos > 0 && diferencaMinutos <= alertaAntecedenciaMinutos) {
        mostrarToastAgendamento(evento);
      }
    });
  }
  
  function checarAgendamentos() {
    fetch('fetch_treinamentos.php')
      .then(response => response.json())
      .then(eventos => verificarProximidadeAgendamento(eventos))
      .catch(error => console.error('Erro ao carregar eventos:', error));
  }
  
  setInterval(checarAgendamentos, 300000);
  checarAgendamentos();
  
  $(document).ready(function(){
    // Busca para o campo de cliente no cadastro de agendamento
    $('#cliente_treino').on('keyup', function(){
      var query = $(this).val();
      if(query.length < 2) {
        $('#cliente_suggestions').empty();
        return;
      }
      $.ajax({
        url: 'search_clientes.php',
        data: { q: query },
        dataType: 'json',
        success: function(data) {
          var suggestions = '';
          data.forEach(function(item) {
            suggestions += '<a href="#" class="list-group-item list-group-item-action" data-id="'+item.id+'" data-nome="'+item.cliente+'">'+item.cliente+' - '+item.cnpjcpf+' - '+item.serial+'</a>';
          });
          $('#cliente_suggestions').html(suggestions);
        }
      });
    });
  
    $('#cliente_suggestions').on('click', 'a', function(e){
      e.preventDefault();
      var id = $(this).data('id');
      var nome = $(this).data('nome');
      $('#cliente_treino').val(nome);
      $('#cliente_id').val(id);
      $('#cliente_suggestions').empty();
    });
  
    $(document).click(function(e) {
      if (!$(e.target).closest('#cliente_treino, #cliente_suggestions').length) {
        $('#cliente_suggestions').empty();
      }
    });
  
    // Busca para o campo de cliente no modal de edição
    $('#edit_cliente').on('keyup', function(){
      var query = $(this).val();
      if(query.length < 2) {
        $('#edit_cliente_suggestions').empty();
        return;
      }
      $.ajax({
        url: 'search_clientes.php',
        data: { q: query },
        dataType: 'json',
        success: function(data) {
          var suggestions = '';
          data.forEach(function(item) {
            suggestions += '<a href="#" class="list-group-item list-group-item-action" data-id="'+item.id+'" data-nome="'+item.cliente+'">'+item.cliente+' - '+item.cnpjcpf+' - '+item.serial+'</a>';
          });
          $('#edit_cliente_suggestions').html(suggestions);
        }
      });
    });
  
    $('#edit_cliente_suggestions').on('click', function(e){
      e.stopPropagation();
    });
  
    $('#edit_cliente_suggestions').on('click', 'a', function(e){
      e.preventDefault();
      var id = $(this).data('id');
      var nome = $(this).data('nome');
      $('#edit_cliente').val(nome);
      $('#edit_cliente_id').val(id);
      $('#edit_cliente_suggestions').empty();
    });
  
    $(document).click(function(e) {
      if (!$(e.target).closest('#edit_cliente, #edit_cliente_suggestions').length) {
        $('#edit_cliente_suggestions').empty();
      }
    });
  
    $('#edit_cliente').on('blur', function(){
      var originalNome = $(this).data('original-client-nome');
      if($(this).val().trim() === '' || $(this).val().trim() === originalNome){
        var originalId = $(this).data('original-client-id');
        $('#edit_cliente_id').val(originalId);
      }
    });
  });

  document.addEventListener('DOMContentLoaded', function() {
    // Inicializa os tooltips do Bootstrap (caso já não esteja feito)
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Listener para o modal de exclusão
    var modalExcluirEl = document.getElementById('modalExcluirTreinamento');
    if (modalExcluirEl) {
      modalExcluirEl.addEventListener('shown.bs.modal', function () {
        document.body.classList.add('blur');
      });
      modalExcluirEl.addEventListener('hidden.bs.modal', function () {
        document.body.classList.remove('blur');
      });
    }
    
    // Listener para o modal de horas excedidas
    var modalExceededEl = document.getElementById('modalExceeded');
    if (modalExceededEl) {
      modalExceededEl.addEventListener('shown.bs.modal', function () {
        document.body.classList.add('blur');
      });
      modalExceededEl.addEventListener('hidden.bs.modal', function () {
        document.body.classList.remove('blur');
      });
    }
  });
// Para o modal de cadastro
$('#data_treino').on('change', function(){
    var dataSelecionada = $(this).val();
    var duracao = $('#duracao_treino').val() || 30;
    if (dataSelecionada) {
        $.ajax({
            url: 'horarios_disponiveis.php',
            method: 'GET',
            data: { data: dataSelecionada, duracao: duracao },
            dataType: 'json',
            success: function(response) {
                var html = '<option value="">Selecione um horário</option>';
                $.each(response, function(index, horario) {
                    html += '<option value="'+ horario +'">'+ horario +'</option>';
                });
                $('#hora_treino').html(html);
            },
            error: function(){
                alert('Erro ao buscar horários disponíveis.');
            }
        });
    } else {
        $('#hora_treino').html('<option value="">Selecione um horário</option>');
    }
});

// Para o modal de edição
$('#edit_data').on('change', function(){
    var dataSelecionada = $(this).val();
    var duracao = $('#edit_duracao').val() || 30;
    if (dataSelecionada) {
        $.ajax({
            url: 'horarios_disponiveis.php',
            method: 'GET',
            data: { data: dataSelecionada, duracao: duracao },
            dataType: 'json',
            success: function(response) {
                var html = '<option value="">Selecione um horário</option>';
                $.each(response, function(index, horario) {
                    html += '<option value="'+ horario +'">'+ horario +'</option>';
                });
                $('#edit_hora').html(html);
            },
            error: function(){
                alert('Erro ao buscar horários disponíveis.');
            }
        });
    } else {
        $('#edit_hora').html('<option value="">Selecione um horário</option>');
    }
});



</script>
</body>
</html>
