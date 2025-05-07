<?php
session_start();
require '../Config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: indicacao.php?erro=2");
    exit();
}

$usuarioId = $_SESSION['usuario_id'];
$cargo     = $_SESSION['cargo'] ?? '';
$id        = mysqli_real_escape_string($conn, $_POST['id']);

// 1) Busca o dono e o status atual
$sqlChk = "SELECT user_id, status FROM TB_INDICACAO WHERE id = '$id'";
$resChk = mysqli_query($conn, $sqlChk);
$rowChk = mysqli_fetch_assoc($resChk);
$owner  = $rowChk['user_id'] ?? null;
$status = $rowChk['status']  ?? '';

// 2) Se for User/Conversor e não for dono, nega permissão
if ($stAtual === 'Faturado' && !in_array($cargo, ['Admin','Comercial'], true)) {
    header("Location: indicacao.php?erro=permission");
    exit();
}

$cargo      = $_SESSION['cargo'] ?? '';
$id         = mysqli_real_escape_string($conn, $_POST['id']);
$plugin_id  = mysqli_real_escape_string($conn, $_POST['plugin_id']);
$data       = mysqli_real_escape_string($conn, $_POST['data']);
$cnpj       = mysqli_real_escape_string($conn, $_POST['editar_cnpj']);
$serial     = mysqli_real_escape_string($conn, $_POST['serial']);
$contato    = mysqli_real_escape_string($conn, $_POST['contato']);
$fone       = mysqli_real_escape_string($conn, $_POST['fone']);
$status     = $_POST['editar_status'] ?? null;          // virá no hidden
$consultor  = $_POST['consultor']      ?? null;         // idem

// garante status atual editável
$row = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT status FROM TB_INDICACAO WHERE id = '$id'")
);
if (!in_array($row['status'], ['Pendente','Faturado','Cancelado'], true)) {
    exit("Status atual não permite edição.");
}

// monta campos que todo mundo pode alterar
$sets = [
    "plugin_id = '$plugin_id'",
    "`data`    = '$data'",
    "cnpj      = '$cnpj'",
    "serial    = '$serial'",
    "contato   = '$contato'",
    "fone      = '$fone'"
];

// se for Cancelado ou Pendente, todo mundo pode alterar o status…
if (in_array($status, ['Cancelado','Pendente'], true)) {
    $statusEsc = mysqli_real_escape_string($conn, $status);
    $sets[] = "status = '$statusEsc'";

    // …e, se for Admin/Comercial, também pode trocar o consultor
    if (in_array($cargo, ['Admin','Comercial'], true) && $consultor) {
        $consEsc = mysqli_real_escape_string($conn, $consultor);
        $sets[]  = "idConsultor = '$consEsc'";
    }
}
// só Admin/Comercial faz o Faturado completo
elseif ($status === 'Faturado' && in_array($cargo, ['Admin','Comercial'], true)) {
    $valorRaw = $_POST['editar_valor']     ?? '';
    $vendaRaw = mysqli_real_escape_string($conn, $_POST['editar_venda'] ?? '');
    $fatDate  = $_POST['data_faturamento'] ?? null;

    $rawValor = $_POST['editar_valor'] ?? '';

    // 1) remove tudo que não for dígito, vírgula ou ponto
    $clean = preg_replace('/[^\d\,\.]/', '', $rawValor);
    // 2) tira separador de milhares
    $clean = str_replace('.', '', $clean);
    // 3) converte vírgula decimal em ponto
    $clean = str_replace(',', '.', $clean);
    // 4) float e formata pra DECIMAL(18,4)
    $valorNum     = floatval($clean);
    $valorFormat  = number_format($valorNum, 4, '.', '');

    $sets[] = "status           = 'Faturado'";
    $sets[] = "idConsultor      = '".mysqli_real_escape_string($conn, $consultor)."'";
    $sets[] = "vlr_total = '$valorFormat'";
    $sets[] = "n_venda          = '$vendaRaw'";
    if ($fatDate) {
        $fatEsc = mysqli_real_escape_string($conn, $fatDate);
        $sets[] = "data_faturamento = '$fatEsc'";
    } else {
        $sets[] = "data_faturamento = NULL";
    }
}

// gera e executa UPDATE
$sql = "UPDATE TB_INDICACAO
        SET ".implode(",\n    ", $sets)."
        WHERE id = '$id'";

if (!mysqli_query($conn, $sql)) {
    exit("Erro ao atualizar: " . mysqli_error($conn));
}

header("Location: indicacao.php?success=2");
exit();
