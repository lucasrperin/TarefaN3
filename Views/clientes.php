<?php
include '../Config/Database.php';
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Seleciona todos os clientes (ativos e inativos)
$query = "SELECT * FROM TB_CLIENTES ORDER BY cliente";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Painel N3 - Clientes</title>
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <!-- CSS customizado (mesmo usado no treinamento.php) -->
  <link rel="stylesheet" href="../Public/treinamento.css">
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
        <a class="nav-link" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i> Conversões</a>
        <a class="nav-link" href="destaque.php"><i class="fa-solid fa-ranking-star me-2"></i> Destaques</a>
        <a class="nav-link" href="escutas.php"><i class="fa-solid fa-headphones me-2"></i> Escutas</a>
        <a class="nav-link" href="folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i> Folgas</a>
        <a class="nav-link" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i> Incidentes</a>
        <a class="nav-link" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i> Indicações</a>
        <a class="nav-link" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i> Nível 3</a>
        <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i> Totalizadores</a>
        <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i> Usuários</a>
        <a class="nav-link active" href="treinamento.php"><i class="fa-solid fa-calendar-check me-2"></i> Treinamentos</a>
      </nav>
    </div>
    
    <!-- Main Content -->
    <div class="w-100">
      <!-- Header -->
      <div class="header">
        <h3>Clientes</h3>
        <div class="user-info">
          <span>Bem-vindo(a), <?= htmlspecialchars($_SESSION['usuario_nome'], ENT_QUOTES, 'UTF-8'); ?>!</span>
          <a href="logout.php" class="btn btn-danger">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
          </a>
        </div>
      </div>
      
      <!-- Conteúdo -->
      <div class="content container-fluid">
        <!-- Botão para enviar notificações via WhatsApp -->
        <div class="mb-3">
          <button id="btnEnviarNotificacoes" class="btn btn-primary">
            <i class="fa-solid fa-paper-plane me-1"></i> Enviar Notificações
          </button>
        </div>
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h4><a href="treinamento.php"><i class="fa-solid fa-angle-left me-2" style="color: #283e51"></i></a>Lista de Clientes</h4>
            <button class="btn btn-custom" onclick="novoCliente()">
              <i class="fa-solid fa-plus me-1"></i> Novo Cliente
            </button>
          </div>
          <div class="card-body">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Cliente</th>
                  <th>CNPJ/CPF</th>
                  <th>Serial</th>
                  <th>Horas Adquiridas (minutos)</th>
                  <th>Horas Utilizadas (minutos)</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                <tr class="<?= $row['ativo'] == 0 ? 'table-secondary' : '' ?>">
                  <td><?= $row['id'] ?></td>
                  <td><?= htmlspecialchars($row['cliente'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($row['cnpjcpf'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($row['serial'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= $row['horas_adquiridas'] ?></td>
                  <td><?= $row['horas_utilizadas'] ?></td>
                  <td>
                  <button class="btn btn-sm btn-warning" onclick="editarCliente(
                    '<?= $row['id'] ?>',
                    '<?= addslashes($row['cliente']) ?>',
                    '<?= addslashes($row['cnpjcpf']) ?>',
                    '<?= addslashes($row['serial']) ?>',
                    '<?= $row['horas_adquiridas'] ?>',
                    '<?= addslashes($row['whatsapp']) ?>',
                    '<?= $row['data_conclusao'] ?>'
                  )">Editar</button>
                  <?php if ($row['ativo'] == 1): ?>
                    <a href="inativar_cliente.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Deseja inativar este cliente?')">Inativar</a>
                  <?php else: ?>
                    <a href="ativar_cliente.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Deseja ativar este cliente?')">Ativar</a>
                  <?php endif; ?>
                </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div> <!-- /w-100 -->
  </div> <!-- /d-flex-wrapper -->
  
  <!-- Modal para Cadastro/Editar Cliente -->
  <div class="modal fade" id="modalCliente" tabindex="-1" aria-labelledby="modalClienteLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="formCliente" method="post">
          <div class="modal-header">
            <h5 class="modal-title" id="modalClienteLabel">Cadastrar Cliente</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="id" id="cliente_id">
            <div class="mb-3">
              <label for="cliente_nome" class="form-label">Nome do Cliente</label>
              <input type="text" name="cliente" id="cliente_nome" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="cliente_cnpjcpf" class="form-label">CNPJ/CPF</label>
              <input type="text" name="cnpjcpf" id="cliente_cnpjcpf" class="form-control">
            </div>
            <div class="mb-3">
              <label for="cliente_serial" class="form-label">Serial</label>
              <input type="text" name="serial" id="cliente_serial" class="form-control">
            </div>
            <!-- Novo campo para WhatsApp -->
            <div class="mb-3">
              <label for="cliente_whatsapp" class="form-label">Número do WhatsApp</label>
              <input type="text" name="whatsapp" id="cliente_whatsapp" class="form-control" placeholder="+55XXXXXXXXXXX">
            </div>
            <!-- Novo campo para Data de Conclusão do Treinamento -->
            <div class="mb-3">
              <label for="data_conclusao" class="form-label">Data de Conclusão do Treinamento</label>
              <input type="date" name="data_conclusao" id="data_conclusao" class="form-control">
            </div>
            <div class="mb-3">
              <label for="horas_adquiridas" class="form-label">Horas Adquiridas (minutos)</label>
              <input type="number" name="horas_adquiridas" id="horas_adquiridas" class="form-control" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Modal de Confirmação de Duplicata -->
  <div class="modal fade" id="modalDuplicate" tabindex="-1" aria-labelledby="modalDuplicateLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalDuplicateLabel">Cliente já cadastrado</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <p>Já existe um cliente com esse CNPJ/CPF ou Serial. Deseja abrir o cadastro existente?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" id="btnAbrirCadastro">Sim</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Não</button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Modal: Excedeu Horas Contratadas (usado para cadastro/edição) -->
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
  
  <!-- Modal: Resultado das Notificações -->
  <div class="modal fade" id="modalNotificacoes" tabindex="-1" aria-labelledby="modalNotificacoesLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalNotificacoesLabel">Resultado das Notificações</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div id="notificacoesMensagem"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
  
  <script>
    // Funções para cadastro/edição de clientes
    let duplicateClientData = null;

    function novoCliente() {
      $('#cliente_id').val('');
      $('#cliente_nome').val('');
      $('#cliente_cnpjcpf').val('');
      $('#cliente_serial').val('');
      $('#cliente_whatsapp').val('');
      $('#data_conclusao').val('');
      $('#horas_adquiridas').val('');
      $('#modalClienteLabel').text('Cadastrar Cliente');
      $('#formCliente').attr('action', 'cadastrar_cliente.php');
      var modal = new bootstrap.Modal(document.getElementById('modalCliente'));
      modal.show();
    }

    function editarCliente(id, cliente, cnpjcpf, serial, horasAdquiridas, whatsapp, dataConclusao) {
  $('#cliente_id').val(id);
  $('#cliente_nome').val(cliente);
  $('#cliente_cnpjcpf').val(cnpjcpf);
  $('#cliente_serial').val(serial);
  $('#horas_adquiridas').val(horasAdquiridas);
  $('#cliente_whatsapp').val(whatsapp);
  $('#data_conclusao').val(dataConclusao);
  $('#modalClienteLabel').text('Editar Cliente');
  $('#formCliente').attr('action', 'editar_cliente.php');
  var modal = new bootstrap.Modal(document.getElementById('modalCliente'));
  modal.show();
}

    // Submissão via Ajax para cadastro/edição de cliente
    $('#formCliente').on('submit', function(e) {
      e.preventDefault();
      $.ajax({
        url: $(this).attr('action'),
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
          if(response.status === 'duplicate') {
            duplicateClientData = response;
            var duplicateModal = new bootstrap.Modal(document.getElementById('modalDuplicate'));
            duplicateModal.show();
          } else if(response.status === 'success') {
            alert(response.message);
            location.reload();
          } else {
            alert(response.message);
          }
        },
        error: function() {
          alert('Erro na comunicação com o servidor.');
        }
      });
    });

    $('#btnAbrirCadastro').on('click', function() {
      var duplicateModal = bootstrap.Modal.getInstance(document.getElementById('modalDuplicate'));
      duplicateModal.hide();
      editarCliente(
        duplicateClientData.id,
        duplicateClientData.cliente,
        duplicateClientData.cnpjcpf,
        duplicateClientData.serial,
        duplicateClientData.horas_adquiridas
      );
    });

    // Botão para enviar notificações via WhatsApp
    $('#btnEnviarNotificacoes').on('click', function(){
      $.ajax({
          url: 'enviar_notificacoes.php',
          method: 'GET',
          dataType: 'json',
          success: function(response) {
              let htmlMsg = "<p>" + response.message + "</p>";
              if(response.notified && response.notified.length > 0) {
                  htmlMsg += "<p><strong>Clientes notificados:</strong><br>" + response.notified.join("<br>") + "</p>";
              }
              if(response.errors && response.errors.length > 0) {
                  htmlMsg += "<p class='text-danger'><strong>Erros:</strong><br>" + response.errors.join("<br>") + "</p>";
              }
              $('#notificacoesMensagem').html(htmlMsg);
              let modalNotificacoes = new bootstrap.Modal(document.getElementById('modalNotificacoes'));
              modalNotificacoes.show();
          },
          error: function(){
              alert("Erro na comunicação com o servidor para envio de notificações.");
          }
      });
    });

    // Configuração do FullCalendar
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
          // Preenche os campos do cliente e armazena os valores originais
          document.getElementById('edit_cliente').value = eventObj.extendedProps.cliente;
          document.getElementById('edit_cliente_id').value = eventObj.extendedProps.cliente_id;
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
    
    // Exibe alerta de proximidade (exemplo, adapte conforme necessário)
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
</body>
</html>
