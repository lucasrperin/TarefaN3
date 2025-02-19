<?php
require 'Config/database.php';

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
            -- Adicionamos abaixo os IDs das tabelas relacionadas:
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

// Verificar se a consulta retornou resultados
if ($result === false) {
    die("Erro na consulta SQL: " . $conn->error);
}

// Armazenar os registros em um array para possibilitar o cálculo dos totalizadores
$rows = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
} else {
    $rows = array();
}

// Cálculo dos totalizadores
$totalFichas = 0; // Total de fichas criadas
$totalAnaliseN3 = 0;          // Quantidade de "Análises N3" (baseado no campo Situacao)
$totalAuxilio = 0;            // Total de Auxílio Suporte/Vendas (ajuste a condição conforme necessário)
$totalHoras = 0;              // Soma do campo Total_hora
$uniqueDates = array();       // Para calcular a média diária de horas

foreach ($rows as $row) {
    // Contar quantidade de "Análises N3"
    if (trim($row['Situacao']) == "Análises N3") {
        $totalAnaliseN3++;
    }
    
    // Contabilizar Auxílio Suporte/Vendas (ajuste o valor conforme necessário)
    if (trim($row['Situacao']) == "Auxilio Suporte/Vendas") {
        $totalAuxilio++;
    }
    // Contar quantidade de "Fichas criadas"
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
$mediaDiariaHoras = ($numeroDias > 0) ? ($totalHoras / $numeroDias) : 0;
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
 
   
 
   <!-- Totalizadores exibidos acima da lista -->
<div class="container mt-4">
    <div class="row">
        <div class="col-md-5 mx-auto">
            <div class="card shadow-lg">
                <div class="card-header text-white bg-primary">
                    <h4 class="mb-0">Resumo dos Totalizadores</h4>
                </div>
                <div class="card-body">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th scope="col">Totalizadores</th>
                                <th scope="col">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Fichas Criadas:</strong></td>
                                <td><?php echo $totalFichas; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Análises N3:</strong></td>
                                <td><?php echo $totalAnaliseN3; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Auxílio Suporte/Vendas:</strong></td>
                                <td><?php echo $totalAuxilio; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Média Horas:</strong></td>
                                <td><?php echo number_format($mediaDiariaHoras, 2, ',', '.'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


        <!-- Tituto Lista de Análises e Botão Cadastrar analise -->
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
<<<<<<< HEAD
<div class="container mt-4">
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
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
                        // Botão de edição: passa os IDs (idSituacao, idAnalista, etc.) em vez das descrições
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
=======
    <div class="container mt-4">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
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
                    if (count($rows) > 0) {
                        foreach ($rows as $row) {
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
                            // Botão de edição: passa os IDs para preencher o modal de edição
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
                            echo "<a class='btn-remove' href='Views/deletar_analise.php?codigo=" . $row['Codigo'] . "' onclick=\"return confirm('Confirma a exclusão do Registro?')\"><i class='fa-solid fa-trash'></i></a>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='9' class='text-center'>Nenhum dado encontrado</td></tr>";
>>>>>>> 450d676418ee17a653e3a37fb2957159b67c3b96
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
 
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
                                <select class="form-select" id="situacao" name="situacao" required>
                                    <option value="">Selecione</option>
                                    <?php
                                    $querySituacao = "SELECT Id, Descricao FROM TB_SITUACAO";
                                    $resultSituacao = $conn->query($querySituacao);
                                    while ($rowS = $resultSituacao->fetch_assoc()) {
                                        echo "<option value='" . $rowS['Id'] . "'>" . $rowS['Descricao'] . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
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
<<<<<<< HEAD

<!-- Modal de Edição -->
<div class="modal fade" id="modalExclusao" tabindex="-1" aria-labelledby="modalExclusaoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEdicaoLabel">Confirma a Exclusão da Análise?</h5>
                </div>
                <div class="modal-body">
                    <form action="Views/deletar_analise.php" method="POST">
                        <!-- Campo oculto para armazenar o ID da análise -->
                        <input type="hidden" id="id_excluir" name="id_excluir">
                        <div class="text-end">
                            <button type="submit" class="btn btn-success">Sim</button>
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal" aria-label="Close">Não</button>
=======
 
    <!-- Modal de Exclusão (opcional, se necessário) -->
    <div class="modal fade" id="modalExclusao" tabindex="-1" aria-labelledby="modalExclusaoLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalExclusaoLabel">Excluir Análise</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="Views/deletar_analise.php" method="POST">
                        <!-- Campo oculto para armazenar o ID da análise a ser excluída -->
                        <input type="hidden" id="id_excluir" name="id_excluir">
                        <p>Tem certeza que deseja excluir esta análise?</p>
                        <div class="text-end">
                            <button type="submit" class="btn btn-danger">Excluir</button>
>>>>>>> 450d676418ee17a653e3a37fb2957159b67c3b96
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
 
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
 
        // Função para preencher o modal de exclusão (caso seja utilizado)
        function excluirAnalise(id) {
            document.getElementById("id_excluir").value = id;
        }
    </script>
<<<<<<< HEAD

    <!-- Script JS -->
    <script>
      // 3) A função recebe os IDs (idSituacao, idAnalista, etc.) e não as descrições
      function editarAnalise(id, descricao, idSituacao, idAnalista, idSistema, idStatus, hora_ini, hora_fim) {
        // Preenche o campo oculto de ID
        document.getElementById("id_editar").value = id;

        // Preenche a descrição
        document.getElementById("descricao_editar").value = descricao;
        
        // Atribui os IDs diretamente aos selects do modal
        document.getElementById("situacao_editar").value = idSituacao; 
        document.getElementById("analista_editar").value = idAnalista;
        document.getElementById("sistema_editar").value = idSistema;
        document.getElementById("status_editar").value = idStatus;
        
        // Preenche as datas/horas
        document.getElementById("hora_ini_editar").value = hora_ini;
        document.getElementById("hora_fim_editar").value = hora_fim;
      }

      function excluirAnalise(id) {
        // Preenche o campo oculto de ID
        document.getElementById("id_excluir").value = id;
      }
    </script>

=======
 
>>>>>>> 450d676418ee17a653e3a37fb2957159b67c3b96
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
