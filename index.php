<?php
require 'Config/Database.php';

session_start();

// Verifica se o usuário está logado; se não, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Consulta principal (incluindo os IDs para edição)
$sql = "SELECT
            tas.Id as Codigo,
            tas.Descricao as Descricao,
            sit.Descricao as Situacao,
            tba.Descricao as Analista,
            sis.Descricao as Sistema,
            sta.Descricao as Status,
            tas.Hora_ini,
            tas.Hora_fim,
            tas.Total_hora,
            -- IDs para edição
            tas.idSituacao AS idSituacao,
            tas.idAnalista AS idAnalista,
            tas.idSistema AS idSistema,
            tas.idStatus AS idStatus
        FROM TB_ANALISES tas
            LEFT JOIN TB_SITUACAO sit ON sit.Id = tas.idSituacao
            LEFT JOIN TB_ANALISTA tba ON tba.Id = tas.idAnalista
            LEFT JOIN TB_SISTEMA sis ON sis.Id = tas.idSistema
            LEFT JOIN TB_STATUS sta ON sta.Id = tas.idStatus
            LEFT JOIN TB_USUARIO usu ON usu.Id = tas.idUsuario
        ORDER BY tas.Id DESC";

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
$totalAnaliseN3 = 0; // Quantidade de "Análise N3" (baseado no campo Situacao)
$totalAuxilio = 0; // Total de Auxílio Suporte/Vendas
$totalHoras = 0; // Soma do campo Total_hora
$uniqueDates = array(); // Para calcular a média diária de horas

foreach ($rows as $row) {
    // Contar quantidade de "Análise N3"
    if (trim($row['Situacao']) == "Análise N3") {
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
    
    // Somar o Total_hora (certifique-se de que é numérico)
    $totalHoras += floatval($row["Total_hora"]);
    
    // Extrair a data (dia) de Hora_ini para cálculo da média diária
    $date = date("Y-m-d", strtotime($row["Hora_ini"]));
    $uniqueDates[$date] = true;
}
$numeroDias = count($uniqueDates);
$mediaDiariaHoras = ($totalAnaliseN3 > 0) ? ($totalHoras / $totalAnaliseN3) : 0;

// Processamento dos dados para o gráfico de barras
$fichasPorMes = array_fill(1, 12, 0);
$analisesN3PorMes = array_fill(1, 12, 0);
$currentYear = date("Y");

foreach ($rows as $row) {
    $dataHora = strtotime($row["Hora_ini"]);
    $year = date("Y", $dataHora);
    if ($year == $currentYear) {
        $month = intval(date("n", $dataHora)); // 1 a 12
        if (trim($row['Situacao']) == "Ficha Criada") {
            $fichasPorMes[$month]++;
        }
        if (trim($row['Situacao']) == "Análise N3") {
            $analisesN3PorMes[$month]++;
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

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
   
    <!-- Ícones personalizados -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
 
    <!-- Arquivo CSS personalizado -->
    <link rel="stylesheet" href="Public/index.css">
</head>
<body class="bg-light">
 
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <span class="navbar-brand mb-0 h1">Tarefas N3</span>
            <span class="text-white">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
            <a href="Views/logout.php" class="btn btn-danger">Sair</a>
        </div>
    </nav>
 
    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?> 
        <div class="alert alert-success" role="alert">
            Análise cadastrada com sucesso!
        </div>
    <?php elseif (isset($_GET['success']) && $_GET['success'] == 2): ?> 
        <div class="alert alert-success" role="alert">
            Análise editada com sucesso!
        </div>
    <?php elseif (isset($_GET['success']) && $_GET['success'] == 3): ?> 
        <div class="alert alert-success" role="alert">
            Análise excluída com sucesso!
        </div>
    <?php endif; ?>
 
    <!-- Resumo dos Totalizadores e Gráfico Mensal -->
    <div class="container mt-4">
        <div class="row">
            <!-- Totalizadores -->
            <div class="col-md-5">
                <div class="card shadow-lg">
                    <div class="card-header text-white bg-primary">
                        <h4 class="mb-0">Resumo dos Totalizadores</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover table-striped">
                            <tbody>
                                <tr>
                                    <td><strong>Fichas Criadas:</strong></td>
                                    <td><?php echo $totalFichas; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Análise N3:</strong></td>
                                    <td><?php echo $totalAnaliseN3; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Auxílio Suporte/Vendas:</strong></td>
                                    <td><?php echo $totalAuxilio; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Média Horas:</strong></td>
                                    <td><?php echo number_format($mediaDiariaHoras, 2, ':', ':'); ?></td>
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
                        <canvas id="chartMensal" width="400" height="175"></canvas>
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
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastro">Cadastrar</button>
            </div>
        </div>
    </div>

    <!-- Exibição da Lista de Análises -->
    <div class="container mt-4">
        <div class="table-responsive">
            <table id="tabelaAnalises" class="table table-bordered table-hover">
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
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $row["Descricao"] . "</td>";
                            echo "<td>" . $row["Situacao"] . "</td>";
                            echo "<td>" . $row["Analista"] . "</td>";
                            echo "<td>" . $row["Sistema"] . "</td>";
                            echo "<td>" . $row["Status"] . "</td>";
                            echo "<td>" . $row["Hora_ini"] . "</td>";
                            echo "<td>" . $row["Hora_fim"] . "</td>";
                            echo "<td>" . $row["Total_hora"] . "</td>";
                            echo "<td>";
                            // Botão de edição: passando os IDs para edição
                            echo "<a href='javascript:void(0)' class='btn-edit' data-bs-toggle='modal' data-bs-target='#modalEdicao' onclick=\"editarAnalise(" 
                                 . $row['Codigo'] . ", '" 
                                 . addslashes($row['Descricao']) . "', '" 
                                 . $row['idSituacao'] . "', '" 
                                 . $row['idAnalista'] . "', '" 
                                 . $row['idSistema'] . "', '" 
                                 . $row['idStatus'] . "', '" 
                                 . $row['Hora_ini'] . "', '" 
                                 . $row['Hora_fim'] . "')\"><i class='fa-sharp fa-solid fa-pen'></i></a> ";
                            // Botão de exclusão com confirmação
                            echo "<a href='javascript:void(0)' class='btn-remove' data-bs-toggle='modal' data-bs-target='#modalExclusao' onclick=\"excluirAnalise(" 
                                 . $row['Codigo'] .")\"><i class='fa-sharp fa-solid fa-trash'></i></a> ";
                            echo "</td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Script para ajustar as cores dos status via JavaScript -->
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll("#tabelaAnalises tbody tr").forEach(row => {
            let statusCell = row.cells[4]; // 5ª coluna (índice 4)
            let status = statusCell.textContent.trim();
            switch (status) {
                case "Aguardando":
                    statusCell.style.backgroundColor = "#D8BFD8"; // Roxo claro
                    break;
                case "Desenvolvimento":
                    statusCell.style.backgroundColor = "#FFD700"; // Amarelo
                    break;
                case "Resolvido":
                    statusCell.style.backgroundColor = "#28a745"; // Verde
                    statusCell.style.color = "white"; // Texto branco para melhor visibilidade
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
                    label: 'Análise N3',
                    data: analisesN3PorMes,
                    backgroundColor: 'rgba(255, 99, 132, 0.5)', // Vermelho
                    borderColor: 'rgba(255, 99, 132, 1)',
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
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="descricao" class="form-label">Descrição</label>
                                <input type="text" class="form-control" id="descricao" name="descricao" maxlength="50" required>
                            </div>
                            <div class="col-md-6">
                                <label for="situacao" class="form-label">Situação</label>
                                <select class="form-select" id="situacao" name="situacao" required onchange="verificarSituacao()">
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
                                <div class="row mb-3 mt-3" id="fichaContainer" style="display: none;">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="chkFicha" name="chkFicha" onchange="verificarFicha()">
                                            <label class="form-check-label" for="chkFicha">Ficha</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3" id="numeroFichaContainer" style="display: none;">
                                    <div class="col-md-15">
                                        <label for="numeroFicha" class="form-label">Número da Ficha</label>
                                        <input type="number" class="form-control" id="numeroFicha" name="numeroFicha" pattern="\d+">
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

                            // Verifica se a opção selecionada é "Análise N3"
                            if (situacaoSelecionada === "Análise N3") {
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
                        </script>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="analista" class="form-label">Analista</label>
                                <select class="form-select" id="analista" name="analista" required>
                                    <option value="">Selecione</option>
                                    <?php
                                    $queryAnalista = "SELECT Id, Descricao FROM TB_ANALISTA";
                                    $resultAnalista = $conn->query($queryAnalista);
                                    while ($rowA = $resultAnalista->fetch_assoc()) {
                                        echo "<option value='" . $rowA['Id'] . "'>" . $rowA['Descricao'] . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
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
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEdicaoLabel">Editar Análise</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="Views/editar_analise.php" method="POST">
                        <!-- Campo oculto para armazenar o ID da análise -->
                        <input type="hidden" id="id_editar" name="id_editar">
 
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="descricao_editar" class="form-label">Descrição</label>
                                <input type="text" class="form-control" id="descricao_editar" name="descricao_editar" maxlength="50" required>
                            </div>
                            <div class="col-md-6">
                                <label for="situacao_editar" class="form-label">Situação</label>
                                <select class="form-select" id="situacao_editar" name="situacao_editar" required>
                                    <option value="">Selecione</option>
                                    <?php
                                    $querySituacao2 = "SELECT Id, Descricao FROM TB_SITUACAO";
                                    $resultSituacao2 = $conn->query($querySituacao2);
                                    while ($rowS2 = $resultSituacao2->fetch_assoc()) {
                                        echo "<option value='" . $rowS2['Id'] . "'>" . $rowS2['Descricao'] . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="analista_editar" class="form-label">Analista</label>
                                <select class="form-select" id="analista_editar" name="analista_editar" required>
                                    <option value="">Selecione</option>
                                    <?php
                                    $queryAnalista2 = "SELECT Id, Descricao FROM TB_ANALISTA";
                                    $resultAnalista2 = $conn->query($queryAnalista2);
                                    while ($rowA2 = $resultAnalista2->fetch_assoc()) {
                                        echo "<option value='" . $rowA2['Id'] . "'>" . $rowA2['Descricao'] . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
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
                        </div>
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
                        <div class="text-end">
                            <button type="submit" class="btn btn-success">Salvar</button>
                        </div>
                    </form>
                </div>
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
        // Exibe e remove automaticamente as mensagens de sucesso após 2 segundos
        document.addEventListener("DOMContentLoaded", function () {
            setTimeout(function () {
                let alertSuccess = document.querySelector(".alert-success");
                if (alertSuccess) {
                    alertSuccess.style.transition = "opacity 0.5s";
                    alertSuccess.style.opacity = "0";
                    setTimeout(() => alertSuccess.remove(), 500);
                }
            }, 2000);
        });
 
        // Função para preencher o modal de edição
        function editarAnalise(id, descricao, idSituacao, idAnalista, idSistema, idStatus, hora_ini, hora_fim) {
            document.getElementById("id_editar").value = id;
            document.getElementById("descricao_editar").value = descricao;
            document.getElementById("situacao_editar").value = idSituacao;
            document.getElementById("analista_editar").value = idAnalista;
            document.getElementById("sistema_editar").value = idSistema;
            document.getElementById("status_editar").value = idStatus;
            document.getElementById("hora_ini_editar").value = hora_ini;
            document.getElementById("hora_fim_editar").value = hora_fim;
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
