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
    <!-- Sidebar (reaproveitada) -->
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
        <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-calculator me-2"></i> Totalizadores</a>
        <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i> Usuários</a>
        <a class="nav-link" href="treinamento.php"><i class="fa-solid fa-calendar-check me-2"></i> Treinamentos</a>
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
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h4>Lista de Clientes</h4>
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
                  <th>Horas Adquiridas</th>
                  <th>Horas Utilizadas</th>
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
                      '<?= $row['horas_adquiridas'] ?>'
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

  <!-- Bootstrap JS e jQuery -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    // Variável para armazenar os dados do cliente duplicado
    let duplicateClientData = null;

    function novoCliente() {
      $('#cliente_id').val('');
      $('#cliente_nome').val('');
      $('#cliente_cnpjcpf').val('');
      $('#cliente_serial').val('');
      $('#horas_adquiridas').val('');
      $('#modalClienteLabel').text('Cadastrar Cliente');
      $('#formCliente').attr('action', 'cadastrar_cliente.php');
      var modal = new bootstrap.Modal(document.getElementById('modalCliente'));
      modal.show();
    }

    function editarCliente(id, cliente, cnpjcpf, serial, horasAdquiridas) {
      $('#cliente_id').val(id);
      $('#cliente_nome').val(cliente);
      $('#cliente_cnpjcpf').val(cnpjcpf);
      $('#cliente_serial').val(serial);
      $('#horas_adquiridas').val(horasAdquiridas);
      $('#modalClienteLabel').text('Editar Cliente');
      $('#formCliente').attr('action', 'editar_cliente.php');
      var modal = new bootstrap.Modal(document.getElementById('modalCliente'));
      modal.show();
    }

    // Submissão via Ajax para cadastro/edição
    $('#formCliente').on('submit', function(e) {
      e.preventDefault();
      $.ajax({
        url: $(this).attr('action'),
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
          if(response.status === 'duplicate') {
            // Armazena os dados do cliente duplicado
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

    // Ao clicar em "Sim" no modal de duplicata, abre o modal de edição com os dados do cliente duplicado
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

    // Quando o modal de cadastro/edição for fechado (por exemplo, ao clicar em "Cancelar"), recarrega a página
    $('#modalCliente').on('hidden.bs.modal', function () {
       location.reload();
    });
  </script>
</body>
</html>
