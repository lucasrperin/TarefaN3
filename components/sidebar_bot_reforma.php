<?php
// sidebar.php (coloque em uma pasta "components" ou similar)
require_once __DIR__ . '../../Includes/auth.php';

$usuario_id = $_SESSION['usuario_id'] ?? null;
$cargo = $_SESSION['cargo'] ?? '';
$idsLiberados = [6, 17, 24, 48];
$userAcessoBot = ($cargo === 'Admin') || in_array($usuario_id, $idsLiberados);
?>
<div class="sidebar sidebar-scroll">
  <a class="light-logo" href="#">
    <img src="../../Public/Image/zucchetti_blue.png" width="150" alt="Logo Zucchetti">
  </a>
  <nav class="nav flex-column">
    <a class="nav-link" href="../../Views/menu.php">
      <i class="fa-solid fa-house me-2"></i>Home
    </a>
      <!-- item “pai” IA -->
      <a
        class="nav-link <?php if(str_contains(basename($_SERVER['PHP_SELF']), 'index_') || str_contains(basename($_SERVER['PHP_SELF']), 'config_')) echo ' active'; ?> d-flex justify-content-between align-items-center"
        data-bs-toggle="collapse"
        href="#submenu-ia"
        role="button"
        aria-expanded="false"
        aria-controls="submenu-ia"
      >
        <span><i class="bi bi-robot me-2"></i>IA</span>
        <i class="bi bi-caret-down-fill"></i>
      </a>
      <!-- submenu -->
      <div class="collapse ps-3" id="submenu-ia">
        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) === 'index_suporte.php') echo ' active'; ?>" href="../../ChatBot/webchat/index_suporte.php">
          <img src="../../Public/Image/clippbranco.png" width="30" height="30" class="ms-0" alt="Benchmark">Linha Clipp
        </a>
        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) === 'index_small.php') echo ' active'; ?>" href="../../ChatBot/webchat_small/index_small.php">
          <img src="../../Public/Image/smallbranco.png" width="30" height="30" class="ms-0" alt="Benchmark">Linha Small
        </a>
        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) === 'index_reforma.php') echo ' active'; ?>" href="../../chatbotreforma/webchat/index_reforma.php">
          <img src="../../Public/Image/reformatributaria.png" width="30" height="30" class="ms-0" alt="Benchmark">Reforma Tributária
        </a>
        <?php if($userAcessoBot): ?>
          <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) === 'config_bot.php') echo ' active'; ?>" href="../../ChatBot/webchat/config_bot.php">
            <i class="fa fa-gear me-2"></i>Configurações
          </a>
          <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) === 'config_bot_reforma.php') echo ' active'; ?>" href="../../chatbotreforma/webchat/config_bot_reforma.php">
            <i class="fa fa-gear me-2"></i>Configurações Reforma
          </a>
        <?php endif; ?>
      </div>
      
    <?php if ($cargo === 'Admin' || $cargo === 'Conversor'): ?>
      <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) === 'conversao.php') echo ' active'; ?>" href="../../Views/conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin'): ?>
        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) === 'destaque.php') echo ' active'; ?>" href="../../Views/destaque.php"><i class="fa-solid fa-ranking-star me-2"></i>Destaques</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin'): ?>
        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) === 'escutas.php') echo ' active'; ?>" href="../../Views/escutas.php"><i class="fa-solid fa-headphones me-2"></i>Escutas</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin'): ?>
        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) === 'folga.php') echo ' active'; ?>" href="../../Views/folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin'): ?>
        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) === 'incidente.php') echo ' active'; ?>" href="../../Views/incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'User' || $cargo === 'Conversor'): ?>
      <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) === 'indicacao.php') echo ' active'; ?>" href="../../Views/indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin' || $cargo === 'Viewer' || $cargo === 'User' || $cargo === 'Conversor'): ?>
      <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) === 'user.php') echo ' active'; ?>" href="../../Views/user.php"><i class="fa-solid fa-users-rectangle me-2"></i>Meu Painel</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin'): ?>
      <a class="nav-link" href="../../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a>
    <?php endif; ?>
    <?php if ($cargo != 'Comercial'): ?>
      <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) === 'okr.php') echo ' active'; ?>" href="../../Views/okr.php">
        <img src="../../Public/Image/benchmarkbranco.png" width="27" height="27" class="me-1" alt="Benchmark">OKRs
      </a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin'): ?>
      <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) === 'dashboard.php') echo ' active'; ?>" href="../../Views/dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'Treinamento'): ?>
      <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) === 'treinamento.php') echo ' active'; ?>" href="../../Views/treinamento.php"><i class="fa-solid fa-calendar-check me-2"></i>Treinamentos</a>
    <?php endif; ?>
    <?php if ($cargo === 'Admin'): ?>
      <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) === 'usuarios.php') echo ' active'; ?>" href="../../Views/usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a>
    <?php endif; ?>
  </nav>
</div>
