<!-- Modal de Exclusão de Parceiro -->
<div class="modal fade" id="modalExclusaoParceiro" tabindex="-1" aria-labelledby="modalExclusaoParceiroLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../Public/Php/deletar_parceiro.php" method="POST">
                <input type="hidden" id="id_excluir_parceiro" name="id_parceiro">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalExclusaoParceiroLabel">Confirma a Exclusão do Parceiro?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir o parceiro <strong id="excluir_nome_parceiro"></strong>?</p>
                    <div class="text-end">
                        <button type="submit" class="btn btn-success">Sim</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal" aria-label="Close">Não</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
