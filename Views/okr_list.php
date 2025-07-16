<?php
require_once __DIR__ . '/../Public/Php/okr_php.php';
require_once __DIR__ . '/../Includes/auth.php';

$sql = "
  SELECT 
  o.id, 
  o.descricao, 
  o.idEquipe,
  e.descricao AS equipe,
  GROUP_CONCAT(onl.idNivel)    AS niveis_ids,
  GROUP_CONCAT(n.descricao SEPARATOR ', ') AS niveis
FROM TB_OKR o
JOIN TB_EQUIPE e    ON e.id = o.idEquipe
LEFT JOIN TB_OKR_NIVEL onl ON onl.idOkr = o.id
LEFT JOIN TB_NIVEL n     ON n.id     = onl.idNivel
GROUP BY o.id
ORDER BY o.descricao";
$rs = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Painel N3 - OKRs</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icones -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">

  <!-- Custom Styles -->
  <link rel="stylesheet" href="../Public/usuarios.css">
  <link rel="stylesheet" href="../Public/okr.css">
  <link rel="icon" href="../Public/Image/LogoTituto.png" type="image/png">
</head>
<body class="bg-light">
<div class="d-flex-wrapper">
  <?php include __DIR__ . '/../components/sidebar.php'; ?>
        
<main class="w-100">

    <!-- Minimalist Modern Toast Layout -->
    <div id="toast-container" class="toast-container">
      <div id="toastSucesso" class="toast toast-success">
        <i class="fa-solid fa-check-circle"></i>
        <span id="toastMensagem"></span>
      </div>
      <div id="toastErro" class="toast toast-error">
        <i class="fa-solid fa-exclamation-triangle"></i>
        <span id="toastMensagemErro"></span>
      </div>
    </div>
    <!-- HEADER -->
    <header class="header d-flex justify-content-between align-items-center px-3">
        <h3>Listagem de OKRs – <?= $anoAtual ?></h3>

        <div class="user-info">
        <span>Bem-vindo(a), <?= htmlspecialchars($usuario_nome) ?>!</span>
        <a href="logout.php" class="btn btn-danger btn-sm">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
        </a>
        </div>
    </header>

<section class="content container-fluid flex-fill py-5">
  <div class="d-flex justify-content-end mb-2">
    <button
      class="btn btn-outline-secondary"
      data-bs-toggle="modal"
      data-bs-target="#modalNovoOKR"
    >
      <i class="fa-solid fa-bullseye me-1"></i> Novo OKR
    </button>
  </div>
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Descrição</th>
          <th>Equipe</th>
          <th>Níveis</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php while($o = $rs->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($o['descricao']) ?></td>
          <td><?= htmlspecialchars($o['equipe']) ?></td>
          <td><?= htmlspecialchars($o['niveis']) ?></td>
          <td class="text-end">
            <!-- Editar: dispara modal -->
            <button
                class="btn btn-sm btn-link text-primary"
                data-bs-toggle="modal"
                data-bs-target="#modalEditarOKR"
                data-id="<?= $o['id'] ?>"
                data-descricao="<?= htmlspecialchars($o['descricao'], ENT_QUOTES) ?>"
                data-equipe="<?= $o['idEquipe'] ?>"
                data-niveis="<?= htmlspecialchars($o['niveis_ids'], ENT_QUOTES) ?>"
                title="Editar"
            >
                <i class="fa-solid fa-pen"></i>
            </button>

            <!-- Excluir: link para delete_okr.php -->
            <button
              class="btn btn-sm btn-link text-danger"
              data-bs-toggle="modal"
              data-bs-target="#modalExcluirOKR"
              data-id="<?= $o['id'] ?>"
              data-nome="<?= htmlspecialchars($o['descricao'], ENT_QUOTES) ?>"
              title="Excluir"
            >
              <i class="fa-solid fa-trash"></i>
            </button>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    <a href="okr.php?view=year&q=1&nivel=0" class="btn btn-outline-secondary mt-3">
      Voltar ao Painel de OKRs
    </a>
        </section>
  </main>
</div>


<?php include '../Public/Modals/okr_edit_modal.php';?>
<?php include '../Public/Modals/okr_modals.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../Public/Js/okr.js"></script>


</body>
</html>
