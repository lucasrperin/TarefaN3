<?php
include '../Config/Database.php';
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id']) && isset($_GET['ajax'])) {
    // requisição AJAX: devolve JSON com todos os campos
    $id = intval($_GET['id']);
    $query = "
      SELECT 
        id,
        cliente,
        cnpjcpf,
        serial,
        horas_adquiridas,
        whatsapp,
        DATE_FORMAT(data_conclusao, '%Y-%m-%d') AS data_conclusao,
        faturamento,
        valor_faturamento
      FROM TB_CLIENTES
      WHERE id = ?
    ";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        echo json_encode([
           'id'               => $row['id'],
           'cliente'          => $row['cliente'],
           'cnpjcpf'          => $row['cnpjcpf'],
           'serial'           => $row['serial'],
           'horas_adquiridas' => $row['horas_adquiridas'],
           'whatsapp'         => $row['whatsapp'],
           'data_conclusao'   => $row['data_conclusao'],
           'faturamento'      => $row['faturamento'],
           'valor_faturamento'=> $row['valor_faturamento']
        ]);
    } else {
        echo json_encode(['error'=>'Cliente não encontrado']);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= isset($_GET['id']) ? "Editar Cliente" : "Cadastrar Cliente" ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h2><?= isset($_GET['id']) ? "Editar Cliente" : "Cadastrar Cliente" ?></h2>
    <form action="<?= $action ?>" method="post">
        <?php if(isset($_GET['id'])): ?>
            <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>
        <div class="mb-3">
            <label for="cliente" class="form-label">Nome do Cliente</label>
            <input type="text" name="cliente" id="cliente" class="form-control" required value="<?= htmlspecialchars($cliente, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="mb-3">
            <label for="cnpjcpf" class="form-label">CNPJ/CPF</label>
            <input type="text" name="cnpjcpf" id="cnpjcpf" class="form-control" value="<?= htmlspecialchars($cnpjcpf, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="mb-3">
            <label for="serial" class="form-label">Serial</label>
            <input type="text" name="serial" id="serial" class="form-control" value="<?= htmlspecialchars($serial, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="mb-3">
            <label for="horas_adquiridas" class="form-label">Horas Adquiridas (minutos)</label>
            <input type="number" name="horas_adquiridas" id="horas_adquiridas" class="form-control" required value="<?= htmlspecialchars($horas_adquiridas, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <button type="submit" class="btn btn-primary"><?= isset($_GET['id']) ? "Atualizar" : "Cadastrar" ?></button>
        <a href="clientes.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
