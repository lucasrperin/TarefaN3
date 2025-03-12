<?php
include '../Config/Database.php';
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verifica se o usuário é Admin
$usuario_id = $_SESSION['usuario_id'];
$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';
if ($cargo !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

// Recebe o id do usuário (analista) via GET
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if ($user_id <= 0) {
    header("Location: escutas.php");
    exit;
}

// Recupera o nome do analista
$stmt = $conn->prepare("SELECT nome FROM TB_USUARIO WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$analista = $result->fetch_assoc();
$stmt->close();
$usuario_nome = $analista ? $analista['nome'] : "Analista Desconhecido";

// Recupera o histórico de escutas para esse usuário (analista)
$query = "SELECT e.*, u.nome AS usuario_nome, a.nome AS admin_nome 
          FROM TB_ESCUTAS e
          JOIN TB_USUARIO u ON e.user_id = u.id 
          JOIN TB_USUARIO a ON e.admin_id = a.id
          WHERE e.user_id = $user_id
          ORDER BY e.data_escuta DESC";
$result = $conn->query($query);
$escutas = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $escutas[] = $row;
    }
    $result->free();
}

// Recupera os usuários (para o select do modal de edição)
$users = [];
$queryUsers = "SELECT id, nome FROM TB_USUARIO WHERE cargo = 'User'";
$resultUsers = $conn->query($queryUsers);
if ($resultUsers) {
    while ($row = $resultUsers->fetch_assoc()) {
        $users[] = $row;
    }
    $resultUsers->free();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Escutas de <?php echo $usuario_nome; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Seus CSS customizados -->
  <link rel="stylesheet" href="../css/index.css">
  <link rel="stylesheet" href="../css/dashboard.css">
  <link rel="stylesheet" href="../css/user.css">
  <!-- Ícones -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
  <div class="container d-flex justify-content-between align-items-center">
    <div class="dropdown">
      <button class="navbar-toggler" type="button" data-bs-toggle="dropdown">
        <span class="navbar-toggler-icon"></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-dark">
      <li><a class="dropdown-item" href="conversao.php">Conversão</a></li>
        <li><a class="dropdown-item" href="../index.php">Painel</a></li>
        <li><a class="dropdown-item" href="dashboard.php">Totalizadores</a></li>
      </ul>
    </div>
    <span class="text-white">Escutas de <?php echo $usuario_nome; ?></span>
    <a href="escutas.php" class="btn btn-danger">
      <i class="fa-solid fa-arrow-left me-2"></i>Voltar
    </a>
  </div>
</nav>

<div class="container mt-5">
  <h3 class="mb-4">Histórico de Escutas - <?php echo $usuario_nome; ?></h3>
  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered">
          <thead class="table-light">
            <tr>
              <th>Data da Escuta</th>
              <th>Usuário</th>
              <th>Transcrição</th>
              <th>Feedback</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($escutas) > 0): ?>
              <?php foreach ($escutas as $escuta): ?>
                <tr>
                  <td><?php echo date('d/m/Y', strtotime($escuta['data_escuta'])); ?></td>
                  <td><?php echo $escuta['usuario_nome']; ?></td>
                  <td><?php echo $escuta['transcricao']; ?></td>
                  <td><?php echo $escuta['feedback']; ?></td>
                  <td>
                    <!-- Botão Editar -->
                    <button class="btn btn-outline-primary btn-sm" 
                            data-bs-toggle="modal" 
                            data-bs-target="#modalEditar"
                            onclick="preencherModalEditar('<?php echo $escuta['id']; ?>',
                                                          '<?php echo $escuta['user_id']; ?>',
                                                          '<?php echo date('Y-m-d', strtotime($escuta['data_escuta'])); ?>',
                                                          '<?php echo addslashes($escuta['transcricao']); ?>',
                                                          '<?php echo addslashes($escuta['feedback']); ?>')">
                      <i class="fa-solid fa-pen"></i>
                    </button>
                    <!-- Botão Excluir -->
                    <button class="btn btn-outline-danger btn-sm" 
                            data-bs-toggle="modal" 
                            data-bs-target="#modalExcluir"
                            onclick="preencherModalExcluir('<?php echo $escuta['id']; ?>')">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5">Nenhuma escuta registrada para este analista.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal Editar Escuta -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content p-4">
      <h5 class="modal-title mb-3" id="modalEditarLabel">Editar Escuta</h5>
      <form method="POST" action="editar_escuta.php">
        <input type="hidden" name="id" id="edit_id">
        <div class="mb-3">
          <label for="edit_user_id" class="form-label">Selecione o Usuário</label>
          <select name="user_id" id="edit_user_id" class="form-select" required>
            <option value="">Escolha o usuário</option>
            <?php foreach($users as $user): ?>
              <option value="<?php echo $user['id']; ?>"><?php echo $user['nome']; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label for="edit_data_escuta" class="form-label">Data da Escuta</label>
          <input type="date" name="data_escuta" id="edit_data_escuta" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="edit_transcricao" class="form-label">Transcrição da Ligação</label>
          <textarea name="transcricao" id="edit_transcricao" class="form-control" rows="4" required></textarea>
        </div>
        <div class="mb-3">
          <label for="edit_feedback" class="form-label">Feedback / Ajustes</label>
          <textarea name="feedback" id="edit_feedback" class="form-control" rows="2" required></textarea>
        </div>
        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-primary">Salvar Alterações</button>
          <button type="button" class="btn btn-secondary ms-2" data-bs-dismiss="modal">Fechar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Excluir Escuta -->
<div class="modal fade" id="modalExcluir" tabindex="-1" aria-labelledby="modalExcluirLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content p-4">
      <h5 class="modal-title mb-3" id="modalExcluirLabel">Confirmar Exclusão</h5>
      <form method="POST" action="deletar_escuta.php">
        <input type="hidden" name="id" id="delete_id">
        <p>Tem certeza que deseja excluir esta escuta?</p>
        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-danger">Sim, excluir</button>
          <button type="button" class="btn btn-secondary ms-2" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function preencherModalEditar(id, user_id, data_escuta, transcricao, feedback) {
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_user_id').value = user_id;
  document.getElementById('edit_data_escuta').value = data_escuta;
  document.getElementById('edit_transcricao').value = transcricao;
  document.getElementById('edit_feedback').value = feedback;
}

function preencherModalExcluir(id) {
  document.getElementById('delete_id').value = id;
}
</script>
</body>
</html>
