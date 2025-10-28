<?php
include '../Config/Database.php';

require_once __DIR__ . '/../Includes/auth.php';

$usuario_id   = $_SESSION['usuario_id'];
$cargo        = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

// Consulta para obter os usuários com vínculo (equipe e nível)
$query = "SELECT
            u.Id,
            u.Nome,
            u.Email,
            ena.idEquipe,
            GROUP_CONCAT(ena.idNivel) AS niveis,
            u.Cargo,
            IFNULL(c.descricao, 'Não definido') AS EquipeDescricao,
            GROUP_CONCAT(n.descricao SEPARATOR ', ') AS NivelDescricao
          FROM TB_USUARIO u
          LEFT JOIN TB_EQUIPE_NIVEL_ANALISTA ena ON ena.idUsuario = u.Id
          LEFT JOIN TB_EQUIPE c ON ena.idEquipe = c.id
          LEFT JOIN TB_NIVEL n ON ena.idNivel = n.id
          GROUP BY u.Id
          ORDER BY u.Nome ASC";
$result = mysqli_query($conn, $query);

// Consulta para as equipes
$queryC = "SELECT id, descricao FROM TB_EQUIPE";
$resultC = mysqli_query($conn, $queryC);
$equipes = [];
while ($row = mysqli_fetch_assoc($resultC)) {
  $equipes[] = $row;
}

// Consulta para os níveis
$queryN = "SELECT id, descricao FROM TB_NIVEL";
$resultN = mysqli_query($conn, $queryN);
$niveis = [];
while ($row = mysqli_fetch_assoc($resultN)) {
  $niveis[] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Painel N3 - Usuários Moderno</title>
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icones -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">

  <link rel="icon" href="../Public/Image/LogoTituto.png" type="image/png">
  <!-- CSS externo -->
  <link rel="stylesheet" href="../Public/usuarios.css">
</head>
<body class="bg-light">
  <div class="d-flex-wrapper">
    <!-- Sidebar -->
    <?php include __DIR__ . '/../components/sidebar.php'; ?>
    <!-- Minimalist Modern Toast Layout -->
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
              msg = "Usuário Cadastrado!";
              break;
            case "2":
              msg = "Usuário Editado!";
              break;
            case "3":
              msg = "Usuário Excluído!";
              break;
            case "4":
              msg = "Erro ao cadastrar!";
              break;
          }
          if (msg) showToast(msg, "success");
        }

        if (error) {
          let msg = "";
          switch (error) {
            case "1":
              msg = "Email já existe!";
              break;
            case "2":
              msg = "Não é possível excluir usuário que possui vínculos!";
              break;
          }
          if (msg) showToast(msg, "error");
        }
      });
    </script>
    <!-- Main -->
    <div class="w-100">
      <!-- Header -->
      <div class="header">
        <h3>Controle de Usuários</h3>
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
              <h4>Usuários</h4>
              <small>Gerencie os usuários do sistema</small>
            </div>
            <div class="d-flex align-items-center">
              <input type="text" id="searchInput" class="form-control" style="width: 200px;" placeholder="Pesquisar...">
              <button class="btn btn-custom ms-2" data-bs-toggle="modal" data-bs-target="#modalCadastro">
                <i class="fa-solid fa-plus me-1"></i> Novo Usuário
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive access-scroll">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>Nome</th>
                    <th style="width: 10%">Email</th>
                    <th class="text-center">Cargo</th>
                    <th class="text-center">Equipe</th>
                    <th class="text-center">Nível</th>
                    <th class="text-center">Ações</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if($result && mysqli_num_rows($result) > 0): ?>
                    <?php while($user = mysqli_fetch_assoc($result)): ?>
                      <tr>
                        <td><?= $user['Nome'] ?></td>
                        <td><?= $user['Email'] ?></td>
                        <td class="text-center"><?= $user['Cargo'] ?></td>
                        <td class="text-center"><?= $user['EquipeDescricao'] ?></td>
                        <td class="text-center sobrepor"><?= $user['NivelDescricao'] ?></td>
                        <td class="text-center">
                        <button type="button" class="btn btn-outline-primary btn-sm" 
                          onclick="editarUser('<?= $user['Id'] ?>', '<?= addslashes($user['Nome']) ?>', '<?= addslashes($user['Email']) ?>', '<?= $user['idEquipe'] ?>', '<?= $user['niveis'] ?>', '<?= $user['Cargo'] ?>')">
                          <i class="fa-solid fa-pen"></i>
                        </button>
                          <button type="button" class="btn btn-outline-danger btn-sm" 
                            onclick="modalExcluir('<?= $user['Id'] ?>', '<?= addslashes($user['Nome']) ?>')">
                            <i class="fa-solid fa-trash"></i>
                          </button>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="6" class="text-center">Nenhum usuário encontrado.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <!-- Modal de Cadastro -->
      <div class="modal fade" id="modalCadastro" tabindex="-1" aria-labelledby="modalCadastroLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="modalCadastroLabel">Novo Usuário</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form action="cadastrar_usuario.php" method="post">
              <div class="modal-body">
                <div class="mb-3">
                  <label for="nome_cad" class="form-label">Nome</label>
                  <input type="text" name="Nome" id="nome_cad" class="form-control" required>
                </div>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="email_cad" class="form-label">Email</label>
                    <input type="email" name="Email" id="email_cad" class="form-control" required>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="senha_cad" class="form-label">Senha</label>
                    <input type="password" name="Senha" id="senha_cad" class="form-control" required>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="equipe_cad" class="form-label">Equipe:</label>
                    <select name="idEquipe" id="equipe_cad" class="form-select" required>
                      <?php foreach($equipes as $equipe): ?>
                        <option value="<?= $equipe['id'] ?>"><?= $equipe['descricao'] ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="cargo_cad" class="form-label">Cargo:</label>
                    <select name="Cargo" id="cargo_cad" class="form-select" required>
                      <option value="Admin">Admin</option>
                      <option value="Comercial">Comercial</option>
                      <option value="Conversor">Conversor</option>
                      <option value="Produto">Produto</option>
                      <option value="User">User</option>
                      <option value="Viewer">Viewer</option>
                    </select>
                  </div>
                </div>
                <!-- Checklist de Níveis em rows com máximo 3 itens -->
                <div class="mb-3">
                  <label class="form-label">Níveis:</label>
                  <div id="cadastro-niveis-checklist" class="niveis-checklist">
                    <?php foreach($niveis as $nivel): ?>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="idNivel[]" value="<?= $nivel['id'] ?>" id="cad_nivel_<?= $nivel['id'] ?>">
                        <label class="form-check-label" for="cad_nivel_<?= $nivel['id'] ?>">
                          <?= $nivel['descricao'] ?>
                        </label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-custom">Cadastrar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <!-- Modal de Edição -->
      <div class="modal fade" id="modalEditar" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="modalEditarLabel">Editar Usuário</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form action="editar_usuario.php" method="post">
              <div class="modal-body">
                <input type="hidden" name="id" id="editar_id">
                <div class="mb-3">
                  <label for="nome_edit" class="form-label">Nome</label>
                  <input type="text" name="Nome" id="nome_edit" class="form-control" required>
                </div>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="email_edit" class="form-label">Email</label>
                    <input type="email" name="Email" id="email_edit" class="form-control" required>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="senha_edit" class="form-label">Senha</label>
                    <input type="password" name="Senha" id="senha_edit" class="form-control" placeholder="Preencha para alterar">
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="equipe_edit" class="form-label">Equipe</label>
                    <select name="idEquipe" id="equipe_edit" class="form-select" required>
                      <?php foreach($equipes as $equipe): ?>
                        <option value="<?= $equipe['id'] ?>"><?= $equipe['descricao'] ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="edit_cargo_cad" class="form-label">Cargo</label>
                    <select name="Cargo" id="edit_cargo_cad" class="form-select" required>
                      <option value="Admin">Admin</option>
                      <option value="Comercial">Comercial</option>
                      <option value="Conversor">Conversor</option>
                      <option value="Produto">Produto</option>
                      <option value="User">User</option>
                      <option value="Viewer">Viewer</option>
                    </select>
                  </div>
                </div>
                <!-- Checklist de Níveis (modificado para exibir no máximo 3 por row) -->
                <div class="mb-3">
                  <label class="form-label">Níveis:</label>
                  <div id="edit-niveis-checklist" class="niveis-checklist">
                    <?php foreach($niveis as $nivel): ?>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="idNivel[]" value="<?= $nivel['id'] ?>" id="edit_nivel_<?= $nivel['id'] ?>">
                        <label class="form-check-label" for="edit_nivel_<?= $nivel['id'] ?>">
                          <?= $nivel['descricao'] ?>
                        </label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-custom">Salvar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <!-- Modal de Exclusão -->
      <div class="modal fade" id="modalExcluir" tabindex="-1" aria-labelledby="modalExcluirLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="modalExcluirLabel">Excluir Usuário</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form action="deletar_usuario.php" method="post">
              <div class="modal-body">
                <input type="hidden" name="id" id="excluir_id">
                <p>Tem certeza que deseja excluir o usuário <strong id="excluir_nome"></strong>?</p>
              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-danger">Excluir</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Scripts JS -->
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

    function editarUser(id, nome, email, idEquipe, niveisUser, cargo) {
    document.getElementById('editar_id').value = id;
    document.getElementById('nome_edit').value = nome;
    document.getElementById('email_edit').value = email;
    document.getElementById('equipe_edit').value = idEquipe;
    document.getElementById('edit_cargo_cad').value = cargo;
    
    // Desmarca todos os checkboxes
    const checkboxes = document.querySelectorAll('#edit-niveis-checklist .form-check-input');
    checkboxes.forEach(chk => chk.checked = false);
    
    // Se houver níveis associados, marque os checkboxes correspondentes
    if (niveisUser) {
      const niveisArray = niveisUser.split(',').map(item => item.trim());
      niveisArray.forEach(function(nivelId) {
        const checkbox = document.getElementById('edit_nivel_' + nivelId);
        if (checkbox) {
          checkbox.checked = true;
        }
      });
    }
    
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
  }

  function modalExcluir(id, nome) {
      document.getElementById('excluir_id').value = id;
      document.getElementById('excluir_nome').textContent = nome;
      new bootstrap.Modal(document.getElementById('modalExcluir')).show();
    }
  </script>
</body>
</html>