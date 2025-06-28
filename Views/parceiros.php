<?php
include '../Config/Database.php';
require_once __DIR__ . '/../Includes/auth.php';

$usuario_id   = $_SESSION['usuario_id'];
$cargo        = $_SESSION['cargo'] ?? '';
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

// Consulta parceiros
$query = "SELECT * FROM TB_PARCEIROS ORDER BY Nome ASC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <title>Painel N3 - Parceiros</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <link rel="icon" href="../Public/Image/LogoTituto.png" type="image/png">
  <link rel="stylesheet" href="../Public/usuarios.css"> <!-- usa o mesmo css por enquanto -->
</head>
<body class="bg-light">

<div class="d-flex-wrapper">
  <!-- Sidebar -->
  <div class="sidebar">
    <a class="light-logo" href="parceiros.php">
      <img src="../Public/Image/zucchetti_blue.png" width="150" alt="Logo Zucchetti">
    </a>
    <nav class="nav flex-column">
        <a class="nav-link" href="menu.php"><i class="fa-solid fa-house me-2"></i>Home</a>
        <?php if ($cargo === 'Admin'): ?>
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
        <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'User'): ?>
        <a class="nav-link" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin' || $cargo === 'Viewer' || $cargo === 'User' || $cargo === 'Conversor'): ?>
        <a class="nav-link" href="user.php"><i class="fa-solid fa-users-rectangle me-2"></i>Meu Painel</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin' || $cargo === 'Produto'): ?>
        <a class="nav-link active" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a>
        <?php endif; ?>
        <?php if ($cargo != 'Comercial'): ?>
            <a class="nav-link" href="okr.php"><img src="../Public/Image/benchmarkbranco.png" width="27" height="27" class="me-1" alt="Benchmark">OKRs</a>
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

  <!-- Toasts -->
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

  <!-- Main -->
  <div class="w-100">
    <!-- Header -->
    <div class="header">
      <h3>Controle de Parceiros</h3>
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
            
            <h4> 
                <a href="../index.php" class="me-2 text-decoration-none mb-2" style="color: #283e51; font-size: 18px;">
                    <i class="fa-solid fa-angle-left"></i>
                </a>
                Parceiros
            </h4>
            <small>Gerencie os parceiros cadastrados</small>
          </div>
          <div class="d-flex align-items-center">
            <input type="text" id="searchInput" class="form-control" style="width: 200px;" placeholder="Pesquisar...">
            <button class="btn btn-custom ms-2" data-bs-toggle="modal" data-bs-target="#modalCadastroParceiro">
              <i class="fa-solid fa-plus me-1"></i> Novo Parceiro
            </button>
          </div>
        </div>

        <div class="card-body">
          <div class="table-responsive access-scroll">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>Nome</th>
                  <th>CNPJ / CPF</th>
                  <th>Serial</th>
                  <th>Contato</th>
                  <th>Status</th>
                  <th class="text-center">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if($result && mysqli_num_rows($result) > 0): ?>
                  <?php while($parceiro = mysqli_fetch_assoc($result)): ?>
                    <tr>
                      <td><?= htmlspecialchars($parceiro['Nome']) ?></td>
                      <td><?= htmlspecialchars($parceiro['CPNJ_CPF']) ?></td>
                      <td><?= htmlspecialchars($parceiro['serial']) ?></td>
                      <td><?= htmlspecialchars($parceiro['contato']) ?></td>
                      <td><?= ($parceiro['status'] === 'A') ? 'Ativo' : 'Inativo' ?></td>
                      <td class="text-center">
                        <!-- Editar -->
                        <button type="button" class="btn btn-outline-primary btn-sm"
                          onclick="editarParceiro('<?= $parceiro['Id'] ?>', '<?= addslashes($parceiro['Nome']) ?>', '<?= addslashes($parceiro['CPNJ_CPF']) ?>', '<?= addslashes($parceiro['serial']) ?>', '<?= addslashes($parceiro['contato']) ?>', '<?= $parceiro['status'] ?>')">
                          <i class="fa-solid fa-pen"></i>
                        </button>
                        <!-- Ativar/Inativar -->
                        <?php if ($parceiro['status'] === 'A'): ?>
                            <a href="../Public/Php/alterar_status_parceiro.php?id=<?= $parceiro['Id'] ?>&status=I" 
                              class="btn btn-outline-warning btn-sm" title="Ativar/Inativar">
                              <i class="fa-solid fa-ban"></i>
                            </a>
                        <?php else: ?>
                            <a href="../Public/Php/alterar_status_parceiro.php?id=<?= $parceiro['Id'] ?>&status=A" 
                              class="btn btn-outline-success btn-sm" title="Ativar/Inativar">
                              <i class="fa-solid fa-check"></i>
                            </a>
                        <?php endif; ?>
                        <!-- Deletar -->
                        <button type="button" class="btn btn-outline-danger btn-sm"
                            onclick="modalExcluirParceiro('<?= $parceiro['Id'] ?>', '<?= addslashes($parceiro['Nome']) ?>')">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" class="text-center">Nenhum parceiro cadastrado.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de Cadastrar Parceiro -->
    <?php include '../Public/Modals/modal_cadastro_parceiro.php'; ?>
    <!-- Modal de Editar Parceiro -->
    <?php include '../Public/Modals/modal_editar_parceiro.php'; ?>
    <!-- Modal de Excluir Parceiro -->
    <?php include '../Public/Modals/modal_excluir_parceiro.php'; ?>
    
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
  $(document).ready(function(){
    $("#searchInput").on("keyup", function() {
      var value = $(this).val().toLowerCase();
      $("table tbody tr").filter(function() {
        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
      });
    });
  });

  function editarParceiro(id, nome, cnpj, serial, contato, status) {
    document.getElementById('editar_id_parceiro').value = id;
    document.getElementById('nome_parceiro_edit').value = nome;
    document.getElementById('cnpj_cpf_edit').value = cnpj;
    document.getElementById('serial_edit').value = serial;
    document.getElementById('contato_edit').value = contato;

    new bootstrap.Modal(document.getElementById('modalEditarParceiro')).show();
  }

  function modalExcluirParceiro(id, nome) {
    document.getElementById('id_excluir_parceiro').value = id;
    document.getElementById('excluir_nome_parceiro').textContent = nome;
    new bootstrap.Modal(document.getElementById('modalExclusaoParceiro')).show();
}



  function showToast(message, type) {
    const container = document.getElementById("toast-container");
    const toast = document.createElement("div");
    toast.className = "toast " + type;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => {
      toast.classList.add("show");
    }, 10);
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
      if (success === "1") msg = "Parceiro cadastrado!";
      if (success === "2") msg = "Parceiro editado!";
      if (success === "3") msg = "Parceiro removido!";
      if (success === "4") msg = "Status Alterado!";
      if (msg) showToast(msg, "success");
    }

    if (error) {
      let msg = "";
      if (error === "1") msg = "Erro ao cadastrar parceiro!";
      if (msg) showToast(msg, "error");
    }
  });
</script>

</body>
</html>
