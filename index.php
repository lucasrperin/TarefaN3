<?php
require 'Config/Database.php';

session_start();

// Verifica se o usuário está logado; se não, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: Views/login.php");
    exit();
}

// Definir o cargo do usuário (supondo que ele esteja armazenado na sessão, com a chave "Cargo")
$usuario_id = $_SESSION['usuario_id'];
$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';

// Captura os parâmetros do filtro, se enviados
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$data_fim    = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';

// Captura os parâmetros do filtro ou define o mês atual por padrão na primeira carga
// Captura os parâmetros do filtro ou define o mês atual por padrão
if (isset($_GET['data_inicio']) && isset($_GET['data_fim'])) {
    if ($_GET['data_inicio'] === '' && $_GET['data_fim'] === '') {
        // sem filtro, mostra tudo
        $data_inicio = '';
        $data_fim = '';
    } else {
        $data_inicio = $_GET['data_inicio'];
        $data_fim = $_GET['data_fim'];
    }
} else {
    // Padrão: Primeiro e último dia do mês atual
    $data_inicio = date('Y-m-01');
    $data_fim = date('Y-m-t');
}

// Monta a query base, SEC_TO_TIME converte em segundos para o cálculo da média
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
            LEFT JOIN TB_USUARIO usu ON usu.Id = tas.idUsuario";

// Se as datas estiverem definidas, adiciona a cláusula WHERE para filtrar pelo período
if (!empty($data_inicio) && !empty($data_fim)) {
    $sql .= " WHERE DATE(tas.Hora_ini) BETWEEN '$data_inicio' AND '$data_fim'";
} 

$sql .= " ORDER BY tas.Id DESC";

$result = $conn->query($sql);
$result1 = $conn->query($sql);

// Verificar se a consulta retornou resultados
if ($result1 === false) {
    die("Erro na consulta SQL: " . $conn->error);
}

// Armazenar os registros em um array para possibilitar o cálculo dos totalizadores
$rows = array();
if ($result1->num_rows > 0) {
    while ($row = $result1->fetch_assoc()) {
        $rows[] = $row;
    }
} else {
    $rows = array();
}

// Cálculo dos totalizadores
$totalFichas = 0; // Total de fichas criadas
$totalAnaliseN3 = 0; // Quantidade de "Analise N3" (baseado no campo Situacao)
$totalAuxilio = 0; // Total de Auxílio Suporte/Vendas
$totalParado = 0; // Total de Cliente Parado
$totalHoras = 0; // Soma do campo Total_hora
$uniqueDates = array(); // Para calcular a média diária de horas

foreach ($rows as $row) {
    // Contar quantidade de "Analise N3"
    if (trim($row['Situacao']) == "Analise N3") {
        $totalAnaliseN3++;
    }
    // Contabilizar Auxílio Suporte/Vendas
    if (trim($row['Situacao']) == "Auxilio Suporte/Vendas") {
        $totalAuxilio++;
    }
    // Contar quantidade de "Fichas Criadas"
    if (trim($row['Situacao']) == "Ficha Criada") {
        $totalFichas++;
    }
    // Contar quantidade de "Cliente Parado"
    if (trim($row['Parado']) == "S") {
        $totalParado++;
    }
    
    // Extrair a data (dia) de Hora_ini para cálculo da média diária
    $date = date("Y-m-d", strtotime($row["Hora_ini"]));
    $uniqueDates[$date] = true;
}
$numeroDias = count($uniqueDates);
// Certifique-se de que total de horas é tratado corretamente
$totalHoras = 0; 
$uniqueDates = array();

foreach ($rows as $row) {
    if (!empty($row["Total_hora"])) {
        // Converter HH:MM:SS para segundos
        list($h, $m, $s) = explode(":", $row["Total_hora"]);
        $totalHoras += ($h * 3600) + ($m * 60) + $s;
    }

    // Extrai a data para cálculo da média diária
    $date = date("Y-m-d", strtotime($row["Hora_ini"]));
    $uniqueDates[$date] = true;
}

// Calculando a média corretamente
if ($totalAnaliseN3 > 0) {
    $mediaSegundos = round($totalHoras / $totalAnaliseN3);

    // Converter segundos para HH:MM:SS
    $horas = floor($mediaSegundos / 3600);
    $minutos = floor(($mediaSegundos % 3600) / 60);
    $segundos = $mediaSegundos % 60;
} else {
    $horas = $minutos = $segundos = 0; 
}


// Processamento dos dados para o gráfico de barras
$fichasPorMes = array_fill(1, 12, 0);
$analisesN3PorMes = array_fill(1, 12, 0);
$clienteParadoPorMes = array_fill(1, 12, 0);
$currentYear = date("Y");

foreach ($rows as $row) {
    $dataHora = strtotime($row["Hora_ini"]);
    $year = date("Y", $dataHora);
    if ($year == $currentYear) {
        $month = intval(date("n", $dataHora)); // 1 a 12
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

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tarefas N3</title>
    <!-- Arquivo CSS personalizado -->
    <link href="Public/index.css" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
   
    <!-- Ícones personalizados -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
 
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">

    <link rel="icon" href="Public\Image\icone2.png" type="image/png">
    
</head>
<body class="bg-light">

<!-- Container do Toast no canto superior direito -->
<div class="toast-container">
    <div id="toastSucesso" class="toast">
        <div class="toast-body">
            <i class="fa-solid fa-check-circle"></i> <span id="toastMensagem"></span>
        </div>
    </div>
</div>

<script dref>
document.addEventListener("DOMContentLoaded", function () {
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get("success");

    if (success) {
        let mensagem = "";
        switch (success) {
            case "1":
                mensagem = "Análise cadastrada com sucesso!";
                break;
            case "2":
                mensagem = "Análise editada com sucesso!";
                break;
            case "3":
                mensagem = "Análise excluída com sucesso!";
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


<nav class="navbar navbar-dark bg-dark">
    <div class="container d-flex justify-content-between align-items-center">
        <!-- Botão Hamburguer com Dropdown -->
        <div class="dropdown">
            <button class="navbar-toggler" type="button" id="menuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="navbar-toggler-icon"></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="menuDropdown">
                <li><a class="dropdown-item" href="Views/conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversão</a></li>
                <?php if ($cargo === 'Admin'): ?>  <!-- Verifica o cargo do usuário -->
                <li><a class="dropdown-item" href="Views/escutas.php"><i class="fa-solid fa-headphones me-2"></i>Escutas</a></li>
                <li><a class="dropdown-item" href="Views/folga.php"><i class="fa-solid fa-umbrella-beach me-2"></i>Folgas</a></li>
                <li><a class="dropdown-item" href="Views/incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a></li>
                <li><a class="dropdown-item" href="Views/indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a></li>
                <li><a class="dropdown-item" href="Views/dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a></li>
                <li><a class="dropdown-item" href="Views/usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <span class="text-white">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
        <a href="Views/menu.php" class="btn btn-danger">
            <i class="fa-solid fa-arrow-left me-2" style="font-size: 0.8em;"></i>Voltar
        </a>
    </div>
</nav>
 
   <!-- Linha com Resumo dos Totalizadores, Gráfico Mensal e Filtro de Período -->
<div class="container mt-4">
    <div class="row" id="dashboardCards">
        <!-- Totalizadores -->
        <div class="col-md-4">
            <div class="card shadow-lg">
                <div class="card-header text-white bg-primary">
                    <h4 class="mb-0">Resumo dos Totalizadores</h4>
                </div>
                <div class="card-body mt-4">
                    <table class="table table-hover table-striped">
                        <tbody>
                            <tr>
                                <td><strong>Fichas Criadas:</strong></td>
                                <td><?php echo $totalFichas; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Analise N3:</strong></td>
                                <td><?php echo $totalAnaliseN3; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Auxílio Suporte/Vendas:</strong></td>
                                <td><?php echo $totalAuxilio; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Cliente Parado</strong></td>
                                <td><?php echo $totalParado; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Média Horas:</strong></td>
                                <td><?php echo sprintf("%02d:%02d:%02d", $horas, $minutos, $segundos); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Gráfico Mensal -->
        <div class="col-md-5">
            <div class="card shadow-lg">
                <div class="card-header text-white bg-info">
                    <h4 class="mb-0">Gráfico Mensal</h4>
                </div>
                <div class="card-body">
                    <canvas id="chartMensal" width="18%" height="10%"></canvas>
                </div>
            </div>
        </div>

        <!-- Filtro de Período -->
        <div class="col-md-3" >
            <div class="card shadow-lg">
                <div class="card-header text-white bg-secondary">
                    <h4 class="mb-0">Filtro de Período</h4>
                </div>
                <div class="card-body" >
                    <form method="GET" action="">
                        <div class="mb-3">
                            <label for="data_inicio" class="form-label mt-2" >Data Início:</label>
                            <input type="date" class="form-control" name="data_inicio" id="data_inicio" value="<?php echo htmlspecialchars($data_inicio); ?>">
                        </div>
                        <div class="mb-4">
                            <label for="data_fim" class="form-label">Data Fim:</label>
                            <input type="date" class="form-control" name="data_fim" id="data_fim" value="<?php echo htmlspecialchars($data_fim); ?>">
                        </div>
                        <div class="d-flex justify-content-center gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                            <a href="index.php?data_inicio=&data_fim=" class="btn btn-secondary btn-sm">Limpar Filtros</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

  <!-- Título Lista de Análises e Botão Cadastrar análise -->
<div class="container mt-4">
  <div class="row align-items-center">
    <div class="col-4"></div> 
    <div class="col-4 text-center">
      <h2 class="mb-0">Lista de Análises</h2>
    </div>
    <div class="col-4 text-end">
        <?php if ($cargo === 'Admin'): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastro">
                <i class="fa-solid fa-plus-circle me-1"></i> Cadastrar
            </button>
        <?php endif; ?>
    </div>
  </div>
</div>

<!-- Exibição da Lista de Análises dentro de um Card -->
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="table-responsive access-scroll">
        <table id="tabelaAnalises" class="table table-bordered table-striped table-hover align-middle">
          <thead class="table-dark">
            <tr>
              <th style="width:30%">Descrição</th>
              <th style="width:11%">Situação</th>
              <th style="width:10%">Analista</th>
              <th>Sistema</th>
              <th>Status</th>
              <th style="width:15%">Hora Início</th>
              <th style="width:15%">Hora Fim</th>
              <th style="width:10%">Total Horas</th>
              <?php
              if ($cargo === 'Admin') { //Se o cargo for ADM apresenta o menu ações
                echo "<th>Ações</th>";
                }?>
            </tr>
          </thead>
          <tbody>
            <?php 
            if ($result->num_rows > 0) {
              while($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td class=\"sobrepor\">" . $row["Descricao"] . "</td>";
                echo "<td>" . $row["Situacao"] . "</td>";
                echo "<td>" . $row["NomeUsuario"] . "</td>";
                echo "<td>" . $row["Sistema"] . "</td>";
                echo "<td>" . $row["Status"] . "</td>";
                echo "<td>" . $row["Hora_ini2"] . "</td>";
                echo "<td>" . $row["Hora_fim2"] . "</td>";
                echo "<td>" . $row["Total_hora"] . "</td>";
                if ($cargo === 'Admin') { // Se o cargo for ADM apresenta o menu ações
                    echo "<td class='text-center'>";
                    echo "<a href='javascript:void(0)' class='btn btn-outline-primary btn-sm' data-bs-toggle='modal' data-bs-target='#modalEdicao' onclick='editarAnalise(" 
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
                         ")'><i class='fa-sharp fa-solid fa-pen'></i></a> ";
                    echo "<a href='javascript:void(0)' class='btn btn-outline-danger btn-sm' data-bs-toggle='modal' data-bs-target='#modalExclusao' onclick='excluirAnalise(" 
                         . htmlspecialchars(json_encode($row['Codigo']), ENT_QUOTES, 'UTF-8') .
                         ")'><i class='fa-sharp fa-solid fa-trash'></i></a>";
                    echo "</td>";
                }
                
                echo "</td>";
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
<script>
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll("#tabelaAnalises tbody tr").forEach(row => {
    let statusCell = row.cells[4]; // 5ª coluna (índice 4)
    let status = statusCell.textContent.trim();
    // Remove classes de cores anteriores, se houver
    statusCell.classList.remove("pastel-aguardando", "pastel-desenvolvimento", "pastel-resolvido");
    // Aplica as classes com as novas cores:
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

    <!-- Adiciona a biblioteca Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Script para configurar o gráfico de barras -->
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        // Dados gerados pelo PHP
        const fichasPorMes = <?php echo json_encode(array_values($fichasPorMes)); ?>;
        const analisesN3PorMes = <?php echo json_encode(array_values($analisesN3PorMes)); ?>;
        const clienteParadoPorMes = <?php echo json_encode(array_values($clienteParadoPorMes)); ?>;
        
        // Labels dos meses (abreviados)
        const labels = ["Jan", "Fev", "Mar", "Abr", "Mai", "Jun", "Jul", "Ago", "Set", "Out", "Nov", "Dez"];

        const data = {
            labels: labels,
            datasets: [
                {
                    label: 'Fichas Criadas',
                    data: fichasPorMes,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)', // Azul
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Analise N3',
                    data: analisesN3PorMes,
                    backgroundColor: 'rgba(255, 99, 132, 0.5)', // Vermelho
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Cliente Parado',
                    data: clienteParadoPorMes,
                    backgroundColor: 'rgb(248, 11, 11)', // Vermelho
                    borderColor: 'rgb(243, 137, 160)',
                    borderWidth: 1
                }
            ]
        };

        const config = {
            type: 'bar',
            data: data,
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        };

        // Cria o gráfico na canvas com id "chartMensal"
        new Chart(
            document.getElementById('chartMensal'),
            config
        );
    });
    </script>
 
    <!-- Modal de Cadastro -->
    <div class="modal fade" id="modalCadastro" tabindex="-1" aria-labelledby="modalCadastroLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCadastroLabel">Nova Análise</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                                <textarea name="justificativa" id="just_nota" class="form-control" maxlength="255" rows="2"></textarea>
                            </div>
                        </div>

                        <div class="row mb-3">    
                            <div class="col-md-6">
                                <label for="sistema" class="form-label">Sistema</label>
                                <select class="form-select" id="sistema" name="sistema" required>
                                    <option value="">Selecione</option>
                                    <?php
                                    $querySistema = "SELECT Id, Descricao FROM TB_SISTEMA";
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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                                $querySistema2 = "SELECT Id, Descricao FROM TB_SISTEMA";
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
                        <div class="text-end">
                            <button type="submit" class="btn btn-success">Sim</button>
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal" aria-label="Close">Não</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
 
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
 
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
</body>
</html>
