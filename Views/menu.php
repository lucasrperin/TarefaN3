<?php
include '../Config/Database.php';
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verifica se o usuário logado é Admin
$usuario_id = $_SESSION['usuario_id'];
$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';

// Recupera o nome do usuário
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Página de Boas-Vindas</title>
  <!-- CSS externo minimalista -->
  <link rel="stylesheet" href="../Public/menu.css">
  <!-- CSS do Bootstrap -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Ícones do Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">

</head>
<body>
  <div class="container my-3">
    <div class="text-end mb-3">
        <a href="logout.php" class="btn btn-danger">
          <i class="fa-solid fa-right-from-bracket me-2" style="font-size: 0.8em;"></i>Sair
        </a>
    </div>
    <!-- Mensagem de Boas-Vindas -->
    <h2 class="text-center welcome-msg">
      Bem-vindo, <?php echo htmlspecialchars($usuario_nome, ENT_QUOTES, 'UTF-8'); ?>!
    </h2>
    <!-- Botão Sair alinhado à direita -->

    <div class="row justify-content-center g-3 mt-3">
      <?php if ($cargo === 'Admin' || $cargo === 'Conversor'): ?>
        <!-- Conversão (sempre visível) -->
        <div class="col-12 col-md-3 max-three">
          <div class="tile" onclick="location.href='conversao.php';">
            <i class="fa-solid fa-right-left"></i>
            <h5>Conversões</h5>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($cargo === 'Admin'): ?>
        <!-- Escutas -->
        <div class="col-12 col-md-3 max-three">
          <div class="tile" onclick="location.href='escutas.php';">
            <i class="fa-solid fa-headphones"></i>
            <h5>Escutas</h5>
          </div>
        </div>
        <!-- Incidentes -->
        <div class="col-12 col-md-3 max-three">
          <div class="tile" onclick="location.href='incidente.php';">
            <i class="fa-solid fa-exclamation-triangle"></i>
            <h5>Incidentes</h5>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($cargo === 'Admin' || $cargo === 'User' || $cargo === 'Conversor' || $cargo === 'Comercial'): ?>
        <!-- Indicações -->
        <div class="col-12 col-md-3 max-three">
          <div class="tile" onclick="location.href='indicacao.php';">
            <i class="fa-solid fa-hand-holding-dollar"></i>
            <h5>Indicações</h5>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($cargo === 'User' || $cargo === 'Conversor'): ?>
        <!-- Meu Painel -->
        <div class="col-12 col-md-3 max-three">
          <div class="tile" onclick="location.href='user.php';">
            <i class="fa-solid fa-chalkboard-user"></i>
            <h5>Meu Painel</h5>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($cargo === 'Admin'): ?>
        <!-- Nível 3 -->
        <div class="col-12 col-md-3 max-three">
          <div class="tile" onclick="location.href='../index.php';">
            <i class="fa-solid fa-layer-group"></i>
            <h5>Nível 3</h5>
          </div>
        </div>
        <!-- Totalizadores -->
        <div class="col-12 col-md-3 max-three">
          <div class="tile" onclick="location.href='dashboard.php';">
            <i class="fa-solid fa-calculator"></i>
            <h5>Totalizadores</h5>
          </div>
        </div>

        <!-- Nível 3 -->
        <div class="col-12 col-md-3 max-three">
          <div class="tile" onclick="location.href='folga.php';">
            <i class="fa-solid fa-umbrella-beach"></i>
            <h5>Folgas</h5>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- JS do Bootstrap -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
