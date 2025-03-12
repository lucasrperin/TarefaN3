<?php
include '../Config/Database.php';

session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Recupera o id do usuário e seu cargo armazenados na sessão
$usuario_id = $_SESSION['usuario_id'];
$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';

// Verifica se o usuário tem cargo Admin; se não, redireciona para o login
if ($cargo !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

// Processa o envio do formulário para registrar uma nova escuta
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Dados enviados pelo formulário
    $user_id     = $_POST['user_id'];
    $data_escuta = $_POST['data_escuta'];
    $transcricao = trim($_POST['transcricao']);
    $feedback    = trim($_POST['feedback']);
    $admin_id    = $usuario_id; // ID do Admin logado

    // Verifica se o Admin já registrou 5 escutas neste mês
    $currentMonth = date('Y-m');
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM TB_ESCUTAS WHERE admin_id = ? AND DATE_FORMAT(data_escuta, '%Y-%m') = ?");
    $stmt->bind_param("is", $admin_id, $currentMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'];
    $stmt->close();

    if ($count >= 5) {
        $error = "Você já registrou 5 escutas neste mês.";
    } else {
        // Insere o novo registro de escuta na tabela
        $stmt = $conn->prepare("INSERT INTO TB_ESCUTAS (user_id, admin_id, data_escuta, transcricao, feedback) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $user_id, $admin_id, $data_escuta, $transcricao, $feedback);
        if ($stmt->execute()) {
            $success = "Escuta registrada com sucesso.";
        } else {
            $error = "Erro ao registrar a escuta. Tente novamente.";
        }
        $stmt->close();
    }
}

// Recupera os usuários com cargo "User" para preencher o select
$users = [];
$query = "SELECT id, nome FROM TB_USUARIO WHERE cargo = 'User'";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $result->free();
}

// Recupera o histórico de escutas, juntando os dados dos usuários e do Admin que realizou a escuta
$escutas = [];
$query = "SELECT e.*, u.nome AS usuario_nome, a.nome AS admin_nome 
          FROM TB_ESCUTAS e
          JOIN TB_USUARIO u ON e.user_id = u.id 
          JOIN TB_USUARIO a ON e.admin_id = a.id
          ORDER BY e.data_escuta DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $escutas[] = $row;
    }
    $result->free();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel de Escutas</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS customizados do projeto -->
    <link rel="stylesheet" href="../css/index.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/user.css">
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
          <li><a class="dropdown-item" href="../index.php">Painel</a></li>
          <li><a class="dropdown-item" href="conversao.php">Conversão</a></li>
        </ul>
      </div>
      <span class="text-white">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
      <a href="../index.php" class="btn btn-danger">
        <i class="fa-solid fa-arrow-left me-2" style="font-size: 0.8em;"></i>Voltar
      </a>
    </div>
  </nav>
    <div class="container mt-5">
        <!-- Exibição de mensagens de sucesso ou erro -->
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php elseif(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Card para registrar nova escuta -->
        <div class="card mb-4">
            <div class="card-header">
                Registrar Nova Escuta
            </div>
            <div class="card-body">
                <form method="POST" action="escutas.php">
                    <div class="mb-3">
                        <label for="user_id" class="form-label">Selecione o Usuário</label>
                        <select name="user_id" id="user_id" class="form-select" required>
                            <option value="">Escolha o usuário</option>
                            <?php foreach($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo $user['nome']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="data_escuta" class="form-label">Data da Escuta</label>
                        <input type="date" name="data_escuta" id="data_escuta" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="transcricao" class="form-label">Transcrição da Ligação</label>
                        <textarea name="transcricao" id="transcricao" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="feedback" class="form-label">Feedback / Ajustes</label>
                        <textarea name="feedback" id="feedback" class="form-control" rows="2" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Registrar Escuta</button>
                </form>
            </div>
        </div>

        <!-- Card com o histórico de escutas -->
        <div class="card">
            <div class="card-header">
                Histórico de Escutas
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Data da Escuta</th>
                                <th>Usuário</th>
                                <th>Admin (Quem ouviu)</th>
                                <th>Transcrição</th>
                                <th>Feedback</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($escutas) > 0): ?>
                                <?php foreach($escutas as $escuta): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($escuta['data_escuta'])); ?></td>
                                        <td><?php echo $escuta['usuario_nome']; ?></td>
                                        <td><?php echo $escuta['admin_nome']; ?></td>
                                        <td><?php echo $escuta['transcricao']; ?></td>
                                        <td><?php echo $escuta['feedback']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">Nenhuma escuta registrada.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap Bundle com Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
