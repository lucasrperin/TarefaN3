<?php
include '../Config/Database.php';
require_once __DIR__ . '/../Includes/auth.php';
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
  <title>Painel N3 - Dashboard Moderno</title>
  <!-- Estilos customizados -->
  <link rel="stylesheet" href="../Public/menu.css">
  <!-- Bootstrap 5.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link
  rel="stylesheet"
  href="https://cdnjs.cloudflare.com/ajax/libs/tabler-icons/3.28.1/tabler-icons.min.css"
/>  <!-- :contentReference[oaicite:0]{index=0} -->




  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <link rel="icon" href="../Public/Image/LogoTituto.png" type="image/png">
</head>
<body>
  <!-- Hero Header -->
  <header class="hero-header">
    <nav class="navbar navbar-expand-lg navbar-dark">
      <div class="container">
        <!-- Logo inserido no lugar do texto "Painel N3" -->
        <a class="light-logo" href="#">
          <img src="../Public/Image/zucchetti_blue.png" width="150" alt="Logo Zucchetti">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu"
                aria-controls="navbarMenu" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarMenu">
          <ul class="navbar-nav">
            <li class="nav-item">
              <span class="nav-link">
                Bem-vindo(a), <?php echo htmlspecialchars($usuario_nome, ENT_QUOTES, 'UTF-8'); ?>!
              </span>
            </li>
            <li class="nav-item">
              <a href="logout.php" class="nav-link btn btn-danger text-white ms-2">
                <i class="fa-solid fa-right-from-bracket me-1"></i>Sair
              </a>
            </li>
          </ul>
        </div>
      </div>
    </nav>
    <div class="hero-content container text-center mt-0">
      <h1 class="hero-title">Painel de Controle</h1>
      <p class="hero-subtitle">Gerencie suas operações de forma simples e moderna.</p>
    </div>
  </header>

  <!-- Área principal com os cards de menu -->
  <main class="container menu-container mt-5">
    <!-- 
         row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 
         g-4 => espaçamento entre colunas 
    -->
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 g-4">
      
      <!-- C: Conversões -->
      <?php if($cargo==='Admin' || $cargo==='Conversor' || $cargo==='Viewer'): ?>
      <div class="col">
        <div class="menu-card" onclick="location.href='conversao.php';">
          <div class="menu-icon">
            <i class="fa-solid fa-right-left"></i>
          </div>
          <h5 class="menu-title">Conversões</h5>
        </div>
      </div>
      <?php endif; ?>

      <!-- D: Destaques -->
      <?php if($cargo==='Admin'): ?>
      <div class="col">
        <div class="menu-card" onclick="location.href='destaque.php';">
          <div class="menu-icon">
            <i class="fa-solid fa-ranking-star"></i>
          </div>
          <h5 class="menu-title">Destaques</h5>
        </div>
      </div>
      <?php endif; ?>

      <!-- E: Escutas -->
      <?php if($cargo==='Admin'): ?>
      <div class="col">
        <div class="menu-card" onclick="location.href='escutas.php';">
          <div class="menu-icon">
            <i class="fa-solid fa-headphones"></i>
          </div>
          <h5 class="menu-title">Escutas</h5>
        </div>
      </div>
      <?php endif; ?>

      <!-- F: Folgas -->
      <?php if($cargo==='Admin'): ?>
      <div class="col">
        <div class="menu-card" onclick="location.href='folga.php';">
          <div class="menu-icon">
            <i class="fa-solid fa-umbrella-beach"></i>
          </div>
          <h5 class="menu-title">Folgas</h5>
        </div>
      </div>
      <?php endif; ?>

      <!-- I: Incidentes -->
      <?php if($cargo==='Admin' || $cargo==='Viewer' || $cargo === 'Produto'): ?>
      <div class="col">
        <div class="menu-card" onclick="location.href='incidente.php';">
          <div class="menu-icon">
            <i class="fa-solid fa-exclamation-triangle"></i>
          </div>
          <h5 class="menu-title">Incidentes</h5>
        </div>
      </div>
      <?php endif; ?>

      <!-- I: Indicações -->
      <?php if($cargo==='Admin' || $cargo==='Conversor' || $cargo==='User' || $cargo==='Comercial'): ?>
      <div class="col">
        <div class="menu-card" onclick="location.href='indicacao.php';">
          <div class="menu-icon">
            <i class="fa-solid fa-hand-holding-dollar"></i>
          </div>
          <h5 class="menu-title">Indicações</h5>
        </div>
      </div>
      <?php endif; ?>

      <!-- M: Meu Painel -->
      <?php if($cargo==='User' || $cargo==='Conversor' || $cargo==='Treinamento' || $cargo==='Viewer' || $cargo==='Admin'): ?>
      <div class="col">
        <div class="menu-card" onclick="location.href='user.php';">
          <div class="menu-icon">
            <i class="fa-solid fa-users-rectangle"></i>
          </div>
          <h5 class="menu-title">Meu Painel</h5>
        </div>
      </div>
      <?php endif; ?>

      <!-- N: Nível 3 -->
      <?php if($cargo==='Admin' || $cargo==='Produto'): ?>
      <div class="col">
        <div class="menu-card" onclick="location.href='../index.php';">
          <div class="menu-icon">
            <i class="fa-solid fa-layer-group"></i>
          </div>
          <h5 class="menu-title">Nível 3</h5>
        </div>
      </div>
      <?php endif; ?>

      <!-- OKR -->
      <?php if($cargo != 'Comercial'): ?>
        <div class="col">
          <div class="menu-card" onclick="location.href='okr.php';">
            <div class="menu-icon">
              <img src="../Public/Image/benchmarksolid.png" alt="Benchmark" width="50" height="50">
            </div>
            <h5 class="menu-title">OKR's</h5>
          </div>
        </div>
      <?php endif; ?>

      <!-- T: Totalizadores -->
      <?php if($cargo==='Admin'): ?>
      <div class="col">
        <div class="menu-card" onclick="location.href='dashboard.php';">
          <div class="menu-icon">
            <i class="fa-solid fa-calculator"></i>
          </div>
          <h5 class="menu-title">Totalizadores</h5>
        </div>
      </div>
      <?php endif; ?>

      <!-- T: Treinamentos -->
      <?php if($cargo==='Admin' || $cargo==='Treinamento' || $cargo==='Comercial'): ?>
      <div class="col">
        <div class="menu-card" onclick="location.href='treinamento.php';">
          <div class="menu-icon">
            <i class="fa-solid fa-calendar-check"></i>
          </div>
          <h5 class="menu-title">Treinamentos</h5>
        </div>
      </div>
      <?php endif; ?>

      <!-- U: Usuários -->
      <?php if($cargo==='Admin'): ?>
      <div class="col">
        <div class="menu-card" onclick="location.href='usuarios.php';">
          <div class="menu-icon">
            <i class="fa-solid fa-users-gear"></i>
          </div>
          <h5 class="menu-title">Usuários</h5>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </main>

  <!-- Bootstrap JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
