<?php
include '../Config/Database.php';
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verifica se o usuário logado é Admin
$usuario_id = $_SESSION['usuario_id'];
$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';
if ($cargo !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

// Recupera o nome do usuário
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Página de Boas-Vindas</title>
  <!-- CSS do Bootstrap -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Ícones do Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      background-color: #f5f5f5;
      font-family: 'Montserrat', sans-serif;
    }
    .welcome-msg {
      color: #333;
      font-size: 2rem;
      font-weight: 600;
    }
    .tile {
      background-color: #1976d2;
      color: #fff;
      border-radius: 8px;
      height: 120px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      text-align: center;
      transition: transform 0.2s ease;
      cursor: pointer;
    }
    .tile:hover {
      transform: scale(1.05);
    }
    .tile i {
      font-size: 1.8rem;
      margin-bottom: 6px;
    }
    .tile h5 {
      margin: 0;
      font-size: 1rem;
    }
  </style>
</head>
<body>
  <div class="container my-5">

  <!-- Mensagem de Boas-Vindas -->
  <h2 class="mb-4 text-center welcome-msg">
      Bem-vindo, <?php echo htmlspecialchars($usuario_nome, ENT_QUOTES, 'UTF-8'); ?>!
    </h2>
    <!-- Botão Sair alinhado à direita -->
    <div class="text-end mb-3">
      <a href="logout.php" class="btn btn-danger">
        <i class="fa-solid fa-right-from-bracket me-2" style="font-size: 0.8em;"></i>Sair
      </a>
    </div>
    
    
    
    <!-- PRIMEIRA LINHA: 3 ícones (se Admin, senão aparece só o primeiro) -->
    <div class="row justify-content-center g-3">
      <!-- Conversão (sempre visível) -->
      <div class="col-md-3">
        <div class="tile" onclick="location.href='conversao.php';">
          <i class="fa-solid fa-right-left"></i>
          <h5>Conversão</h5>
        </div>
      </div>
      <?php if ($cargo === 'Admin'): ?>
        <!-- Escutas -->
        <div class="col-md-3">
          <div class="tile" onclick="location.href='escutas.php';">
            <i class="fa-solid fa-headphones"></i>
            <h5>Escutas</h5>
          </div>
        </div>
        <!-- Incidentes -->
        <div class="col-md-3">
          <div class="tile" onclick="location.href='incidente.php';">
            <i class="fa-solid fa-exclamation-triangle"></i>
            <h5>Incidentes</h5>
          </div>
        </div>
      <?php endif; ?>
    </div>
    
    <?php if ($cargo === 'Admin'): ?>
      <!-- SEGUNDA LINHA: 2 ícones -->
      <div class="row justify-content-center g-3 mt-3">
        <!-- Totalizadores -->
        <div class="col-md-3">
          <div class="tile" onclick="location.href='dashboard.php';">
            <i class="fa-solid fa-calculator"></i>
            <h5>Totalizadores</h5>
          </div>
        </div>
        <!-- Nível 3 -->
        <div class="col-md-3">
          <div class="tile" onclick="location.href='../index.php';">
            <i class="fa-solid fa-layer-group"></i>
            <h5>Nível 3</h5>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- JS do Bootstrap -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
