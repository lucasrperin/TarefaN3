<?php
require 'config/database.php'; 

session_start();

// Verifica se o usuário está logado, se não, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Código para mostrar o conteúdo da página

$sql = "SELECT * FROM TB_ANALISES";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tarefas N3</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Arquivo CSS personalizado -->
    <link rel="stylesheet" href="css/home.css">
</head>
<body class="bg-light">

    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <span class="navbar-brand mb-0 h1">Tarefas N3</span>
            <span class="text-white">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
            <a href="Views/logout.php" class="btn btn-danger">Sair</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="text-center mb-4">Lista de Análises</h2>

        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Descrição</th>
                        <th>Situação</th>
                        <th>Analista</th>
                        <th>Sistema</th>
                        <th>Status</th>
                        <th>Hora Início</th>
                        <th>Hora Fim</th>
                        <th>Total Horas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>". $row["Descricao"]. "</td>";
                            echo "<td>". $row["idSituacao"]. "</td>";
                            echo "<td>". $row["idAnalista"]. "</td>";
                            echo "<td>". $row["idSistema"]. "</td>";
                            echo "<td>". $row["idStatus"]. "</td>";
                            echo "<td>". $row["Hora_ini"]. "</td>";
                            echo "<td>". $row["Hora_fim"]. "</td>";
                            echo "<td>". $row["Total_hora"]. "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8' class='text-center'>Nenhum dado encontrado</td></tr>";
                    }
                    $conn->close();
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>