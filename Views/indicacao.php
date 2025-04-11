<?php 
session_start();

// Verifica se o usuário está logado; se não, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../Config/Database.php';

$usuario_id = $_SESSION['usuario_id'];
$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';

// Consulta para pegar todas as indicações do mês e ano atuais, incluindo nome do usuário e status
$sql = "  SELECT 
              i.*, 
              p.nome AS plugin_nome,
              u.nome AS usuario_nome,
              case
              when idConsultor = 29 -- Quando for a Vanessa muda para não possui, explicação no cadastrar_indicacao.php
                  then 'Não Possui'
                  else c.nome 
              end consultor_nome
          FROM TB_INDICACAO i
          JOIN TB_PLUGIN p ON i.plugin_id = p.id
          JOIN TB_USUARIO u ON i.user_id = u.id
          JOIN TB_USUARIO c ON i.idConsultor = c.id
          WHERE MONTH(i.data) = MONTH(CURRENT_DATE())
            AND YEAR(i.data) = YEAR(CURRENT_DATE())
          ORDER BY i.data DESC";
$result = mysqli_query($conn, $sql);

// Consulta para o ranking: quantidade de indicações por usuário
$sqlRanking = "
  SELECT u.nome AS usuario_nome, COUNT(i.id) AS total_indicacoes
  FROM TB_INDICACAO i
  JOIN TB_USUARIO u ON i.user_id = u.id
  GROUP BY u.id
  ORDER BY total_indicacoes DESC
";
$resultRanking = mysqli_query($conn, $sqlRanking);
$ranking = array();
while($row = mysqli_fetch_assoc($resultRanking)) {
    $ranking[] = $row;
}

// Consulta para o ranking: quantidade de indicações por usuário
$sqlRankingConsult = "SELECT 
                        c.Nome AS usuario_nome, 
                        SUM(CASE WHEN i.status = 'Faturado' THEN i.vlr_total ELSE 0 END) AS total_faturado_consult
                      FROM TB_INDICACAO i
                      JOIN TB_USUARIO c ON i.idConsultor = c.id
                      WHERE c.Id <> 29
                      AND i.status = 'Faturado'
                      GROUP BY i.idConsultor
                      ORDER BY total_faturado_consult DESC";
$resultRankingConsult = mysqli_query($conn, $sqlRankingConsult);
$rankingConsult = array();
while($rowC = mysqli_fetch_assoc($resultRankingConsult)) {
    $rankingConsult[] = $rowC;
}

// Total de Faturamento
$sqlFaturamento = "SELECT SUM(vlr_total) AS total_faturamento FROM TB_INDICACAO WHERE status = 'Faturado'";
$resultFaturamento = mysqli_query($conn, $sqlFaturamento);
$rowFaturamento = mysqli_fetch_assoc($resultFaturamento);
$totalFaturamento = $rowFaturamento['total_faturamento'] ?: 0;

// Totalizador por Plugin
$sqlPluginsCount = "SELECT 
                      p.nome AS plugin_nome, 
                      COUNT(i.id) AS total_indicacoes, 
                      SUM(CASE WHEN i.status = 'Faturado' THEN i.vlr_total ELSE 0 END) AS total_faturado
                    FROM TB_INDICACAO i
                    JOIN TB_PLUGIN p ON i.plugin_id = p.id
                    GROUP BY p.id
                    ORDER BY total_indicacoes DESC";
$resultPluginsCount = mysqli_query($conn, $sqlPluginsCount);
$pluginsCount = array();
while($rowPC = mysqli_fetch_assoc($resultPluginsCount)) {
    $pluginsCount[] = $rowPC;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Painel N3 - Indicações de Plugins</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- CSS personalizado para Indicações -->
  <link href="../Public/indicacao.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="d-flex-wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
      <a class="light-logo" href="indicacao.php">
        <img src="../Public/Image/zucchetti_blue.png" width="150" alt="Logo Zucchetti">
      </a>
      <nav class="nav flex-column">
        <a class="nav-link" href="menu.php"><i class="fa-solid fa-house me-2"></i>Home</a>
        <?php if ($cargo === 'Admin'): ?>
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
        <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'User'): ?>
         <a class="nav-link active" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
         <a class="nav-link" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
         <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'Treinamento'): ?>
          <a class="nav-link" href="treinamento.php"><i class="fa-solid fa-calendar-check me-2"></i>Treinamentos</a>
        <?php endif; ?>
      </nav>
    </div>
    
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
    <script>
      function showToast(message, type) {
        const container = document.getElementById("toast-container");
        const toast = document.createElement("div");
        toast.className = "toast " + type;
        toast.textContent = message;
        container.appendChild(toast);
        // Trigger the CSS animation
        setTimeout(() => {
          toast.classList.add("show");
        }, 10);
        // Hide after 2 seconds and remove from DOM
        setTimeout(() => {
          toast.classList.remove("show");
          setTimeout(() => {
            container.removeChild(toast);
          }, 300);
        }, 2000);
      }

      document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get("success");
        const error = urlParams.get("error");

        if (success) {
          let msg = "";
          switch (success) {
            case "1":
              msg = "Indicação Cadastrada!";
              break;
            case "2":
              msg = "Indicação Editada!";
              break;
            case "3":
              msg = "Indicação Excluída!";
              break;
          }
          if (msg) showToast(msg, "success");
        }
      });
    </script>
    
    <!-- Área principal -->
    <div class="w-100">
      <!-- Header -->
      <div class="header">
        <h3>Controle de Indicações</h3>
        <div class="user-info">
          <span>Bem-vindo(a), <?php echo htmlspecialchars($usuario_nome, ENT_QUOTES, 'UTF-8'); ?>!</span>
          <a href="logout.php" class="btn btn-danger">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
          </a>
        </div>
      </div>
      
      <!-- Conteúdo principal -->
      <div class="content container-fluid">
        <!-- Accordion para Indicadores do Mês -->
        <div class="accordion mb-4" id="accordionIndicadores">
          <div class="accordion-item border-0">
            <h2 class="accordion-header" id="headingIndicadores">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseIndicadores" aria-expanded="true" aria-controls="collapseIndicadores" style="background-color: #fff; border: 1px solid #dee2e6;">
                <b>Indicadores do Mês - Resumo e Totalizações</b>
              </button>
            </h2>
            <div id="collapseIndicadores" class="accordion-collapse collapse show" aria-labelledby="headingIndicadores" data-bs-parent="#accordionIndicadores">
              <div class="accordion-body">
                <div class="row g-3">
                <?php if ($cargo != 'Comercial'): ?>
                  <!-- Ranking de Indicações -->
                  <div class="col-md-4">
                    <div class="card card-fixed-height shadow">
                      <div class="card-header border-bottom" style="background-color: #4b79a1;">
                        <h6 class="mb-0" style="color: #fff"><b>Ranking de Indicações</b></h6>
                      </div>
                      <div class="card-body p-2">
                        <?php if (count($ranking) > 0): ?>
                          <ul class="list-group list-group-flush">
                            <?php foreach ($ranking as $index => $rank): ?>
                              <li class="list-group-item d-flex justify-content-between align-items-center border-0">
                                <span>
                                  <?php
                                    if ($index == 0) echo "🥇 ";
                                    elseif ($index == 1) echo "🥈 ";
                                    elseif ($index == 2) echo "🥉 ";
                                    else echo ($index + 1) . "º ";
                                    echo $rank['usuario_nome'];
                                  ?>
                                </span>
                                <span class="badge bg-secondary">
                                  <?php echo $rank['total_indicacoes']; ?>
                                </span>
                              </li>
                            <?php endforeach; ?>
                          </ul>
                        <?php else: ?>
                          <p class="text-muted mb-0">Nenhum ranking disponível.</p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  <?php endif; ?>

                  <?php if ($cargo === 'Comercial'): ?>
                    <!-- Ranking de Faturamento -->
                    <div class="col-md-4" id="rankingFaturamento">
                      <div class="card card-fixed-height shadow">
                        <div class="card-header border-bottom" style="background-color: #4b79a1;">
                          <h6 class="mb-0" style="color: #fff"><b>Ranking de Faturamento</b></h6>
                        </div>
                        <div class="card-body p-2">
                          <?php if (count($rankingConsult) > 0): ?>
                            <ul class="list-group list-group-flush">
                              <?php foreach ($rankingConsult as $indexC => $rankC): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center border-0">
                                  <span>
                                    <?php
                                      if ($indexC == 0) echo "🥇 ";
                                      elseif ($indexC == 1) echo "🥈 ";
                                      elseif ($indexC == 2) echo "🥉 ";
                                      else echo ($indexC + 1) . "º ";
                                      echo $rankC['usuario_nome'];
                                    ?>
                                  </span>
                                  <span class="text-center">
                                        R$ <?php echo number_format($rankC['total_faturado_consult'], 2, ',', '.'); ?>
                                  </span>
                                </li>
                              <?php endforeach; ?>
                            </ul>
                          <?php else: ?>
                            <p class="text-muted mb-0">Nenhum ranking disponível.</p>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php endif; ?>

                  <!-- Total por Plugin -->
                  <div class="col-md-4">
                    <div class="card card-fixed-height shadow">
                      <div class="card-header border-bottom" style="background-color: #4b79a1;">
                        <h6 class="mb-0" style="color: #fff"><b>Total por Plugin</b></h6>
                      </div>
                      <div class="card-body p-0">
                        <?php if (count($pluginsCount) > 0): ?>
                          <!-- Torna a tabela responsiva -->
                          <div class="table-responsive p-4">
                            <table class="table table-bordered table-sm mb-0" style="table-layout: fixed;">
                              <thead>
                                <tr>
                                  <th style="width: 40%">Plugin</th>
                                  <th style="width: 30%">Quantidade</th>
                                  <th style="width: 30%">Faturado</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php foreach ($pluginsCount as $pc): ?>
                                  <tr>
                                    <!-- Classe para truncar o texto caso o plugin tenha nome muito grande -->
                                    <td class="text-truncate" style="max-width: 150px;">
                                      <?php echo $pc['plugin_nome']; ?>
                                    </td>
                                    <td class="text-center"><?php echo $pc['total_indicacoes']; ?></td>
                                    <td class="text-center">
                                      R$ <?php echo number_format($pc['total_faturado'], 2, ',', '.'); ?>
                                    </td>
                                  </tr>
                                <?php endforeach; ?>
                              </tbody>
                            </table>
                          </div>
                        <?php else: ?>
                          <p class="p-3 text-muted mb-0">Nenhum plugin encontrado.</p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Total de Faturamento -->
                  <div class="col-md-4">
                    <div class="card card-fixed-height shadow">
                      <div class="card-header border-bottom" style="background-color: #4b79a1;">
                        <h6 class="mb-0" style="color: #fff"><b>Total de Faturamento</b></h6>
                      </div>
                      <div class="card-body d-flex justify-content-center align-items-center">
                        <h4 class="mb-0" style="font-size: 3rem">
                          <strong><?php echo "R$ " . number_format($totalFaturamento, 2, ',', '.'); ?></strong>
                        </h4>
                      </div>
                    </div>
                  </div>
                </div><!-- /row -->
              </div><!-- /accordion-body -->
            </div>
          </div>
        </div>

        <!-- Card com a lista de indicações -->
        <div class="card shadow mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Indicações de Plugins</h4>
            <div class="d-flex justify-content-end gap-2">
              <input type="text" id="searchInput" class="form-control ms-2" style="max-width: 200px;" placeholder="Pesquisar...">
              <?php if ($cargo === 'Admin' || $cargo === 'User' || $cargo === 'Conversor'): ?>
                <button class="btn btn-custom ms-2" data-bs-toggle="modal" data-bs-target="#modalNovaIndicacao">
                  <i class="fa-solid fa-plus-circle me-1"></i> Cadastrar
                </button>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive access-scroll">
              <table class="table table-striped table-bordered tabelaEstilizada">
                <thead class="thead-dark">
                  <tr>
                    <th>Plugin</th>
                    <th width="5%">Data</th>
                    <th width="13%">CNPJ</th>
                    <th width="10%">Serial</th>
                    <th>Contato</th>
                    <th>Fone</th>
                    <th width="10%">Usuário</th>
                    <th width="5%">Status</th>
                    <th width="5%">Consultor(a)</th>
                    <th width="5%">Ações</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while($row = mysqli_fetch_assoc($result)): ?> 
                    <tr>
                      <td class="sobrepor"><?php echo $row['plugin_nome']; ?></td>
                      <td><?php echo date('d/m/Y', strtotime($row['data'])); ?></td>
                      <td><?php echo $row['cnpj']; ?></td>
                      <td><?php echo $row['serial']; ?></td>
                      <td><?php echo $row['contato']; ?></td>
                      <td><?php echo $row['fone']; ?></td>
                      <td><?php echo $row['usuario_nome']; ?></td>
                      <td><?php echo $row['status']; ?></td>
                      <td><?php echo $row['consultor_nome']; ?></td>
                      <td class="text-center">
                        <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $row['user_id'] == $usuario_id): ?>
                          <div class="d-flex flex-row align-items-center gap-1">
                            <button type="button" 
                                    class="btn btn-outline-primary btn-sm"
                                    title="Editar"
                                    onclick='editarIndicacao(
                                      <?php echo htmlspecialchars(json_encode($row["id"]), ENT_QUOTES, "UTF-8"); ?>,
                                      <?php echo htmlspecialchars(json_encode($row["plugin_id"]), ENT_QUOTES, "UTF-8"); ?>,
                                      <?php echo htmlspecialchars(json_encode($row["data"]), ENT_QUOTES, "UTF-8"); ?>,
                                      <?php echo htmlspecialchars(json_encode($row["cnpj"]), ENT_QUOTES, "UTF-8"); ?>,
                                      <?php echo htmlspecialchars(json_encode($row["serial"]), ENT_QUOTES, "UTF-8"); ?>,
                                      <?php echo htmlspecialchars(json_encode($row["contato"]), ENT_QUOTES, "UTF-8"); ?>,
                                      <?php echo htmlspecialchars(json_encode($row["fone"]), ENT_QUOTES, "UTF-8"); ?>,
                                      <?php echo htmlspecialchars(json_encode($row["idConsultor"]), ENT_QUOTES, "UTF-8"); ?>,
                                      <?php echo htmlspecialchars(json_encode($row["status"]), ENT_QUOTES, "UTF-8"); ?>,
                                      <?php echo htmlspecialchars(json_encode($row["vlr_total"]), ENT_QUOTES, "UTF-8"); ?>,
                                      <?php echo htmlspecialchars(json_encode($row["n_venda"]), ENT_QUOTES, "UTF-8"); ?>
                                    )'>
                              <i class="fa-solid fa-pen"></i>
                            </button>
                            <a type="button"
                               class="btn btn-outline-danger btn-sm"
                               title="Excluir"
                               onclick="modalExcluir('<?php echo $row['id']; ?>')">
                              <i class="fa-sharp fa-solid fa-trash"></i>
                            </a>
                          </div>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div><!-- /content -->
    </div><!-- /Área principal -->
  </div><!-- /d-flex-wrapper -->
  <!-- Função de pesquisa nas tabelas-->
  <script>
      $(document).ready(function(){
        $("#searchInput").on("keyup", function() {
          var value = $(this).val().toLowerCase();
          // Para cada linha em todas as tabelas com a classe 'tabelaEstilizada'
          $(".tabelaEstilizada tbody tr").filter(function() {
            // Se o texto da linha conter o valor da pesquisa (ignorando maiúsculas/minúsculas), mostra a linha; caso contrário, oculta
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
          });
        });
      });
  </script>
  <!-- Modal para cadastro de nova indicação -->
  <div class="modal fade" id="modalNovaIndicacao" tabindex="-1" role="dialog" aria-labelledby="modalNovaIndicacaoLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <!-- Conteúdo do modal de cadastro -->
        <div class="modal-header">
          <h5 class="modal-title" id="modalNovaIndicacaoLabel">Nova Indicação</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="cadastrar_indicacao.php" method="POST">
          <div class="modal-body">
            <!-- Linha 1: Plugin e Data -->
            <div class="row">
              <div class="col-md-6">
                <div class="form-group position-relative">
                  <label for="plugin_id" class="form-label mb-0">Plugin</label>
                  <div class="input-group mt-0">
                    <select class="form-control" id="plugin_id" name="plugin_id" required>
                      <?php
                        $sqlPlugins = "SELECT * FROM TB_PLUGIN ORDER BY nome";
                        $resPlugins = mysqli_query($conn, $sqlPlugins);
                        while($plugin = mysqli_fetch_assoc($resPlugins)):
                      ?>
                        <option value="<?php echo $plugin['id']; ?>"><?php echo $plugin['nome']; ?></option>
                      <?php endwhile; ?>
                    </select>
                    <button class="btn btn-outline-secondary" type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#novoPluginCollapse"
                            aria-expanded="false"
                            aria-controls="novoPluginCollapse">
                      <i class="fa-solid fa-plus"></i>
                    </button>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="data">Data</label>
                  <input type="date" class="form-control" id="data" name="data" required>
                </div>
              </div>
            </div>
            <!-- Collapse para novo plugin -->
            <div class="collapse mb-3 mt-2" id="novoPluginCollapse">
              <div class="card card-body">
                <div class="form-group">
                  <label for="novo_plugin">Nome do Novo Plugin</label>
                  <input type="text" class="form-control" id="novo_plugin" placeholder="Informe o nome do novo plugin">
                </div>
                <button type="button" class="btn btn-primary mt-1" id="btnCadastrarPlugin">Salvar Plugin</button>
              </div>
            </div>
            <!-- Linha 2: CNPJ e Serial -->
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="cnpj">CNPJ</label>
                  <input type="text" class="form-control" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00" maxlength="18" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="serial">Serial</label>
                  <input type="text" class="form-control" id="serial" name="serial" required>
                </div>
              </div>
            </div>
            <!-- Linha 3: Contato e Fone -->
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="contato">Contato</label>
                  <input type="text" class="form-control" id="contato" name="contato" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="fone">Fone</label>
                  <input type="text" class="form-control" id="fone" name="fone" required>
                </div>
              </div>
            </div>
          </div><!-- /modal-body -->
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Cadastrar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal para edição de indicação -->
  <div class="modal fade" id="modalEditarIndicacao" tabindex="-1" role="dialog" aria-labelledby="modalEditarIndicacaoLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form action="editar_indicacao.php" method="POST">
          <div class="modal-header">
            <h5 class="modal-title" id="modalEditarIndicacaoLabel">Editar Indicação</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <!-- Campo oculto para o ID -->
            <input type="hidden" id="editar_id" name="id">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group position-relative">
                  <label for="editar_plugin_id">Plugin</label>
                  <div class="input-group mt-0">
                    <select class="form-control" id="editar_plugin_id" name="plugin_id" required>
                      <?php
                        $sqlPlugins = "SELECT * FROM TB_PLUGIN ORDER BY nome";
                        $resPlugins = mysqli_query($conn, $sqlPlugins);
                        while($plugin = mysqli_fetch_assoc($resPlugins)):
                      ?>
                        <option value="<?php echo $plugin['id']; ?>"><?php echo $plugin['nome']; ?></option>
                      <?php endwhile; ?>
                    </select>
                    <button class="btn btn-outline-secondary" type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#novoPluginCollapseEdicao"
                            aria-expanded="false"
                            aria-controls="novoPluginCollapseEdicao">
                      <i class="fa-solid fa-plus"></i>
                    </button>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editar_data">Data</label>
                  <input type="date" class="form-control" id="editar_data" name="data" required>
                </div>
              </div>
            </div>
            <!-- Collapse para novo plugin (Edição) -->
            <div class="collapse mb-3 mt-2" id="novoPluginCollapseEdicao">
              <div class="card card-body">
                <div class="form-group">
                  <label for="novo_plugin_edit">Nome do Novo Plugin</label>
                  <input type="text" class="form-control" id="novo_plugin_edit" placeholder="Informe o nome do novo plugin">
                </div>
                <button type="button" class="btn btn-primary mt-1" id="btnCadastrarPluginEdit">Salvar Plugin</button>
              </div>
            </div>
            <div class="row mt-2">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editar_cnpj">CNPJ</label>
                  <input type="text" class="form-control" id="editar_cnpj" name="editar_cnpj" maxlength="18" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editar_serial">Serial</label>
                  <input type="text" class="form-control" id="editar_serial" name="serial" required>
                </div>
              </div>
            </div>
            <div class="row mt-2">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editar_contato">Contato</label>
                  <input type="text" class="form-control" id="editar_contato" name="contato" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editar_fone">Fone</label>
                  <input type="text" class="form-control" id="editar_fone" name="fone" required>
                </div>
              </div>
            </div>
            <?php if ($cargo === 'Admin' || $cargo === 'Comercial'): ?>
              <div class="row mt-2">
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="editar_status">Status</label>
                    <select name="editar_status" class="form-select" id="editar_status" onchange="verificarStatus()">
                      <option value="Pendente">Pendente</option>
                      <option value="Faturado">Faturado</option>
                      <option value="Cancelado">Cancelado</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="editar_consultor">Consultor</label>
                    <select class="form-select" id="editar_consultor" name="consultor" required>
                        <option value="">Selecione</option>
                        <?php
                        $sqlConsult = "SELECT Id, Nome FROM TB_USUARIO WHERE Cargo = 'Comercial' ORDER BY Nome";
                        $resConsult = $conn->query($sqlConsult);
                        while ($row = $resConsult->fetch_assoc()) {
                            echo "<option value='" . $row['Id'] . "'>" . $row['Nome'] . "</option>";
                        }
                        ?>
                    </select>
                  </div>
                </div>
              </div>
              <div class="row mt-2">
                <!-- Container para campos adicionais quando o status for Faturado -->
                <div class="col-md-6" id="valorContainer" style="display: none;">
                  <div class="form-groupo mt-2">
                    <label for="editar_valor">Valor R$</label>
                    <input type="text" class="form-control" id="editar_valor" name="editar_valor" value="0">
                  </div>
                </div>
                <div class="col-md-6" id="vendaContainer" style="display: none;"> 
                  <div class="form-group mt-2">
                    <label for="editar_venda">Nº Venda</label>
                    <input type="text" class="form-control" id="editar_venda" name="editar_venda">
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div><!-- /modal-body -->
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Salvar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal de Exclusão -->
  <div class="modal fade" id="modalExcluir" tabindex="-1" aria-labelledby="modalExcluirLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalExcluirLabel">Excluir Indicação</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <form action="deletar_indicacao.php" method="post">
          <div class="modal-body">
            <input type="hidden" name="id" id="excluir_id">
            <p>Tem certeza que deseja excluir essa indicação?</p>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-danger">Excluir</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Scripts JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
  // Exibe/oculta campos do modal de edição quando status é "Faturado"
  function verificarStatus() {
    var status = document.getElementById("editar_status");
    var valorContainer = document.getElementById("valorContainer");
    var vendaContainer = document.getElementById("vendaContainer");
    var valor = document.getElementById("editar_valor");
    var venda = document.getElementById("editar_venda");

    var statusSelecionado = status.options[status.selectedIndex].text.trim();
    if (statusSelecionado === "Faturado") {
      valorContainer.style.display = "block";
      vendaContainer.style.display = "block";
      valor.setAttribute("required", "true");
      venda.setAttribute("required", "true");
    } else {
      valorContainer.style.display = "none";
      vendaContainer.style.display = "none";
      valor.removeAttribute("required");
      venda.removeAttribute("required");
    }
  }
</script>
<script>
  // Formatação do campo de valor (duas casas decimais)
  function formatCurrency(digits) {
    digits = digits.replace(/\D/g, "");
    while (digits.length < 4) {
      digits = "0" + digits;
    }
    var intPart = digits.slice(0, digits.length - 4);
    var decPart = digits.slice(-4);
    intPart = intPart.replace(/^0+/, "") || "0";
    intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    return "R$" + intPart + "," + decPart;
  }
  function updateValorField() {
    var input = document.getElementById("editar_valor");
    if (!input) return;
    var digits = input.value.replace(/\D/g, "");
    input.value = formatCurrency(digits);
  }
  document.addEventListener("DOMContentLoaded", function() {
    var input = document.getElementById("editar_valor");
    if (input) {
      if (!input.value || input.value.replace(/\D/g, "") === "") {
        input.value = formatCurrency("0");
      } else {
        input.value = formatCurrency(input.value.replace(/\D/g, ""));
      }
      input.addEventListener("input", updateValorField);
    }

    $('#modalEditarIndicacao').on('shown.bs.modal', function () {
      if (input) updateValorField();
    });
  });
</script>
<script>
  // Cadastrar novo plugin via AJAX (modal de cadastro)
  $(document).ready(function(){
    $('#btnCadastrarPlugin').click(function(){
      var novoPlugin = $('#novo_plugin').val().trim();
      if(novoPlugin === ''){
        alert('Informe o nome do novo plugin.');
        return;
      }
      $.ajax({
        url: 'cadastrar_plugin.php',
        type: 'POST',
        data: { nome: novoPlugin },
        dataType: 'json',
        success: function(resp){
          if (resp.duplicate === true) {
            alert(resp.message);
            $('#plugin_id').val(resp.id);
          } else if (resp.id) {
            $('#plugin_id').append('<option value="' + resp.id + '">' + resp.nome + '</option>');
            $('#plugin_id').val(resp.id);
            alert('Plugin cadastrado com sucesso!');
          } else {
            alert('Erro: ' + resp.message);
          }
          $('#novo_plugin').val('');
          $('#novoPluginCollapse, #novoPluginCollapseEdicao').collapse('hide');
        },
        error: function(jqXHR, textStatus, errorThrown){
          alert('Erro na requisição: ' + errorThrown);
        }
      });
    });
  });

  // Cadastrar novo plugin via AJAX (modal de edição)
  $(document).ready(function(){
    $('#btnCadastrarPluginEdit').click(function(){
      var novoPluginEdit = $('#novo_plugin_edit').val().trim();
      if(novoPluginEdit === ''){
        alert('Informe o nome do novo plugin.');
        return;
      }
      $.ajax({
        url: 'cadastrar_plugin.php',
        type: 'POST',
        data: { nome: novoPluginEdit },
        dataType: 'json',
        success: function(resp){
          if (resp.duplicate === true) {
            alert(resp.message);
            $('#editar_plugin_id').val(resp.id);
          } else if (resp.id) {
            $('#editar_plugin_id').append('<option value="' + resp.id + '">' + resp.nome + '</option>');
            $('#editar_plugin_id').val(resp.id);
            alert('Plugin cadastrado com sucesso!');
          } else {
            alert('Erro: ' + resp.message);
          }
          $('#novo_plugin_edit').val('');
          $('#novoPluginCollapse, #novoPluginCollapseEdicao').collapse('hide');
        },
        error: function(jqXHR, textStatus, errorThrown){
          alert('Erro na requisição: ' + errorThrown);
        }
      });
    });
  });
</script>
<script>
  // Máscara de CNPJ para #cnpj e #editar_cnpj
  document.addEventListener("DOMContentLoaded", function(){
    var cnpjInputs = document.querySelectorAll("#cnpj, #editar_cnpj");
    cnpjInputs.forEach(function(input) {
      input.addEventListener("input", function(e) {
        var v = e.target.value.replace(/\D/g, "");
        // Formata: 00.000.000/0000-00
        v = v.replace(/^(\d{2})(\d)/, "$1.$2");
        v = v.replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3");
        v = v.replace(/\.(\d{3})(\d)/, ".$1/$2");
        v = v.replace(/(\d{4})(\d)/, "$1-$2");
        e.target.value = v;
      });
      input.addEventListener("blur", function(e) {
        var formattedValue = e.target.value;
        var errorSpanId = e.target.id + "-error";
        var errorSpan = document.getElementById(errorSpanId);
        if (formattedValue.length !== 18) {
          if (!errorSpan) {
            errorSpan = document.createElement("span");
            errorSpan.id = errorSpanId;
            errorSpan.style.color = "red";
            errorSpan.style.fontSize = "0.9em";
            e.target.parentNode.appendChild(errorSpan);
          }
          errorSpan.textContent = "CNPJ inválido. Revise e tente novamente!";
          setTimeout(function(){
            if(e.target.value.length !== 18) {
              e.target.focus();
            }
            errorSpan.textContent = "";
          }, 3000);
        }
      });
    });
  });
</script>
<script>
  // Função para popular o modal de edição com os dados recebidos
  function editarIndicacao(id, plugin_id, data, cnpj, serial, contato, fone, idConsultor, status, editar_valor, editar_venda) {
    document.getElementById("editar_id").value = id;
    document.getElementById("editar_plugin_id").value = plugin_id;
    document.getElementById("editar_data").value = data;
    document.getElementById("editar_cnpj").value = cnpj;
    document.getElementById("editar_serial").value = serial;
    document.getElementById("editar_contato").value = contato;
    document.getElementById("editar_fone").value = fone;
    document.getElementById("editar_consultor").value = idConsultor;

    if (document.getElementById("editar_status")) {
      document.getElementById("editar_status").value = status;
      if (status === "Faturado") {
        if (document.getElementById("editar_valor")) {
          document.getElementById("editar_valor").value = editar_valor;
        }
        if (document.getElementById("editar_venda")) {
          document.getElementById("editar_venda").value = editar_venda;
        }
      } else {
        if (document.getElementById("editar_valor")) {
          document.getElementById("editar_valor").value = "";
        }
        if (document.getElementById("editar_venda")) {
          document.getElementById("editar_venda").value = "";
        }
      }
      verificarStatus();
    } else if (document.getElementById("editar_status_hidden")) {
      document.getElementById("editar_status_hidden").value = status;
    }

    $('#modalEditarIndicacao').modal('show');
  }

  function modalExcluir(id) {
      document.getElementById('excluir_id').value = id;
      new bootstrap.Modal(document.getElementById('modalExcluir')).show();
    }
</script>
</body>
</html>
