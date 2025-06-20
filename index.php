<?php
require 'Config/Database.php';

require_once __DIR__ . '/Includes/auth.php';

$usuario_id = $_SESSION['usuario_id'];
$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';

// Para preencher os selects do filtro, buscamos os dados dos usuários e demais categorias
$resultado_usuarios_dropdown = $conn->query("SELECT Id, Nome FROM TB_USUARIO WHERE Cargo in ('User', 'Conversor', 'Treinamento', 'Viewer') ORDER BY Nome ASC");
$lista_situacoes = $conn->query("SELECT Id, Descricao FROM TB_SITUACAO ORDER BY Descricao ASC");
$lista_sistemas   = $conn->query("SELECT Id, Descricao FROM TB_SISTEMA ORDER BY Descricao ASC");
$lista_status     = $conn->query("SELECT Id, Descricao FROM TB_STATUS ORDER BY Descricao ASC");

// Monta a query base (SEC_TO_TIME converte de segundos para formatação de hora)
$sql = "SELECT
            tas.Id as Codigo,
            tas.Descricao as Descricao,
            sit.Descricao as Situacao,
            usu.Nome as Atendente,
            sis.Descricao as Sistema,
            sta.Descricao as Status,
            tas.Hora_ini,
            tas.Hora_fim,
            DATE_FORMAT(tas.Hora_ini, '%d/%m %H:%i:%s') as Hora_ini2,
            DATE_FORMAT(tas.Hora_fim, '%d/%m %H:%i:%s') as Hora_fim2,
            SEC_TO_TIME(TIME_TO_SEC(tas.Total_hora)) AS Total_hora, 
            tas.idSituacao AS idSituacao,
            tas.idAtendente AS idAtendente,
            usu.Nome AS NomeUsuario,
            tas.idSistema AS idSistema,
            tas.idStatus AS idStatus,
            tas.chkParado as Parado,
            tas.Nota as Nota,
            usu.Cargo as Cargo,
            tas.justificativa as Justificativa
        FROM TB_ANALISES tas
            LEFT JOIN TB_SITUACAO sit ON sit.Id = tas.idSituacao
            LEFT JOIN TB_SISTEMA sis ON sis.Id = tas.idSistema
            LEFT JOIN TB_STATUS sta ON sta.Id = tas.idStatus
            LEFT JOIN TB_USUARIO usu ON usu.Id = tas.idUsuario
        WHERE tas.Hora_ini is not null";

// Filtros por data usando somente a parte de data (DATE) para evitar problemas com os horários
if (!empty($_GET['data_inicio'])) {
    $sql .= " AND DATE(tas.Hora_ini) >= '" . $_GET['data_inicio'] . "'";
}
if (!empty($_GET['data_fim'])) {
    $sql .= " AND DATE(tas.Hora_ini) <= '" . $_GET['data_fim'] . "'";
}
// Filtro por analista
if (!empty($_GET['analista'])) {
    $sql .= " AND tas.idAtendente = '" . $_GET['analista'] . "'";
}
// Filtro por situação
if (!empty($_GET['situacao'])) {
    $sql .= " AND tas.idSituacao = '" . $_GET['situacao'] . "'";
}
// Filtro por sistema
if (!empty($_GET['sistema'])) {
    $sql .= " AND tas.idSistema = '" . $_GET['sistema'] . "'";
}
// Filtro por status
if (!empty($_GET['status'])) {
    $sql .= " AND tas.idStatus = '" . $_GET['status'] . "'";
}

$sql .= " ORDER BY tas.Id DESC";

$result = $conn->query($sql);
$result1 = $conn->query($sql);
if ($result1 === false) {
    die("Erro na consulta SQL: " . $conn->error);
}

// Armazena os registros em um array para possibilitar o cálculo dos totalizadores
$rows = array();
while ($row = $result1->fetch_assoc()) {
    $rows[] = $row;
}

// Cálculo dos totalizadores
$totalFichas = 0;
$totalAnaliseN3 = 0;
$totalAuxilio = 0;
$totalParado = 0;
$totalHoras = 0;

foreach ($rows as $row) {
    if (trim($row['Situacao']) == "Analise N3") {
        $totalAnaliseN3++;
    }
    if (trim($row['Situacao']) == "Auxilio Suporte/Vendas") {
        $totalAuxilio++;
    }
    if (trim($row['Situacao']) == "Ficha Criada") {
        $totalFichas++;
    }
    if (trim($row['Parado']) == "S") {
        $totalParado++;
    }
    if (!empty($row["Total_hora"])) {
        list($h, $m, $s) = explode(":", $row["Total_hora"]);
        $totalHoras += ($h * 3600) + ($m * 60) + $s;
    }
}

if ($totalAnaliseN3 > 0) {
    $mediaSegundos = round($totalHoras / $totalAnaliseN3);
    $horas = floor($mediaSegundos / 3600);
    $minutos = floor(($mediaSegundos % 3600) / 60);
    $segundos = $mediaSegundos % 60;
} else {
    $horas = $minutos = $segundos = 0;
}

// Processamento dos dados para o gráfico de barras mensal
$fichasPorMes = array_fill(1, 12, 0);
$analisesN3PorMes = array_fill(1, 12, 0);
$clienteParadoPorMes = array_fill(1, 12, 0);
$currentYear = date("Y");

foreach ($rows as $row) {
    $dataHora = strtotime($row["Hora_ini"]);
    if (date("Y", $dataHora) == $currentYear) {
        $month = intval(date("n", $dataHora));
        if (trim($row['Situacao']) == "Ficha Criada") {
            $fichasPorMes[$month]++;
        }
        if (trim($row['Situacao']) == "Analise N3") {
            $analisesN3PorMes[$month]++;
        }
        if (trim($row['Parado']) == "S") {
            $clienteParadoPorMes[$month]++;
        }
    }
}
// === Indicadores adicionais ===
$totalAnalises = count($rows);
$percentFicha = $totalAnalises
    ? round(($totalFichas / $totalAnalises) * 100, 2)
    : 0;

    // antes de montar o HTML, inclua:

    $logos = include __DIR__ . '/Config/logos.php';
    // transforma todas as chaves do array em lowercase
    $logosLower = array_change_key_case($logos, CASE_LOWER);
    $fichasPorSistema     = [];
    $analisesPorSistema   = [];
    
    foreach ($rows as $row) {
        $sis = $row['Sistema'];
    
        if (trim($row['Situacao']) === "Ficha Criada") {
            // só incrementa ficha quando for realmente ficha
            $fichasPorSistema[$sis] = ($fichasPorSistema[$sis] ?? 0) + 1;
        } else {
            // somente aqui contam as “análises” (tudo que não for Ficha Criada)
            $analisesPorSistema[$sis] = ($analisesPorSistema[$sis] ?? 0) + 1;
        }
    }
   
  // Calcular percentual geral de fichas em relação às análises N3
  $pctFichasGeral = $totalAnaliseN3
    ? round($totalFichas / $totalAnaliseN3 * 100, 2)
    : 0;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Painel N3 - Tarefas N3</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap, Font Awesome, Google Fonts, Chart.js -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <!-- CSS Personalizado (layout unificado com os demais módulos) -->
  <link rel="stylesheet" href="Public/index.css">
  <link rel="icon" href="Public/Image/LogoTituto.png" type="image/png">
</head>
<body class="bg-light">
    <div class="d-flex-wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
        <a class="light-logo" href="index.php">
            <img src="Public/Image/zucchetti_blue.png" width="150" alt="Logo Zucchetti">
        </a>
        <nav class="nav flex-column">
            <a class="nav-link" href="Views/menu.php"><i class="fa-solid fa-house me-2"></i>Home</a>
            <?php if ($cargo === 'Admin'): ?>
            <a class="nav-link" href="Views/conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a>
            <?php endif; ?>
            <?php if ($cargo === 'Admin'): ?>
            <a class="nav-link" href="Views/destaque.php"><i class="fa-solid fa-ranking-star me-2"></i>Destaques</a>
            <?php endif; ?>
            <?php if ($cargo === 'Admin'): ?>
            <a class="nav-link" href="Views/escutas.php"><i class="fa-solid fa-headphones me-2"></i>Escutas</a>
            <?php endif; ?>
            <?php if ($cargo === 'Admin'): ?>
            <a class="nav-link" href="Views/folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a>
            <?php endif; ?>
            <?php if ($cargo === 'Admin'): ?>
            <a class="nav-link" href="Views/incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a>
            <?php endif; ?>
            <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'User'): ?>
            <a class="nav-link" href="Views/indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a>
            <?php endif; ?>
            <?php if ($cargo === 'Admin' || $cargo === 'Viewer' || $cargo === 'User' || $cargo === 'Conversor'): ?>
            <a class="nav-link" href="Views/user.php"><i class="fa-solid fa-users-rectangle me-2"></i>Meu Painel</a>
            <?php endif; ?>
            <?php if ($cargo === 'Admin'): ?>
            <a class="nav-link active" href="index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a>
            <?php endif; ?>
            <?php if ($cargo != 'Comercial'): ?>
                <a class="nav-link" href="Views/okr.php"><img src="Public/Image/benchmarkbranco.png" width="27" height="27" class="me-1" alt="Benchmark">OKRs</a>
            <?php endif; ?>
            <?php if ($cargo === 'Admin'): ?>
            <a class="nav-link" href="Views/dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a>
            <?php endif; ?>
            <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'Treinamento'): ?>
            <a class="nav-link" href="Views/treinamento.php"><i class="fa-solid fa-calendar-check me-2"></i>Treinamentos</a>
            <?php endif; ?>
            <?php if ($cargo === 'Admin'): ?>
            <a class="nav-link" href="Views/usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a>
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
                msg = "Análise Cadastrada!";
                break;
                case "2":
                msg = "Análise Editada!";
                break;
                case "3":
                msg = "Análise Excluída!";
                break;
            }
            if (msg) showToast(msg, "success");
            }
        });
        </script>

        <!-- Área Principal -->
        <div class="w-100">
            <!-- Header -->
            <div class="header">
                <h3>Tarefas N3</h3>
                <div class="user-info">
                <span>Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
                <a href="Views/logout.php" class="btn btn-danger">
                    <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
                </a>
                </div>
            </div>
            
    <!-- Conteúdo -->
    <div class="content container-fluid">
            
            <!-- Dashboard em Accordion: Totalizadores e Gráfico Mensal -->
      <!-- Nav Tabs -->
 <ul class="nav nav-tabs mb-4" id="dashboardTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tab-analises" data-bs-toggle="tab"
            data-bs-target="#pane-analises" type="button" role="tab"
            aria-controls="pane-analises" aria-selected="true">
      Análises N3
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-relatorios" data-bs-toggle="tab"
            data-bs-target="#pane-relatorios" type="button" role="tab"
            aria-controls="pane-relatorios" aria-selected="false">
      Relatórios
    </button>
  </li>
</ul>

<div class="tab-content" id="dashboardTabsContent">
  <!-- Aba 1: Lista de Análises -->
  <div class="tab-pane fade show active" id="pane-analises" role="tabpanel" aria-labelledby="tab-analises">
  <div class="card shadow mb-4 modern-card">
                    <div class="card-header d-flex align-items-center modern-card-header">
                        <h4 class="mb-0 flex-grow-1">Lista de Análises</h4>
                        <div class="d-flex gap-2 align-items-center">
                        <!-- Botão para abrir o modal de filtro -->
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
                            <i class="fa-solid fa-filter"></i>
                        </button>
                        <!-- Campo de pesquisa -->
                        <input type="text" id="searchInput" class="form-control" style="max-width: 200px;" placeholder="Pesquisar...">
                        <?php if ($cargo === 'Admin'): ?>
                        <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#modalCadastro">
                            <i class="fa-solid fa-plus me-1"></i> Cadastrar
                        </button>
                        <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive access-scroll">
                        <table id="tabelaAnalises" class="table table-hover modern-table">
                            <thead class="thead-light modern-thead">
                            <tr>
                                <th style="width: 17%">Descrição</th>
                                <th style="width: 7%">Situação</th>
                                <th style="width: 7%">Analista</th>
                                <th style="width: 5%">Sistema</th>
                                <th style="width: 5%">Status</th>
                                <th style="width: 5%">Total Horas</th>
                                <?php if ($cargo === 'Admin') echo '<th style="width: 5%">Ações</th>'; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php 
                            if ($result->num_rows > 0) {
                                while($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td class='sobrepor'>" . $row["Descricao"] . "</td>";
                                    echo "<td>" . $row["Situacao"] . "</td>";
                                    echo "<td>" . $row["NomeUsuario"] . "</td>";
                                    echo "<td>" . $row["Sistema"] . "</td>";
                                    echo "<td>" . $row["Status"] . "</td>";
                                    echo "<td>" . $row["Total_hora"] . "</td>";
                                    if ($cargo === 'Admin') {
                                        echo "<td class='text-center'>";
                                        echo "<button class='btn btn-outline-primary btn-sm' data-bs-toggle='modal' data-bs-target='#modalEdicao' onclick='editarAnalise(" 
                                            . htmlspecialchars(json_encode($row['Codigo']), ENT_QUOTES, 'UTF-8') . ", " 
                                            . htmlspecialchars(json_encode($row['Descricao']), ENT_QUOTES, 'UTF-8') . ", " 
                                            . htmlspecialchars(json_encode($row['idSituacao']), ENT_QUOTES, 'UTF-8') . ", " 
                                            . htmlspecialchars(json_encode($row['idAtendente']), ENT_QUOTES, 'UTF-8') . ", " 
                                            . htmlspecialchars(json_encode($row['idSistema']), ENT_QUOTES, 'UTF-8') . ", " 
                                            . htmlspecialchars(json_encode($row['idStatus']), ENT_QUOTES, 'UTF-8') . ", " 
                                            . htmlspecialchars(json_encode($row['Hora_ini']), ENT_QUOTES, 'UTF-8') . ", " 
                                            . htmlspecialchars(json_encode($row['Hora_fim']), ENT_QUOTES, 'UTF-8') . ", " 
                                            . htmlspecialchars(json_encode($row['Nota']), ENT_QUOTES, 'UTF-8') . ", " 
                                            . htmlspecialchars(json_encode($row['Justificativa']), ENT_QUOTES, 'UTF-8') .
                                            ")'><i class='fa-solid fa-pen'></i></button> ";
                                        echo "<button class='btn btn-outline-danger btn-sm' data-bs-toggle='modal' data-bs-target='#modalExclusao' onclick='excluirAnalise(" 
                                            . htmlspecialchars(json_encode($row['Codigo']), ENT_QUOTES, 'UTF-8') .
                                            ")'><i class='fa-solid fa-trash'></i></button>";
                                        echo "</td>";
                                    }
                                    echo "</tr>";
                                }
                            }
                            ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                
    </div>
  </div>
  </div>

  <div class="tab-pane fade" id="pane-relatorios" role="tabpanel" aria-labelledby="tab-relatorios">


<h5 class="fw-bold mb-3">Totalizador Geral</h5>
<div class="row row-cols-1 row-cols-sm-2 row-cols-md-5 g-4 mb-4">

  <!-- Fichas Criadas -->
  <div class="col">
    <div class="card layout9-card h-100">
      <div class="layout9-item">
        <div class="layout9-icon" style="background: #3B82F6;">
          <i class="fa-solid fa-file-alt"></i>
        </div>
        <div class="layout9-text">
          <h4><?= $totalFichas; ?></h4>
          <small>Fichas Criadas</small>
        </div>
      </div>
    </div>
  </div>

  <!-- Análise N3 -->
  <div class="col">
    <div class="card layout9-card h-100">
      <div class="layout9-item">
        <div class="layout9-icon" style="background: #10B981;">
          <i class="fa-solid fa-chart-line"></i>
        </div>
        <div class="layout9-text">
          <h4><?= $totalAnaliseN3; ?></h4>
          <small>Análises N3</small>
        </div>
      </div>
    </div>
  </div>

<!-- Percentual Geral de Fichas -->
<div class="col">
  <div class="card layout9-card h-100">
    <div class="layout9-item">
      <div class="layout9-icon" style="background: #6D28D9;">
        <i class="fa-solid fa-percent"></i>
      </div>
      <div class="layout9-text">
        <h4><?= $pctFichasGeral; ?>%</h4>
        <small>% Fichas</small>
      </div>
    </div>
  </div>
</div>

  <!-- Cliente Parado -->
  <div class="col">
    <div class="card layout9-card h-100">
      <div class="layout9-item">
        <div class="layout9-icon" style="background: #EF4444;">
          <i class="fa-solid fa-stop-circle"></i>
        </div>
        <div class="layout9-text">
          <h4><?= $totalParado; ?></h4>
          <small>Clientes Parados</small>
        </div>
      </div>
    </div>
  </div>

  <!-- Média Horas -->
  <div class="col">
    <div class="card layout9-card h-100">
      <div class="layout9-item">
        <div class="layout9-icon" style="background: #6B7280;">
          <i class="fa-solid fa-clock"></i>
        </div>
        <div class="layout9-text">
          <h4><?= sprintf('%02d:%02d:%02d', $horas, $minutos, $segundos); ?></h4>
          <small>Média de Horas</small>
        </div>
      </div>
    </div>
  </div>

</div>

<?php
$fichasPorSistema   = $fichasPorSistema   ?? [];
$analisesPorSistema = $analisesPorSistema ?? [];
$systems = array_unique(array_merge(
    array_keys($fichasPorSistema),
    array_keys($analisesPorSistema)
));
?>

<h5 class="fw-bold mb-3">Totalizador por Sistemas</h5>
<div class="systems-scroll mb-4">
    <?php
  // Normaliza as chaves de $logosLower para lowercase
  $logosNormalized = array_change_key_case($logosLower ?? [], CASE_LOWER);
?>
<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4 mb-4">
  <?php foreach ($systems as $sis): 
      // Garante contagens
      $cntFicha   = isset($fichasPorSistema[$sis])   ? $fichasPorSistema[$sis]   : 0;
      $cntAnalise = isset($analisesPorSistema[$sis]) ? $analisesPorSistema[$sis] : 0;
      $pctFicha   = $cntAnalise
                  ? round($cntFicha / $cntAnalise * 100)
                  : 0;

      // Busca a logo pelo nome em lowercase, cai em Concorrente se não existir
      $lowerSis = strtolower($sis);
      if (isset($logosNormalized[$lowerSis])) {
          $logoPath = $logosNormalized[$lowerSis];
      } else {
          $logoPath = "/Public/Image/Concorrente.png";
      }
  ?>
    <div class="col">
      <div class="layout8-card h-100">
        <div class="layout8-header">
          <img
            src="<?= htmlspecialchars($logoPath, ENT_QUOTES) ?>"
            alt="<?= htmlspecialchars($sis, ENT_QUOTES) ?>"
          >
          <h6><?= htmlspecialchars($sis) ?></h6>
        </div>
        <div class="layout8-badges">
          <span class="layout8-badge badge-ficha">
            <i class="fa-solid fa-file-alt"></i>
            <?= $cntFicha ?> Fichas
          </span>
          <span class="layout8-badge badge-analise">
            <i class="fa-solid fa-chart-line"></i>
            <?= $cntAnalise ?> Análises
          </span>
        </div>
        <div class="layout8-bar-container">
          <div
            class="layout8-bar"
            style="width: <?= $pctFicha ?>%;"
          ></div>
        </div>
        <div class="layout8-footer">
          <?= $pctFicha ?>% geraram ficha
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div> <!-- /.row -->



  </div>  <!-- /.systems-scroll -->
</div>
</div>           
            </div> <!-- /.content -->
        </div>
    </div> <!-- /.d-flex-wrapper -->

  <!-- Modal de Cadastro -->
  <div class="modal fade" id="modalCadastro" tabindex="-1" aria-labelledby="modalCadastroLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCadastroLabel">Nova Análise</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="Views/cadastrar_analise.php" method="POST">
                        <div class="row mb-1">
                            <div class="col-md-12 mb-2">
                                <label for="descricao" class="form-label">Descrição</label>
                                <textarea type="text" class="form-control" id="descricao" name="descricao" maxlength="100" required></textarea>
                            </div>
                        </div>    
                        <div class="row mb-1">
                            <div class="col-md-6 mb-2">
                                <label for="nota" id="notaAnalise" class="form-label">Nota</label>
                                <select class="form-select" id="nota" name="nota">
                                    <option value="0">0</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="situacao" class="form-label">Situação</label>
                                <select class="form-select" id="situacao" name="situacao" required onchange="verificarSituacao(); verificarSituacao2();">
                                    <option value="">Selecione</option>
                                    <?php
                                    $querySituacao = "SELECT Id, Descricao FROM TB_SITUACAO";
                                    $resultSituacao = $conn->query($querySituacao);
                                    while ($rowS = $resultSituacao->fetch_assoc()) {
                                        echo "<option value='" . $rowS['Id'] . "'>" . $rowS['Descricao'] . "</option>";
                                    }
                                    ?>
                                </select>
                                <!-- Checkbox e campo de Número da Ficha (inicialmente ocultos) -->
                                <div class="row mt-3" id="fichaContainer" style="display: none;">
                                    <div class="row mb-3 mt-3">
                                        <div class="form-check d-flex justify-content-center ms-1">
                                            <input class="form-check-input" type="checkbox" id="chkFicha" name="chkFicha" onchange="verificarFicha() ">
                                            <label class="form-check-label" for="chkFicha">Ficha</label>
                                            <input class="form-check-input ms-2" type="checkbox" id="chkParado" name="chkParado" onchange="marcaParado()">
                                            <label class="form-check-label" for="chkParado">Cliente Parado</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3" id="numeroFichaContainer" style="display: none;">
                                    <div class="col-md-12">
                                        <label for="numeroFicha" class="form-label">Número da Ficha</label>
                                        <input type="number" class="form-control" id="numeroFicha" name="numeroFicha" pattern="\d+">
                                    </div>
                                </div>

                                <!-- Checkbox e campo de Número do multiplicador (inicialmente ocultos) -->
                                <div class="row mb-3 mt-3" id="multiplicaContainer" style="display: none;">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="chkMultiplica" name="chkMultiplica" onchange="verificarMultiplica()">
                                            <label class="form-check-label" for="chkMultiplica">Replicar</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3" id="numeroMultiContainer" style="display: none;">
                                    <div class="col-md-15">
                                        <label for="numeroMulti" class="form-label">Quantidade para Replicar</label>
                                        <input type="number" class="form-control" id="numeroMulti" name="numeroMulti" pattern="\d+">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- JavaScript para controlar a exibição dos campos -->
                        <script>
                            function verificarSituacao() {
                                var situacao = document.getElementById("situacao");
                                var fichaContainer = document.getElementById("fichaContainer");

                                // Pega o texto da opção selecionada
                                var situacaoSelecionada = situacao.options[situacao.selectedIndex].text.trim();

                                // Verifica se a opção selecionada é "Analise N3"
                                if (situacaoSelecionada === "Analise N3") {
                                    fichaContainer.style.display = "block";
                                } else {
                                    fichaContainer.style.display = "none";
                                    document.getElementById("numeroFichaContainer").style.display = "none";
                                    document.getElementById("chkFicha").checked = false;
                
                                }
                            }

                            function verificarFicha() {
                                var chkFicha = document.getElementById("chkFicha").checked;
                                var numeroFichaContainer = document.getElementById("numeroFichaContainer");
                                var numeroFichaInput = document.getElementById("numeroFicha");

                                if (chkFicha) {
                                    numeroFichaContainer.style.display = "block";
                                    numeroFichaInput.setAttribute("required", "true"); // Adiciona required quando visível
                                } else {
                                    numeroFichaContainer.style.display = "none";
                                    numeroFichaInput.removeAttribute("required"); // Remove required quando oculto
                                    numeroFichaInput.value = ""; // Limpa o valor do campo
                                }
                            }

                            function verificarSituacao2() {
                                var situacao = document.getElementById("situacao");
                                var fichaContainer = document.getElementById("multiplicaContainer");

                                // Pega o texto da opção selecionada
                                var situacaoSelecionada = situacao.options[situacao.selectedIndex].text.trim();

                                var atendente = document.getElementById("atendente");
                                var atenTitulo = document.getElementById("atenTitulo");

                                // Verifica se a opção selecionada é "Analise N3"
                                if (situacaoSelecionada === "Auxilio Suporte/Vendas") {
                                    multiplicaContainer.style.display = "block";
                                    atendente.style.display = "none";
                                    atendente.removeAttribute("required"); // Adiciona required quando visível
                                    atenTitulo.style.display = "none";
                                } else {
                                    multiplicaContainer.style.display = "none";
                                    document.getElementById("numeroMultiplicaContainer").style.display = "none";
                                    document.getElementById("chkMultiplica").checked = false;
                                }
                            }

                            function verificarMultiplica() {
                                var chkMultiplica = document.getElementById("chkMultiplica").checked;
                                var numeroMultiContainer = document.getElementById("numeroMultiContainer");
                                var numeroMulti = document.getElementById("numeroMulti");
                                
                                if (chkMultiplica) {
                                    numeroMultiContainer.style.display = "block";
                                    numeroMulti.setAttribute("required", "true"); // Adiciona required quando visível
                                } else {
                                    numeroMultiContainer.style.display = "none";
                                    numeroMulti.removeAttribute("required"); // Remove required quando oculto
                                    numeroMulti.value = ""; // Limpa o valor do campo
                                }
                            }

                            function marcaParado() {
                                var chkParado = document.getElementById("chkParado").checked;
                                var chkFicha = document.getElementById("chkFicha");

                                if (chkParado) {
                                    chkFicha.setAttribute("required", "true"); // Adiciona required quando marcado o Parado
                                } else {
                                    chkFicha.removeAttribute("required"); // Remove required quando não marcado o Parado
                                }
                            }
                            </script>
                        <div class="row mb-3"> 
                            <div>
                                <label for="just_nota" class="form-label">Justificativa Nota</label>
                                <textarea name="justificativa" id="just_nota" class="form-control" rows="2"></textarea>
                            </div>
                        </div>

                        <div class="row mb-3">    
                            <div class="col-md-6">
                                <label for="sistema" class="form-label">Sistema</label>
                                <select class="form-select" id="sistema" name="sistema" required>
                                    <option value="">Selecione</option>
                                    <?php
                                    $querySistema = "SELECT Id, Descricao FROM TB_SISTEMA ORDER BY Descricao ASC";
                                    $resultSistema = $conn->query($querySistema);
                                    while ($rowSi = $resultSistema->fetch_assoc()) {
                                        echo "<option value='" . $rowSi['Id'] . "'>" . $rowSi['Descricao'] . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="atendente" id="atenTitulo" class="form-label">Atendente</label>
                                <select class="form-select" id="atendente" name="atendente" required>
                                    <option value="">Selecione</option>
                                    <?php
                                    $queryAtendente = "SELECT Id, Nome FROM TB_USUARIO WHERE Cargo in ('User', 'Conversor', 'Treinamento', 'Viewer') ORDER BY Nome ASC";
                                    $resultAtendente = $conn->query($queryAtendente);
                                    while ($rowA = $resultAtendente->fetch_assoc()) {
                                        echo "<option value='" . $rowA['Id'] . "'>" . $rowA['Nome'] . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="">Selecione</option>
                                    <?php
                                    $queryStatus = "SELECT Id, Descricao FROM TB_STATUS";
                                    $resultStatus = $conn->query($queryStatus);
                                    while ($rowSt = $resultStatus->fetch_assoc()) {
                                        echo "<option value='" . $rowSt['Id'] . "'>" . $rowSt['Descricao'] . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="hora_ini" class="form-label">Hora Início</label>
                                <input type="datetime-local" class="form-control" id="hora_ini" name="hora_ini" required>
                            </div>
                            <div class="col-md-3">
                                <label for="hora_fim" class="form-label">Hora Fim</label>
                                <input type="datetime-local" class="form-control" id="hora_fim" name="hora_fim" required>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-success">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
 
    <!-- Modal de Edição -->
    <div class="modal fade" id="modalEdicao" tabindex="-1" aria-labelledby="modalEdicaoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
            <!-- Cabeçalho do Modal -->
            <div class="modal-header">
                <h5 class="modal-title" id="modalEdicaoLabel">Editar Análise</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- Corpo do Modal -->
            <div class="modal-body">
                <form action="Views/editar_analise.php" method="POST">
                    <!-- Campo oculto para o ID -->
                    <input type="hidden" id="id_editar" name="id_editar">
                    <!-- Linha de Descrição -->
                    <div class="row mb-1">
                        <div class="col-md-12 mb-2">
                            <label for="descricao_editar" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao_editar" name="descricao_editar" maxlength="100" required></textarea>
                        </div>
                    </div>
                    <!-- Linha com Nota e Situação -->
                    <div class="row mb-1">
                        <div class="col-md-6 mb-2">
                            <label for="nota_editar" id="notaAnalise_editar" class="form-label">Nota</label>
                            <select class="form-select" id="nota_editar" name="nota_editar">
                                <option value="0">0</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="situacao_editar" class="form-label">Situação</label>
                            <select class="form-select" id="situacao_editar" name="situacao_editar" required onchange="verificarSituacao_editar(); verificarSituacao2_editar();">
                                <option value="">Selecione</option>
                                <?php
                                $querySituacao2 = "SELECT Id, Descricao FROM TB_SITUACAO";
                                $resultSituacao2 = $conn->query($querySituacao2);
                                while ($rowS2 = $resultSituacao2->fetch_assoc()) {
                                echo "<option value='" . $rowS2['Id'] . "'>" . $rowS2['Descricao'] . "</option>";
                                }
                                ?>
                            </select>
                            <!-- Checkbox e campo de Número da Ficha (inicialmente ocultos) -->
                            <div class="row mt-3" id="fichaContainer_editar" style="display: none;">
                                <div class="row mb-3 mt-3">
                                <div class="form-check d-flex justify-content-center ms-1">
                                    <input class="form-check-input" type="checkbox" id="chkFicha_editar" name="chkFicha_editar" onchange="verificarFicha_editar()">
                                    <label class="form-check-label" for="chkFicha_editar">Ficha</label>
                                    <input class="form-check-input ms-2" type="checkbox" id="chkParado_editar" name="chkParado_editar" onchange="marcaParado_editar()">
                                    <label class="form-check-label" for="chkParado_editar">Cliente Parado</label>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3" id="numeroFichaContainer_editar" style="display: none;">
                            <div class="col-md-12">
                            <label for="numeroFicha_editar" class="form-label">Número da Ficha</label>
                            <input type="number" class="form-control" id="numeroFicha_editar" name="numeroFicha_editar" pattern="\d+">
                            </div>
                        </div>
                        <!-- Checkbox e campo de Quantidade para Replicar (inicialmente ocultos) -->
                        <div class="row mb-3 mt-3" id="multiplicaContainer_editar" style="display: none;">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkMultiplica_editar" name="chkMultiplica_editar" onchange="verificarMultiplica_editar()">
                                    <label class="form-check-label" for="chkMultiplica_editar">Replicar</label>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3" id="numeroMultiContainer_editar" style="display: none;">
                            <div class="col-md-12">
                                <label for="numeroMulti_editar" class="form-label">Quantidade para Replicar</label>
                                <input type="number" class="form-control" id="numeroMulti_editar" name="numeroMulti_editar" pattern="\d+">
                            </div>
                        </div>
                        </div>
                    </div>
                    <!-- Justificativa Nota -->
                    <div class="row mb-3"> 
                        <div>
                            <label for="just_nota_editar" class="form-label">Justificativa Nota</label>
                            <textarea name="just_nota_editar" id="just_nota_editar" class="form-control" maxlength="255" rows="2"></textarea>
                        </div>
                    </div>
                    <!-- Linha com Sistema e Atendente -->
                    <div class="row mb-3">    
                        <div class="col-md-6">
                            <label for="sistema_editar" class="form-label">Sistema</label>
                            <select class="form-select" id="sistema_editar" name="sistema_editar" required>
                                <option value="">Selecione</option>
                                <?php
                                $querySistema2 = "SELECT Id, Descricao FROM TB_SISTEMA ORDER BY Descricao ASC";
                                $resultSistema2 = $conn->query($querySistema2);
                                while ($rowSi2 = $resultSistema2->fetch_assoc()) {
                                echo "<option value='" . $rowSi2['Id'] . "'>" . $rowSi2['Descricao'] . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="atendente_editar" id="atenTitulo_editar" class="form-label">Atendente</label>
                            <select class="form-select" id="atendente_editar" name="atendente_editar" required>
                                <option value="">Selecione</option>
                                <?php
                                $queryAtendente2 = "SELECT Id, Nome FROM TB_USUARIO WHERE Cargo in ('User', 'Conversor', 'Treinamento', 'Viewer') ORDER BY Nome ASC";
                                $resultAtendente2 = $conn->query($queryAtendente2);
                                while ($rowA2 = $resultAtendente2->fetch_assoc()) {
                                echo "<option value='" . $rowA2['Id'] . "'>" . $rowA2['Nome'] . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <!-- Linha com Status, Hora Início e Hora Fim -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="status_editar" class="form-label">Status</label>
                            <select class="form-select" id="status_editar" name="status_editar" required>
                                <option value="">Selecione</option>
                                <?php
                                $queryStatus2 = "SELECT Id, Descricao FROM TB_STATUS";
                                $resultStatus2 = $conn->query($queryStatus2);
                                while ($rowSt2 = $resultStatus2->fetch_assoc()) {
                                echo "<option value='" . $rowSt2['Id'] . "'>" . $rowSt2['Descricao'] . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="hora_ini_editar" class="form-label">Hora Início</label>
                            <input type="datetime-local" class="form-control" id="hora_ini_editar" name="hora_ini_editar" required>
                        </div>
                        <div class="col-md-3">
                            <label for="hora_fim_editar" class="form-label">Hora Fim</label>
                            <input type="datetime-local" class="form-control" id="hora_fim_editar" name="hora_fim_editar" required>
                        </div>
                    </div>
                    <!-- Botão Salvar -->
                    <div class="text-end">
                        <button type="submit" class="btn btn-success">Salvar</button>
                    </div>
                </form>
            </div>
            <!-- Você precisará criar funções JavaScript similares às do modal de cadastro, mas com os sufixos _editar, para controlar a exibição dos campos -->
            </div>
        </div>
    </div>

    <!-- Modal de Exclusão -->
    <div class="modal fade" id="modalExclusao" tabindex="-1" aria-labelledby="modalExclusaoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalExclusaoLabel">Confirma a Exclusão da Análise?</h5>
                </div>
                <div class="modal-body">
                    <form action="Views/deletar_analise.php" method="POST">
                        <!-- Campo oculto para armazenar o ID da análise -->
                        <input type="hidden" id="id_excluir" name="id_excluir">
                        <p>Tem certeza que deseja excluir o usuário <strong id="excluir_nome"></strong>?</p>
                        <div class="text-end">
                            <button type="submit" class="btn btn-success">Sim</button>
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal" aria-label="Close">Não</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Filtro com Controle por Coluna -->
    <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
          <div class="modal-content">
            <form method="GET" action="index.php">
              <div class="modal-header">
                <h5 class="modal-title" id="filterModalLabel">Filtro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
              </div>
              <div class="modal-body">
                <!-- Seletor de Coluna para filtrar -->
                <div class="mb-3">
                  <label for="filterColumn" class="form-label">Filtrar por Coluna:</label>
                  <select class="form-select" id="filterColumn" name="filterColumn">
                    <option value="period" <?php if(isset($_GET['filterColumn']) && $_GET['filterColumn'] == 'period') echo "selected"; ?>>Período</option>
                    <option value="analista" <?php if(isset($_GET['filterColumn']) && $_GET['filterColumn'] == 'analista') echo "selected"; ?>>Analista</option>
                    <option value="situacao" <?php if(isset($_GET['filterColumn']) && $_GET['filterColumn'] == 'situacao') echo "selected"; ?>>Situação</option>
                    <option value="sistema" <?php if(isset($_GET['filterColumn']) && $_GET['filterColumn'] == 'sistema') echo "selected"; ?>>Sistema</option>
                    <option value="status" <?php if(isset($_GET['filterColumn']) && $_GET['filterColumn'] == 'status') echo "selected"; ?>>Status</option>
                  </select>
                </div>
                <!-- Campos de filtro, exibidos conforme a seleção -->
                <div id="filterPeriod" style="display: none;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="data_inicio" class="form-label">Data Início:</label>
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo isset($_GET['data_inicio']) ? $_GET['data_inicio'] : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="data_fim" class="form-label">Data Fim:</label>
                            <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo isset($_GET['data_fim']) ? $_GET['data_fim'] : ''; ?>">
                        </div>
                    </div>
                </div>
                <div id="filterAnalista" style="display: none;">
                  <div class="mb-3">
                    <label for="analista" class="form-label">Analista:</label>
                    <select class="form-select" id="analista" name="analista">
                      <option value="">Selecione</option>
                      <?php while ($row = $resultado_usuarios_dropdown->fetch_assoc()) { ?>
                        <option value="<?php echo $row['Id']; ?>" <?php if(isset($_GET['analista']) && $_GET['analista'] == $row['Id']) echo "selected"; ?>><?php echo $row['Nome']; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                </div>
                <div id="filterSituacao" style="display: none;">
                  <div class="mb-3">
                    <label for="situacao" class="form-label">Situação:</label>
                    <select class="form-select" id="situacao" name="situacao">
                      <option value="">Selecione</option>
                      <?php while ($row = $lista_situacoes->fetch_assoc()) { ?>
                        <option value="<?php echo $row['Id']; ?>" <?php if(isset($_GET['situacao']) && $_GET['situacao'] == $row['Id']) echo "selected"; ?>><?php echo $row['Descricao']; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                </div>
                <div id="filterSistema" style="display: none;">
                  <div class="mb-3">
                    <label for="sistema" class="form-label">Sistema:</label>
                    <select class="form-select" id="sistema" name="sistema">
                      <option value="">Selecione</option>
                      <?php while ($row = $lista_sistemas->fetch_assoc()) { ?>
                        <option value="<?php echo $row['Id']; ?>" <?php if(isset($_GET['sistema']) && $_GET['sistema'] == $row['Id']) echo "selected"; ?>><?php echo $row['Descricao']; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                </div>
                <div id="filterStatus" style="display: none;">
                  <div class="mb-3">
                    <label for="status" class="form-label">Status:</label>
                    <select class="form-select" id="status" name="status">
                      <option value="">Selecione</option>
                      <?php while ($row = $lista_status->fetch_assoc()) { ?>
                        <option value="<?php echo $row['Id']; ?>" <?php if(isset($_GET['status']) && $_GET['status'] == $row['Id']) echo "selected"; ?>><?php echo $row['Descricao']; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">Limpar Filtro</button>
                <button type="submit" class="btn btn-primary">Filtrar</button>
              </div>
              <input type="hidden" name="filterColumn" id="filterColumnHidden">
            </form>
          </div>
        </div>
      </div>
 
      <script>
            // Exibe o campo de filtro correspondente conforme a seleção do "Coluna"
            function adjustFilterFields() {
                let filterColumn = document.getElementById("filterColumn").value;
                // Atualiza um campo hidden, se necessário para o backend (opcional)
                document.getElementById("filterColumnHidden").value = filterColumn;
                // Esconde todos os containers
                document.getElementById("filterPeriod").style.display = "none";
                document.getElementById("filterAnalista").style.display = "none";
                document.getElementById("filterSituacao").style.display = "none";
                document.getElementById("filterSistema").style.display = "none";
                document.getElementById("filterStatus").style.display = "none";
                // Exibe apenas o container selecionado
                if (filterColumn === "period") {
                    document.getElementById("filterPeriod").style.display = "block";
                } else if (filterColumn === "analista") {
                    document.getElementById("filterAnalista").style.display = "block";
                } else if (filterColumn === "situacao") {
                    document.getElementById("filterSituacao").style.display = "block";
                } else if (filterColumn === "sistema") {
                    document.getElementById("filterSistema").style.display = "block";
                } else if (filterColumn === "status") {
                    document.getElementById("filterStatus").style.display = "block";
                }
            }
            document.addEventListener("DOMContentLoaded", function() {
                adjustFilterFields();
                document.getElementById("filterColumn").addEventListener("change", adjustFilterFields);
            });
            </script>

    <!-- Scripts adicionais -->
    <script>
        // Função para preencher o modal de edição
        function editarAnalise(id, descricao, idSituacao, idAtendente, idSistema, idStatus, hora_ini, hora_fim, nota_editar, just_nota_editar) {
            document.getElementById("id_editar").value = id;
            document.getElementById("descricao_editar").value = descricao;
            document.getElementById("situacao_editar").value = idSituacao;
            document.getElementById("atendente_editar").value = idAtendente;
            document.getElementById("sistema_editar").value = idSistema;
            document.getElementById("status_editar").value = idStatus;
            document.getElementById("hora_ini_editar").value = hora_ini;
            document.getElementById("hora_fim_editar").value = hora_fim;
            document.getElementById("nota_editar").value = nota_editar;
            document.getElementById("just_nota_editar").value = just_nota_editar;
        }
 
        // Função para preencher o modal de exclusão
        function excluirAnalise(id) {
            document.getElementById("id_excluir").value = id;
        }
    </script>
 
 <!-- Script para alterar as cores do status da tabela -->
 <script>
      document.addEventListener("DOMContentLoaded", function () {
          document.querySelectorAll("#tabelaAnalises tbody tr").forEach(row => {
              let statusCell = row.cells[4];
              let status = statusCell.textContent.trim();
              statusCell.classList.remove("pastel-aguardando", "pastel-desenvolvimento", "pastel-resolvido");
              switch (status) {
                  case "Aguardando":
                      statusCell.classList.add("pastel-aguardando");
                      break;
                  case "Desenvolvimento":
                      statusCell.classList.add("pastel-desenvolvimento");
                      break;
                  case "Resolvido":
                      statusCell.classList.add("pastel-resolvido");
                      break;
              }
          });
      });
  </script>
  
  <!-- Chart.js inicialização (apenas UMA instância!) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  document.getElementById('tab-relatorios')
    .addEventListener('shown.bs.tab', () => {
      const ctx = document.getElementById('chartRelatorios').getContext('2d');
      // se já existir, destrói:
      if (window._relatorioChart) window._relatorioChart.destroy();
      window._relatorioChart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'],
          datasets: [
            { label: 'Fichas',      data: <?= json_encode(array_values($fichasPorMes)); ?> },
            { label: 'Análises N3',  data: <?= json_encode(array_values($analisesN3PorMes)); ?> },
            { label: 'Parados',      data: <?= json_encode(array_values($clienteParadoPorMes)); ?> }
          ]
        },
        options: {
          scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
          }
        }
      });
    });
</script>



    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- Script para pesquisar na tabela -->
    <script>
    $(document).ready(function(){
        $("#searchInput").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#tabelaAnalises tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });
        });
    </script>
</body>
</html>
