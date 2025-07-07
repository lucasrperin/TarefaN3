<?php
require '../Config/Database.php';
require_once __DIR__ . '/../Includes/auth.php';

// Variáveis de sessão já disponíveis:
$usuario_id = $_SESSION['usuario_id'];
$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';
$usuario_nome = isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Chatbot IA</title>
  <link rel="icon" href="../Public/Image/LogoTituto.png" type="image/png">
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Google Fonts: Montserrat -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <!-- Seu CSS customizado -->
  <link rel="stylesheet" href="../Public/dashboard.css">
  <link rel="stylesheet" href="chatbot.css">
  <style>
    .chatbot-iframe {
      width: 100%;
      min-height: 700px;
      border: none;
      border-radius: 1.2rem;
      background: #fff;
      box-shadow: 0 4px 32px rgba(0,77,161,0.09), 0 1px 8px rgba(0,0,0,0.03);
      margin-bottom: 24px;
    }
    @media (max-width: 768px) {
      .chatbot-iframe { min-height: 500px; border-radius: .7rem;}
    }
    @media (max-width: 480px) {
      .chatbot-iframe { min-height: 350px; border-radius: .4rem;}
    }
  </style>
</head>
<body class="bg-light"> 
  <div class="d-flex-wrapper">
    <!-- Sidebar fixa -->
    <div class="sidebar">
      <a class="light-logo" href="dashboard.php">
        <img src="../Public/Image/zucchetti_blue.png" width="150" alt="Logo Zucchetti">
      </a>
      <nav class="nav flex-column">
        <a class="nav-link" href="menu.php"><i class="fa-solid fa-house me-2"></i>Home</a>
        <?php if ($cargo != 'Comercial'): ?>
          <a class="nav-link active" href="chatbot.php"><i class="bi bi-robot me-2"></i>Chatbot IA</a>
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
          <a class="nav-link" href="okr.php"><img src="../Public/Image/benchmarkbranco.png" width="27" height="27" class="me-1" alt="Benchmark">OKRs</a>
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
    
    <!-- Conteúdo Principal -->
    <div class="w-100">
      <div class="header d-flex justify-content-between align-items-center">
        <h3 class="mb-0"><i class="bi bi-robot me-2"></i>Chatbot IA</h3>
        <div class="user-info d-flex align-items-center gap-2">
          <span>Bem-vindo, <?php echo htmlspecialchars($usuario_nome, ENT_QUOTES, 'UTF-8'); ?>!</span>
          <a href="logout.php" class="btn btn-danger btn-sm">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
          </a>
        </div>
      </div>
      
      <div class="container-fluid py-4">
        <div class="row justify-content-center">
          <div class="col-12 col-lg-10 col-xl-8">
            <div class="card p-3 shadow-sm rounded-4">

              <!-- Bloco de Boas Práticas (colapsável) -->
              <div class="alert alert-info py-2 px-3 mb-3 rounded-4 shadow-sm" style="font-size: .98rem;">
                <button class="btn btn-link p-0 d-flex align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#dicasIA" aria-expanded="false" aria-controls="dicasIA" style="text-decoration:none;">
                  <i class="bi bi-lightbulb me-2 fs-5"></i>
                  <span><strong>Ver boas práticas para detalhar sua dúvida</strong></span>
                  <i class="bi bi-chevron-down ms-2"></i>
                </button>
                <div class="collapse mt-2" id="dicasIA">
                  <ul class="mb-1 mt-1 ps-3">
                    <li>Informe o sistema (<span class="text-nowrap">ClippPRO</span>, <span class="text-nowrap">ClippMEI</span>, <span class="text-nowrap">ClippSERVICE</span>, <span class="text-nowrap">ClippCHEFF</span>, <span class="text-nowrap">ClippFACIL</span>, <span class="text-nowrap">Clipp360</span> ou <span class="text-nowrap">ZWEB</span>).</li>
                    <li>Descreva o problema com o máximo de detalhes.</li>
                    <li>Cole a mensagem completa do erro, se houver.</li>
                    <li>Cite a tela ou rotina, e os passos até o erro.</li>
                  </ul>
                  <small class="text-secondary">
                    Quanto mais detalhes, mais precisa será a resposta da IA!
                  </small>
                </div>
              </div>

              <!-- Chatbot Iframe -->
              <iframe 
                class="chatbot-iframe"
                src="https://app.gptmaker.ai/widget/3E3645E74C47C231769812C18DE33049/iframe"
                allow="microphone;"
                title="Chatbot IA">
              </iframe>
            </div>
          </div>
        </div>
      </div>
    </div> <!-- /w-100 -->
  </div>
  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
