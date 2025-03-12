<?php
include '../Config/Database.php';

session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definir o cargo do usuário (supondo que ele esteja armazenado na sessão, com a chave "Cargo")
$usuario_id = $_SESSION['usuario_id'];
$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';

/****************************************************************
 * 1) Capturar Filtros (GET)
 ****************************************************************/
$dataInicial = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : '';
$dataFinal   = isset($_GET['data_final'])   ? $_GET['data_final']   : '';
$analistaID  = isset($_GET['analista_id'])  ? intval($_GET['analista_id']) : 0;

/****************************************************************
 * 2) Montar WHERE Dinâmico
 ****************************************************************/
$where = " WHERE 1=1 ";
if (!empty($dataInicial)) {
    $where .= " AND c.data_recebido >= '{$dataInicial} 00:00:00' ";
}
if (!empty($dataFinal)) {
    $where .= " AND c.data_recebido <= '{$dataFinal} 23:59:59' ";
}
if ($analistaID > 0) {
    $where .= " AND c.analista_id = {$analistaID} ";
}

/****************************************************************
 * 3) Dados do Gráfico (Mês x Analista)
 ****************************************************************/
$sqlGrafico = "
    SELECT 
        YEAR(c.data_recebido) AS ano,
        MONTH(c.data_recebido) AS mes,
        a.nome                AS analista_nome,
        COUNT(*)             AS total
      FROM TB_CONVERSOES c
      JOIN TB_ANALISTA_CONVER a ON c.analista_id = a.id
      $where
      AND c.status_id <> 5
      GROUP BY YEAR(c.data_recebido), MONTH(c.data_recebido), c.analista_id
      ORDER BY ano, mes, analista_nome
";
$resGraf = $conn->query($sqlGrafico);

$dataPorMesAnalista = [];
$analistasDistinct  = [];
while ($rowG = $resGraf->fetch_assoc()) {
    $ano  = $rowG['ano'];
    $mes  = $rowG['mes'];
    $anal = $rowG['analista_nome'];
    $tot  = $rowG['total'];

    $rotuloMes = sprintf("%04d-%02d", $ano, $mes);
    if (!isset($dataPorMesAnalista[$rotuloMes])) {
        $dataPorMesAnalista[$rotuloMes] = [];
    }
    $dataPorMesAnalista[$rotuloMes][$anal] = $tot;
    $analistasDistinct[$anal] = true;
}
$labelsMes = array_keys($dataPorMesAnalista);
sort($labelsMes);
$listaAnalistas = array_keys($analistasDistinct);
sort($listaAnalistas);

// Montar datasets p/ Chart.js
$chartDatasets = [];
$cores = ["#d9534f","#5bc0de","#5cb85c","#f0ad4e","#0275d8","#292b2c","#7f7f7f"];
$corIndex = 0;
foreach ($listaAnalistas as $anal) {
    $dataVals = [];
    foreach ($labelsMes as $m) {
        $val = isset($dataPorMesAnalista[$m][$anal]) ? $dataPorMesAnalista[$m][$anal] : 0;
        $dataVals[] = $val;
    }
    $chartDatasets[] = [
        'label' => $anal,
        'backgroundColor' => $cores[$corIndex % count($cores)],
        'data' => $dataVals
    ];
    $corIndex++;
}

/****************************************************************
 * 4) TOTALIZADORES GERAIS (Quantidade, Tempo Médio, Tempo Conversão)
 ****************************************************************/
$sqlQtd = "
    SELECT COUNT(*)
      FROM TB_CONVERSOES c
      $where
      AND c.status_id <> 5
";
$total_conversoes = $conn->query($sqlQtd)->fetch_row()[0] ?? 0;

$sqlTempoRet = "
    SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(tempo_total)))
      FROM TB_CONVERSOES c
      $where
      AND status_id = 1
";
$tempo_medio_ret = $conn->query($sqlTempoRet)->fetch_row()[0] ?? 'N/A';
$tempo_medio_ret = substr($tempo_medio_ret, 0, 8); // Pega apenas "HH:MM:SS"

$sqlTempoConv = "
    SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(tempo_conver)))
      FROM TB_CONVERSOES c
      $where
      AND status_id = 1
";
$tempo_medio_conv = $conn->query($sqlTempoConv)->fetch_row()[0] ?? 'N/A';
$tempo_medio_conv = substr($tempo_medio_conv, 0, 8); // Pega apenas "HH:MM:SS"

// Total de conversões concluídas (status "Concluído")
$sqlTotalConcluidas = "
    SELECT COUNT(*) 
      FROM TB_CONVERSOES c
      JOIN TB_STATUS_CONVER st ON c.status_id = st.id
      $where
      AND st.descricao = 'Concluído'
";
$totalConcluidas = $conn->query($sqlTotalConcluidas)->fetch_row()[0] ?? 0;


/****************************************************************
 * 5) Totalizadores por Status
 ****************************************************************/
$sqlStatusTot = "
    SELECT st.descricao AS status_nome,
           COUNT(*)     AS total
      FROM TB_CONVERSOES c
      JOIN TB_STATUS_CONVER st ON c.status_id = st.id
      $where
      GROUP BY c.status_id
      ORDER BY st.descricao
";
$resStatusTot = $conn->query($sqlStatusTot);

/****************************************************************
 * 6) Totalizadores por Sistema
 ****************************************************************/
$sqlSistemaTot = "
    SELECT TRIM(SUBSTRING_INDEX(s.nome, '/', -1)) AS sistema_exibicao,
           COUNT(*) AS total
      FROM TB_CONVERSOES c
      JOIN TB_SISTEMA_CONVER s ON c.sistema_id = s.id
      $where
      AND c.status_id <> 5
      GROUP BY TRIM(SUBSTRING_INDEX(s.nome, '/', -1))
      ORDER BY sistema_exibicao
";
$resSistemaTot = $conn->query($sqlSistemaTot);

// NOVA QUERY: Conversões que ainda podem ser entregues hoje (dentro do prazo)
$sqlDentroPrazo = "
    SELECT COUNT(*) 
      FROM TB_CONVERSOES c
      JOIN TB_STATUS_CONVER st ON c.status_id = st.id
      $where
      AND st.descricao NOT IN ('Concluído','Cancelada')
      AND NOW() < 
          CASE 
            WHEN TIME(c.data_recebido) < '15:00:00'
              THEN CONCAT(DATE(c.data_recebido), ' 15:00:00')
            ELSE CONCAT(DATE(c.data_recebido + INTERVAL 1 DAY), ' 15:00:00')
          END
";
$countDentroPrazo = $conn->query($sqlDentroPrazo)->fetch_row()[0] ?? 0;


// NOVA QUERY: Conversões atrasadas (já passaram do prazo)
// Considera somente status: Em fila, Analise, Dar prioridade
$sqlAtrasadas = "
    SELECT COUNT(*) 
      FROM TB_CONVERSOES c
      JOIN TB_STATUS_CONVER st ON c.status_id = st.id
      $where
      AND st.descricao IN ('Em fila','Analise','Dar prioridade')
      AND (
           -- Se a conversão foi recebida antes das 15:00, o prazo é até as 15:00 do mesmo dia.
           (TIME(c.data_recebido) < '15:00:00' AND NOW() >= CONCAT(DATE(c.data_recebido), ' 15:00:00'))
           OR
           -- Se a conversão foi recebida às 15:00 ou depois, o prazo é até as 15:00 do dia seguinte.
           (TIME(c.data_recebido) >= '15:00:00' AND NOW() >= CONCAT(DATE(c.data_recebido + INTERVAL 1 DAY), ' 15:00:00'))
      )
";
$countAtrasadas = $conn->query($sqlAtrasadas)->fetch_row()[0] ?? 0;



// Totalizador: Meta não batida (Concluídas, recebidas antes das 15:00 e concluídas em dia diferente)
$sqlMetaNaoBatida = "
    SELECT COUNT(*) 
      FROM TB_CONVERSOES c
      JOIN TB_STATUS_CONVER st ON c.status_id = st.id
      $where
      AND st.descricao = 'Concluído'
      AND TIME(c.data_recebido) < '15:00:00'
      AND DATE(c.data_conclusao) <> DATE(c.data_recebido)
";
$countMetaNaoBatida = $conn->query($sqlMetaNaoBatida)->fetch_row()[0] ?? 0;

// Totalizador: Conversões concluídas no prazo (meta batida)
$sqlMetaBatida = "
    SELECT COUNT(*) 
      FROM TB_CONVERSOES c
      JOIN TB_STATUS_CONVER st ON c.status_id = st.id
      $where
      AND st.descricao = 'Concluído'
      AND (
           (TIME(c.data_recebido) < '15:00:00' 
             AND DATE(c.data_conclusao) = DATE(c.data_recebido))
           OR
           (TIME(c.data_recebido) >= '15:00:00' 
             AND c.data_conclusao < CONCAT(DATE(c.data_recebido + INTERVAL 1 DAY), ' 15:00:00'))
      )
";

$countMetaBatida = $conn->query($sqlMetaBatida)->fetch_row()[0] ?? 0;

$percentAtendimento = $totalConcluidas > 0 ? ($countMetaBatida / $totalConcluidas) * 100 : 0;

// Calcular a porcentagem de atendimento (meta)
// Supondo que $percentAtendimento já esteja calculado
$meta = round($percentAtendimento, 2);

// Definir a cor com base no valor da meta
if ($meta < 90) {
    // Abaixo da meta
    $metaColor = "#FF746C"; // vermelho suave
} elseif ($meta >= 90 && $meta <= 94) {
    // Dentro do esperado
    $metaColor = "#FFDB58"; // amarelo suave
} else { // $meta >= 95
    // Acima do esperado
    $metaColor = "#00674F"; // verde suave
}
/****************************************************************
 * 7) 
 * Dividir a listagem em duas:
 *  - TABELA 1: status = 'Em fila'
 *  - TABELA 2: status != 'Em fila'
 * Precisamos saber qual ID ou descricao corresponde a Em fila.
 * Aqui, assumimos st.descricao = 'Em fila'.
 ****************************************************************/
// Tabela da esquerda: status = 'Em fila'
$sqlFila = "SELECT 
                  c.id,
                  c.contato,
                  c.serial,
                  c.sistema_id,
                  s.nome       AS sistema_nome,
                  c.status_id,
                  st.descricao AS status_nome,
                  c.prazo_entrega,
                  c.data_recebido,
                  c.data_inicio,
                  c.data_conclusao,
                  DATE_FORMAT(c.prazo_entrega, '%d/%m %H:%i:%s') as prazo_entrega2,
                  DATE_FORMAT(c.data_recebido, '%d/%m %H:%i:%s') as data_recebido2,
                  DATE_FORMAT(c.data_inicio, '%d/%m %H:%i:%s') as data_inicio2,
                  DATE_FORMAT(c.data_conclusao, '%d/%m %H:%i:%s') as data_conclusao2,
                  c.analista_id,
                  a.nome       AS analista_nome,
                  c.retrabalho,
                  c.observacao
              FROM TB_CONVERSOES c
              JOIN TB_SISTEMA_CONVER s  ON c.sistema_id  = s.id
              JOIN TB_STATUS_CONVER st  ON c.status_id   = st.id
              JOIN TB_ANALISTA_CONVER a ON c.analista_id = a.id
              $where
                AND st.descricao = 'Em fila'
            ORDER BY c.data_recebido ASC";
$resFila = $conn->query($sqlFila);

// Tabela da direita: status != 'Em fila'
$sqlOutros = "SELECT 
                  c.id,
                  c.contato,
                  c.serial,
                  c.sistema_id,
                  s.nome       AS sistema_nome,
                  c.status_id,
                  st.descricao AS status_nome,
                  c.prazo_entrega,
                  c.data_recebido,
                  c.data_inicio,
                  c.data_conclusao,
                  DATE_FORMAT(c.prazo_entrega, '%d/%m %H:%i:%s') as prazo_entrega2,
                  DATE_FORMAT(c.data_recebido, '%d/%m %H:%i:%s') as data_recebido2,
                  DATE_FORMAT(c.data_inicio, '%d/%m %H:%i:%s') as data_inicio2,
                  DATE_FORMAT(c.data_conclusao, '%d/%m %H:%i:%s') as data_conclusao2,
                  c.analista_id,
                  a.nome       AS analista_nome,
                  c.retrabalho,
                  c.observacao
                FROM TB_CONVERSOES c
                JOIN TB_SISTEMA_CONVER s  ON c.sistema_id  = s.id
                JOIN TB_STATUS_CONVER st  ON c.status_id   = st.id
                JOIN TB_ANALISTA_CONVER a ON c.analista_id = a.id
                $where
                  AND st.descricao not in ('Em fila', 'Concluido', 'Cancelada')
              ORDER BY c.data_recebido ASC";
$resOutros = $conn->query($sqlOutros);

$sqlFinalizados = "SELECT 
                      c.id,
                      c.contato,
                      c.serial,
                      c.sistema_id,
                      s.nome       AS sistema_nome,
                      c.status_id,
                      st.descricao AS status_nome,
                      c.prazo_entrega,
                      c.data_recebido,
                      c.data_inicio,
                      c.data_conclusao,
                      DATE_FORMAT(c.prazo_entrega, '%d/%m %H:%i:%s') as prazo_entrega2,
                      DATE_FORMAT(c.data_recebido, '%d/%m %H:%i:%s') as data_recebido2,
                      DATE_FORMAT(c.data_inicio, '%d/%m %H:%i:%s') as data_inicio2,
                      DATE_FORMAT(c.data_conclusao, '%d/%m %H:%i:%s') as data_conclusao2,
                      c.analista_id,
                      a.nome       AS analista_nome,
                      c.retrabalho,
                      c.observacao
                    FROM TB_CONVERSOES c
                    JOIN TB_SISTEMA_CONVER s  ON c.sistema_id  = s.id
                    JOIN TB_STATUS_CONVER st  ON c.status_id   = st.id
                    JOIN TB_ANALISTA_CONVER a ON c.analista_id = a.id
                    $where
                      AND st.descricao in ('Concluido', 'Cancelada')
                  ORDER BY c.data_conclusao DESC";
$resFinalizados = $conn->query($sqlFinalizados);

/****************************************************************
 * 8) Carregar listas p/ selects
 ****************************************************************/
$sistemas  = $conn->query("SELECT * FROM TB_SISTEMA_CONVER ORDER BY nome");
$status    = $conn->query("SELECT * FROM TB_STATUS_CONVER ORDER BY descricao");
$analistas = $conn->query("SELECT * FROM TB_ANALISTA_CONVER ORDER BY nome");
// Para o filtro:
$analistasFiltro = $conn->query("SELECT * FROM TB_ANALISTA_CONVER ORDER BY nome");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Gerenciar Conversões</title>
  <!-- CSS externo minimalista -->
  <link rel="stylesheet" href="../Public/conversao.css">
  <!-- Ícones personalizados -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">

  <link rel="icon" href="..\Public\Image\icone2.png" type="image/png">
  
</head>
<body>

<nav class="navbar navbar-dark bg-dark">
    <div class="container d-flex justify-content-between align-items-center">
        <!-- Botão Hamburguer com Dropdown -->
        <div class="dropdown">
            <button class="navbar-toggler" type="button" id="menuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="navbar-toggler-icon"></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="menuDropdown">
              <?php if ($cargo === 'Conversor'): ?>  <!-- Verifica o cargo do usuário -->
                <li><a class="dropdown-item" href="user.php">Analises</a></li>
                <?php endif; ?>
              <?php if ($cargo === 'Admin'): ?>  <!-- Verifica o cargo do usuário -->
                <li><a class="dropdown-item" href="../index.php">Painel N3</a></li>
                <li><a class="dropdown-item" href="dashboard.php">Totalizadores</a></li>
              <?php endif; ?>
            </ul>
        </div>
        <span class="text-white">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
        <a href="logout.php" class="btn btn-danger">
            <i class="fa-solid fa-right-from-bracket me-2" style="font-size: 0.8em;"></i>Sair
        </a>
    </div>
</nav>

<!-- Container do Toast no canto superior direito -->
<div class="toast-container">
    <div id="toastSucesso" class="toast">
        <div class="toast-body">
            <i class="fa-solid fa-check-circle"></i> <span id="toastMensagem"></span>
        </div>
    </div>
</div>

<script>
//Toast para mensagem de sucesso
document.addEventListener("DOMContentLoaded", function () {
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get("success");

        if (success) {
            let mensagem = "";
            switch (success) {
                case "1":
                    mensagem = "Conversão cadastrada com sucesso!";
                    break;
                case "2":
                    mensagem = "Conversão editada com sucesso!";
                    break;
                case "3":
                    mensagem = "Conversão excluída com sucesso!";
                    break;
            }
            if (mensagem) {
                document.getElementById("toastMensagem").textContent = mensagem;
                var toastEl = document.getElementById("toastSucesso");
                var toast = new bootstrap.Toast(toastEl, { delay: 2200 });
                toast.show();
            }
        }
    });
</script> 
</head>
<body>
<div class="container mt-4">
  <h1 class="text-center mb-4">Gerenciar Conversões</h1>

  <!-- Linha 1: Gráfico e Filtro Global na mesma linha -->
<div class="row mb-4">
  <!-- Gráfico à esquerda (8 colunas) -->
  <div class="col-md-8">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Conversões Mensais por Analista</h5>
        <canvas id="chartBarras" height="100"></canvas>
      </div>
    </div>
  </div>
  <!-- Filtro Global à direita (4 colunas) -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Filtro Global</h5>
        <form method="GET" class="row gy-2 gx-2">
          <div class="col-12">
            <label>Data Inicial</label>
            <input type="date" name="data_inicial" value="<?= htmlspecialchars($dataInicial) ?>" class="form-control">
          </div>
          <div class="col-12">
            <label>Data Final</label>
            <input type="date" name="data_final" value="<?= htmlspecialchars($dataFinal) ?>" class="form-control">
          </div>
          <div class="col-12">
            <label>Analista</label>
            <select name="analista_id" class="form-select">
              <option value="0">-- Todos --</option>
              <?php while ($anF = $analistasFiltro->fetch_assoc()): ?>
              <option value="<?= $anF['id'] ?>" <?= ($analistaID == $anF['id']) ? 'selected' : '' ?>>
                <?= $anF['nome'] ?>
              </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="d-flex justify-content-center gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                <a href="conversao.php" class="btn btn-secondary btn-sm">Limpar Filtros</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Linha 2: Três Totalizadores lado a lado -->
<div class="row mb-4">
  <!-- Conversões Pendentes para Entrega Hoje -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-body">
         <h5 class="card-title">Conversões Pendentes</h5>
         <ul class="list-group">
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Ainda dentro do prazo
              <span class="badge bg-info rounded-pill"><?= $countDentroPrazo; ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Atrasadas (Em fila, Analise, Dar prioridade)
              <span class="badge bg-warning rounded-pill"><?= $countAtrasadas; ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Meta não batida (Concluídas com prazo não cumprido)
              <span class="badge bg-danger rounded-pill"><?= $countMetaNaoBatida; ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Concluídas no prazo (Meta Batida)
              <span class="badge bg-success rounded-pill"><?= $countMetaBatida; ?></span>
            </li>
         </ul>
      </div>
    </div>
  </div>
  <!-- Conversões por Status -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Conversões por Status</h5>
        <ul class="list-group">
          <?php while ($rowSt = $resStatusTot->fetch_assoc()): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <?= $rowSt['status_nome'] ?>
            <span class="badge bg-primary rounded-pill"><?= $rowSt['total'] ?></span>
          </li>
          <?php endwhile; ?>
        </ul>
      </div>
    </div>
  </div>
  <!-- Conversões por Sistema -->
  <div class="col-md-4">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Conversões por Sistema</h5>
        <ul class="list-group">
          <?php while ($rowSys = $resSistemaTot->fetch_assoc()): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <?= $rowSys['sistema_exibicao'] ?>
            <span class="badge bg-secondary rounded-pill"><?= $rowSys['total'] ?></span>
          </li>
          <?php endwhile; ?>
        </ul>
      </div>
    </div>
  </div>
</div>


<!-- TOTALIZADORES GERAIS (Total de Conversões, Atingimento da Meta e Tempo Médio) -->
<div class="row g-3 mb-3 card-total">
  <!-- Total de Conversões -->
  <div class="col-md-4">
    <div class="card text-white" style="background-color:rgb(120, 157, 184);"> 
      <!-- Cor suave azul -->
      <div class="card-body text-center">
        <h5 class="card-title">Total de Conversões</h5>
        <h3 class="card-text"><?= $total_conversoes; ?></h3>
      </div>
    </div>
  </div>
  <!-- Atingimento da Meta -->
  <div class="col-md-4">
    <div class="card text-white" style="background-color: <?= $metaColor; ?>;">
      <div class="card-body text-center">
        <span data-bs-toggle="tooltip" data-bs-html="true" title="🟩 Acima de 95%  <br>🟨 Entre 90% e 94%  <br>🟥 Abaixo de 90%">
          <h5 class="card-title">Atingimento da Meta</h5>
          <h3 class="card-text"><?= $meta; ?>%</h3>
        </span>
      </div>
    </div>
  </div>
  <!-- Tempo Médio -->
  <div class="col-md-4">
    <div class="card text-white" style="background-color:rgba(91, 41, 170, 0.67);"> 
      <div class="card-body text-center d-flex p-0">
        <div class="card-body text-center">
          <span data-bs-toggle="tooltip" title="Data Conclusão - Data Recebido">
          <h5 class="card-title">Tempo Entrega</h5>
          <h3 class="card-text"><?= $tempo_medio_ret; ?></h3>
        </div>
        <div class="card-body text-center">
          <span data-bs-toggle="tooltip" title="Data Conclusão - Data Inicio">
            <h5 class="card-title">Tempo Conversão</h5>
            <h3 class="card-text"><?= $tempo_medio_conv; ?></h3>
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Código para exibir e remover a mensagem/aviso -->
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  });
</script>

  <!-- Botão Cadastrar -->
  <div class="d-flex justify-content-end mb-3 gap-2">
    <input type="text" id="searchInput" class="form-control ms-2" style="max-width: 200px;" placeholder="Pesquisar...">
    <button class="btn btn-primary" onclick="abrirModalCadastro()">Cadastrar</button>
  </div>
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


  <!-- DUAS TABELAS: ESQUERDA = Fila, DIREITA = Outras -->
  <div class="row">
    <!-- TABELA 1: Em fila -->
    <div class="col-md-6 mb-3">
      <div class="card">
        <div class="card-header">
          <strong class="fila">Em Fila<i class="fa-solid fa-arrows-rotate"></i></strong> <!-- status='Em fila' -->
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0 tabelaEstilizada">
              <thead class="table-light">
                <tr>
                  <th>Contato</th>
                  <th>Sistema</th>
                  <th>Recebido</th>
                  <th>Prazo</th>
                  <th>Início</th>
                  <th>Analista</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($rowF = $resFila->fetch_assoc()): ?>
                <tr>
                  <td class="contato"><?= $rowF['contato']; ?></td>
                  <td><?= $rowF['sistema_nome']; ?></td>
                  <td><?= $rowF['data_recebido2']; ?></td>
                  <td><?= $rowF['prazo_entrega2']; ?></td>
                  <td><?= $rowF['data_inicio2']; ?></td>
                  <td><?= $rowF['analista_nome']; ?></td>
                  <td>
                                  <?php if ($cargo === 'Admin' || $usuario_id == $rowF['analista_id']): ?>
                  <a class="btn btn-outline-primary btn-sm"
                    onclick="abrirModalEdicao(
                      '<?= $rowF['id'] ?>',
                      '<?= $rowF['contato'] ?>',
                      '<?= $rowF['serial'] ?>',
                      '<?= $rowF['retrabalho'] ?>',
                      '<?= $rowF['sistema_id'] ?>',
                      '<?= $rowF['status_id'] ?>',
                      '<?= $rowF['data_recebido'] ?>',
                      '<?= $rowF['prazo_entrega'] ?>',
                      '<?= $rowF['data_inicio'] ?>',
                      '<?= $rowF['data_conclusao'] ?>',
                      '<?= $rowF['analista_id'] ?>',
                      '<?= addslashes($rowF['observacao']) ?>'
                    )">
                    <i class='fa-sharp fa-solid fa-pen'></i>
                  </a>
                  <a href="javascript:void(0)" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalExclusao" onclick="excluirAnalise(<?= $rowF['id'] ?>)">
                    <i class="fa-sharp fa-solid fa-trash"></i>
                  </a>
                <?php endif; ?>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div><!-- table-responsive -->
        </div><!-- card-body -->
      </div><!-- card -->
    </div><!-- col-md-6 -->

    <!-- TABELA 2: Demais status (<> Em fila) -->
<div class="col-md-6 mb-3">
  <div class="card">
    <div class="card-header">
      <strong class="outras">Em Andamento <i class="fa-solid fa-gears"></i></strong>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table id="tabelaOutras" class="table table-striped table-bordered mb-0 tabelaEstilizada">
          <thead class="table-light">
            <tr>
              <th style="width:1%">Contato</th>
              <th>Sistema</th>
              <th>Status</th>
              <th>Recebido</th>
              <th>Início</th>
              <th>Analista</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($rowO = $resOutros->fetch_assoc()): ?>
            <tr>
              <td class="contato"><?= $rowO['contato']; ?></td>
              <td><?= $rowO['sistema_nome']; ?></td>
              <td><?= $rowO['status_nome']; ?></td>
              <td><?= $rowO['data_recebido2']; ?></td>
              <td><?= $rowO['data_inicio2']; ?></td>
              <td><?= $rowO['analista_nome']; ?></td>
              <td>
                <?php if ($cargo === 'Admin' || $rowO['analista_id'] == $usuario_id): ?>
                  <a class="btn btn-outline-primary btn-sm"
                    onclick="abrirModalEdicao(
                      '<?= $rowO['id'] ?>',
                      '<?= $rowO['contato'] ?>',
                      '<?= $rowO['serial'] ?>',
                      '<?= $rowO['retrabalho'] ?>',
                      '<?= $rowO['sistema_id'] ?>',
                      '<?= $rowO['status_id'] ?>',
                      '<?= $rowO['data_recebido'] ?>',
                      '<?= $rowO['prazo_entrega'] ?>',
                      '<?= $rowO['data_inicio'] ?>',
                      '<?= $rowO['data_conclusao'] ?>',
                      '<?= $rowO['analista_id'] ?>',
                      '<?= addslashes($rowO['observacao']) ?>'
                    )">
                    <i class='fa-sharp fa-solid fa-pen'></i>
                  </a>
                  <a href="javascript:void(0)" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalExclusao" onclick="excluirAnalise(<?= $rowO['id'] ?>)">
                    <i class='fa-sharp fa-solid fa-trash'></i>
                  </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div><!-- table-responsive -->
    </div><!-- card-body -->
  </div><!-- card -->
</div><!-- col-md-6 -->


  <!-- Controle de cores da tabela de Outras -->
<script>
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll("#tabelaOutras tbody tr").forEach(row => {
    let statusCell = row.cells[2]; // 5ª coluna (índice 4)
    let status_id = statusCell.textContent.trim();
    // Remove classes de cores anteriores, se houver
    statusCell.classList.remove("pastel-alerta");
    // Aplica as classes com as novas cores:
    switch (status_id) {
      case "Dar prioridade":
        statusCell.classList.add("pastel-alerta");
        break;
    }
  });
});
</script>

  <!--TABELA DE FINALIZADOS-->
  <div class="col-md-12 mb-3">
      <div class="card">
        <div class="card-header">
          <strong class="finalizado">Finalizadas <i class="fa-solid fa-check-circle"></i></strong>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table id="tabelaFinalizados" class="table table-striped table-bordered mb-0 tabelaEstilizada">
              <thead class="table-light">
                <tr>
                  <th>Contato</th>
                  <th>Serial</th>
                  <th>Retrabalho</th>
                  <th>Sistema</th>
                  <th>Status</th>
                  <th>Recebido</th>
                  <th>Prazo</th>
                  <th>Início</th>
                  <th>Conclusão</th>
                  <th>Analista</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($rowC = $resFinalizados->fetch_assoc()): ?>
                <tr>
                  <td><?= $rowC['contato']; ?></td>
                  <td><?= $rowC['serial']; ?></td>
                  <td><?= $rowC['retrabalho']; ?></td>
                  <td><?= $rowC['sistema_nome']; ?></td>
                  <td><?= $rowC['status_nome']; ?></td>
                  <td><?= $rowC['data_recebido2']; ?></td>
                  <td><?= $rowC['prazo_entrega2']; ?></td>
                  <td><?= $rowC['data_inicio2']; ?></td>
                  <td><?= $rowC['data_conclusao2']; ?></td>
                  <td><?= $rowC['analista_nome']; ?></td>
                  <td>
                      <?php if ($cargo === 'Admin' || $rowC['analista_id'] == $usuario_id): ?>
                        <a class="btn btn-outline-primary btn-sm"
                          onclick="abrirModalEdicaoFinal(
                            '<?= $rowC['id'] ?>',
                            '<?= $rowC['contato'] ?>',
                            '<?= $rowC['serial'] ?>',
                            '<?= $rowC['retrabalho'] ?>',
                            '<?= $rowC['sistema_id'] ?>',
                            '<?= $rowC['status_id'] ?>',
                            '<?= $rowC['data_recebido'] ?>',
                            '<?= $rowC['prazo_entrega'] ?>',
                            '<?= $rowC['data_inicio'] ?>',
                            '<?= $rowC['data_conclusao'] ?>',
                            '<?= $rowC['analista_id'] ?>',
                            '<?= addslashes($rowC['observacao']) ?>'
                          )">
                          <i class='fa-sharp fa-solid fa-pen'></i>
                        </a>
                        <a href="javascript:void(0)" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalExclusao" onclick="excluirAnalise(<?= $rowC['id'] ?>)">
                          <i class="fa-sharp fa-solid fa-trash"></i>
                        </a>
                      <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div><!-- table-responsive -->
        </div><!-- card-body -->
      </div><!-- card -->
    </div><!-- col-md-6 -->
</div><!-- container -->

<!-- Controle de cores da tabela de concluído -->
<script>
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll("#tabelaFinalizados tbody tr").forEach(row => {
    let statusCell = row.cells[4]; // 5ª coluna (índice 4)
    let status_id = statusCell.textContent.trim();
    // Remove classes de cores anteriores, se houver
    statusCell.classList.remove("pastel-cancelado", "pastel-concluido");
    // Aplica as classes com as novas cores:
    switch (status_id) {
      case "Cancelada":
        statusCell.classList.add("pastel-cancelado");
        break;
      case "Concluído":
        statusCell.classList.add("pastel-concluido");
        break;
    }
  });
});
</script>

<!-- MODAL CADASTRO (id=modalCadastro) -->
<div class="modal fade" id="modalCadastro" tabindex="-1">
  <div class="modal-dialog ">
    <div class="modal-content p-4">
      <h4 class="modal-title mb-3">Cadastrar Conversão</h4>
      <form id="formCadastro" action="cadastrar_conversao.php" method="POST">
        <input type="hidden" name="id">
          <div class="row mb-2"> 
            <div class="col-md-5">
              <div class="mb-3">
                <label class="form-label">Contato:</label>
                <input type="text" class="form-control" name="contato" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Serial / CNPJ:</label>
                <input type="text" class="form-control" name="serial">
              </div>
            </div>
            <div class="col-md-3">
              <div class="mb-3">
                <label class="form-label">Retrabalho:</label>
                <select name="retrabalho" class="form-select">
                  <option value="Sim">Sim</option>
                  <option value="Nao" selected>Nao</option>
                </select>
              </div>
            </div>
          </div>

          <div class="row mb-3"> 
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Sistema:</label>
                <select name="sistema_id" class="form-select" required>
                  <option value="">Selecione...</option>
                  <?php
                  mysqli_data_seek($sistemas, 0);
                  while ($sis = $sistemas->fetch_assoc()):
                  ?>
                    <option value="<?= $sis['id']; ?>"><?= $sis['nome']; ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Status:</label>
                <select name="status_id" id="status_id" class="form-select" required onchange="verificarStatus()">
                  <option value="">Selecione...</option>
                  <?php
                  mysqli_data_seek($status, 0);
                  while ($st = $status->fetch_assoc()):
                  ?>
                    <option value="<?= $st['id']; ?>"><?= $st['descricao']; ?></option>
                  <?php endwhile; ?>
                </select>
                <span id="statusError2" class="text-danger mt-1" style="display: none;">Para concluir, selecione "Concluído".</span>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Analista:</label>
                <select name="analista_id" class="form-select" required>
                  <option value="">Selecione...</option>
                  <?php
                  mysqli_data_seek($analistas, 0);
                  while ($an = $analistas->fetch_assoc()):
                  ?>
                    <option value="<?= $an['id']; ?>"><?= $an['nome']; ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
            </div>
          </div>

          <div class="row mb-3"> 
            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Data Recebido:</label>
                <input type="datetime-local" class="form-control" name="data_recebido" required>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Data Início:</label>
                <input type="datetime-local" class="form-control" name="data_inicio">
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Data Conclusão:</label>
                <input type="datetime-local" class="form-control" name="data_conclusao" id="data_conclusao">
              </div>
            </div>
          </div>
        
          <div class="row mb-4"> 
            <div class="mb-3">
              <label class="form-label">Observação:</label>
              <textarea name="observacao" class="form-control" rows="3"></textarea>
            </div>
          </div>
        <div class="text-end">
          <button type="submit" class="btn btn-success">Salvar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!--Verifica se o status é concluido e obriga a data conclusao-->
<script>

document.getElementById("formCadastro").addEventListener("submit", function(event) {
    var status = document.getElementById("status_id");
    var dataConclusao = document.getElementById("data_conclusao");
    var statusError2 = document.getElementById("statusError2");

    // ID real do status "Concluído" (substituir pelo valor correto do banco)
    var idConcluido = "1"; 

    // Se a data de conclusão estiver preenchida, mas o status não for "Concluído"
    if (dataConclusao.value.trim() !== "" && status.value !== idConcluido) {
      statusError2.style.display = "block"; // Exibe a mensagem de erro
      event.preventDefault(); // Impede o envio do formulário

      // Remove a mensagem após 2 segundos
      setTimeout(function() {
        statusError2.style.display = "none";
      }, 2000);
    } else {
      statusError2.style.display = "none"; // Oculta a mensagem se estiver tudo certo
    }
});

  function verificarStatus() {
    var status2 = document.getElementById("status_id");
    var dataConclusao2 = document.getElementById("data_conclusao");

    // Pega o texto da opção selecionada
    var statusSelecionado = status2.options[status2.selectedIndex].text.trim();

    // Verifica se a opção selecionada é "Concluido"
    if (statusSelecionado === "Concluído") {
      dataConclusao.setAttribute("required", "true"); // Adiciona required quando visível
    } else {
      dataConclusao.removeAttribute("required"); // Remove required quando oculto
    }
}
</script>

<!-- MODAL EDIÇÃO (id=modalEdicao) -->
<div class="modal fade" id="modalEdicao" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content p-4">
      <h4 class="modal-title mb-3">Editar Conversão</h4>
      <form id="formEdicao" action="editar_conversao.php" method="POST">
        <input type="hidden" name="id" id="edit_id">
        <div class="row mb-2"> 
          <div class="col-md-5">
            <div class="mb-3">
              <label class="form-label">Contato:</label>
              <input type="text" class="form-control" name="contato" id="edit_contato" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Serial / CNPJ:</label>
              <input type="text" class="form-control" name="serial" id="edit_serial">
            </div>
          </div>
          <div class="col-md-3">
            <div class="mb-3">
              <label class="form-label">Retrabalho:</label>
              <select name="retrabalho" class="form-select" id="edit_retrabalho">
                <option value="Sim">Sim</option>
                <option value="Nao" selected>Nao</option>
              </select>
            </div>
          </div>
        </div>

        <div class="row mb-3"> 
          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Sistema:</label>
              <select name="sistema_id" class="form-select" id="edit_sistema" required>
                <option value="">Selecione...</option>
                <?php
                mysqli_data_seek($sistemas, 0);
                while ($sis = $sistemas->fetch_assoc()):
                ?>
                  <option value="<?= $sis['id']; ?>"><?= $sis['nome']; ?></option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>

          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Status:</label>
              <select name="status_id" class="form-select" id="edit_status" required onchange="verificarStatusEdit()">
                <option value="">Selecione...</option>
                <?php
                mysqli_data_seek($status, 0);
                while ($st = $status->fetch_assoc()):
                ?>
                  <option value="<?= $st['id']; ?>"><?= $st['descricao']; ?></option>
                <?php endwhile; ?>
              </select>
              <span id="statusError" class="text-danger mt-1" style="display: none;">Para concluir, selecione "Concluído".</span>
            </div>
          </div>

          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Analista:</label>
              <select name="analista_id" class="form-select" id="edit_analista" required>
                <option value="">Selecione...</option>
                <?php
                mysqli_data_seek($analistas, 0);
                while ($an = $analistas->fetch_assoc()):
                ?>
                  <option value="<?= $an['id']; ?>"><?= $an['nome']; ?></option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-3">
            <div class="mb-3">
              <label class="form-label">Prazo Entrega:</label>
              <input type="datetime-local" class="form-control" id="edit_prazo_entrega" name="prazo_entrega" required>
            </div>  
          </div> 
        
          <div class="col-md-3">
            <div class="mb-3">
              <label class="form-label">Data Recebido:</label>
              <input type="datetime-local" class="form-control" name="data_recebido" id="edit_data_recebido" required>
            </div>
          </div>

          <div class="col-md-3">
            <div class="mb-3">
              <label class="form-label">Data Início:</label>
              <input type="datetime-local" class="form-control" name="data_inicio" id="edit_data_inicio" required>
            </div>
          </div>

          <div class="col-md-3">
            <div class="mb-3">
              <label class="form-label">Data Conclusão:</label>
              <input type="datetime-local" class="form-control" name="data_conclusao" id="edit_data_conclusao">
            </div>
          </div>
        </div>
      
        <div class="row mb-4"> 
          <div class="mb-3">
            <label class="form-label">Observação:</label>
            <textarea name="observacao" class="form-control" id="edit_observacao" rows="3"></textarea>
          </div>
        </div>
        
        <div class="text-end">
          <button type="submit" class="btn btn-success">Salvar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!--Verifica se a data ta preenchida e obriga o status concluido-->
<script>
  document.getElementById("formEdicao").addEventListener("submit", function(event) {
    var statusEdit = document.getElementById("edit_status");
    var dataEditConclusao = document.getElementById("edit_data_conclusao");
    var statusError = document.getElementById("statusError");

    // ID real do status "Concluído" (substituir pelo valor correto do banco)
    var idConcluido = "1"; 

    // Se a data de conclusão estiver preenchida, mas o status não for "Concluído"
    if (dataEditConclusao.value.trim() !== "" && statusEdit.value !== idConcluido) {
      statusError.style.display = "block"; // Exibe a mensagem de erro
      event.preventDefault(); // Impede o envio do formulário

      // Remove a mensagem após 2 segundos
      setTimeout(function() {
        statusError.style.display = "none";
      }, 2000);
    } else {
      statusError.style.display = "none"; // Oculta a mensagem se estiver tudo certo
    }
  });

  function verificarStatusEdit() {
    var statusEdit2 = document.getElementById("edit_status");
    var dataConclusao2 = document.getElementById("edit_data_conclusao");

    // Pega o texto da opção selecionada
    var statusSelecionado2 = statusEdit2.options[statusEdit2.selectedIndex].text.trim();

    // Verifica se a opção selecionada é "Concluido"
    if (statusSelecionado2 === "Concluído") {
      dataConclusao2.setAttribute("required", "true"); // Adiciona required quando visível
    } else {
      dataConclusao2.removeAttribute("required"); // Remove required quando oculto
    }
}
</script>

<!-- Modal de Exclusão -->
<div class="modal fade" id="modalExclusao" tabindex="-1" aria-labelledby="modalExclusaoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalExclusaoLabel">Confirma a Exclusão da Análise?</h5>
                </div>
                <div class="modal-body">
                    <form action="deletar_conversao.php" method="POST">
                        <!-- Campo oculto para armazenar o ID da análise -->
                        <input type="hidden" id="id_excluir" name="id_excluir">
                        <div class="text-end">
                            <button type="submit" class="btn btn-success">Sim</button>
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal" aria-label="Close">Não</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Chart.js
let labelsMes = <?= json_encode($labelsMes); ?>;
let chartDatasets = <?= json_encode($chartDatasets); ?>;

let ctx = document.getElementById('chartBarras').getContext('2d');
let chartBarras = new Chart(ctx, {
  type: 'bar',
  data: {
    labels: labelsMes,
    datasets: chartDatasets
  },
  options: {
    responsive: true,
    scales: {
      x: { title: { display: true, text: 'Mês (ano-mês)' } },
      y: { beginAtZero: true, title: { display: true, text: 'Quantidade' } }
    }
  }
});
</script>

<script>
    // Mostra modal de cadastro
    function abrirModalCadastro() {
      $("#modalCadastro").modal('show');
    }

    // Mostra modal de edição
    function abrirModalEdicao(
      id, contato, serial, retrabalho,
      sistemaID, statusID, dataRecebido,
      prazoEntrega, dataInicio, dataConclusao,
      analistaID, observacao
    ) {
      // Preenche campos do modal Edição
      $("#edit_id").val(id);
      $("#edit_contato").val(contato);
      $("#edit_serial").val(serial);
      $("#edit_retrabalho").val(retrabalho);
      $("#edit_sistema").val(sistemaID);
      $("#edit_status").val(statusID);
      $("#edit_data_recebido").val(dataRecebido);
      $("#edit_prazo_entrega").val(prazoEntrega);
      $("#edit_data_inicio").val(dataInicio);
      $("#edit_data_conclusao").val(dataConclusao);
      $("#edit_analista").val(analistaID);
      $("#edit_observacao").val(observacao);
      $("#modalEdicao").modal('show');
    }

    // Mostra modal de edição de conversão finalizado
    function abrirModalEdicaoFinal(
      id, contato, serial, retrabalho,
      sistemaID, statusID, dataRecebido, 
      prazoEntrega, dataInicio, dataConclusao,
      analistaID, observacao
    ) {
      // Preenche campos do modal Edição
      $("#edit_id").val(id);
      $("#edit_contato").val(contato);
      $("#edit_serial").val(serial);
      $("#edit_retrabalho").val(retrabalho);
      $("#edit_sistema").val(sistemaID);
      $("#edit_status").val(statusID);
      $("#edit_data_recebido").val(dataRecebido);
      $("#edit_prazo_entrega").val(prazoEntrega);
      $("#edit_data_inicio").val(dataInicio);
      $("#edit_data_conclusao").val(dataConclusao);
      $("#edit_analista").val(analistaID);
      $("#edit_observacao").val(observacao);
      $("#modalEdicao").modal('show');
    }

    // Função para preencher o modal de exclusão
    function excluirAnalise(id) {
        document.getElementById("id_excluir").value = id;
    }
  </script>
</body>
</html>
