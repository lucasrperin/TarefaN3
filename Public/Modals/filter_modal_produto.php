<!-- Public/Modals/filter_modal_produto.php -->
<div class="modal fade" id="filterModalProduto" tabindex="-1" aria-labelledby="filterModalProdutoLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form method="GET" action="index.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="filterModalProdutoLabel">Filtro - Setor Produto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="filterColumnProduto" class="form-label">Filtrar por:</label>
                        <select class="form-select" id="filterColumnProduto" name="filterColumnProduto">
                            <option value="parceiro">Parceiro</option>
                            <option value="period">Período</option>
                            <option value="situacao">Situação</option>
                            <option value="sistema">Sistema</option>
                            <option value="status">Status</option>
                        </select>
                    </div>
                    <div id="filterPeriodProduto" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="data_inicio_prod" class="form-label">Data Início:</label>
                                <input type="date" class="form-control" id="data_inicio_prod" name="data_inicio">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="data_fim_prod" class="form-label">Data Fim:</label>
                                <input type="date" class="form-control" id="data_fim_prod" name="data_fim">
                            </div>
                        </div>
                    </div>
                    <div id="filterSituacaoProduto" style="display: none;">
                        <div class="mb-3">
                            <label for="situacao_prod" class="form-label">Situação:</label>
                            <select class="form-select" id="situacao_prod" name="situacao">
                                <option value="">Selecione</option>
                                <?php
                                $res_situacoes = $conn->query("SELECT Id, Descricao FROM TB_SITUACAO ORDER BY Descricao ASC");
                                while ($row = $res_situacoes->fetch_assoc()) {
                                    echo '<option value="' . $row['Id'] . '">' . htmlspecialchars($row['Descricao']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div id="filterSistemaProduto" style="display: none;">
                        <div class="mb-3">
                            <label for="sistema_prod" class="form-label">Sistema:</label>
                            <select class="form-select" id="sistema_prod" name="sistema">
                                <option value="">Selecione</option>
                                <?php
                                $res_sistemas = $conn->query("SELECT Id, Descricao FROM TB_SISTEMA ORDER BY Descricao ASC");
                                while ($row = $res_sistemas->fetch_assoc()) {
                                    echo '<option value="' . $row['Id'] . '">' . htmlspecialchars($row['Descricao']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div id="filterStatusProduto" style="display: none;">
                        <div class="mb-3">
                            <label for="status_prod" class="form-label">Status:</label>
                            <select class="form-select" id="status_prod" name="status">
                                <option value="">Selecione</option>
                                <?php
                                $res_status = $conn->query("SELECT Id, Descricao FROM TB_STATUS ORDER BY Descricao ASC");
                                while ($row = $res_status->fetch_assoc()) {
                                    echo '<option value="' . $row['Id'] . '">' . htmlspecialchars($row['Descricao']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div id="filterParceiroProduto" style="display: none;">
                        <div class="mb-3">
                            <label for="parceiro_prod" class="form-label">Parceiro:</label>
                            <select class="form-select" id="parceiro_prod" name="parceiro">
                                <option value="">Selecione</option>
                                <?php
                                $res_parceiros = $conn->query("SELECT Id, Nome FROM TB_PARCEIROS WHERE status = 'A' ORDER BY Nome ASC");
                                while ($row = $res_parceiros->fetch_assoc()) {
                                    echo '<option value="' . $row['Id'] . '">' . htmlspecialchars($row['Nome']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">Limpar Filtro</button>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    function showProdFilterFields() {
        let sel = document.getElementById("filterColumnProduto").value;
        document.getElementById("filterPeriodProduto").style.display    = (sel === "period") ? "block" : "none";
        document.getElementById("filterSituacaoProduto").style.display  = (sel === "situacao") ? "block" : "none";
        document.getElementById("filterSistemaProduto").style.display   = (sel === "sistema") ? "block" : "none";
        document.getElementById("filterStatusProduto").style.display    = (sel === "status") ? "block" : "none";
        document.getElementById("filterParceiroProduto").style.display  = (sel === "parceiro") ? "block" : "none";
    }
    document.getElementById("filterColumnProduto").addEventListener("change", showProdFilterFields);
    showProdFilterFields(); // executa no load
});
</script>
