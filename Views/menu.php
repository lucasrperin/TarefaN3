<?php
include '../Config/Database.php';
session_start();

// Verifica se o usuário está logado; se não, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Dados do usuário
$usuario_id = $_SESSION['usuario_id'];
$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Página de Boas-Vindas</title>
  <!-- Estilos customizados -->
  <link rel="stylesheet" href="../Public/menu.css">
  <!-- Bootstrap 5.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>

<!-- Navbar com fundo preto, texto centralizado e botão vermelho -->
<nav class="navbar navbar-dark bg-dark">
  <div class="container position-relative">
    <!-- Placeholder à esquerda para equilíbrio -->
    <div class="d-none d-sm-block" style="width: 75px;"></div>

    <!-- Texto de boas-vindas centralizado -->
    <span class="navbar-text position-absolute top-50 start-50 translate-middle text-white">
      Bem-vindo(a), <?php echo $_SESSION['usuario_nome']; ?>!
    </span>

    <!-- Botão de logout à direita -->
    <div>
      <a href="logout.php" class="btn btn-danger">
        <i class="fa-solid" style="font-size: 0.8em;"></i> Sair
      </a>
    </div>
  </div>
</nav>

<div class="container">
  <div class="row">
    <!-- Card: Colaboradores -->
    <div class="col-12 mt-3">
      <div class="card card-modern">
        <div class="card-header">
          Colaboradores
        </div>
        <div class="card-body">
          <div class="row g-3">
            <?php if ($cargo === 'Admin' || $cargo === 'Conversor' || $cargo === 'Viewer'): ?>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
              <div class="tile" onclick="location.href='conversao.php';">
                <i class="fa-solid fa-right-left"></i>
                <h5>Conversões</h5>
              </div>
            </div>
            <?php endif; ?>

            <?php if ($cargo === 'Admin' || $cargo === 'Conversor' || $cargo === 'User' || $cargo === 'Comercial'): ?>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
              <div class="tile" onclick="location.href='indicacao.php';">
                <i class="fa-solid fa-hand-holding-dollar"></i>
                <h5>Indicações</h5>
              </div>
            </div>
            <?php endif; ?>

            <?php if ($cargo === 'Admin'): ?>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
              <div class="tile" onclick="location.href='../index.php';">
                <i class="fa-solid fa-layer-group"></i>
                <h5>Nível 3</h5>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Card: Gestão -->
    <div class="col-12">
      <div class="card card-modern">
        <div class="card-header">
          Gestão
        </div>
        <div class="card-body">
          <div class="row g-3">
            <?php if ($cargo === 'Admin'): ?>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
              <div class="tile" onclick="location.href='escutas.php';">
                <i class="fa-solid fa-headphones"></i>
                <h5>Escutas</h5>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
              <div class="tile" onclick="location.href='folga.php';">
                <i class="fa-solid fa-umbrella-beach"></i>
                <h5>Folgas</h5>
              </div>
            </div>
            <?php endif; ?>

            <?php if ($cargo === 'Admin' || $cargo === 'Viewer'): ?>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
              <div class="tile" onclick="location.href='incidente.php';">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <h5>Incidentes</h5>
              </div>
            </div>
            <?php endif; ?>

            <?php if ($cargo === 'Admin'): ?>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
              <div class="tile" onclick="location.href='dashboard.php';">
                <i class="fa-solid fa-calculator"></i>
                <h5>Totalizadores</h5>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
              <div class="tile" onclick="location.href='usuarios.php';">
                <i class="fa-solid fa-users-gear"></i>
                <h5>Usuários</h5>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3">
              <div class="tile" onclick="location.href='destaque.php';">
                <i class="fa-solid fa-ranking-star"></i>
                <h5>Destaques</h5>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
