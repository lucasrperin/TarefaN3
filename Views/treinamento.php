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
  <div class="d-flex-wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
      <a class="light-logo" href="menu.php">
        <img src="../Public/Image/zucchetti_blue.png" width="150" alt="Logo Zucchetti">
      </a>
      <nav class="nav flex-column">
        <a class="nav-link" href="menu.php"><i class="fa-solid fa-house me-2"></i>Home</a>
        <a class="nav-link" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a>
        <a class="nav-link" href="destaque.php"><i class="fa-solid fa-ranking-star me-2"></i>Destaques</a>       
        <a class="nav-link" href="escutas.php"><i class="fa-solid fa-headphones me-2"></i>Escutas</a>
        <a class="nav-link" href="folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a>
        <a class="nav-link" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a>
        <a class="nav-link" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a>
        <a class="nav-link" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a>
        <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a>
        <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a>
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
              <input type="time" name="hora" id="hora_treino" class="form-control" required>
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
              <input type="time" name="hora" id="edit_hora" class="form-control" required>
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
          <button type="submit" class="btn btn-custom">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Exclusão de Agendamento -->
<div class="modal fade" id="modalExcluirTreinamento" tabindex="-1" aria-labelledby="modalExcluirLabel" aria-hidden="true">
  <div class="modal-dialog">
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
      
  </div> <!-- / w-100 -->
</div> <!-- / d-flex-wrapper -->
<!-- Modal: Excedeu Horas Contratadas -->
<div class="modal fade" id="modalExceeded" tabindex="-1" aria-labelledby="modalExceededLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalExceededLabel">Limite de Horas Excedido</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
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
<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<script>
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
        
        document.getElementById('edit_data').value = `${year}-${month}-${day}`;
        document.getElementById('edit_hora').value = `${hours}:${mins}`;
        
        // Preenche os campos do cliente
        document.getElementById('edit_cliente').value = eventObj.extendedProps.cliente;
        document.getElementById('edit_cliente_id').value = eventObj.extendedProps.cliente_id;
        // Armazena os valores originais para recuperação caso o usuário apague o campo
        $('#edit_cliente').data('original-client-id', eventObj.extendedProps.cliente_id);
        $('#edit_cliente').data('original-client-nome', eventObj.extendedProps.cliente);
        
        document.getElementById('edit_sistema').value = eventObj.extendedProps.sistema;
        document.getElementById('edit_consultor').value = eventObj.extendedProps.consultor;
        document.getElementById('edit_status').value = eventObj.extendedProps.status;
        document.getElementById('edit_tipo').value = eventObj.extendedProps.tipo || 'TREINAMENTO';
        document.getElementById('edit_observacoes').value = eventObj.extendedProps.observacoes || '';
        document.getElementById('edit_duracao').value = eventObj.extendedProps.duracao || 30;
  
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
    document.getElementById('edit_id').value = evento.id;
    const dataEvento = new Date(evento.start);
    const ano = dataEvento.getFullYear();
    const mes = String(dataEvento.getMonth() + 1).padStart(2, '0');
    const dia = String(dataEvento.getDate()).padStart(2, '0');
    const hora = String(dataEvento.getHours()).padStart(2, '0');
    const minuto = String(dataEvento.getMinutes()).padStart(2, '0');
    document.getElementById('edit_data').value = `${ano}-${mes}-${dia}`;
    document.getElementById('edit_hora').value = `${hora}:${minuto}`;
    document.getElementById('edit_cliente').value = evento.extendedProps.cliente;
    document.getElementById('edit_cliente_id').value = evento.extendedProps.cliente_id;
    document.getElementById('edit_sistema').value = evento.extendedProps.sistema;
    document.getElementById('edit_consultor').value = evento.extendedProps.consultor;
    document.getElementById('edit_status').value = evento.extendedProps.status;
    document.getElementById('edit_tipo').value = evento.extendedProps.tipo;
    document.getElementById('edit_observacoes').value = evento.extendedProps.observacoes;
    document.getElementById('edit_duracao').value = evento.extendedProps.duracao || 30;
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
</script>

<script>
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
        // Evita que o clique em qualquer lugar dentro da área de sugestões feche a lista
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

    // Ao sair (blur) do campo de edição, se o valor não mudar, garante que o hidden seja o original
    $('#edit_cliente').on('blur', function(){
      var originalNome = $(this).data('original-client-nome');
      if($(this).val().trim() === '' || $(this).val().trim() === originalNome){
         var originalId = $(this).data('original-client-id');
         $('#edit_cliente_id').val(originalId);
      }
    });
});
</script>
<script>
$(document).ready(function(){
  // Intercepta a submissão do formulário do modal de cadastro de agendamento
  $('#modalCadastroTreinamento form').on('submit', function(e){
      e.preventDefault();
      $.ajax({
          url: $(this).attr('action'),
          method: 'POST',
          data: $(this).serialize(),
          dataType: 'json',
          success: function(response) {
              if(response.status === 'exceeded'){
                  // Converte as quebras de linha para <br> e exibe a mensagem no modal
                  var formattedMsg = response.message.replace(/\n/g, "<br>");
                  $('#exceededMessage').html(formattedMsg);
                  var modalExceeded = new bootstrap.Modal(document.getElementById('modalExceeded'));
                  modalExceeded.show();
              } else if(response.status === 'success'){
                  alert(response.message);
                  location.reload();
              } else {
                  alert(response.message);
              }
          },
          error: function(){
              alert('Erro na comunicação com o servidor.');
          }
      });
  });

  // Ao clicar no botão para redirecionar para a aba de clientes para registrar mais horas
  $('#btnRedirectClients').on('click', function(){
      window.location.href = 'clientes.php';
  });
});

  </script>
</body>
</html>
