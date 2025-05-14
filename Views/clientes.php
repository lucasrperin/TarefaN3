<?php
include '../Config/Database.php';
session_start();
 
// Verifica se o usuário está autenticado
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
  <!-- CSS customizado -->
  <link rel="icon" href="../Public/Image/LogoTituto.png" type="image/png">
  <link rel="stylesheet" href="../Public/treinamento.css">
  
  
  <style>
    /* Ajustes para coluna de ações */
    th.acoes, td.acoes { width: 100px !important; }
    /* Botões de ação minimalistas */
    .acao-btn {
      background: none;
      border: none;
      padding: 4px;
      margin: 0 2px;
    }
    .acao-btn i {
      font-size: 0.9rem !important;
      color: #666;
      opacity: 0.8;
    }
    .acao-btn i:hover {
      opacity: 1;
      color: #333;
    }
  </style>
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
        <?php if ($cargo != 'Comercial'): ?>
          <a class="nav-link" href="okr.php"><img src="../Public/Image/benchmarkbranco.png" width="27" height="27" class="me-1" alt="Benchmark">OKRs</a>
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
    
    <!-- Main Content -->
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

  <!-- Accordion para Notificações Enviadas e Totalizadores -->
  <div class="accordion mb-4" id="notificacoesAccordion">

    <!-- Notificações Enviadas -->
    <div class="accordion-item mb-3">
      <h2 class="accordion-header" id="headingNotificacoes">
        <button class="accordion-button collapsed"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#collapseNotificacoes"
                aria-expanded="false"
                aria-controls="collapseNotificacoes">
          Notificações Enviadas
        </button>
      </h2>
      <div id="collapseNotificacoes"
           class="accordion-collapse collapse"
           aria-labelledby="headingNotificacoes"
           data-bs-parent="#notificacoesAccordion">
        <div class="accordion-body">
          <ul class="list-group" id="listaNotificacoes">
            <li class="list-group-item">Carregando notificações...</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Totalizadores -->
    <div class="accordion-item mb-3">
      <h2 class="accordion-header" id="headingTotalizadores">
        <button class="accordion-button collapsed"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#collapseTotalizadores"
                aria-expanded="false"
                aria-controls="collapseTotalizadores">
          Totalizadores
        </button>
      </h2>
      <div id="collapseTotalizadores"
           class="accordion-collapse collapse"
           aria-labelledby="headingTotalizadores"
           data-bs-parent="#notificacoesAccordion">
        <div class="accordion-body">
          <div class="row">
            <!-- Gráfico Mensal -->
            <div class="col-md-6">
              <canvas id="chartTotalizadores"></canvas>
            </div>
            <!-- Totais à direita do gráfico -->
            <div class="col-md-6">
                <!-- Total Geral em destaque -->
                <div class="card bg-light border-0 mb-4 text-center shadow-sm">
                  <div class="card-body">
                    <i class="fa-solid fa-chart-line fa-2x mb-2 text-primary"></i>
                    <h6 class="card-subtitle mb-1 text-muted">Geral de Faturamento</h6>
                    <h3 id="totalGeral" class="card-title display-6"></h3>
                  </div>
                </div>

                <!-- Subtotais em pequenas cartas lado a lado -->
                <div class="row gx-2">
                  <div class="col-6">
                    <div class="card bg-white border-0 text-center shadow-sm">
                      <div class="card-body py-3">
                        <i class="fa-solid fa-chalkboard-user fa-lg mb-2 text-success"></i>
                        <p class="mb-1 text-muted">Treinamentos</p>
                        <h5 id="totalTreinamentos" class="mb-0"></h5>
                      </div>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="card bg-white border-0 text-center shadow-sm">
                      <div class="card-body py-3">
                        <i class="fa-solid fa-handshake fa-lg mb-2 text-warning"></i>
                        <p class="mb-1 text-muted">Indicações</p>
                        <h5 id="totalIndicacoes" class="mb-0"></h5>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Espaçamento extra -->
  <div class="mb-2"></div>

<!-- Card de Lista de Clientes -->
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
          <a href="treinamento.php" class="me-2" style="color: #283e51; text-decoration: none;">
            <i class="fa-solid fa-angle-left"></i>
          </a>
          <h4 class="mb-0">Lista de Clientes</h4>
        </div>
        <div class="d-flex align-items-center flex-nowrap">
          <input type="text" id="clientSearch" class="form-control me-2" style="width: 200px;" placeholder="Pesquisar clientes...">
          <button class="btn btn-custom" onclick="novoCliente()">
            <i class="fa-solid fa-plus me-1"></i> Novo Cliente
          </button>
        </div>
      </div>
      <div class="card-body">
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Cliente</th>
              <th>CNPJ/CPF</th>
              <th>Serial</th>
              <th>Adquiridos</th>
              <th>Utilizados</th>
              <th>Faturamento</th>
              <th class="acoes">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = mysqli_fetch_assoc($result)): ?>
            <tr class="<?= $row['ativo'] == 0 ? 'table-secondary' : '' ?>">
              <td><?= htmlspecialchars($row['cliente'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($row['cnpjcpf'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($row['serial'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= $row['horas_adquiridas'] ?></td>
              <td><?= $row['horas_utilizadas'] ?></td>
              <td>
  <?php if($row['faturamento']=='FATURADO'): ?>
    <i class="fa-solid fa-brazilian-real-sign"></i> <?= number_format($row['valor_faturamento'],2,',','.') ?>
  <?php else: ?>
    BRINDE
  <?php endif; ?>
</td>
              <td class="acoes" style="width:150px; white-space: nowrap; text-align: center;">
                <div class="acao-container">
                  <button class="btn btn-sm btn-warning acao-btn" onclick="editarCliente(
                    '<?= $row['id'] ?>',
                    '<?= addslashes($row['cliente']) ?>',
                    '<?= addslashes($row['cnpjcpf']) ?>',
                    '<?= addslashes($row['serial']) ?>',
                    '<?= $row['horas_adquiridas'] ?>',
                    '<?= addslashes($row['whatsapp']) ?>',
                    '<?= $row['data_conclusao'] ?>',
                    '<?= $row['faturamento'] ?>',
                    '<?= $row['valor_faturamento'] ?>'
                  )" title="Editar">
                    <i class="fa-solid fa-pencil"></i>
                  </button>
                  <?php if ($row['ativo'] == 1): ?>
                    <a href="inativar_cliente.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger acao-btn" onclick="return confirm('Deseja inativar este cliente?')" title="Inativar">
                      <i class="fa-solid fa-user-slash"></i>
                    </a>
                  <?php else: ?>
                    <a href="ativar_cliente.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-success acao-btn" onclick="return confirm('Deseja ativar este cliente?')" title="Ativar">
                      <i class="fa-solid fa-user-check"></i>
                    </a>
                  <?php endif; ?>
                  <button class="btn btn-sm btn-info acao-btn" onclick="verNotificacoesCliente('<?= $row['id'] ?>', '<?= addslashes($row['cliente']) ?>')" title="Notificações">
                    <i class="fa-solid fa-comments"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div> <!-- /w-100 -->



<!-- Modal Cadastrar/Editar Cliente -->
<div class="modal fade" id="modalCliente" tabindex="-1" aria-labelledby="modalClienteLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formCliente" method="post" action="">
        <div class="modal-header">
          <h5 class="modal-title" id="modalClienteLabel">Cadastrar Cliente</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="cliente_id">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="cliente_nome" class="form-label">Nome do Cliente</label>
              <input type="text" name="cliente" id="cliente_nome" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="cliente_cnpjcpf" class="form-label">CNPJ/CPF</label>
              <input type="text" name="cnpjcpf" id="cliente_cnpjcpf" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
              <label for="cliente_serial" class="form-label">Serial</label>
              <input type="text" name="serial" id="cliente_serial" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
              <label for="cliente_whatsapp" class="form-label">WhatsApp</label>
              <input type="text" name="whatsapp" id="cliente_whatsapp" class="form-control" placeholder="+55XXXXXXXXXXX">
            </div>
            <div class="col-md-6 mb-3">
              <label for="data_conclusao" class="form-label">Data de Conclusão</label>
              <input type="date" name="data_conclusao" id="data_conclusao" class="form-control">
            </div>
            <div class="col-md-6 mb-3" id="group_faturamento" style="display:none;">
              <label for="faturamento_cliente" class="form-label">Faturamento</label>
              <select name="faturamento" id="faturamento_cliente" class="form-select">
                <option value="BRINDE">Brinde</option>
                <option value="FATURADO">Faturado</option>
              </select>
            </div>
            <div class="col-md-6 mb-3" id="group_valor_cliente" style="display:none;">
              <label for="valor_faturamento" class="form-label">Valor (R$)</label>
              <input type="number" step="0.01" name="valor_faturamento" id="valor_faturamento" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
              <label for="horas_adquiridas" class="form-label">Minutos Adquiridos (min)</label>
              <input type="number" name="horas_adquiridas" id="horas_adquiridas" class="form-control" required>
            </div>
          </div><!-- /.row -->
        </div><!-- /.modal-body -->
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
  
  <!-- Modal: Resultado das Notificações (global) -->
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
  
  <!-- Modal: Notificações Individuais do Cliente -->
  <div class="modal fade" id="modalNotificacoesCliente" tabindex="-1" aria-labelledby="modalNotificacoesClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalNotificacoesClienteLabel">Notificações de <span id="clienteNomeNotificacao"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <ul class="list-group" id="listaNotificacoesCliente">
            <li class="list-group-item">Carregando notificações...</li>
          </ul>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Bootstrap JS e jQuery -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
  
  <script>
    // Funções para gerenciamento de clientes
    let duplicateClientData = null;
    
    function novoCliente() {
  // limpa campos básicos
  $('#cliente_id').val('');
  $('#cliente_nome').val('');
  $('#cliente_cnpjcpf').val('');
  $('#cliente_serial').val('');
  $('#cliente_whatsapp').val('');
  $('#data_conclusao').val('');
  $('#horas_adquiridas').val('');

  // limpa e reseta faturamento/valor
  $('#faturamento_cliente').val('BRINDE');    // ou selectedIndex = 0
  $('#valor_faturamento').val('');

  // esconde os grupos opcionais
  $('#group_faturamento').hide();
  $('#group_valor_cliente').hide();

  // ajusta título e ação do form
  $('#modalClienteLabel').text('Cadastrar Cliente');
  $('#formCliente').attr('action', 'cadastrar_cliente.php');

  // exibe o modal
  var modal = new bootstrap.Modal(document.getElementById('modalCliente'));
  modal.show();
}
    
function editarCliente(
  id, cliente, cnpjcpf, serial,
  horasAdquiridas, whatsapp,
  dataConclusao, faturamento, valorFaturamento
) {
  $('#cliente_id').val(id);
  $('#cliente_nome').val(cliente);
  $('#cliente_cnpjcpf').val(cnpjcpf);
  $('#cliente_serial').val(serial);
  $('#horas_adquiridas').val(horasAdquiridas);
  $('#cliente_whatsapp').val(whatsapp);
  $('#data_conclusao').val(dataConclusao);

  // dispara o toggle para faturamento/valor
  $('#data_conclusao').trigger('change');
  $('#faturamento_cliente').val(faturamento).trigger('change');
  $('#valor_faturamento').val(valorFaturamento);

  $('#modalClienteLabel').text('Editar Cliente');
  $('#formCliente').attr('action','editar_cliente.php');
  new bootstrap.Modal(document.getElementById('modalCliente')).show();
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
  // fecha modal de aviso
  var dupModal = bootstrap.Modal.getInstance(document.getElementById('modalDuplicate'));
  dupModal.hide();

  // busca o cliente completo no servidor, pedindo JSON (ajax=1)
  $.getJSON('form_cliente.php', { id: duplicateClientData.id, ajax:1 }, function(data) {
    editarCliente(
      data.id,
      data.cliente,
      data.cnpjcpf,
      data.serial,
      data.horas_adquiridas,
      data.whatsapp,
      data.data_conclusao,
      data.faturamento,
      data.valor_faturamento
    );
  });
});
    
    
    // Botão para enviar notificações via WhatsApp (global)
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
    
    // Função para filtrar a tabela de clientes
    $(document).ready(function(){
      $("#clientSearch").on("keyup", function() {
          var value = $(this).val().toLowerCase();
          $("table tbody tr").filter(function() {
              $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
          });
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
    
    // Exibe alerta de proximidade (exemplo, adaptar conforme necessário)
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
    
    function verNotificacoesCliente(clientId, clientName) {
  // Atualiza o título do modal com o nome do cliente
  $('#clienteNomeNotificacao').text(clientName);
  // Requisição AJAX com filtro por cliente_id:
  $.ajax({
     url: 'buscar_notificacoes.php',
     method: 'GET',
     data: { cliente_id: clientId },
     dataType: 'json',
     success: function(response) {
       let listaHtml = "";
       if (response.status === "success" && response.data.length > 0) {
         $.each(response.data, function(index, notif){
           listaHtml += '<li class="list-group-item">';
           listaHtml += '<strong>' + notif.titulo + '</strong><br>';
           listaHtml += notif.mensagem.replace(/\n/g, '<br>') + '<br>';
           listaHtml += '<small class="text-muted">Enviado em: ' + notif.data_envio + '</small>';
           listaHtml += '</li>';
         });
       } else {
         listaHtml = '<li class="list-group-item">Nenhuma notificação encontrada.</li>';
       }
       $('#listaNotificacoesCliente').html(listaHtml);
       let modal = new bootstrap.Modal(document.getElementById('modalNotificacoesCliente'));
       modal.show();
     },
     error: function(){
       $('#listaNotificacoesCliente').html('<li class="list-group-item text-danger">Erro ao carregar notificações.</li>');
     }
  });
}

$('#collapseNotificacoes').on('shown.bs.collapse', function(){
  $.ajax({
    url: 'buscar_notificacoes.php',
    method: 'GET',
    dataType: 'json',
    success: function(response) {
      console.log("Response:", response); // Debug: veja o que está retornando
      let listaHtml = "";
      if(response.status === "success" && response.data.length > 0) {
        $.each(response.data, function(index, notif){
          listaHtml += '<li class="list-group-item">';
          listaHtml += '<strong>' + notif.titulo + '</strong><br>';
          listaHtml += notif.mensagem.replace(/\n/g, '<br>') + '<br>';
          listaHtml += '<small class="text-muted">Enviado em: ' + notif.data_envio + '</small>';
          listaHtml += '</li>';
        });
      } else {
        // Se o array estiver vazio, mostra mensagem de que não há notificações
        listaHtml = '<li class="list-group-item">Nenhuma notificação enviada.</li>';
      }
      $('#listaNotificacoes').html(listaHtml);
    },
    error: function(jqXHR, textStatus, errorThrown){
      console.error("Erro na requisição:", textStatus, errorThrown);
      $('#listaNotificacoes').html('<li class="list-group-item text-danger">Erro ao buscar notificações.</li>');
    }
  });
});

    function toggleFields() {
      var show = !!$('#data_conclusao').val();
      $('#group_faturamento').toggle(show);
      var fat = $('#faturamento_cliente').val()==='FATURADO';
      $('#group_valor_cliente').toggle(fat);
      if (!fat) $('#valor_faturamento').val('');
    }
    $(function() {
      $('#data_conclusao').on('change', toggleFields);
      $('#faturamento_cliente').on('change', toggleFields);
    });

    // Remove qualquer backdrop que fique “perdido” quando o modal for fechado
$('#modalCliente').on('hidden.bs.modal', function () {
  // remove a classe que bloqueia o scroll/foco
  $('body').removeClass('modal-open');
  // remove backdrops órfãs
  $('.modal-backdrop').remove();
  // devolve o foco ao botão “Novo Cliente”
  $('#clientSearch').focus(); 
});
  </script>
  
  <!-- Modal: Notificações Individuais do Cliente -->
  <div class="modal fade" id="modalNotificacoesCliente" tabindex="-1" aria-labelledby="modalNotificacoesClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalNotificacoesClienteLabel">Notificações de <span id="clienteNomeNotificacao"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <ul class="list-group" id="listaNotificacoesCliente">
            <li class="list-group-item">Carregando notificações...</li>
          </ul>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Modal: Resultado das Notificações (global) -->
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
  <!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
 $('#collapseTotalizadores').on('shown.bs.collapse', function() {
  if (window.totalizadoresLoaded) return;
  window.totalizadoresLoaded = true;

 $.getJSON('totalizadores_clientes.php', function(resp) {
  // totais
  $('#totalGeral')
    .text((resp.totalGeral).toLocaleString('pt-BR', { style:'currency', currency:'BRL' }));
  $('#totalTreinamentos')
    .text((resp.totalTreinamentos).toLocaleString('pt-BR', { style:'currency', currency:'BRL' }));
  $('#totalIndicacoes')
    .text((resp.totalIndicacoes).toLocaleString('pt-BR', { style:'currency', currency:'BRL' }));


    // Extrai labels e dados
    const labels       = resp.monthly.map(r => r.mes);
    const dataBrinde   = resp.monthly.map(r => r.brinde);
    const dataFaturado = resp.monthly.map(r => r.faturado);

    // Gera o gráfico
    new Chart(document.getElementById('chartTotalizadores'), {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Brinde',
            data: dataBrinde,
            backgroundColor: 'rgba(54, 162, 235, 0.5)'
          },
          {
            label: 'Faturado',
            data: dataFaturado,
            backgroundColor: 'rgba(75, 192, 192, 0.5)'
          }
        ]
      },
      options: {
        responsive: true,
        scales: {
          x: {
            stacked: false
          },
          y: {
            beginAtZero: true,
            stacked: false
          }
        }
      }
    });
  });
});

</script>
</body>
</html>
