<!-- Modal de Remover Análise Produto -->
<div class="modal fade" id="modalRemoverAnaliseProduto" tabindex="-1" aria-labelledby="modalRemoverAnaliseProdutoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRemoverAnaliseProdutoLabel">Remover Análise Produto</h5>
            </div>
            <div class="modal-body">
                <form action="Public/Php/deletar_analise_produto.php" method="POST">
                    <input type="hidden" id="id_remover_produto" name="id_remover_produto">
                    <p>Tem certeza que deseja remover esta análise do setor Produto?</p>
                    <div class="text-end">
                        <button type="submit" class="btn btn-success">Sim</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Não</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
