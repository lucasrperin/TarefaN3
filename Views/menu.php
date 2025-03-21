<?php
session_start();
// Recupera o cargo do usuário a partir da sessão
$cargo = $_SESSION['cargo'] ?? 'User';
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
  <style>
    /* Estilos para os tiles */
    .tile {
      background-color: #1976d2; /* Azul ou outra cor de sua preferência */
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
      transform: scale(1.05); /* Efeito zoom ao passar o mouse */
    }
    .tile i {
      font-size: 1.8rem; /* Tamanho do ícone */
      margin-bottom: 6px;
    }
    .tile h5 {
      margin: 0;
      font-size: 1rem;
    }
    body {
      background-color: #f5f5f5; /* Fundo claro */
    }
  </style>
</head>
<body>
  <div class="container my-5">
    <h2 class="mb-4 text-center">Seja Bem-Vindo ao Sistema!</h2>
    
    <!-- PRIMEIRA LINHA (3 ícones, mas se não for Admin, aparece só o primeiro) -->
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
      <!-- SEGUNDA LINHA (2 ícones) -->
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
