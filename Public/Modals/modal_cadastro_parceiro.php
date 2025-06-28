<div class="modal fade" id="modalCadastroParceiro" tabindex="-1" aria-labelledby="modalCadastroParceiroLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- Cabeçalho -->
            <div class="modal-header">
                <h5 class="modal-title" id="modalCadastroParceiroLabel">Cadastrar Novo Parceiro</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Corpo -->
            <div class="modal-body">
                <form action="../Public/Php/cadastrar_parceiro.php" method="POST">
                    <div class="mb-3">
                        <label for="nome_parceiro" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="nome_parceiro" name="nome_parceiro" maxlength="100" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cnpj_cpf" class="form-label">CNPJ / CPF</label>
                            <input type="text" class="form-control" id="cnpj_cpf" name="cnpj_cpf" maxlength="20">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="serial" class="form-label">Serial</label>
                            <input type="text" class="form-control" id="serial" name="serial" maxlength="20">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="contato" class="form-label">Contato</label>
                        <input type="text" class="form-control" id="contato" name="contato" maxlength="100">
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-success">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
