<?php 
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['usuario_id'])) {
    $_SESSION['usuario_id'] = $_POST['usuario_id'];
    header("Location: user.php");
    exit();
}
require '../Config/Database.php';

$usuario_id = $_SESSION['usuario_id'];

// Consulta para pegar todas as indicações do mês e ano atuais, incluindo nome do usuário e status
$sql = "
  SELECT i.*, p.nome AS plugin_nome, u.nome AS usuario_nome, i.status
  FROM TB_INDICACAO i
  JOIN TB_PLUGIN p ON i.plugin_id = p.id
  JOIN TB_USUARIO u ON i.user_id = u.id
  WHERE MONTH(i.data) = MONTH(CURRENT_DATE())
    AND YEAR(i.data) = YEAR(CURRENT_DATE())
  ORDER BY i.data DESC
";
$result = mysqli_query($conn, $sql);

// Consulta para o ranking: quantidade de indicações por usuário
$sqlRanking = "
  SELECT u.nome AS usuario_nome, COUNT(i.id) AS total_indicacoes
  FROM TB_INDICACAO i
  JOIN TB_USUARIO u ON i.user_id = u.id
  GROUP BY u.id
  ORDER BY total_indicacoes DESC
";
$resultRanking = mysqli_query($conn, $sqlRanking);
$ranking = array();
while($row = mysqli_fetch_assoc($resultRanking)) {
    $ranking[] = $row;
}

// Consulta para o totalizador por plugin
$sqlPluginsCount = "
  SELECT p.nome AS plugin_nome, COUNT(i.id) AS total_indicacoes
  FROM TB_INDICACAO i
  JOIN TB_PLUGIN p ON i.plugin_id = p.id
  GROUP BY p.id
  ORDER BY total_indicacoes DESC
";
$resultPluginsCount = mysqli_query($conn, $sqlPluginsCount);
$pluginsCount = array();
while($rowPC = mysqli_fetch_assoc($resultPluginsCount)) {
    $pluginsCount[] = $rowPC;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Indicações de Plugins</title>
  <!-- CSS personalizado -->
  <link rel="stylesheet" href="indicacao.css">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Ícones do Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Fonte personalizada -->
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<!-- Carregue primeiro o jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<nav class="navbar navbar-dark bg-dark">
  <div class="container d-flex justify-content-between align-items-center">
    <!-- Botão Hamburguer com Dropdown -->
    <div class="dropdown">
      <button class="navbar-toggler" type="button" id="menuDropdown" data-toggle="dropdown" aria-expanded="false">
        <span class="navbar-toggler-icon"></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="menuDropdown">
        <!-- Aqui você pode colocar as opções do menu -->
      </ul>
    </div>
    <span class="text-white">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
    <a href="menu.php" class="btn btn-danger">
      <i class="fa-solid fa-arrow-left me-2" style="font-size: 0.8em;"></i>Voltar
    </a>
  </div>
</nav>

<div class="container mt-4">
  <!-- Linha para Ranking e Totalizador por Plugin -->
  <div class="row mb-4">
    <!-- Ranking de Indicações -->
    <div class="col-md-3">
      <div class="card card-ranking">
        <div class="card-header text-center">Ranking de Indicações</div>
        <div class="card-body">
          <?php if (count($ranking) > 0): ?>
            <div class="ranking-scroll">
              <ul class="list-group">
                <?php foreach ($ranking as $index => $rank): ?>
                  <li class="list-group-item d-flex align-items-center justify-content-between">
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
                        echo $rank['usuario_nome'];
                      ?>
                    </span>
                    <span class="badge bg-primary rounded-pill ml-auto">
                      <?php echo $rank['total_indicacoes']; ?>
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
    <!-- Totalizador por Plugin -->
    <div class="col-md-3">
      <div class="card">
        <div class="card-header text-center">Total por Plugin</div>
        <div class="card-body">
          <?php if (count($pluginsCount) > 0): ?>
            <div class="ranking-scroll">
              <ul class="list-group">
                <?php foreach ($pluginsCount as $pc): ?>
                  <li class="list-group-item d-flex align-items-center justify-content-between">
                    <span class="ranking-name">
                      <?php echo $pc['plugin_nome']; ?>
                    </span>
                    <span class="badge bg-primary rounded-pill ml-auto">
                      <?php echo $pc['total_indicacoes']; ?>
                    </span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php else: ?>
            <p>Nenhum plugin encontrado.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Card com a lista de indicações -->
  <div class="card shadow mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h4 class="mb-0">Indicações de Plugins</h4>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaIndicacao">
        Cadastrar
      </button>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped table-bordered tabelaEstilizada">
          <thead class="thead-dark">
            <tr>
              <th>Plugin</th>
              <th width="5%">Data</th>
              <th width="13%">CNPJ</th>
              <th width="10%">Serial</th>
              <th>Contato</th>
              <th>Fone</th>
              <th width="10%">Usuário</th>
              <th width="5%">Status</th>
              <th width="5%">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = mysqli_fetch_assoc($result)): ?> 
              <tr>
                <td><?= $row['plugin_nome'] ?></td>
                <td><?= date('d/m/Y', strtotime($row['data'])) ?></td>
                <td><?= $row['cnpj'] ?></td>
                <td><?= $row['serial'] ?></td>
                <td><?= $row['contato'] ?></td>
                <td><?= $row['fone'] ?></td>
                <td><?= $row['usuario_nome'] ?></td>
                <td><?= $row['status'] ?></td>
                <td class="text-center">
                  <div class="d-flex flex-row align-items-center gap-1">
                    <button type="button" 
                            class="btn btn-outline-primary btn-sm "
                            title="Editar"
                            onclick='editarIndicacao(<?= 
                              htmlspecialchars(json_encode($row['id']), ENT_QUOTES, "UTF-8") ?>, 
                              <?= htmlspecialchars(json_encode($row['plugin_id']), ENT_QUOTES, "UTF-8") ?>, 
                              <?= htmlspecialchars(json_encode($row["data"]), ENT_QUOTES, "UTF-8") ?>, 
                              <?= htmlspecialchars(json_encode($row["cnpj"]), ENT_QUOTES, "UTF-8") ?>, 
                              <?= htmlspecialchars(json_encode($row["serial"]), ENT_QUOTES, "UTF-8") ?>, 
                              <?= htmlspecialchars(json_encode($row["contato"]), ENT_QUOTES, "UTF-8") ?>, 
                              <?= htmlspecialchars(json_encode($row["fone"]), ENT_QUOTES, "UTF-8") ?>,
                              <?= htmlspecialchars(json_encode($row["status"]), ENT_QUOTES, "UTF-8") ?>,
                              <?= htmlspecialchars(json_encode($row["vlr_total"]), ENT_QUOTES, "UTF-8") ?>,
                              <?= htmlspecialchars(json_encode($row["n_venda"]), ENT_QUOTES, "UTF-8") ?>
                            )'>
                      <i class="fa-sharp fa-solid fa-pen"></i>
                    </button>
                    <a href="deletar_indicacao.php?id=<?= $row['id'] ?>" 
                        class="btn btn-outline-danger btn-sm"
                        title="Excluir"
                        onclick="return confirm('Tem certeza que deseja excluir esta indicação?');">
                      <i class="fa-sharp fa-solid fa-trash"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal para cadastro de nova indicação -->
<div class="modal fade" id="modalNovaIndicacao" tabindex="-1" role="dialog" aria-labelledby="modalNovaIndicacaoLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <!-- Conteúdo do modal de cadastro (como antes) -->
      <div class="modal-header">
        <h5 class="modal-title" id="modalNovaIndicacaoLabel">Nova Indicação</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="cadastrar_indicacao.php" method="POST">
        <div class="modal-body">
          <!-- Linha 1: Plugin e Data -->
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="plugin_id">Plugin</label>
                <div class="input-group">
                  <select class="form-control" id="plugin_id" name="plugin_id" required>
                    <?php
                    $sqlPlugins = "SELECT * FROM TB_PLUGIN ORDER BY nome";
                    $resPlugins = mysqli_query($conn, $sqlPlugins);
                    while($plugin = mysqli_fetch_assoc($resPlugins)):
                    ?>
                      <option value="<?= $plugin['id'] ?>"><?= $plugin['nome'] ?></option>
                    <?php endwhile; ?>
                  </select>
                  <div class="input-group-append">
                    <button class="btn btn-secondary" type="button" data-toggle="collapse" data-target="#novoPluginCollapse" aria-expanded="false" aria-controls="novoPluginCollapse">
                      Cadastrar
                    </button>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="data">Data</label>
                <input type="date" class="form-control" id="data" name="data" required>
              </div>
            </div>
          </div>
          <!-- Collapse para novo plugin -->
          <div class="collapse mb-3" id="novoPluginCollapse">
            <div class="card card-body">
              <div class="form-group">
                <label for="novo_plugin">Nome do Novo Plugin</label>
                <input type="text" class="form-control" id="novo_plugin" placeholder="Informe o nome do novo plugin">
              </div>
              <button type="button" class="btn btn-info" id="btnCadastrarPlugin">Salvar Plugin</button>
            </div>
          </div>
          <!-- Linha 2: CNPJ e Serial -->
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="cnpj">CNPJ</label>
                <input type="text" class="form-control" id="cnpj" name="cnpj" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="serial">Serial</label>
                <input type="text" class="form-control" id="serial" name="serial" required>
              </div>
            </div>
          </div>
          <!-- Linha 3: Contato e Fone -->
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="contato">Contato</label>
                <input type="text" class="form-control" id="contato" name="contato" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="fone">Fone</label>
                <input type="text" class="form-control" id="fone" name="fone" required>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Cadastrar Indicação</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal para edição de indicação -->
<div class="modal fade" id="modalEditarIndicacao" tabindex="-1" role="dialog" aria-labelledby="modalEditarIndicacaoLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form action="editar_indicacao.php" method="POST">
          <div class="modal-header">
            <h5 class="modal-title" id="modalEditarIndicacaoLabel">Editar Indicação</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <!-- Campo oculto para o ID -->
            <input type="hidden" id="editar_id" name="id">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editar_plugin_id">Plugin</label>
                  <select class="form-control" id="editar_plugin_id" name="plugin_id" required>
                    <?php
                    $sqlPlugins = "SELECT * FROM TB_PLUGIN ORDER BY nome";
                    $resPlugins = mysqli_query($conn, $sqlPlugins);
                    while($plugin = mysqli_fetch_assoc($resPlugins)):
                    ?>
                      <option value="<?= $plugin['id'] ?>"><?= $plugin['nome'] ?></option>
                    <?php endwhile; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editar_data">Data</label>
                  <input type="date" class="form-control" id="editar_data" name="data" required>
                </div>
              </div>
            </div>
            <div class="row mt-2">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editar_cnpj">CNPJ</label>
                  <input type="text" class="form-control" id="editar_cnpj" name="cnpj" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editar_serial">Serial</label>
                  <input type="text" class="form-control" id="editar_serial" name="serial" required>
                </div>
              </div>
            </div>
            <div class="row mt-2">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editar_contato">Contato</label>
                  <input type="text" class="form-control" id="editar_contato" name="contato" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editar_fone">Fone</label>
                  <input type="text" class="form-control" id="editar_fone" name="fone" required>
                </div>
              </div>
            </div>
            <div class="row mt-2">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="editar_status">Status</label>
                  <select name="editar_status" class="form-select" id="editar_status" onchange="verificarStatus()">
                    <option value="Pendente">Pendente</option>
                    <option value="Faturado">Faturado</option>
                    <option value="Cancelado">Cancelado</option>
                  </select>
                </div>
              </div>
              <!-- Container para campos adicionais quando o status for Faturado -->
              <div class="col-md-6" id="faturadoContainer" style="display: none;">
                <div class="form-group">
                  <label for="editar_valor">Valor R$</label>
                  <input type="text" class="form-control" id="editar_valor" name="editar_valor" value="0">
                </div>
                <div class="form-group mt-2">
                  <label for="editar_venda">Nº Venda</label>
                  <input type="text" class="form-control" id="editar_venda" name="editar_venda">
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Função para exibir ou ocultar campos quando o status for "Faturado"
    function verificarStatus() {
        var status = document.getElementById("editar_status");
        var faturadoContainer = document.getElementById("faturadoContainer");
        var valor = document.getElementById("editar_valor");
        var venda = document.getElementById("editar_venda");

        // Pega o texto da opção selecionada
        var statusSelecionado = status.options[status.selectedIndex].text.trim();

        if (statusSelecionado === "Faturado") {
          faturadoContainer.style.display = "block";
          valor.setAttribute("required", "true");
          venda.setAttribute("required", "true");
        } else {
          faturadoContainer.style.display = "none";
          valor.removeAttribute("required");
          venda.removeAttribute("required");
        }
      }
  </script>
  <script>
  // Função que formata uma string de dígitos para o formato "R$X,XXXX"
  function formatCurrency(digits) {
    // Garante que a string tenha pelo menos 4 dígitos para os decimais
    while (digits.length < 4) {
      digits = "0" + digits;
    }
    // Separa a parte inteira e a decimal
    var intPart = digits.slice(0, digits.length - 4);
    var decPart = digits.slice(-4);
    // Remove zeros à esquerda da parte inteira (mas se ficar vazia, define como "0")
    intPart = intPart.replace(/^0+/, "") || "0";
    // Adiciona separador de milhares à parte inteira, se necessário
    intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    return "R$" + intPart + "," + decPart;
  }

  // Atualiza o valor do input conforme os dígitos digitados
  function updateValorField() {
    var input = document.getElementById("editar_valor");
    // Remove tudo que não seja dígito
    var digits = input.value.replace(/\D/g, "");
    input.value = formatCurrency(digits);
  }

  // Configura o comportamento ao carregar a página e quando o modal for aberto
  document.addEventListener("DOMContentLoaded", function() {
    var input = document.getElementById("editar_valor");

    // Se estiver vazio ou sem dígitos, define o padrão "R$0,0000"
    if (!input.value || input.value.replace(/\D/g, "") === "") {
      input.value = formatCurrency("0");
    } else {
      input.value = formatCurrency(input.value.replace(/\D/g, ""));
    }

    // Atualiza conforme o usuário digita
    input.addEventListener("input", updateValorField);

    // Quando o modal de edição for exibido, garante a formatação
    $('#modalEditarIndicacao').on('shown.bs.modal', function () {
      updateValorField();
    });
  });
  </script>

  <script>
  // Script para cadastrar novo plugin via AJAX
  $(document).ready(function(){
      $('#btnCadastrarPlugin').click(function(){
          var novoPlugin = $('#novo_plugin').val().trim();
          if(novoPlugin === ''){
              alert('Informe o nome do novo plugin.');
              return;
          }
          $.ajax({
              url: 'cadastrar_plugin.php',
              type: 'POST',
              data: { nome: novoPlugin },
              dataType: 'json',
              success: function(resp){
                  if (resp.duplicate === true) {
                      alert(resp.message);
                      $('#plugin_id').val(resp.id);
                  } else if (resp.id) {
                      $('#plugin_id').append('<option value="' + resp.id + '">' + resp.nome + '</option>');
                      $('#plugin_id').val(resp.id);
                      alert('Plugin cadastrado com sucesso!');
                  } else {
                      alert('Erro: ' + resp.message);
                  }
                  $('#novo_plugin').val('');
                  $('#novoPluginCollapse').collapse('hide');
              },
              error: function(jqXHR, textStatus, errorThrown){
                  alert('Erro na requisição: ' + errorThrown);
              }
          });
      });
  });
  </script>
  <script>
  // Função para popular o modal de edição com os dados da indicação
      // Agora inclui o status, valor e nº venda
      function editarIndicacao(id, plugin_id, data, cnpj, serial, contato, fone, status, editar_valor, editar_venda) {
        document.getElementById("editar_id").value = id;
        document.getElementById("editar_plugin_id").value = plugin_id;
        document.getElementById("editar_data").value = data;
        document.getElementById("editar_cnpj").value = cnpj;
        document.getElementById("editar_serial").value = serial;
        document.getElementById("editar_contato").value = contato;
        document.getElementById("editar_fone").value = fone;
        document.getElementById("editar_status").value = status;

        // Se o status for Faturado, popula os campos extras
        if (status === "Faturado") {
          document.getElementById("editar_valor").value = editar_valor;
          document.getElementById("editar_venda").value = editar_venda;
        } else {
          document.getElementById("editar_valor").value = "";
          document.getElementById("editar_venda").value = "";
        }
        // Chama a função para ajustar a exibição dos campos
        verificarStatus();

        // Exibe o modal (utilizando jQuery/Bootstrap)
        $('#modalEditarIndicacao').modal('show');
      }
  </script>
</body>
</html>
