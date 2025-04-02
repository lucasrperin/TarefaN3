<?php
include '../Config/Database.php';

session_start();

// Verifica se o usuário está logado; se não, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

$usuario_id = $_SESSION['usuario_id'];
$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';

// Consulta para obter os usuários com vínculo (equipe e nível)
$query = "SELECT
            u.Id, 
            u.Nome, 
            u.Email, 
            ena.idEquipe, 
            ena.idNivel,
            u.Cargo,
            IFNULL(c.descricao, 'Não definido') AS EquipeDescricao,
            IFNULL(n.descricao, 'Não definido') AS NivelDescricao
          FROM TB_USUARIO u
          LEFT JOIN TB_EQUIPE_NIVEL_ANALISTA ena ON ena.idUsuario = u.Id
          LEFT JOIN TB_EQUIPE c ON ena.idEquipe = c.id
          LEFT JOIN TB_NIVEL n ON ena.idNivel = n.id
          ORDER BY u.Id ASC";
$result = mysqli_query($conn, $query);

// Consulta para as equipes
$queryC = "SELECT id, descricao FROM TB_EQUIPE";
$resultC = mysqli_query($conn, $queryC);
$equipes = [];
while($row = mysqli_fetch_assoc($resultC)) {
    $equipes[] = $row;
}

// Consulta para os níveis
$queryN = "SELECT id, descricao FROM TB_NIVEL";
$resultN = mysqli_query($conn, $queryN);
$niveis = [];
while($row = mysqli_fetch_assoc($resultN)) {
    $niveis[] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Controle de Usuários</title>
    <!-- Link para o CSS principal -->
    <link href="../Public/usuarios.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Ícones do Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Fonte personalizada -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- JQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
  <nav class="navbar navbar-dark bg-dark">
    <div class="container d-flex justify-content-between align-items-center">
      <!-- Botão Hamburguer com Dropdown -->
      <div class="dropdown">
        <button class="navbar-toggler" type="button" id="menuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="navbar-toggler-icon"></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="menuDropdown">
          <li><a class="dropdown-item" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversão</a></li>
          <li><a class="dropdown-item" href="escutas.php"><i class="fa-solid fa-headphones me-2"></i>Escutas</a></li>
          <li><a class="dropdown-item" href="folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a></li>
          <li><a class="dropdown-item" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a></li>
          <li><a class="dropdown-item" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a></li>
          <li><a class="dropdown-item" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a></li>
          <li><a class="dropdown-item" href="dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a></li>
        </ul>
      </div>
      <span class="text-white">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
      <a href="menu.php" class="btn btn-danger">
        <i class="fa-solid fa-arrow-left me-2" style="font-size: 0.8em;"></i>Voltar
      </a>
    </div>
  </nav>

  <!-- Container do Toast no canto superior direito -->
  <div class="toast-container">
      <div id="toastSucesso" class="toast">
          <div class="toast-body">
              <i class="fa-solid fa-check-circle"></i> <span id="toastMensagem"></span>
          </div>
      </div>
  </div>
  <!-- Toast de Erro -->
  <div class="toast-container"> 
    <div id="toastErro" class="toast bg-danger text-white" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="toast-body">
        <i class="fa-solid fa-exclamation-triangle"></i> <span id="toastMensagemErro"></span>
      </div>
    </div>
  </div>

  <script>
    //Toast para mensagem de sucesso e erro
    document.addEventListener("DOMContentLoaded", function () {
      const urlParams = new URLSearchParams(window.location.search);
      const success = urlParams.get("success");
      const error = urlParams.get("error");

      if (success) {
          let mensagem = "";
          switch (success) {
              case "1":
                  mensagem = "Usuário cadastrado com sucesso!";
                  break;
              case "2":
                  mensagem = "Usuário editado com sucesso!";
                  break;
              case "3":
                  mensagem = "Usuário excluído com sucesso!";
                  break;
              case "4":
                  mensagem = "Erro ao cadastrar usuário, verifique!";
                  break;
          }
          if (mensagem) {
              document.getElementById("toastMensagem").textContent = mensagem;
              var toastEl = document.getElementById("toastSucesso");
              var toast = new bootstrap.Toast(toastEl, { delay: 2200 });
              toast.show();
          }
      }
      
      if (error) {
          let mensagem = "";
          switch (error) {
              case "1":
                mensagem = "E-mail já cadastrado!";
                break;
          }
          if (mensagem) {
              // Exibe um toast de erro
              document.getElementById("toastMensagemErro").textContent = mensagem;
              var toastElErro = document.getElementById("toastErro");
              var toastErro = new bootstrap.Toast(toastElErro, { delay: 2200 });
              toastErro.show();
          }
      }
    });
  </script>

  <!-- Função de pesquisa nas tabelas-->
  <script>
      $(document).ready(function(){
        $("#searchInput").on("keyup", function() {
          var value = $(this).val().toLowerCase();
          // Para cada linha em todas as tabelas com a classe 'tabelaEstilizada'
          $(".tabelaEstilizada tbody tr").filter(function() {
            // Se o texto da linha conter o valor da pesquisa (ignorando maiúsculas/minúsculas), mostra a linha; caso contrário, oculta
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
          });
        });
      });
  </script>
  
  <div class="container mt-4">
    <div class="card shadow mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h1 class="mb-0">Controle de Usuários</h1>
        <div class="d-flex justify-content-end gap-2">
          <input type="text" id="searchInput" class="form-control ms-2" style="max-width: 200px;" placeholder="Pesquisar...">
          <!-- Botão para abrir o modal de cadastro -->
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastro">
            <i class="fa-solid fa-plus-circle me-1"></i> Cadastrar
          </button>
        </div>
      </div>
      <div class="card-body">
        <div class="table-responsive access-scroll">
          <table class="table table-striped table-bordered tabelaEstilizada ">
            <thead class="table-dark">
              <tr>
                <th>Nome</th>
                <th>Email</th>
                <th>Cargo</th>
                <th>Equipe</th>
                <th>Nível</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if($result && mysqli_num_rows($result) > 0): ?>
                <?php while($user = mysqli_fetch_assoc($result)): ?>
                  <tr>
                    <td><?= $user['Nome'] ?></td>
                    <td><?= $user['Email'] ?></td>
                    <td><?= $user['Cargo'] ?></td>
                    <td><?= $user['EquipeDescricao'] ?></td>
                    <td><?= $user['NivelDescricao'] ?></td>
                    <td>
                      <button type="button" title="Editar" class="btn btn-outline-primary btn-sm" 
                        onclick="editarUser('<?= $user['Id'] ?>', 
                                              '<?= addslashes($user['Nome']) ?>', 
                                              '<?= addslashes($user['Email']) ?>', 
                                              '<?= $user['idEquipe'] ?>', 
                                              '<?= $user['idNivel'] ?>', 
                                              '<?= $user['Cargo'] ?>')">
                        <i class="fa-solid fa-pen"></i>
                      </button>
                      <button class="btn btn-outline-danger btn-sm" 
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
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="cadastrar_usuario.php" method="post">
            <div class="modal-body">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="nome_cad" class="form-label">Nome:</label>
                  <input type="text" name="Nome" id="nome_cad" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="equipe_cad" class="form-label">Equipe:</label>
                  <select name="idEquipe" id="equipe_cad" class="form-select">
                    <?php foreach($equipes as $equipe): ?>
                      <option value="<?= $equipe['id'] ?>"><?= $equipe['descricao'] ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="email_cad" class="form-label">Email:</label>
                  <input type="email" name="Email" id="email_cad" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="senha_cad" class="form-label">Senha:</label>
                  <input type="password" name="Senha" id="senha_cad" class="form-control" required>
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="nivel_cad" class="form-label">Nível:</label>
                  <select name="idNivel" id="nivel_cad" class="form-select">
                    <?php foreach($niveis as $nivel): ?>
                      <option value="<?= $nivel['id'] ?>"><?= $nivel['descricao'] ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="cargo_cad" class="form-label">Cargo:</label>
                  <select name="Cargo" id="cargo_cad" class="form-select" required>
                    <option value="Admin">Admin</option>
                    <option value="Comercial">Comercial</option>
                    <option value="Conversor">Conversor</option>
                    <option value="User">User</option>
                    <option value="Viewer">Viewer</option>
                  </select>
                </div>
              </div>
          </div>
          <div class="modal-footer">
            <input type="submit" value="Cadastrar" class="btn btn-primary">
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal de Edição -->
  <div class="modal fade" id="modalEditar" tabindex="-1" role="dialog" aria-labelledby="modalEditarLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalEditarLabel">Editar Usuário</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="editar_usuario.php" method="post">
          <div class="modal-body">
              <input type="hidden" name="id" id="editar_id">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="nome_edit" class="form-label">Nome:</label>
                  <input type="text" name="Nome" id="nome_edit" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="equipe_edit" class="form-label">Equipe:</label>
                  <select name="idEquipe" id="equipe_edit" class="form-select">
                    <?php foreach($equipes as $equipe): ?>
                      <option value="<?= $equipe['id'] ?>"><?= $equipe['descricao'] ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="email_edit" class="form-label">Email:</label>
                  <input type="email" name="Email" id="email_edit" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="senha_edit" class="form-label">Senha:</label>
                  <input type="password" name="Senha" id="senha_edit" class="form-control" placeholder="Preencha para alterar">
                </div>
              </div>
              <div class="row">
                
                <div class="col-md-6 mb-3">
                  <label for="nivel_edit" class="form-label">Nível:</label>
                  <select name="idNivel" id="nivel_edit" class="form-select">
                    <?php foreach($niveis as $nivel): ?>
                      <option value="<?= $nivel['id'] ?>"><?= $nivel['descricao'] ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="edit_cargo_cad" class="form-label">Cargo:</label>
                  <select name="Cargo" id="edit_cargo_cad" class="form-select" required>
                    <option value="Admin">Admin</option>
                    <option value="Comercial">Comercial</option>
                    <option value="Conversor">Conversor</option>
                    <option value="User">User</option>
                    <option value="Viewer">Viewer</option>
                  </select>
                </div>
              </div>
          </div>
          <div class="modal-footer">
            <input type="submit" value="Salvar" class="btn btn-primary">
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
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="deletar_usuario.php" method="post">
          <div class="modal-body">
              <input type="hidden" name="id" id="excluir_id">
              <p>Tem certeza que deseja excluir o usuário <strong id="excluir_nome"></strong>?</p>
          </div>
          <div class="modal-footer mb-0">
            <input type="submit" value="Excluir" class="btn btn-danger">
          </div>
        </form>
      </div>
    </div>
  </div>

<script>
// Preenche e abre o modal de edição
    function editarUser(id, nome, email, idEquipe, idNivel, cargo) {
        document.getElementById('editar_id').value = id;
        document.getElementById('nome_edit').value = nome;
        document.getElementById('email_edit').value = email;
        document.getElementById('equipe_edit').value = idEquipe;
        document.getElementById('nivel_edit').value = idNivel;
        document.getElementById('edit_cargo_cad').value = cargo;

        $('#modalEditar').modal('show');
    }
    // Preenche e abre o modal de exclusão
    function modalExcluir(id, nome) {
        document.getElementById('excluir_id').value = id;
        document.getElementById('excluir_nome').textContent = nome;

        $('#modalExcluir').modal('show');
    }
    // Fecha o modal ao clicar fora do conteúdo
    window.onclick = function(event) {
        var modals = document.getElementsByClassName('modal');
        for (var i = 0; i < modals.length; i++) {
            if (event.target == modals[i]) {
                modals[i].style.display = "none";
            }
        }
    }
</script>
</body>
</html>
