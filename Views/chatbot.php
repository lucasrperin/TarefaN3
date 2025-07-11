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
    <?php include __DIR__ . '/../components/sidebar.php'; ?>
    
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
                src="https://app.gptmaker.ai/widget/3E3E5932D2ADE26BC22AB22FDC791FD9/iframe"
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
