<?php
include '../Config/Database.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");

// Verifica se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Acesso não autorizado']);
    exit();
}

// Carrega o autoload do Composer – ajuste o caminho conforme necessário
require_once __DIR__ . '/../vendor/autoload.php';
use Twilio\Rest\Client;

// Obtenha as credenciais do Twilio – idealmente via variáveis de ambiente
$sid   = getenv('TWILIO_SID') ?: 'ACfc2e783b722dda5ad310e6cb7480c059';
$token = getenv('TWILIO_TOKEN') ?: '0efaf8abb2d44cd62d4db19f6a292594';
$client = new Client($sid, $token);

// Número do Sandbox do WhatsApp (para testes)
$from = "whatsapp:+14155238886";

// Consulta os clientes ativos com data_conclusao preenchida
$query = "SELECT c.id as cliente_id, c.cliente, c.whatsapp, c.data_conclusao 
          FROM TB_CLIENTES c 
          WHERE c.ativo = 1 
            AND c.data_conclusao IS NOT NULL";
$result = mysqli_query($conn, $query);

$notified = [];
$errors   = [];
$debug    = [];
$today    = new DateTime();

while ($row = mysqli_fetch_assoc($result)) {
    
    // Se não houver número de WhatsApp, pule o cliente
    if (empty($row['whatsapp'])) {
        continue;
    }
    
    try {
        $concluded = new DateTime($row['data_conclusao']);
    } catch (Exception $e) {
        $errors[] = "{$row['cliente']}: data_conclusao inválida ({$row['data_conclusao']})";
        continue;
    }
    
    // Calcula a diferença em dias entre hoje e a data de conclusão
    $diffDays = (int)$today->diff($concluded)->format('%a');
    $debug[] = [
        'cliente'        => $row['cliente'],
        'data_conclusao' => $row['data_conclusao'],
        'diffDays'       => $diffDays
    ];
    
    // Se diffDays for menor que 15, não enviar nada
    if ($diffDays < 15) {
        continue;
    }
    
    $cliente_id = $row['cliente_id'];
    $clientName = $row['cliente'];
    $to = "whatsapp:" . $row['whatsapp'];
    
    // ---------------------------
    // Verificação e envio da notificação de 15 dias
    // ---------------------------
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM TB_NOTIFICACOES WHERE cliente_id = ? AND titulo = 'Treinamento - 15 dias'");
    mysqli_stmt_bind_param($stmt, "i", $cliente_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $cnt15);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    
    // Se nenhuma notificação de 15 dias existe, envie a de 15 dias
    if ($cnt15 == 0) {
        $title = "Treinamento - 15 dias";
        $messageBody = "Olá {$clientName}, como está o funcionamento do sistema após o seu treinamento concluído em {$row['data_conclusao']}? (15 dias)";
        try {
            $message = $client->messages->create(
                $to,
                [
                    "from" => $from,
                    "body" => $messageBody
                ]
            );
            $notified[] = "{$clientName} (15 dias, SID: {$message->sid})";
            
            $stmtInsert = mysqli_prepare($conn, "INSERT INTO TB_NOTIFICACOES (titulo, mensagem, cliente_id) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmtInsert, "ssi", $title, $messageBody, $cliente_id);
            mysqli_stmt_execute($stmtInsert);
            mysqli_stmt_close($stmtInsert);
        } catch (Exception $e) {
            $errors[] = "{$clientName} (15 dias): " . $e->getMessage();
        }
        continue; // Após enviar a notificação de 15 dias, passa para o próximo cliente
    }
    
    // ---------------------------
    // Se já existe a notificação de 15 dias e diffDays >= 30, verificar e enviar a de 30 dias
    // ---------------------------
    if ($diffDays >= 30) {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM TB_NOTIFICACOES WHERE cliente_id = ? AND titulo = 'Treinamento - 30 dias'");
        mysqli_stmt_bind_param($stmt, "i", $cliente_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $cnt30);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        
        if ($cnt30 == 0) {
            $title = "Treinamento - 30 dias";
            $messageBody = "Olá {$clientName}, lembrando que se passaram 30 dias desde a conclusão do seu treinamento em {$row['data_conclusao']}. Como está o funcionamento do sistema? (30 dias)";
            try {
                $message = $client->messages->create(
                    $to,
                    [
                        "from" => $from,
                        "body" => $messageBody
                    ]
                );
                $notified[] = "{$clientName} (30 dias, SID: {$message->sid})";
                
                $stmtInsert = mysqli_prepare($conn, "INSERT INTO TB_NOTIFICACOES (titulo, mensagem, cliente_id) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmtInsert, "ssi", $title, $messageBody, $cliente_id);
                mysqli_stmt_execute($stmtInsert);
                mysqli_stmt_close($stmtInsert);
            } catch (Exception $e) {
                $errors[] = "{$clientName} (30 dias): " . $e->getMessage();
            }
            continue;
        }
    }
    
    // ---------------------------
    // Se já existem notificações de 15 e 30 dias e diffDays >= 45, verificar e enviar a de 45 dias
    // ---------------------------
    if ($diffDays >= 45) {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM TB_NOTIFICACOES WHERE cliente_id = ? AND titulo = 'Treinamento - 45 dias'");
        mysqli_stmt_bind_param($stmt, "i", $cliente_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $cnt45);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        
        if ($cnt45 == 0) {
            $title = "Treinamento - 45 dias";
            $messageBody = "Olá {$clientName}, se passaram 45 dias desde a conclusão do seu treinamento em {$row['data_conclusao']}. Gostaríamos de saber como está o funcionamento do sistema. (45 dias)";
            try {
                $message = $client->messages->create(
                    $to,
                    [
                        "from" => $from,
                        "body" => $messageBody
                    ]
                );
                $notified[] = "{$clientName} (45 dias, SID: {$message->sid})";
                
                $stmtInsert = mysqli_prepare($conn, "INSERT INTO TB_NOTIFICACOES (titulo, mensagem, cliente_id) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmtInsert, "ssi", $title, $messageBody, $cliente_id);
                mysqli_stmt_execute($stmtInsert);
                mysqli_stmt_close($stmtInsert);
            } catch (Exception $e) {
                $errors[] = "{$clientName} (45 dias): " . $e->getMessage();
            }
        }
    }
}

if (empty($notified) && empty($errors)) {
    $messageToShow = "Nenhuma notificação para ser enviada nesse período.";
} else {
    $messageToShow = "Notificações enviadas.";
}

echo json_encode([
    'status'   => 'success',
    'message'  => $messageToShow,
    'notified' => $notified,
    'errors'   => $errors,
    'debug'    => $debug
]);
exit();
?>
