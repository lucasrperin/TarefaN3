<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}
require '../Config/Database.php';

// Definir o cargo do usuário (opcional)
$usuario_id = $_SESSION['usuario_id'];
$cargo = isset($_SESSION['cargo']) ? $_SESSION['cargo'] : '';

// Carrega todos os critérios da TB_CRITERIOS para montar um mapa: nome => id e obter os pesos
$sqlCriteria = "SELECT id, nome, peso FROM TB_CRITERIOS";
$resultCriteria = $conn->query($sqlCriteria);
$criteriaMap = [];
$pesosMap = [];
if ($resultCriteria && $resultCriteria->num_rows > 0) {
    while ($row = $resultCriteria->fetch_assoc()) {
        $criteriaMap[$row['nome']] = $row['id'];
        $pesosMap[$row['id']] = $row['peso'];
    }
}

// Array de nomes amigáveis para os indicadores
$friendlyNames = [
    'boa_relacao_colegas' => 'Boa Relação com Colegas',
    'participacao_novos_projetos' => 'Participação Novos Projetos',
    'engajamento_supervisao' => 'Engajamento Supervisão',
    'uso_celular' => 'Uso do Celular',
    'engajamento_cultura' => 'Engajamento Cultura',
    'pontualidade' => 'Pontualidade',
    'criacao_novos_projetos' => 'Criação Novos Projetos',
    'elogios_atendimento_externo' => 'Elogios de Atendimento Externo',
    'nota_1_plausivel' => 'Nota 1 Plausível',
    'elogio_interno_auxilio' => 'Elogio Interno de Auxílio',
    'retorno_analise' => 'Retorno de Análise',
    'elogio_atendimento_externo_conversao' => 'Elogio de Atendimento Externo',
    'retorno_conversao' => 'Retorno de Conversão'
];

// Consulta para carregar os usuários com seu nível
$sql = "SELECT u.Id, u.Nome, 
       (SELECT n.descricao FROM TB_EQUIPE_NIVEL_ANALISTA e 
        LEFT JOIN TB_NIVEL n ON e.idNivel = n.id 
        WHERE e.idUsuario = u.Id LIMIT 1) as nivel 
        FROM TB_USUARIO u 
        LEFT JOIN TB_EQUIPE_NIVEL_ANALISTA e ON e.idUsuario = u.Id
        WHERE e.idNivel not in (6, 7, 8)
        ORDER BY u.Nome";
$result = $conn->query($sql);
$usuarios = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $usuarios[] = $row;
    }
}

// Definição dos indicadores para cada grupo, usando o id obtido de TB_CRITERIOS
$indicadoresAll = [
    isset($criteriaMap['boa_relacao_colegas']) ? $criteriaMap['boa_relacao_colegas'] : 0 => 'Boa Relação com Colegas',
    isset($criteriaMap['participacao_novos_projetos']) ? $criteriaMap['participacao_novos_projetos'] : 0 => 'Participação Novos Projetos',
    isset($criteriaMap['engajamento_supervisao']) ? $criteriaMap['engajamento_supervisao'] : 0 => 'Engajamento Supervisão',
    isset($criteriaMap['uso_celular']) ? $criteriaMap['uso_celular'] : 0 => 'Uso do Celular',
    isset($criteriaMap['engajamento_cultura']) ? $criteriaMap['engajamento_cultura'] : 0 => 'Engajamento Cultura',
    isset($criteriaMap['pontualidade']) ? $criteriaMap['pontualidade'] : 0 => 'Pontualidade',
    isset($criteriaMap['criacao_novos_projetos']) ? $criteriaMap['criacao_novos_projetos'] : 0 => 'Criação Novos Projetos'
];
$indicadoresNivel1 = [
    isset($criteriaMap['elogios_atendimento_externo']) ? $criteriaMap['elogios_atendimento_externo'] : 0 => 'Elogios de Atendimento Externo',
    isset($criteriaMap['nota_1_plausivel']) ? $criteriaMap['nota_1_plausivel'] : 0 => 'Nota 1 Plausível'
];
$indicadoresNivel3 = [
    isset($criteriaMap['elogio_interno_auxilio']) ? $criteriaMap['elogio_interno_auxilio'] : 0 => 'Elogio Interno de Auxílio',
    isset($criteriaMap['retorno_analise']) ? $criteriaMap['retorno_analise'] : 0 => 'Retorno de Análise'
];
$indicadoresConversao = [
    isset($criteriaMap['elogio_atendimento_externo_conversao']) ? $criteriaMap['elogio_atendimento_externo_conversao'] : 0 => 'Elogio de Atendimento Externo',
    isset($criteriaMap['retorno_conversao']) ? $criteriaMap['retorno_conversao'] : 0 => 'Retorno de Conversão'
];

// Query de Ranking (calculando a média ponderada)
$sqlRanking = "
SELECT 
    u.Nome AS usuario_nome,
    SUM(a.valor * c.peso) AS totalPonderado,
    COUNT(*) AS qtdAvaliacoes,
    (SUM(a.valor * c.peso) / COUNT(*)) AS mediaNotas
FROM TB_AVALIACOES a
JOIN TB_USUARIO u ON a.usuario_id = u.Id
JOIN TB_CRITERIOS c ON a.criterio = c.id
GROUP BY a.usuario_id
ORDER BY mediaNotas DESC
";
$resultRanking = $conn->query($sqlRanking);
$ranking = [];
if ($resultRanking && $resultRanking->num_rows > 0) {
    while ($row = $resultRanking->fetch_assoc()) {
        $ranking[] = $row;
    }
}

// Query para listar as avaliações registradas
$sqlAvaliacoes = "
SELECT 
    a.id,
    a.usuario_id,
    u.Nome as usuario_nome,
    a.trimestre,
    a.criterio,
    c.nome as criterio_nome,
    a.valor,
    c.peso as criterio_peso,
    a.created_at
FROM TB_AVALIACOES a
JOIN TB_USUARIO u ON a.usuario_id = u.Id
JOIN TB_CRITERIOS c ON a.criterio = c.id
ORDER BY a.created_at DESC
";
$resultAvaliacoes = $conn->query($sqlAvaliacoes);
$avaliacoes = [];
if ($resultAvaliacoes && $resultAvaliacoes->num_rows > 0) {
    while($row = $resultAvaliacoes->fetch_assoc()){
       $avaliacoes[] = $row;
    }
}

// Query para totalizador de estrelas por critério (soma dos valores por critério)
$sqlTotalizadores = "
SELECT 
    c.nome as criterio_nome,
    SUM(a.valor) as total_estrelas
FROM TB_AVALIACOES a
JOIN TB_CRITERIOS c ON a.criterio = c.id
GROUP BY a.criterio
ORDER BY c.nome
";
$resultTotalizadores = $conn->query($sqlTotalizadores);
$totalizadores = [];
if ($resultTotalizadores && $resultTotalizadores->num_rows > 0) {
    while($row = $resultTotalizadores->fetch_assoc()){
       $totalizadores[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Avaliação de Usuário e Ranking</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <!-- Ícones personalizados -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"> 
  <!-- Fonte personalizada -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <!-- CSS externo -->
  <link rel="stylesheet" href="../Public/destaque.css">
  <!-- JQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
  <!-- Início do layout unificado: Sidebar e Header -->
  <div class="d-flex-wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
      <a class="light-logo" href="destaque.php">
        <img src="../Public/Image/zucchetti_blue.png" width="150" alt="Logo Zucchetti">
      </a>
      <nav class="nav flex-column">
        <a class="nav-link" href="menu.php"><i class="fa-solid fa-house me-2"></i>Home</a>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link active" href="destaque.php"><i class="fa-solid fa-ranking-star me-2"></i>Destaques</a>
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
         <a class="nav-link" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Indicações</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
         <a class="nav-link" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
          <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-calculator me-2"></i>Totalizadores</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin'): ?>
         <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users-gear me-2"></i>Usuários</a>
        <?php endif; ?>
        <?php if ($cargo === 'Admin' || $cargo === 'Comercial' || $cargo === 'Treinamento'): ?>
          <a class="nav-link" href="treinamento.php"><i class="fa-solid fa-calendar-check me-2"></i>Treinamentos</a>
        <?php endif; ?>
      </nav>
    </div>
    <!-- Main Content -->
    <div class="w-100">
      <!-- Header -->
      <div class="header">
        <h3>Avaliação de Usuário e Ranking</h3>
        <div class="user-info">
          <span>Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
          <a href="logout.php" class="btn btn-danger">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
          </a>
        </div>
      </div>
      <!-- Conteúdo principal (permanece inalterado) -->
      <div class="content container-fluid">
        <div class="container my-4">
            <!-- Ranking no topo com totalizador de estrelas por critério à direita -->
            <div class="row section-spacing">
              <div class="col-md-6">
                <div class="card text-center card-ranking flex-grow-1 mb-4">
                    <div class="card-header text-center">Ranking</div>
                    <div class="card-body limitar-altura">  
                        <?php if (count($ranking) > 0): ?>
                            <div class="ranking-scroll">
                                <ul class="list-group">
                                    <?php foreach ($ranking as $index => $rank): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span class="ranking-name">
                                                <?php
                                                if ($index == 0) {
                                                    echo "🥇 ";
                                                } elseif ($index == 1) {
                                                    echo "🥈 ";
                                                } elseif ($index == 2) {
                                                    echo "🥉 ";
                                                } else {
                                                    echo ($index + 1) . "º ";
                                                }
                                                echo htmlspecialchars($rank['usuario_nome']);
                                                ?>
                                            </span>
                                            <span class="badge bg-primary rounded-pill">
                                                <?php echo number_format($rank['mediaNotas'], 2, ',', '.'); ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php else: ?>
                            <p>Nenhum ranking disponível.</p>
                        <?php endif; ?>
                    </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card text-center card-ranking flex-grow-1 mb-4">
                    <div class="card-header text-center">Totalizador de Estrelas</div>
                    <div class="card-body limitar-altura">
                        <?php if(count($totalizadores) > 0): ?>
                            <ul class="list-group">
                                <?php foreach($totalizadores as $tot): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><?php echo isset($friendlyNames[$tot['criterio_nome']]) ? $friendlyNames[$tot['criterio_nome']] : ucwords(str_replace('_', ' ', $tot['criterio_nome'])); ?></span>
                                        <span class="badge bg-secondary rounded-pill"><?php echo $tot['total_estrelas']; ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>Nenhum totalizador disponível.</p>
                        <?php endif; ?>
                    </div>
                </div>
              </div>
            </div>
            
            <!-- Seção de Avaliação de Colaborador -->
            <div class="card-header d-flex align-items-center mb-4">
              <h4 class="mb-0 me-3">Avaliação de Colaborador</h4>
              <div class="ms-auto d-flex justify-content-end gap-2">
                <!-- Botão de Filtro -->
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
                  <i class="fa-solid fa-filter"></i>
                </button>
                <?php if ($cargo === 'Admin' || $cargo === 'User' || $cargo === 'Conversor'): ?>
                  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#avaliacaoModal">
                    <i class="fa-solid fa-plus-circle me-1"></i> Cadastrar
                  </button>
                <?php endif; ?>
              </div>
            </div>
            
            <!-- Modal de Filtro -->
            <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="filterModalLabel">Filtrar Avaliações</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                  </div>
                  <div class="modal-body">
                    <div class="row">
                      <div class="col-md-6">
                        <!-- Campo para selecionar a coluna -->
                        <div class="mb-3">
                          <label for="filterColumn" class="form-label">Coluna</label>
                          <select class="form-select" id="filterColumn">
                            <option value="0">Colaborador</option>
                            <option value="1">Indicador</option>
                            <option value="3">Trimestre</option>
                            <option value="4">Data</option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <!-- Campo para inserir o termo de busca -->
                        <div class="mb-3">
                          <label for="filterValue" class="form-label">Buscar por</label>
                          <input type="text" class="form-control" id="filterValue" placeholder="Digite o valor a buscar">
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="clearFilter">Limpar Filtro</button>
                    <button type="button" class="btn btn-primary" id="applyFilter">Filtrar</button>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Accordion de Avaliações Registradas (exibido em tabela) -->
            <div class="accordion section-spacing" id="accordionAvaliacoes">
              <div class="accordion-item">
                <h2 class="accordion-header" id="headingAvaliacoes">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAvaliacoes" aria-expanded="false" aria-controls="collapseAvaliacoes">
                    Visualizar Avaliações Registradas
                  </button>
                </h2>
                <div id="collapseAvaliacoes" class="accordion-collapse collapse" aria-labelledby="headingAvaliacoes" data-bs-parent="#accordionAvaliacoes">
                  <div class="d-flex justify-content-end mt-2 me-3">
                    <input type="text" id="searchInput" class="form-control ms-2" style="max-width: 200px;" placeholder="Pesquisar...">
                  </div>
                  <div class="accordion-body limitar-altura-avaliacoes">
                    <?php if(count($avaliacoes) > 0): ?>
                      <table class="table table-bordered tabelaEstilizada">
                        <thead>
                          <tr>
                            <th>Colaborador</th>
                            <th>Indicador</th>
                            <th>Avaliação</th>
                            <th>Trimestre</th>
                            <th>Data</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach($avaliacoes as $av): 
                            $nomeIndicador = isset($friendlyNames[$av['criterio_nome']]) ? $friendlyNames[$av['criterio_nome']] : ucwords(str_replace('_', ' ', $av['criterio_nome']));
                            $peso = isset($pesosMap[$av['criterio']]) ? $pesosMap[$av['criterio']] : 1;
                            $corEstrela = ($peso < 0) ? "lightcoral" : "#f7d106";
                            $estrelas = "";
                            for($i=1; $i<=5; $i++){
                                if($i <= $av['valor']){
                                    $estrelas .= '<i class="bi bi-star-fill" style="color: '.$corEstrela.';"></i>';
                                } else {
                                    $estrelas .= '<i class="bi bi-star" style="color: '.$corEstrela.';"></i>';
                                }
                            }
                          ?>
                          <tr>
                            <td><?php echo htmlspecialchars($av['usuario_nome']); ?></td>
                            <td><?php echo htmlspecialchars($nomeIndicador); ?></td>
                            <td><?php echo $estrelas; ?></td>
                            <td><?php echo htmlspecialchars($av['trimestre']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($av['created_at'])); ?></td>
                          </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    <?php else: ?>
                      <p>Nenhuma avaliação registrada.</p>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Modal de Avaliação -->
            <div class="modal fade" id="avaliacaoModal" tabindex="-1" aria-labelledby="avaliacaoModalLabel" aria-hidden="true">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form action="cadastrar_destaque.php" method="POST" id="formAvaliacaoModal">
                    <div class="modal-header">
                      <h5 class="modal-title" id="avaliacaoModalLabel">Cadastrar Avaliação</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                      <!-- Seleção do Usuário -->
                      <div class="mb-3">
                        <label for="usuario_id_modal" class="form-label">Selecione o Usuário:</label>
                        <select name="usuario_id" id="usuario_id_modal" class="form-select" required>
                          <option value="">-- Selecione --</option>
                          <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?php echo $usuario['Id']; ?>" data-nivel="<?php echo htmlspecialchars($usuario['nivel']); ?>">
                              <?php echo htmlspecialchars($usuario['Nome']); ?> <?php echo $usuario['nivel'] ? "({$usuario['nivel']})" : ""; ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <!-- Dropdown de Indicadores -->
                      <div class="mb-3">
                        <label for="indicador" class="form-label">Selecione o Indicador:</label>
                        <select name="criterio" id="indicador" class="form-select" required>
                          <option value="">-- Selecione --</option>
                          <!-- As opções serão definidas via JavaScript -->
                        </select>
                      </div>
                      <!-- Container para as 5 estrelas -->
                      <div class="mb-3" id="starContainer" style="display:none;">
                        <label class="form-label">Avalie:</label>
                        <div class="star-rating">
                          <?php for ($i = 5; $i >= 1; $i--): 
                                  $inputId = "estrela_" . $i;
                          ?>
                            <input type="radio" id="<?php echo $inputId; ?>" name="valor" value="<?php echo $i; ?>" required>
                            <label for="<?php echo $inputId; ?>"><i class="bi bi-star-fill"></i></label>
                          <?php endfor; ?>
                        </div>
                      </div>
                      <!-- Campo Trimestre gerado automaticamente -->
                      <input type="hidden" name="trimestre" id="trimestre_modal">
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                      <button type="submit" class="btn btn-primary">Cadastrar Avaliação</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Fim do layout unificado -->
  
  <script>
    // Evento para aplicar o filtro
    document.getElementById('applyFilter').addEventListener('click', function(){
      var colIndex = parseInt(document.getElementById('filterColumn').value);
      var filterText = document.getElementById('filterValue').value.toLowerCase();
      var tableRows = document.querySelectorAll('.tabelaEstilizada tbody tr');

      tableRows.forEach(function(row) {
        // Seleciona a célula com base no índice da coluna
        var cellText = row.cells[colIndex].innerText.toLowerCase();
        // Se a célula contém o texto buscado, exibe a linha; caso contrário, oculta
        if(cellText.indexOf(filterText) > -1){
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });

      // Fecha o modal após aplicar o filtro
      var filterModalEl = document.getElementById('filterModal');
      var modalInstance = bootstrap.Modal.getInstance(filterModalEl);
      modalInstance.hide();
    });

    // Evento para limpar o filtro e exibir todas as linhas
    document.getElementById('clearFilter').addEventListener('click', function(){
      document.getElementById('filterValue').value = '';
      var tableRows = document.querySelectorAll('.tabelaEstilizada tbody tr');
      tableRows.forEach(function(row) {
        row.style.display = '';
      });
    });
  </script>
  
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
  
  <script>
    // Definição dos indicadores para cada grupo usando os IDs obtidos de TB_CRITERIOS
    var indicadoresAll = {
      <?php 
        foreach($indicadoresAll as $id => $rotulo) {
          echo $id . ': ' . json_encode($rotulo) . ',';
        }
      ?>
    };
    var indicadoresNivel1 = {
      <?php 
        foreach($indicadoresNivel1 as $id => $rotulo) {
          echo $id . ': ' . json_encode($rotulo) . ',';
        }
      ?>
    };
    var indicadoresNivel3 = {
      <?php 
        foreach($indicadoresNivel3 as $id => $rotulo) {
          echo $id . ': ' . json_encode($rotulo) . ',';
        }
      ?>
    };
    var indicadoresConversao = {
      <?php 
        foreach($indicadoresConversao as $id => $rotulo) {
          echo $id . ': ' . json_encode($rotulo) . ',';
        }
      ?>
    };

    // Função para popular o dropdown de indicadores com base no nível do usuário
    function populateIndicadores(nivel) {
      var indicadorSelect = document.getElementById('indicador');
      indicadorSelect.innerHTML = '<option value="">-- Selecione --</option>';
      
      // Sempre adiciona os indicadores do grupo ALL
      for (var key in indicadoresAll) {
        var option = document.createElement('option');
        option.value = key;
        option.text = indicadoresAll[key];
        indicadorSelect.appendChild(option);
      }
      
      // Adiciona os indicadores específicos conforme o nível
      if(nivel === 'Nível 1' || nivel === 'Nível 2' || nivel === 'Exclusivo') {
        for (var key in indicadoresNivel1) {
          var option = document.createElement('option');
          option.value = key;
          option.text = indicadoresNivel1[key];
          indicadorSelect.appendChild(option);
        }
      }
      if(nivel === 'Nível 3') {
        for (var key in indicadoresNivel3) {
          var option = document.createElement('option');
          option.value = key;
          option.text = indicadoresNivel3[key];
          indicadorSelect.appendChild(option);
        }
      }
      if(nivel === 'Conversão') {
        for (var key in indicadoresConversao) {
          var option = document.createElement('option');
          option.value = key;
          option.text = indicadoresConversao[key];
          indicadorSelect.appendChild(option);
        }
      }
    }

    // Ao selecionar um usuário, popula o dropdown de indicadores conforme o nível do usuário
    document.getElementById('usuario_id_modal').addEventListener('change', function(){
      var selectedOption = this.options[this.selectedIndex];
      var nivel = selectedOption.getAttribute('data-nivel');
      populateIndicadores(nivel);
    });

    // Ao selecionar um indicador, exibe o container de estrelas
    document.getElementById('indicador').addEventListener('change', function(){
      if (this.value !== "") {
        document.getElementById('starContainer').style.display = 'block';
      } else {
        document.getElementById('starContainer').style.display = 'none';
      }
    });

    // Quando o modal for aberto, calcula o trimestre atual automaticamente e preenche o campo hidden
    var avaliacaoModal = document.getElementById('avaliacaoModal');
    avaliacaoModal.addEventListener('show.bs.modal', function () {
      var now = new Date();
      var month = now.getMonth(); // 0 a 11
      var year = now.getFullYear();
      var trimestre = "";
      if (month >= 0 && month <= 2) {
        trimestre = "Primeiro Trimestre " + year;
      } else if (month >= 3 && month <= 5) {
        trimestre = "Segundo Trimestre " + year;
      } else if (month >= 6 && month <= 8) {
        trimestre = "Terceiro Trimestre " + year;
      } else if (month >= 9 && month <= 11) {
        trimestre = "Quarto Trimestre " + year;
      }
      document.getElementById('trimestre_modal').value = trimestre;
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
