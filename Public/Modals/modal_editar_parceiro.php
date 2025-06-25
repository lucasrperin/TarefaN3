<div class="modal fade" id="modalEditarParceiro" tabindex="-1" aria-labelledby="modalEditarParceiroLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="../Public/Php/editar_parceiro.php" method="POST">
        <input type="hidden" name="id_parceiro" id="editar_id_parceiro">
        <div class="modal-header">
          <h5 class="modal-title" id="modalEditarParceiroLabel">Editar Parceiro</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label for="nome_parceiro_edit" class="form-label">Nome</label>
                <input type="text" class="form-control" name="nome_parceiro" id="nome_parceiro_edit" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="cnpj_cpf_edit" class="form-label">CNPJ / CPF</label>
                    <input type="text" class="form-control" name="cnpj_cpf" id="cnpj_cpf_edit">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="serial_edit" class="form-label">Serial</label>
                    <input type="text" class="form-control" name="serial" id="serial_edit">
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="contato_edit" class="form-label">Contato</label>
                    <input type="text" class="form-control" name="contato" id="contato_edit">
                </div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Salvar alterações</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>
