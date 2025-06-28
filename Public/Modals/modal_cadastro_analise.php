<div class="modal fade" id="modalCadastroAnalise" tabindex="-1" aria-labelledby="modalCadastroAnaliseLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- Cabeçalho -->
            <div class="modal-header">
                <h5 class="modal-title" id="modalCadastroAnaliseLabel">Nova Análise</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Corpo -->
            <div class="modal-body">
                <form action="Public/Php/cadastrar_analise_produto.php" method="POST">
                    <div class="row mb-1">
                        </div>    
                        <div class="row mb-1">
                            <div class="col-md-12 mb-2">
                                <label for="descricao" class="form-label">Descrição</label>
                                <textarea type="text" class="form-control" id="descricao" name="descricao" maxlength="100" required></textarea>
                            </div>
                        </div>   
                        <div class="row mb-1">    
                            <div class="col-md-6">
                                <label for="situacaoPro" class="form-label">Situação</label>
                                <select class="form-select" id="situacao_produto" name="situacao" required onchange="verificarSituacaoProduto();">
                                    <option value="">Selecione</option>
                                    <?php
                                    $querySituacao = "SELECT Id, Descricao FROM TB_SITUACAO WHERE Id not in (1, 2, 3)";
                                    $resultSituacao = $conn->query($querySituacao);
                                    while ($rowS = $resultSituacao->fetch_assoc()) {
                                        echo "<option value='" . $rowS['Id'] . "'>" . $rowS['Descricao'] . "</option>";
                                    }
                                    ?>
                                </select>
                                <!-- Checkbox e campo de Número da Ficha (inicialmente ocultos) -->
                                <div class="row mt-3" id="fichaContainer_produto" style="display: none;">
                                    <div class="form-check d-flex justify-content-center ms-1">
                                        <input class="form-check-input" type="checkbox" id="chkFicha_produto" name="chkFicha" onchange="verificarFichaProduto()">
                                        <label class="form-check-label" for="chkFicha_produto">Ficha</label>
                                        <input class="form-check-input ms-2" type="checkbox" id="chkParado_produto" name="chkParado" onchange="marcaParadoProduto()">
                                        <label class="form-check-label" for="chkParado_produto">Cliente Parado</label>
                                    </div>
                                </div>

                                <div class="row mb-3" id="numeroFichaContainer_produto" style="display: none;">
                                    <div class="col-md-12">
                                        <label for="numeroFicha_produto" class="form-label">Número da Ficha</label>
                                        <input type="number" class="form-control" id="numeroFicha_produto" name="numeroFicha" pattern="\d+">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="sistema" class="form-label">Sistema</label>
                                <select class="form-select" id="sistema" name="sistema" required>
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
                                <label for="atendente" id="atenTitulo" class="form-label">Parceiro</label>
                                <select class="form-select" id="atendente" name="atendente" required>
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
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-success">Salvar</button>
                        </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>

    function verificarSituacaoProduto() {
        const situacao = document.getElementById("situacao_produto");
        const fichaContainer = document.getElementById("fichaContainer_produto");

        if (!situacao || !fichaContainer) return;

        const situacaoSelecionada = situacao.options[situacao.selectedIndex].text.trim();

        if (situacaoSelecionada === "Analise Setor Produto") {
            fichaContainer.style.display = "block";
        } else {
            fichaContainer.style.display = "none";
            document.getElementById("numeroFichaContainer_produto").style.display = "none";
            document.getElementById("chkFicha_produto").checked = false;
        }
    }

    function verificarFichaProduto() {
        const chkFicha = document.getElementById("chkFicha_produto").checked;
        const numeroFichaContainer = document.getElementById("numeroFichaContainer_produto");
        const numeroFichaInput = document.getElementById("numeroFicha_produto");

        if (chkFicha) {
            numeroFichaContainer.style.display = "block";
            numeroFichaInput.setAttribute("required", "true");
        } else {
            numeroFichaContainer.style.display = "none";
            numeroFichaInput.removeAttribute("required");
            numeroFichaInput.value = "";
        }
    }

    function marcaParadoProduto() {
        const chkParado = document.getElementById("chkParado_produto").checked;
        const chkFicha = document.getElementById("chkFicha_produto");

        if (chkParado) {
            chkFicha.setAttribute("required", "true");
        } else {
            chkFicha.removeAttribute("required");
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const sit = document.getElementById('situacao_produto');
        if (sit) sit.addEventListener('change', verificarSituacaoProduto);

        const chkF = document.getElementById('chkFicha_produto');
        if (chkF) chkF.addEventListener('change', verificarFichaProduto);

        const chkP = document.getElementById('chkParado_produto');
        if (chkP) chkP.addEventListener('change', marcaParadoProduto);
    });


    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('situacao_produto').addEventListener('change', verificarSituacaoProduto);
        document.getElementById('chkFicha_produto').addEventListener('change', verificarFicha);
        document.getElementById('chkParado_produto').addEventListener('change', marcaParado);
    });

</script>
