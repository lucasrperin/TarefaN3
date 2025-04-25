<?php
// 1) Debug ativo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// 2) Inclui conexão (usa __DIR__ para evitar erro de caminho)
require __DIR__ . '/../Config/Database.php';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Recebe inputs do formulário
    $contato        = $_POST['contato'];
    $serial         = $_POST['serial'];
    $retrabalho     = $_POST['retrabalho'];
    $sistema_id     = intval($_POST['sistema_id']);
    $status_id      = intval($_POST['status_id']);
    $analista_id    = $_POST['analista_id'];
    $observacao     = $_POST['observacao'];

    // 2) Formata data_recebido e calcula prazo_entrega = data_recebido + 3 dias
    $data_recebido_raw       = str_replace('T', ' ', $_POST['data_recebido']);
    $date                   = new DateTime($data_recebido_raw);
    $date->add(new DateInterval('P3D'));
    $prazo_entrega          = $date->format('Y-m-d H:i:s');
    $data_recebido          = $date = null; // evitar confusão de variáveis
    $data_recebido          = $data_recebido_raw;

    // 3) Define data_inicio:
    //    - Se for status 3 (Análise), atribui NOW()
    //    - Senão, usa valor vindo do form (caso tenha) ou NULL
    // IDs de status que não devem ganhar data_inicio automática
    $skipStart = [1, 4, 5];

    if (! in_array($status_id, $skipStart)) {
        // para QUALQUER status diferente de 4, 1 ou 2:
        $data_inicio = date('Y-m-d H:i:s');
    } else {
        // se vier do form, formata; senão, deixa NULL
        if (! empty($_POST['data_inicio'])) {
            $tmp = str_replace('T', ' ', $_POST['data_inicio']);
            $data_inicio = substr($tmp, 0, 16) . ':00';
        } else {
            $data_inicio = null;
        }
    }

    // 4) Define data_conclusao (se veio, ajusta; senão NULL)
    if (!empty($_POST['data_conclusao'])) {
        $tmp = str_replace('T', ' ', $_POST['data_conclusao']);
        $data_conclusao = substr($tmp, 0, 16) . ':00';
    } else {
        $data_conclusao = null;
    }

    // 5) Prepara e executa o INSERT (mantendo o cálculo de tempo total via TIMEDIFF)
    $query = "
        INSERT INTO TB_CONVERSOES 
        (contato, serial, retrabalho, sistema_id, prazo_entrega, status_id, 
         data_recebido, data_inicio, data_conclusao, analista_id, observacao) 
        VALUES 
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        'sssisssssss',
        $contato,
        $serial,
        $retrabalho,
        $sistema_id,
        $prazo_entrega,
        $status_id,
        $data_recebido,
        $data_inicio,
        $data_conclusao,
        $analista_id,
        $observacao
    
    );

    if (!$stmt->execute()) {
        die("Erro ao cadastrar conversão: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

    // 6) Redireciona de volta para a tela de conversão
    header("Location: ../Views/conversao.php?success=1");
    exit();
}
?>
