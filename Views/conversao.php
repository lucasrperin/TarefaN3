<?php
include '../Config/Database.php';


session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

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
 * 4) TOTALIZADORES GERAIS (Quantidade, Tempo Médio)
 ****************************************************************/
$sqlQtd = "
    SELECT COUNT(*)
      FROM TB_CONVERSOES c
      $where
";
$total_conversoes = $conn->query($sqlQtd)->fetch_row()[0] ?? 0;

$sqlTempo = "
    SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(tempo_total)))
      FROM TB_CONVERSOES c
      $where
";
$tempo_medio = $conn->query($sqlTempo)->fetch_row()[0] ?? 'N/A';

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
      AND DATE(c.data_recebido) = CURDATE()
      AND TIME(c.data_recebido) <= '15:00:00'
      AND st.descricao NOT IN ('Concluído','Cancelada')
";
$countDentroPrazo = $conn->query($sqlDentroPrazo)->fetch_row()[0] ?? 0;

// NOVA QUERY: Conversões atrasadas (já passaram do prazo)
// Considera somente status: Em fila, Analise, Dar prioridade
$sqlAtrasadas = "
    SELECT COUNT(*) 
      FROM TB_CONVERSOES c
      JOIN TB_STATUS_CONVER st ON c.status_id = st.id
      $where
      AND (
           (DATE(c.data_recebido) < CURDATE())
           OR (DATE(c.data_recebido) = CURDATE() AND TIME(c.data_recebido) > '15:00:00')
          )
      AND st.descricao IN ('Em fila','Analise','Dar prioridade')
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
           (TIME(c.data_recebido) < '15:00:00' AND DATE(c.data_conclusao) = DATE(c.data_recebido))
           OR
           (TIME(c.data_recebido) >= '15:00:00' 
             AND DATE(c.data_conclusao) = DATE(c.data_recebido + INTERVAL 1 DAY)
             AND TIME(c.data_conclusao) < '15:00:00')
      )
";
$countMetaBatida = $conn->query($sqlMetaBatida)->fetch_row()[0] ?? 0;

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
                  AND st.descricao not in ('Em fila', 'Concluido', 'Cancelado')
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
                  ORDER BY c.data_recebido ASC";
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
                <li><a class="dropdown-item" href="dashboard.php">Totalizadores</a></li>
                <li><a class="dropdown-item" href="user.php">Analises</a></li>
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
              Ainda dentro do prazo (até 15:00)
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


  <!-- TOTAlIZADORES GERAIS -->
  <div class="row g-3 mb-3 card-total">
    <div class="col-md-6">
      <div class="card text-white bg-primary">
        <div class="card-body text-center">
          <h5 class="card-title">Total de Conversões (Filtro)</h5>
          <h3 class="card-text"><?= $total_conversoes; ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card text-white bg-success">
        <div class="card-body text-center">
          <h5 class="card-title">Tempo Médio (Filtro)</h5>
          <h3 class="card-text"><?= $tempo_medio; ?></h3>
        </div>
      </div>
    </div>
  </div>

  <!-- Botão Cadastrar -->
  <div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" onclick="abrirModalCadastro()">Cadastrar</button>
  </div>

  <!-- DUAS TABELAS: ESQUERDA = Fila, DIREITA = Outras -->
  <div class="row">
    <!-- TABELA 1: Em fila -->
    <div class="col-md-6 mb-3">
      <div class="card">
        <div class="card-header bg-warning text-dark">
          <strong>Conversões em Fila</strong> <!-- status='Em fila' -->
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
        <div class="card-header bg-dark text-white">
          <strong>Outras Conversões</strong>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0 tabelaEstilizada">
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
                        <i class="fa-sharp fa-solid fa-trash"></i>
                      </a>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div><!-- table-responsive -->
        </div><!-- card-body -->
      </div><!-- card -->
    </div><!-- col-md-6 -->
  </div><!-- row das duas tabelas -->

  <!--TABELA DE FINALIZADOS-->
  <div class="col-md-12 mb-3">
      <div class="card">
        <div class="card-header bg-success text-white">
          <strong>Conversões Finalizadas</strong>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0 tabelaEstilizada">
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
                    <a href="javascript:void(0)" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalExclusao" onclick="excluirAnalise(<?= $rowO['id'] ?>)">
                        <i class="fa-sharp fa-solid fa-trash"></i>
                      </a>
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
                  <option value="Não" selected>Não</option>
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
                <select name="status_id" class="form-select" required>
                  <option value="">Selecione...</option>
                  <?php
                  mysqli_data_seek($status, 0);
                  while ($st = $status->fetch_assoc()):
                  ?>
                    <option value="<?= $st['id']; ?>"><?= $st['descricao']; ?></option>
                  <?php endwhile; ?>
                </select>
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
                <input type="datetime-local" class="form-control" name="data_inicio" required>
              </div>
            </div>

            <div class="col-md-4">
              <div class="mb-3">
                <label class="form-label">Data Conclusão:</label>
                <input type="datetime-local" class="form-control" name="data_conclusao">
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
                <option value="Não" selected>Não</option>
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
              <select name="status_id" class="form-select" id="edit_status" required>
                <option value="">Selecione...</option>
                <?php
                mysqli_data_seek($status, 0);
                while ($st = $status->fetch_assoc()):
                ?>
                  <option value="<?= $st['id']; ?>"><?= $st['descricao']; ?></option>
                <?php endwhile; ?>
              </select>
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
