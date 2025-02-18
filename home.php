<?php
require 'config/database.php'; 

session_start();

// Verifica se o usuário está logado, se não, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Código para mostrar o conteúdo da página
$sql = "SELECT 
            tas.Descricao as Descricao,
            sit.Descricao as Situacao,
            tba.Descricao as Analista,
            sis.Descricao as Sistema,
            sta.Descricao as Status,
            tas.Hora_ini,
            tas.Hora_fim,
            tas.Total_hora
        FROM TB_ANALISES tas
            LEFT JOIN tb_situacao sit
                ON sit.Id = tas.idSituacao
            LEFT JOIN tb_analista tba
                ON tba.Id = tas.idAnalista
            LEFT JOIN tb_sistema sis
                ON sis.Id = tas.idSistema
            LEFT JOIN tb_status sta
                ON sta.Id = tas.idStatus
            LEFT JOIN tb_usuario usu
                ON usu.Id = tas.idUsuario
        ORDER BY tas.Id DESC";

$result = $conn->query($sql);

// Verificar se a consulta retornou resultados
if ($result === false) {
    die("Erro na consulta SQL: " . $conn->error); // Caso haja erro na consulta
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
    <link rel="stylesheet" href="Public/home.css">
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
    <?php endif; ?>

    <!-- Botão para abrir o modal de cadastro -->
    <div class="text-center mb-3">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastro">Cadastrar</button>
    </div>

     <!-- Exibição da Lista de Análises -->
     <div class="container mt-4">
        <h2 class="text-center mb-4">Lista de Análises</h2>

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
                    <?php if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                             echo "<tr>";
                             echo "<td>". $row["Descricao"]. "</td>";
                             echo "<td>". $row["Situacao"]. "</td>";
                             echo "<td>". $row["Analista"]. "</td>";
                             echo "<td>". $row["Sistema"]. "</td>";
                             echo "<td>". $row["Status"]. "</td>";
                             echo "<td>". $row["Hora_ini"]. "</td>";
                             echo "<td>". $row["Hora_fim"]. "</td>";
                             echo "<td>". $row["Total_hora"]. "</td>";?>
                            <th>
                                <a class="btn-edit" href="Views/login.php"><i class="fa-sharp fa-solid fa-pen"></i></a>
                                <a class="btn-remove" href="Views/login.php"><i class="fa-solid fa-trash"></i></a>
                            </th>
                            <?php echo "</tr>"; 
                        }
                    } else {
                        echo "<tr><td colspan='8' class='text-center'>Nenhum dado encontrado</td></tr>";
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
                                <input type="text" class="form-control" id="descricao" name="descricao" maxlength=50 required>
                            </div>
                            <div class="col-md-6">
                                <label for="situacao" class="form-label">Situação</label>
                                <select class="form-select" id="situacao" name="situacao" required>
                                    <option value="">Selecione</option>
                                    <?php
                                    $query = "SELECT Id, Descricao FROM TB_SITUACAO";
                                    $result = $conn->query($query);
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<option value='" . $row['Id'] . "'>" . $row['Descricao'] . "</option>";
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
                                    $query = "SELECT Id, Descricao FROM TB_ANALISTA";
                                    $result = $conn->query($query);
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<option value='" . $row['Id'] . "'>" . $row['Descricao'] . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="sistema" class="form-label">Sistema</label>
                                <select class="form-select" id="sistema" name="sistema" required>
                                    <option value="">Selecione</option>
                                    <?php
                                    $query = "SELECT Id, Descricao FROM TB_SISTEMA";
                                    $result = $conn->query($query);
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<option value='" . $row['Id'] . "'>" . $row['Descricao'] . "</option>";
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
                                    $query = "SELECT Id, Descricao FROM TB_STATUS";
                                    $result = $conn->query($query);
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<option value='" . $row['Id'] . "'>" . $row['Descricao'] . "</option>";
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

   

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
