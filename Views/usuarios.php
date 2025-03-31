<?php
session_start();
// index.php
include '../Config/Database.php';

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
          <li><a class="dropdown-item" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a></li>
          <li><a class="dropdown-item" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a></li>
        </ul>
      </div>
      <span class="text-white">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
      <a href="menu.php" class="btn btn-danger">
        <i class="fa-solid fa-arrow-left me-2" style="font-size: 0.8em;"></i>Voltar
      </a>
    </div>
  </nav>
<div class="container my-4">
    <h1 class="mb-4">Controle de Usuários</h1>
    <!-- Botão para abrir o modal de cadastro -->
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastro">
      <i class="fa-solid fa-plus me-1"></i> Cadastrar
    </button>
    <table class="table table-striped table-bordered tabelaEstilizada">
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
            <div class="mb-3">
              <label for="nome_cad" class="form-label">Nome:</label>
              <input type="text" name="Nome" id="nome_cad" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="email_cad" class="form-label">Email:</label>
              <input type="email" name="Email" id="email_cad" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="senha_cad" class="form-label">Senha:</label>
              <input type="password" name="Senha" id="senha_cad" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="equipe_cad" class="form-label">Equipe:</label>
              <select name="idEquipe" id="equipe_cad" class="form-select">
                <?php foreach($equipes as $equipe): ?>
                  <option value="<?= $equipe['id'] ?>"><?= $equipe['descricao'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label for="nivel_cad" class="form-label">Nível:</label>
              <select name="idNivel" id="nivel_cad" class="form-select">
                <?php foreach($niveis as $nivel): ?>
                  <option value="<?= $nivel['id'] ?>"><?= $nivel['descricao'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
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
         <div class="modal-footer">
           <input type="submit" value="Cadastrar" class="btn btn-primary">
           <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
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
            <div class="mb-3">
              <label for="nome_edit" class="form-label">Nome:</label>
              <input type="text" name="Nome" id="nome_edit" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="email_edit" class="form-label">Email:</label>
              <input type="email" name="Email" id="email_edit" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="senha_edit" class="form-label">Senha:</label>
              <input type="password" name="Senha" id="senha_edit" class="form-control" placeholder="Preencha para alterar">
            </div>
            <div class="mb-3">
              <label for="equipe_edit" class="form-label">Equipe:</label>
              <select name="idEquipe" id="equipe_edit" class="form-select">
                <?php foreach($equipes as $equipe): ?>
                  <option value="<?= $equipe['id'] ?>"><?= $equipe['descricao'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label for="nivel_edit" class="form-label">Nível:</label>
              <select name="idNivel" id="nivel_edit" class="form-select">
                <?php foreach($niveis as $nivel): ?>
                  <option value="<?= $nivel['id'] ?>"><?= $nivel['descricao'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
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
         <div class="modal-footer">
           <input type="submit" value="Atualizar" class="btn btn-primary">
           <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
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
         <div class="modal-footer">
           <input type="submit" value="Excluir" class="btn btn-danger">
           <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
         </div>
       </form>
    </div>
  </div>
</div>

<!-- Scripts necessários -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
