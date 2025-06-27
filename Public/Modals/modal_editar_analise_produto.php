<div class="modal fade" id="modalEditarAnaliseProduto" tabindex="-1" aria-labelledby="modalEditarAnaliseProdutoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- Cabeçalho -->
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarAnaliseProdutoLabel">Editar Análise do Produto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Corpo -->
            <div class="modal-body">
                <form action="Public/Php/editar_analise_produto.php" method="POST">
                    <input type="hidden" id="id_editar_produto" name="id_editar_produto">
                    <div class="row mb-1">
                        <div class="col-md-12 mb-2">
                            <label for="descricao_editar_produto" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao_editar_produto" name="descricao_editar_produto" maxlength="100" required></textarea>
                        </div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-md-6">
                            <label for="situacao_editar_produto" class="form-label">Situação</label>
                            <select class="form-select" id="situacao_editar_produto" name="situacao_editar_produto" required onchange="verificarSituacaoProdutoEditar();">
                                <option value="">Selecione</option>
                                <?php
                                $querySituacao = "SELECT Id, Descricao FROM TB_SITUACAO WHERE Id NOT IN (1, 2)";
                                $resultSituacao = $conn->query($querySituacao);
                                while ($rowS = $resultSituacao->fetch_assoc()) {
                                    echo "<option value='" . $rowS['Id'] . "'>" . $rowS['Descricao'] . "</option>";
                                }
                                ?>
                            </select>
                            <!-- Checkbox e campo de Número da Ficha (inicialmente ocultos) -->
                            <div class="row mt-3" id="fichaContainer_editar_produto" style="display: none;">
                                <div class="form-check d-flex justify-content-center ms-1">
                                    <input class="form-check-input" type="checkbox" id="chkFicha_editar_produto" name="chkFicha_editar_produto" onchange="verificarFichaProdutoEditar()">
                                    <label class="form-check-label" for="chkFicha_editar_produto">Ficha</label>
                                    <input class="form-check-input ms-2" type="checkbox" id="chkParado_editar_produto" name="chkParado_editar_produto" onchange="marcaParadoProdutoEditar()">
                                    <label class="form-check-label" for="chkParado_editar_produto">Cliente Parado</label>
                                </div>
                            </div>
                            <div class="row mb-3" id="numeroFichaContainer_editar_produto" style="display: none;">
                                <div class="col-md-12">
                                    <label for="numeroFicha_editar_produto" class="form-label">Número da Ficha</label>
                                    <input type="number" class="form-control" id="numeroFicha_editar_produto" name="numeroFicha_editar_produto" pattern="\d+">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="sistema_editar_produto" class="form-label">Sistema</label>
                            <select class="form-select" id="sistema_editar_produto" name="sistema_editar_produto" required>
                                <option value="">Selecione</option>
                                <?php
                                $querySistema = "SELECT Id, Descricao FROM TB_SISTEMA ORDER BY Descricao ASC";
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
                            <label for="parceiro_editar_produto" class="form-label">Parceiro</label>
                            <select class="form-select" id="parceiro_editar_produto" name="parceiro_editar_produto" required>
                                <option value="">Selecione</option>
                                <?php
                                $queryParceiro = "SELECT Id, Nome FROM TB_PARCEIROS WHERE status = 'A' ORDER BY Nome ASC";
                                $resultParceiro = $conn->query($queryParceiro);
                                while ($rowA = $resultParceiro->fetch_assoc()) {
                                    echo "<option value='" . $rowA['Id'] . "'>" . $rowA['Nome'] . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="status_editar_produto" class="form-label">Status</label>
                            <select class="form-select" id="status_editar_produto" name="status_editar_produto" required>
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
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <label for="criado_em_editar_produto" class="form-label mb-1 text-secondary small">Criado em</label>
                            <input type="text"
                            class="form-control-plaintext small text-secondary ps-0"
                            id="criado_em_editar_produto"
                            name="criado_em_editar_produto"
                            readonly
                            tabindex="-1"
                            style="background: transparent; border: none;"
                            >
                        </div>
                        <div class="col-md-6">
                            <label for="ult_edicao_editar_produto" class="form-label mb-1 text-secondary small">Última edição</label>
                            <input type="text"
                            class="form-control-plaintext small text-secondary ps-0"
                            id="ult_edicao_editar_produto"
                            name="ult_edicao_editar_produto"
                            readonly
                            tabindex="-1"
                            style="background: transparent; border: none;"
                            >
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function verificarSituacaoProdutoEditar() {
    const situacao = document.getElementById("situacao_editar_produto");
    const fichaContainer = document.getElementById("fichaContainer_editar_produto");

    if (!situacao || !fichaContainer) return;

    const situacaoSelecionada = situacao.options[situacao.selectedIndex].text.trim();

    if (situacaoSelecionada === "Analise Setor Produto") {
        fichaContainer.style.display = "block";
    } else {
        fichaContainer.style.display = "none";
        document.getElementById("numeroFichaContainer_editar_produto").style.display = "none";
        document.getElementById("chkFicha_editar_produto").checked = false;
    }
}
function verificarFichaProdutoEditar() {
    const chkFicha = document.getElementById("chkFicha_editar_produto").checked;
    const numeroFichaContainer = document.getElementById("numeroFichaContainer_editar_produto");
    const numeroFichaInput = document.getElementById("numeroFicha_editar_produto");
    if (chkFicha) {
        numeroFichaContainer.style.display = "block";
        numeroFichaInput.setAttribute("required", "true");
    } else {
        numeroFichaContainer.style.display = "none";
        numeroFichaInput.removeAttribute("required");
        numeroFichaInput.value = "";
    }
}
function marcaParadoProdutoEditar() {
    const chkParado = document.getElementById("chkParado_editar_produto").checked;
    const chkFicha = document.getElementById("chkFicha_editar_produto");
    if (chkParado) {
        chkFicha.setAttribute("required", "true");
    } else {
        chkFicha.removeAttribute("required");
    }
}
// Carregar eventos ao abrir o modal
document.addEventListener('DOMContentLoaded', function () {
    const sit = document.getElementById('situacao_editar_produto');
    if (sit) sit.addEventListener('change', verificarSituacaoProdutoEditar);
    const chkF = document.getElementById('chkFicha_editar_produto');
    if (chkF) chkF.addEventListener('change', verificarFichaProdutoEditar);
    const chkP = document.getElementById('chkParado_editar_produto');
    if (chkP) chkP.addEventListener('change', marcaParadoProdutoEditar);
});
</script>
