<?php
include '../Config/Database.php'; // Conexão com o banco de dados

// Consultar totalizações
$total_conversoes = $conn->query("SELECT COUNT(*) FROM TB_CONVERSOES WHERE MONTH(data_solicitacao) = MONTH(NOW())")->fetch_row()[0];
$tempo_medio = $conn->query("SELECT SEC_TO_TIME(AVG(TIME_TO_SEC(tempo_total))) FROM TB_CONVERSOES WHERE MONTH(data_solicitacao) = MONTH(NOW())")->fetch_row()[0];

// Buscar conversões
$result = $conn->query("SELECT * FROM TB_CONVERSOES ORDER BY data_solicitacao DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gerenciar Conversões</title>
    <link rel="stylesheet" href="dashboard.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function abrirModalCadastro() {
            $("#modalCadastro").show();
        }
        function abrirModalEdicao(id, email, status, dataSolicitacao, dataFim) {
            $("#edit_id").val(id);
            $("#edit_email_cliente").val(email);
            $("#edit_status").val(status);
            $("#edit_data_solicitacao").val(dataSolicitacao);
            $("#edit_data_fim").val(dataFim);
            $("#modalEdicao").show();
        }
        function fecharModal() {
            $(".modal").hide();
        }
        
                    function salvarCadastro() {
                $.post("cadastrar_conversao.php", $("#formCadastro").serialize(), function(response) {
                    if (response.trim() === "success") {
                        location.reload();
                    } else {
                        alert("Erro ao cadastrar: " + response);
                    }
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    alert("Erro AJAX: " + textStatus + " - " + errorThrown);
                });
            }

            function salvarEdicao() {
                $.post("editar_conversao.php", $("#formEdicao").serialize(), function(response) {
                    if (response.trim() === "success") {
                        location.reload();
                    } else {
                        alert("Erro ao editar: " + response);
                    }
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    alert("Erro AJAX: " + textStatus + " - " + errorThrown);
                });
            }
    </script>
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 5px;
            width: 50%;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gerenciar Conversões</h1>
        <div class="totalizadores">
            <div class="card">Total de conversões este mês: <?= $total_conversoes; ?></div>
            <div class="card">Tempo médio de conversão: <?= $tempo_medio ?: 'N/A'; ?></div>
        </div>
        <button onclick="abrirModalCadastro()">Cadastrar Nova Conversão</button>
        <table>
            <tr>
                <th>ID</th>
                <th>Email Cliente</th>
                <th>Status</th>
                <th>Data Solicitação</th>
                <th>Data Fim</th>
                <th>Tempo Total</th>
                <th>Ações</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id']; ?></td>
                <td><?= $row['email_cliente']; ?></td>
                <td><?= $row['status']; ?></td>
                <td><?= $row['data_solicitacao']; ?></td>
                <td><?= $row['data_fim'] ?: 'Em andamento'; ?></td>
                <td><?= $row['tempo_total'] ?: 'N/A'; ?></td>
                <td>
                    <button onclick="abrirModalEdicao(<?= $row['id']; ?>, '<?= $row['email_cliente']; ?>', '<?= $row['status']; ?>', '<?= $row['data_solicitacao']; ?>', '<?= $row['data_fim']; ?>')">Editar</button>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <!-- Modal Cadastro -->
    <div id="modalCadastro" class="modal">
        <div class="modal-content">
            <h2>Cadastrar Conversão</h2>
            <form id="formCadastro">
                <label>E-mail do Cliente:</label>
                <input type="email" name="email_cliente" required>
                <label>Status:</label>
                <select name="status">
                    <option value="Pendente">Pendente</option>
                    <option value="Em andamento">Em andamento</option>
                    <option value="Concluído">Concluído</option>
                </select>
                <label>Data Solicitação:</label>
                <input type="datetime-local" name="data_solicitacao" required>
                <label>Data Fim:</label>
                <input type="datetime-local" name="data_fim">
                <button type="button" onclick="salvarCadastro()">Salvar</button>
                <button type="button" onclick="fecharModal()">Fechar</button>
            </form>
        </div>
    </div>

    <!-- Modal Edição -->
    <div id="modalEdicao" class="modal">
        <div class="modal-content">
            <h2>Editar Conversão</h2>
            <form id="formEdicao">
                <input type="hidden" id="edit_id" name="id">
                <label>E-mail do Cliente:</label>
                <input type="email" id="edit_email_cliente" name="email_cliente" required>
                <label>Status:</label>
                <select id="edit_status" name="status">
                    <option value="Pendente">Pendente</option>
                    <option value="Em andamento">Em andamento</option>
                    <option value="Concluído">Concluído</option>
                </select>
                <label>Data Solicitação:</label>
                <input type="datetime-local" id="edit_data_solicitacao" name="data_solicitacao" required>
                <label>Data Fim:</label>
                <input type="datetime-local" id="edit_data_fim" name="data_fim">
                <button type="button" onclick="salvarEdicao()">Salvar</button>
                <button type="button" onclick="fecharModal()">Fechar</button>
            </form>
        </div>
    </div>
</body>
</html>
