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
// 1. Carregar lista de SISTEMAS
// ------------------------------------------------------------------
$sistemaQuery  = "SELECT Id, Descricao FROM TB_SISTEMA ORDER BY Descricao";
$sistemaResult = mysqli_query($conn, $sistemaQuery);
$sistemas      = [];
while ($row = mysqli_fetch_assoc($sistemaResult)) {
  $sistemas[] = $row; // Ex: ['Id' => 1, 'Descricao' => 'Clipp 360']
}

// ------------------------------------------------------------------
// 2. Carregar lista de USUÁRIOS (consultores)
// ------------------------------------------------------------------
$consultorQuery  = "SELECT Id, Nome FROM TB_USUARIO ORDER BY Nome";
$consultorResult = mysqli_query($conn, $consultorQuery);
$consultores     = [];
while ($row = mysqli_fetch_assoc($consultorResult)) {
  $consultores[] = $row; // Ex: ['Id' => 1, 'Nome' => 'Lucas Perin']
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Painel N3 - Treinamentos</title>

  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
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
  <div class="d-flex-wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
      <a class="light-logo" href="menu.php">
        <img src="../Public/Image/zucchetti_blue.png" width="150" alt="Logo Zucchetti">
      </a>
      <nav class="nav flex-column">
        <a class="nav-link" href="menu.php"><i class="fa-solid fa-house me-2"></i> Home</a>
        <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users me-2"></i> Usuários</a>
        <a class="nav-link" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a>
        <a class="nav-link" href="escutas.php"><i class="fa-solid fa-headphones me-2"></i>Escutas</a>
        <a class="nav-link" href="folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a>
        <a class="nav-link" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a>
        <a class="nav-link" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a>
        <a class="nav-link" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a>
        <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a>
        <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a>
        <!-- Item de Treinamentos ativo -->
        <a class="nav-link active" href="treinamento.php"><i class="fa-solid fa-calendar-check me-2"></i>Treinamentos</a>
      </nav>
    </div>

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

    <!-- Main Content -->
    <div class="w-100">
      <!-- Header -->
      <div class="header">
        <h3>Agendamento de Treinamentos</h3>
        <div class="user-info">
          <span>Bem-vindo(a), <?php echo htmlspecialchars($usuario_nome, ENT_QUOTES, 'UTF-8'); ?>!</span>
          <a href="logout.php" class="btn btn-danger">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
          </a>
        </div>
      </div>

      <!-- Conteúdo -->
      <div class="content container-fluid">
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <h4>Treinamentos</h4>
              <small>Visualize e gerencie os treinamentos agendados</small>
            </div>
            <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#modalCadastroTreinamento">
              <i class="fa-solid fa-plus me-1"></i> Novo Treinamento
            </button>
          </div>
          <div class="card-body">
            <!-- Calendário -->
            <div id="calendar"></div>
          </div>
        </div>
      </div>

      <!-- Modal: Cadastro de Treinamento -->
      <div class="modal fade" id="modalCadastroTreinamento" tabindex="-1" aria-labelledby="modalCadastroLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="modalCadastroLabel">Novo Treinamento</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form action="cadastrar_treinamento.php" method="post">
              <div class="modal-body">
                <div class="row">
                  <!-- Data -->
                  <div class="col-md-6 mb-3">
                    <label for="data_treino" class="form-label">Data</label>
                    <input type="date" name="data" id="data_treino" class="form-control" required>
                  </div>
                  <!-- Hora -->
                  <div class="col-md-6 mb-3">
                    <label for="hora_treino" class="form-label">Hora</label>
                    <input type="time" name="hora" id="hora_treino" class="form-control" required>
                  </div>
                </div>

                <div class="row">
                  <!-- Cliente -->
                  <div class="col-md-6 mb-3">
                    <label for="cliente_treino" class="form-label">Cliente</label>
                    <input type="text" name="cliente" id="cliente_treino" class="form-control" required>
                  </div>
                  <!-- Sistema -->
                  <div class="col-md-6 mb-3">
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
                </div>

                <div class="row">
                  <!-- Consultor -->
                  <div class="col-md-6 mb-3">
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
                  <!-- Status -->
                  <div class="col-md-6 mb-3">
                    <label for="status_treino" class="form-label">Status</label>
                    <select name="status" id="status_treino" class="form-select">
                      <option value="PENDENTE">Pendente</option>
                      <option value="CONCLUIDO">Concluído</option>
                      <option value="CANCELADO">Cancelado</option>
                    </select>
                  </div>
                </div>

                <!-- Observações (2 colunas de largura) -->
                <div class="row">
                  <div class="col-12 mb-3">
                    <label for="observacoes_treino" class="form-label">Observações</label>
                    <textarea name="observacoes" id="observacoes_treino" class="form-control" rows="3"></textarea>
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

      <!-- Modal: Edição de Treinamento -->
      <div class="modal fade" id="modalEditarTreinamento" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="modalEditarLabel">Editar Treinamento</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form action="editar_treinamento.php" method="post">
              <div class="modal-body">
                <input type="hidden" name="id" id="edit_id">

                <div class="row">
                  <!-- Data -->
                  <div class="col-md-6 mb-3">
                    <label for="edit_data" class="form-label">Data</label>
                    <input type="date" name="data" id="edit_data" class="form-control" required>
                  </div>
                  <!-- Hora -->
                  <div class="col-md-6 mb-3">
                    <label for="edit_hora" class="form-label">Hora</label>
                    <input type="time" name="hora" id="edit_hora" class="form-control" required>
                  </div>
                </div>

                <div class="row">
                  <!-- Cliente -->
                  <div class="col-md-6 mb-3">
                    <label for="edit_cliente" class="form-label">Cliente</label>
                    <input type="text" name="cliente" id="edit_cliente" class="form-control" required>
                  </div>
                  <!-- Sistema -->
                  <div class="col-md-6 mb-3">
                    <label for="edit_sistema" class="form-label">Sistema</label>
                    <select name="sistema" id="edit_sistema" class="form-select" required>
                      <?php foreach($sistemas as $sis): ?>
                        <option value="<?= htmlspecialchars($sis['Descricao'], ENT_QUOTES) ?>">
                          <?= htmlspecialchars($sis['Descricao'], ENT_QUOTES) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="row">
                  <!-- Consultor -->
                  <div class="col-md-6 mb-3">
                    <label for="edit_consultor" class="form-label">Consultor</label>
                    <select name="consultor" id="edit_consultor" class="form-select" required>
                      <?php foreach($consultores as $cons): ?>
                        <option value="<?= htmlspecialchars($cons['Nome'], ENT_QUOTES) ?>">
                          <?= htmlspecialchars($cons['Nome'], ENT_QUOTES) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <!-- Status -->
                  <div class="col-md-6 mb-3">
                    <label for="edit_status" class="form-label">Status</label>
                    <select name="status" id="edit_status" class="form-select">
                      <option value="PENDENTE">Pendente</option>
                      <option value="CONCLUIDO">Concluído</option>
                      <option value="CANCELADO">Cancelado</option>
                    </select>
                  </div>
                </div>

                <!-- Observações (2 colunas de largura) -->
                <div class="row">
                  <div class="col-12 mb-3">
                    <label for="edit_observacoes" class="form-label">Observações</label>
                    <textarea name="observacoes" id="edit_observacoes" class="form-control" rows="3"></textarea>
                  </div>
                </div>

              </div><!-- modal-body -->
              <div class="modal-footer">
                <button type="submit" class="btn btn-custom">Salvar</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Modal: Exclusão de Treinamento -->
      <div class="modal fade" id="modalExcluirTreinamento" tabindex="-1" aria-labelledby="modalExcluirLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="modalExcluirLabel">Excluir Treinamento</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form action="deletar_treinamento.php" method="post">
              <div class="modal-body">
                <input type="hidden" name="id" id="excluir_id">
                <p>Tem certeza que deseja excluir o treinamento <strong id="excluir_cliente"></strong>?</p>
              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-danger">Excluir</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      
    </div> <!-- / w-100 -->
  </div> <!-- / d-flex-wrapper -->

  <!-- Scripts JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <!-- FullCalendar JS -->
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var calendarEl = document.getElementById('calendar');
      var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'pt-br',
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

          document.getElementById('edit_data').value        = `${year}-${month}-${day}`;
          document.getElementById('edit_hora').value        = `${hours}:${mins}`;
          document.getElementById('edit_cliente').value     = eventObj.extendedProps.cliente;
          document.getElementById('edit_sistema').value     = eventObj.extendedProps.sistema;
          document.getElementById('edit_consultor').value   = eventObj.extendedProps.consultor;
          document.getElementById('edit_status').value      = eventObj.extendedProps.status;
          document.getElementById('edit_observacoes').value = eventObj.extendedProps.observacoes || '';

          let editModal = new bootstrap.Modal(document.getElementById('modalEditarTreinamento'));
          editModal.show();
        }
      });
      calendar.render();
    });

    function modalExcluir(id, cliente) {
      document.getElementById('excluir_id').value = id;
      document.getElementById('excluir_cliente').textContent = cliente;
      new bootstrap.Modal(document.getElementById('modalExcluirTreinamento')).show();
    }
  </script>
</body>
</html>
