<?php
// folga.php
include '../Config/Database.php';  // Inclui a conexão em $conn
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1) Carrega a lista de usuários para o SELECT de cadastro
$sqlUsuarios = "SELECT Id, Nome FROM TB_USUARIO ORDER BY Nome";
$resultUsuarios = $conn->query($sqlUsuarios);
if (!$resultUsuarios) {
    die("Erro ao buscar usuários: " . $conn->error);
}

// 2) Processa o formulário de CADASTRO (POST) e redireciona (PRG)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cadastrar') {
    $usuario_id    = $_POST['usuario_id']    ?? '';
    $tipo          = $_POST['tipo']          ?? '';
    $data_inicio   = $_POST['data_inicio']   ?? '';
    $data_fim      = $_POST['data_fim']      ?? '';
    $justificativa = $_POST['justificativa'] ?? '';  // pode ser vazio se for Férias

    if (!empty($data_inicio) && !empty($data_fim)) {
        $dtInicio = strtotime($data_inicio);
        $dtFim    = strtotime($data_fim);
        if ($dtInicio !== false && $dtFim !== false && $dtFim >= $dtInicio) {
            // +1 para incluir o dia de início
            $diffSegundos    = $dtFim - $dtInicio;
            $quantidade_dias = floor($diffSegundos / 86400) + 1;
        } else {
            $quantidade_dias = 0;
        }

        // Se a quantidade de dias for válida, insere
        if ($quantidade_dias >= 1) {
            $sqlInsert = "INSERT INTO TB_FOLGA 
                            (usuario_id, tipo, data_inicio, data_fim, quantidade_dias, justificativa)
                          VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sqlInsert);
            if ($stmt) {
                $stmt->bind_param("isssis", 
                    $usuario_id, 
                    $tipo, 
                    $data_inicio, 
                    $data_fim, 
                    $quantidade_dias,
                    $justificativa
                );
                $stmt->execute();
                $stmt->close();
            } else {
                echo "Erro na preparação da query: " . $conn->error;
            }
        }
    }
    // Redireciona para evitar reenvio do formulário ao atualizar
    header("Location: folga.php");
    exit();
}

// 3) Lista os registros separadamente para Férias e Folga
$sqlListarFerias = "
    SELECT f.id,
           f.usuario_id,
           u.Nome AS nome_colaborador,
           f.data_inicio,
           f.data_fim,
           f.quantidade_dias
      FROM TB_FOLGA f
      JOIN TB_USUARIO u ON f.usuario_id = u.Id
     WHERE f.tipo = 'Férias'
  ORDER BY f.id DESC
";
$resultFerias = $conn->query($sqlListarFerias);

$sqlListarFolga = "
    SELECT f.id,
           f.usuario_id,
           u.Nome AS nome_colaborador,
           f.data_inicio,
           f.data_fim,
           f.quantidade_dias,
           f.justificativa
      FROM TB_FOLGA f
      JOIN TB_USUARIO u ON f.usuario_id = u.Id
     WHERE f.tipo = 'Folga'
  ORDER BY f.id DESC
";
$resultFolga = $conn->query($sqlListarFolga);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Controle de Férias e Folgas</title>

  <!-- Bootstrap 5 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">

  <!-- Flatpickr CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

  <!-- Ícones Font Awesome (opcional) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

  <style>
    .flatpickr-calendar {
      z-index: 9999;
    }
  </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
  <div class="container d-flex justify-content-between align-items-center">
    <div class="dropdown">
      <button class="navbar-toggler" type="button" data-bs-toggle="dropdown">
        <span class="navbar-toggler-icon"></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-dark">
        <li><a class="dropdown-item" href="conversao.php"><i class="fa-solid fa-right-left me-2"></i>Conversões</a></li>
        <li><a class="dropdown-item" href="Views/escutas.php"><i class="fa-solid fa-headphones me-2"></i>Escutas</a></li>
        <li><a class="dropdown-item" href="incidente.php"><i class="fa-solid fa-exclamation-triangle me-2"></i>Incidentes</a></li>
        <li><a class="dropdown-item" href="indicacao.php"><i class="fa-solid fa-hand-holding-dollar me-1"></i>Indicações</a></li>
        <li><a class="dropdown-item" href="../index.php"><i class="fa-solid fa-layer-group me-2"></i>Nível 3</a></li>
        <li><a class="dropdown-item" href="dashboard.php"><i class="fa-solid fa-calculator me-2 ms-1"></i>Totalizadores</a></li>
      </ul>
    </div>
    <span class="text-white">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</span>
    <a href="menu.php" class="btn btn-danger">
      <i class="fa-solid fa-arrow-left me-2" style="font-size: 0.8em;"></i>Voltar
    </a>
  </div>
</nav>
<div class="container my-5">
  <h1 class="mb-4">Controle de Férias e Folgas</h1>

  <!-- Botão que abre o modal de cadastro -->
  <div class="d-flex justify-content-end mb-3">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCadastro">
      <i class="fa-solid fa-plus-circle me-2"></i> Cadastrar
    </button>
  </div>

  <!-- Modal de cadastro -->
  <div class="modal fade" id="modalCadastro" tabindex="-1" aria-labelledby="modalCadastroLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content shadow">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCadastroLabel">Cadastrar Folga/Férias</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <form method="post" action="">
          <input type="hidden" name="acao" value="cadastrar">
          <div class="modal-body">
            <!-- Linha para Colaborador e Tipo -->
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="usuario_id" class="form-label fw-semibold">Colaborador:</label>
                <select class="form-select" name="usuario_id" id="usuario_id" required>
                  <option value="">Selecione</option>
                  <?php
                  // Reposiciona o ponteiro do $resultUsuarios para listar novamente
                  $resultUsuarios->data_seek(0);
                  while($rowU = $resultUsuarios->fetch_assoc()):
                  ?>
                    <option value="<?php echo $rowU['Id']; ?>">
                      <?php echo $rowU['Nome']; ?>
                    </option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label for="tipo" class="form-label fw-semibold">Tipo:</label>
                <select class="form-select" name="tipo" id="tipo">
                  <option value="Férias">Férias</option>
                  <option value="Folga">Folga</option>
                </select>
              </div>
            </div>

            <!-- Calendário -->
            <div class="row mb-3">
              <div class="col text-center">
                <label class="form-label fw-semibold">Selecione o período:</label>
              </div>
            </div>
            <div class="row justify-content-center mb-3">
              <div class="col-auto">
                <div id="calendarioInline" class="border rounded p-2"></div>
              </div>
            </div>

            <!-- Inputs ocultos para enviar as datas -->
            <input type="hidden" name="data_inicio" id="data_inicio">
            <input type="hidden" name="data_fim" id="data_fim">

            <!-- Campo Justificativa (exibido somente se tipo == Folga) -->
            <div class="row mb-3" id="justificativaGroup" style="display: none;">
              <label for="justificativa" class="form-label fw-semibold">Justificativa:</label>
              <textarea class="form-control" name="justificativa" id="justificativa" rows="3"
                placeholder="Descreva a justificativa da folga"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            <button type="submit" class="btn btn-primary">Salvar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal de Edição (toda a lógica do modal está aqui, mas o update é feito em editar_folga.php) -->
  <div class="modal fade" id="modalEditar" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content shadow">
        <div class="modal-header">
          <h5 class="modal-title" id="modalEditarLabel">Editar Folga/Férias</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <!-- Form que envia para editar_folga.php -->
        <form method="post" action="editar_folga.php">
          <div class="modal-body">
            <input type="hidden" name="id" id="edit_id">
            <!-- Linha para Colaborador e Tipo -->
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="edit_usuario_id" class="form-label fw-semibold">Colaborador:</label>
                <?php
                  // Buscando novamente a lista de usuários
                  $sqlUsuarios2 = "SELECT Id, Nome FROM TB_USUARIO ORDER BY Nome";
                  $resultUsuarios2 = $conn->query($sqlUsuarios2);
                ?>
                <select class="form-select" name="usuario_id" id="edit_usuario_id" required>
                  <option value="">Selecione</option>
                  <?php while($user = $resultUsuarios2->fetch_assoc()): ?>
                    <option value="<?php echo $user['Id']; ?>"><?php echo $user['Nome']; ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label for="edit_tipo" class="form-label fw-semibold">Tipo:</label>
                <select class="form-select" name="tipo" id="edit_tipo" required>
                  <option value="Férias">Férias</option>
                  <option value="Folga">Folga</option>
                </select>
              </div>
            </div>

            <!-- Calendário de edição -->
            <div class="row mb-3">
              <div class="col text-center">
                <label class="form-label fw-semibold">Selecione o período:</label>
              </div>
            </div>
            <div class="row justify-content-center mb-3">
              <div class="col-auto">
                <div id="calendarioInlineEdit" class="border rounded p-2"></div>
              </div>
            </div>
            <input type="hidden" name="data_inicio" id="edit_data_inicio">
            <input type="hidden" name="data_fim" id="edit_data_fim">

            <!-- Campo Justificativa (exibido somente se tipo == Folga) -->
            <div class="row mb-3" id="justificativaGroupEdit" style="display: none;">
              <label for="edit_justificativa" class="form-label fw-semibold">Justificativa:</label>
              <textarea class="form-control" name="justificativa" id="edit_justificativa" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Listagem dos registros lado a lado -->
  <div class="row g-4">
    <!-- Card de Férias -->
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white">
          <h4 class="mb-0">Férias</h4>
        </div>
        <div class="card-body p-0">
          <table class="table table-hover table-striped mb-0">
            <thead class="table-light">
              <tr>
                <th>Colaborador</th>
                <th>Data Início</th>
                <th>Data Fim</th>
                <th>Dias</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($resultFerias && $resultFerias->num_rows > 0): ?>
                <?php while($row = $resultFerias->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo $row['nome_colaborador']; ?></td>
                    <td><?php echo date("d/m/Y", strtotime($row['data_inicio'])); ?></td>
                    <td><?php echo date("d/m/Y", strtotime($row['data_fim'])); ?></td>
                    <td><?php echo $row['quantidade_dias']; ?></td>
                    <td>
                        <div class="d-flex flex-column align-items-start">
                            <!-- Botão Editar -->
                            <button 
                            class="btn btn-sm btn-outline-primary editar-btn" 
                            data-bs-toggle="modal" 
                            data-bs-target="#modalEditar"
                            data-id="<?php echo $row['id']; ?>"
                            data-usuarioid="<?php echo $row['usuario_id']; ?>"  
                            data-tipo="Férias"
                            data-inicio="<?php echo $row['data_inicio']; ?>"
                            data-fim="<?php echo $row['data_fim']; ?>"
                            data-justificativa=""
                            >
                            <i class="fa-solid fa-pen"></i>
                            </button>

                            <!-- Botão Excluir -->
                            <a 
                            href="deletar_folga.php?id=<?php echo $row['id']; ?>" 
                            class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Confirma a exclusão?');"
                            >
                            <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                        </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" class="text-center text-muted">Nenhum registro de Férias encontrado.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Card de Folga -->
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white">
          <h4 class="mb-0">Folgas</h4>
        </div>
        <div class="card-body p-0">
          <table class="table table-hover table-striped mb-0">
            <thead class="table-light">
              <tr>
                <th>Colaborador</th>
                <th>Data Início</th>
                <th>Data Fim</th>
                <th>Dias</th>
                <th>Justificativa</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($resultFolga && $resultFolga->num_rows > 0): ?>
                <?php while($row = $resultFolga->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo $row['nome_colaborador']; ?></td>
                    <td><?php echo date("d/m/Y", strtotime($row['data_inicio'])); ?></td>
                    <td><?php echo date("d/m/Y", strtotime($row['data_fim'])); ?></td>
                    <td><?php echo $row['quantidade_dias']; ?></td>
                    <td><?php echo nl2br($row['justificativa'] ?? ''); ?></td>
                    <td>
                      <!-- Botão Editar -->
                      <div class="d-flex flex-column align-items-start"> 
                      
                                <button 
                                class="btn btn-sm btn-outline-primary editar-btn"
                                data-bs-toggle="modal" 
                                data-bs-target="#modalEditar"
                                data-id="<?php echo $row['id']; ?>"
                                data-usuarioid="<?php echo $row['usuario_id']; ?>" 
                                data-tipo="Folga"
                                data-inicio="<?php echo $row['data_inicio']; ?>"
                                data-fim="<?php echo $row['data_fim']; ?>"
                                data-justificativa="<?php echo $row['justificativa']; ?>">
                                <i class="fa-solid fa-pen"></i>
                                </button>

                      <!-- Botão Excluir -->
                      <a href="deletar_folga.php?id=<?php echo $row['id']; ?>" 
                         class="btn btn-sm btn-outline-danger"
                         onclick="return confirm('Confirma a exclusão?');">
                        <i class="fa-solid fa-trash"></i>
                      </a>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" class="text-center text-muted">Nenhum registro de Folga encontrado.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap 5 JS (com Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
  let calendarInstance = null;
  let calendarEditInstance = null;

  // Inicializa o Flatpickr ao mostrar o modal de CADASTRO
  const modalCadastro = document.getElementById('modalCadastro');
  modalCadastro.addEventListener('shown.bs.modal', function () {
    if (!calendarInstance) {
      calendarInstance = flatpickr('#calendarioInline', {
        mode: 'range',
        inline: true,
        dateFormat: 'Y-m-d',
        showMonths: 2,
        onChange: function(selectedDates, dateStr, instance) {
          if (selectedDates.length === 2) {
            document.getElementById('data_inicio').value = 
              instance.formatDate(selectedDates[0], 'Y-m-d');
            document.getElementById('data_fim').value = 
              instance.formatDate(selectedDates[1], 'Y-m-d');
          }
        }
      });
    } else {
      calendarInstance.redraw();
    }
  });

  // Mostrar/ocultar campo Justificativa conforme o tipo (no CADASTRO)
  const tipoSelect = document.getElementById('tipo');
  const justificativaGroup = document.getElementById('justificativaGroup');
  tipoSelect.addEventListener('change', function() {
    if (tipoSelect.value === 'Folga') {
      justificativaGroup.style.display = 'block';
    } else {
      justificativaGroup.style.display = 'none';
    }
  });

  // ------------------ MODAL DE EDIÇÃO --------------------
  // Instancia do Flatpickr para edição
  const modalEditar = document.getElementById('modalEditar');
  modalEditar.addEventListener('shown.bs.modal', function() {
    if (!calendarEditInstance) {
      calendarEditInstance = flatpickr('#calendarioInlineEdit', {
        mode: 'range',
        inline: true,
        dateFormat: 'Y-m-d',
        showMonths: 2,
        onChange: function(selectedDates, dateStr, instance) {
          if (selectedDates.length === 2) {
            document.getElementById('edit_data_inicio').value =
              instance.formatDate(selectedDates[0], 'Y-m-d');
            document.getElementById('edit_data_fim').value =
              instance.formatDate(selectedDates[1], 'Y-m-d');
          }
        }
      });
    } else {
      calendarEditInstance.redraw();
    }
  });

 // Ao clicar em Editar, preenchemos o modal com os valores do registro
// Ao clicar em Editar, preenchemos o modal com os valores do registro
document.querySelectorAll('.editar-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    const id = this.getAttribute('data-id');
    const usuarioId = this.getAttribute('data-usuarioid'); // Pega o valor do usuário gravado na TB_FOLGA
    const tipo = this.getAttribute('data-tipo');
    const dataInicio = this.getAttribute('data-inicio');
    const dataFim = this.getAttribute('data-fim');
    const justificativa = this.getAttribute('data-justificativa') || '';

    document.getElementById('edit_id').value = id;
    document.getElementById('edit_usuario_id').value = usuarioId; // Preenche o select com o usuário correto
    document.getElementById('edit_tipo').value = tipo;
    document.getElementById('edit_data_inicio').value = dataInicio;
    document.getElementById('edit_data_fim').value = dataFim;
    document.getElementById('edit_justificativa').value = justificativa;

    // Ajusta exibição do campo justificativa se necessário
    if (tipo === 'Folga') {
      document.getElementById('justificativaGroupEdit').style.display = 'block';
    } else {
      document.getElementById('justificativaGroupEdit').style.display = 'none';
    }

    // Atualiza o Flatpickr do modal de edição, se já inicializado
    if (calendarEditInstance) {
      if (dataInicio && dataFim) {
        calendarEditInstance.setDate([dataInicio, dataFim], true);
      } else {
        calendarEditInstance.clear();
      }
    }
  });
});

</script>
</body>
</html>
