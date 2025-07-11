<?php
// sidebar.php (coloque em uma pasta "components" ou similar)
if (session_status() === PHP_SESSION_NONE) session_start();

$usuario_id = $_SESSION['usuario_id'] ?? null;
$cargo = $_SESSION['cargo'] ?? '';
?>
<div class="sidebar">
  <a class="light-logo" href="dashboard.php">
    <img src="../Public/Image/zucchetti_blue.png" width="150" alt="Logo Zucchetti">
  </a>
  <nav class="nav flex-column">
    <a class="nav-link" href="menu.php"><i class="fa-solid fa-house me-2"></i>Home</a>
    <?php
    // IDs liberados para Chatbot
    $idsLiberados = [17, 24, 48];
    $userAcessoBot = ($cargo === 'Admin') || in_array($usuario_id, $idsLiberados);
    if ($userAcessoBot): ?>
      <a class="nav-link<?php if(basename($_SERVER['PHP_SELF']) === 'chatbot.php') echo ' active'; ?>" href="chatbot.php">
        <i class="bi bi-robot me-2"></i>Chatbot IA
      </a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin' || $cargo === 'Conversor'): ?>
      <a class="nav-link" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin'): ?>
        <a class="nav-link" href="destaque.php"><i class="fa-solid fa-ranking-star me-2"></i>Destaques</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin'): ?>
        <a class="nav-link" href="escutas.php"><i class="fa-solid fa-headphones me-2"></i>Escutas</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin'): ?>
        <a class="nav-link" href="folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin'): ?>
        <a class="nav-link" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'User' || $cargo === 'Conversor'): ?>
      <a class="nav-link" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin' || $cargo === 'Viewer' || $cargo === 'User' || $cargo === 'Conversor'): ?>
      <a class="nav-link" href="user.php"><i class="fa-solid fa-users-rectangle me-2"></i>Meu Painel</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin'): ?>
      <a class="nav-link" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a>
    <?php endif; ?>
    <?php if ($cargo != 'Comercial'): ?>
      <a class="nav-link" href="okr.php">
        <img src="../Public/Image/benchmarkbranco.png" width="27" height="27" class="me-1" alt="Benchmark">OKRs
      </a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin'): ?>
      <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'Treinamento'): ?>
      <a class="nav-link" href="treinamento.php"><i class="fa-solid fa-calendar-check me-2"></i>Treinamentos</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin'): ?>
      <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a>
    <?php endif; ?>
  </nav>
</div>
