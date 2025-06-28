<?php
// Antes do SQL: Preencha os SELECTs para filtro
$lista_situacoes = $conn->query("SELECT Id, Descricao FROM TB_SITUACAO WHERE Id not in (1, 2) ORDER BY Descricao ASC");
$lista_sistemas   = $conn->query("SELECT Id, Descricao FROM TB_SISTEMA ORDER BY Descricao ASC");
$lista_status     = $conn->query("SELECT Id, Descricao FROM TB_STATUS ORDER BY Descricao ASC");
$lista_parceiros  = $conn->query("SELECT Id, Nome FROM TB_PARCEIROS WHERE status = 'A' ORDER BY Nome ASC");

// Query base
$sql = "SELECT
    tas.Id as Codigo,
    tas.Descricao as Descricao,
    sit.Descricao as Situacao,
    usu.Nome as Atendente,
    sis.Descricao as Sistema,
    sta.Descricao as Status,
    tas.idSituacao AS idSituacao,
    usu.Nome AS NomeUsuario,
    tas.idSistema AS idSistema,
    tas.idStatus AS idStatus,
    tas.chkParado as Parado,
    tas.chkFicha as Ficha,
    tas.numeroFicha as numeroFicha,
    usu.Cargo as Cargo,
    tas.idParceiro AS idParceiro,
    par.Nome as Parceiro,
    tas.criado_em as criado_em,
    tas.ult_edicao as ult_edicao
FROM TB_ANALISES_PROD tas
    LEFT JOIN TB_SITUACAO sit ON sit.Id = tas.idSituacao
    LEFT JOIN TB_SISTEMA sis ON sis.Id = tas.idSistema
    LEFT JOIN TB_STATUS sta ON sta.Id = tas.idStatus
    LEFT JOIN TB_USUARIO usu ON usu.Id = tas.idUsuario
    LEFT JOIN TB_PARCEIROS par ON par.Id = tas.idParceiro
WHERE 1=1"; // facilita adicionar AND

// Filtros dinâmicos
if (!empty($_GET['data_inicio'])) {
    $sql .= " AND DATE(tas.criado_em) >= '" . $_GET['data_inicio'] . "'";
}
if (!empty($_GET['data_fim'])) {
    $sql .= " AND DATE(tas.criado_em) <= '" . $_GET['data_fim'] . "'";
}
if (!empty($_GET['situacao'])) {
    $sql .= " AND tas.idSituacao = '" . $_GET['situacao'] . "'";
}
if (!empty($_GET['sistema'])) {
    $sql .= " AND tas.idSistema = '" . $_GET['sistema'] . "'";
}
if (!empty($_GET['status'])) {
    $sql .= " AND tas.idStatus = '" . $_GET['status'] . "'";
}
if (!empty($_GET['parceiro'])) {
    $sql .= " AND tas.idParceiro = '" . $_GET['parceiro'] . "'";
}
$sql .= " ORDER BY tas.Id DESC";

$result = $conn->query($sql);
if ($result === false) {
    die("Erro na consulta SQL: " . $conn->error);
}

?>
<div class="table-responsive access-scroll">
    <table id="tabelaAnalisesProd" class="table table-hover modern-table">
        <thead class="thead-light modern-thead">
            <tr>
                <th style="width: 12%">Parceiro</th>
                <th style="width: 17%">Descrição</th>
                <th style="width: 7%">Situação</th>
                <th style="width: 7%">Analista</th>
                <th style="width: 5%">Sistema</th>
                <th style="width: 5%">Status</th>
                <?php if ($cargo === 'Admin' || $cargo === 'Produto') echo '<th style="width: 5%">Ações</th>'; ?>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row["Parceiro"]) ?></td>
                <td class="sobrepor"><?= htmlspecialchars($row["Descricao"]) ?></td>
                <td><?= htmlspecialchars($row["Situacao"]) ?></td>
                <td><?= htmlspecialchars($row["NomeUsuario"]) ?></td>
                <td><?= htmlspecialchars($row["Sistema"]) ?></td>
                <td><?= htmlspecialchars($row["Status"]) ?></td>
                <?php if ($cargo === 'Admin' || $cargo === 'Produto'): ?>
                    <td class="text-center">
                        <!-- Supondo que queira ações, adapte aqui conforme seu padrão -->
                        <button class="btn btn-outline-primary btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#modalEditarAnaliseProduto"
                        onclick="editarAnaliseProduto(
                            '<?= htmlspecialchars($row['Codigo'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['Descricao'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['idSituacao'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['idParceiro'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['idSistema'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['idStatus'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['Ficha'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['numeroFicha'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['Parado'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['criado_em'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['ult_edicao'], ENT_QUOTES) ?>'
                        )">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button class="btn btn-outline-danger btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#modalRemoverAnaliseProduto"
                        onclick="removerAnaliseProduto('<?= htmlspecialchars($row['Codigo'], ENT_QUOTES) ?>')">
                        <i class="fa-solid fa-trash"></i>
                    </button>

                    </td>
                <?php endif; ?>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
function editarAnaliseProduto(
    id, descricao, idSituacao, idParceiro, idSistema, idStatus, chkFicha, numeroFicha, chkParado, criado_em, ult_edicao
) {
    document.getElementById("id_editar_produto").value = id;
    document.getElementById("descricao_editar_produto").value = descricao;
    document.getElementById("situacao_editar_produto").value = idSituacao;
    document.getElementById("parceiro_editar_produto").value = idParceiro;
    document.getElementById("sistema_editar_produto").value = idSistema;
    document.getElementById("status_editar_produto").value = idStatus;
    document.getElementById("chkFicha_editar_produto").checked = (chkFicha === 'S');
    document.getElementById("numeroFicha_editar_produto").value = numeroFicha || "";
    document.getElementById("chkParado_editar_produto").checked = (chkParado === 'S');
    document.getElementById("criado_em_editar_produto").value = criado_em ? criado_em.replace('T', ' ').substring(0, 19) : "";
    document.getElementById("ult_edicao_editar_produto").value = ult_edicao ? ult_edicao.replace('T', ' ').substring(0, 19) : "";

    verificarSituacaoProdutoEditar();
    verificarFichaProdutoEditar();
}


function removerAnaliseProduto(id) {
    document.getElementById("id_remover_produto").value = id;
}
</script>

