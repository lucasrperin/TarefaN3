<?php
require_once realpath(__DIR__ . '/../vendor/autoload.php');

$pdo = new PDO("mysql:host=localhost;dbname=TarefaN3;charset=utf8mb4", "root", "");

// Pega a planilha ativa:
$stmt = $pdo->query("SELECT spreadsheet_id FROM TB_PLANILHA_SUPORTE WHERE ativo = 1 LIMIT 1");
$row = $stmt->fetch();
$spreadsheetId = $row['spreadsheet_id'];

// Configuração Google Client
$client = new Google_Client();
$client->setApplicationName('TarefaN3 Dashboard Suporte');
$client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
$client->setAuthConfig(__DIR__ . '/../credentials.json');
$client->setAccessType('offline');

$service = new Google_Service_Sheets($client);
$range = 'Dados Completos!A1:Z500';  // Ajuste se precisar

$response = $service->spreadsheets_values->get($spreadsheetId, $range);
$values = $response->getValues();

if (empty($values)) {
    echo "Nenhum dado encontrado na planilha.";
    exit;
}
?>



<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Suporte</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container mt-5">
    <h2>Dashboard Suporte</h2>
    <div class="row mt-4">
        <!-- Filtros -->
        <div class="col-md-4">
            <label>Atendente:</label>
            <select id="filtroAtendente" class="form-select">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="col-md-4">
            <label>Nível:</label>
            <select id="filtroNivel" class="form-select">
                <option value="">Todos</option>
                <option value="Nível 1">Nível 1</option>
                <option value="Nível 2">Nível 2</option>
            </select>
        </div>
    </div>

    <!-- Gráfico -->
    <div class="row mt-5">
        <div class="col-md-12">
            <canvas id="graficoAtendimentos"></canvas>
        </div>
    </div>
</div>

<script>
    const dados = <?php echo json_encode($values); ?>;
    const cabecalho = dados[0];
    const linhas = dados.slice(1);

    // Popula select de atendentes
    const atendentes = [...new Set(linhas.map(l => l[1]).filter(nome => nome && nome.trim() !== ""))];
    atendentes.forEach(at => {
        document.getElementById('filtroAtendente').innerHTML += `<option value="${at}">${at}</option>`;
    });

    function renderizarGrafico() {
        const ctx = document.getElementById('graficoAtendimentos').getContext('2d');
        const filtroAtendente = document.getElementById('filtroAtendente').value;
        const filtroNivel = document.getElementById('filtroNivel').value;

        const dadosFiltrados = linhas.filter(l => {
            const nivel = l[0] || '';
            const atendente = l[1] || '';
            return (filtroNivel === '' || nivel === filtroNivel) &&
                   (filtroAtendente === '' || atendente === filtroAtendente);
        });

        const labels = dadosFiltrados.map(l => l[1]);
        const valores = dadosFiltrados.map(l => parseInt(l[3]) || 0); // Qtd. Atendi. na coluna 3

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Qtd. Atendimentos',
                    data: valores,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)'
                }]
            }
        });
    }

    document.getElementById('filtroAtendente').addEventListener('change', renderizarGrafico);
    document.getElementById('filtroNivel').addEventListener('change', renderizarGrafico);

    renderizarGrafico();
</script>
</body>
</html>
