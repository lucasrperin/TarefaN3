<?php
include '../Config/Database.php';
session_start();

// Ative o reporting de erros para debug (remova em produção)
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");

// Verifica se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Acesso não autorizado']);
    exit();
}

// Carregue o autoload do Composer – ajuste o caminho conforme sua estrutura
require_once __DIR__ . '/../vendor/autoload.php';
use Twilio\Rest\Client;

// Obtenha as credenciais do Twilio – recomendamos utilizar variáveis de ambiente
$sid   = getenv('TWILIO_SID') ?: 'ACfc2e783b722dda5ad310e6cb7480c059';
$token = getenv('TWILIO_TOKEN') ?: 'edbfb6df7ef4b3182924b3a86d234f0f';
$client = new Client($sid, $token);

// Número de origem para WhatsApp – utilize o número do Sandbox para testes:
$from = "whatsapp:+14155238886";  // Número do Sandbox (não o +18777804236)

// Consulta os clientes ativos que possuem data_conclusao preenchida
$query = "SELECT c.id as cliente_id, c.cliente, c.whatsapp, c.data_conclusao 
          FROM TB_CLIENTES c 
          WHERE c.ativo = 1 
            AND c.data_conclusao IS NOT NULL";
$result = mysqli_query($conn, $query);

$notified = [];
$errors = [];
$debug = [];
$today = new DateTime();

while ($row = mysqli_fetch_assoc($result)) {
    if (empty($row['whatsapp'])) {
        continue;
    }

    try {
        $concluido = new DateTime($row['data_conclusao']);
    } catch (Exception $e) {
        $errors[] = "{$row['cliente']}: data_conclusao inválida ({$row['data_conclusao']})";
        continue;
    }
    
    // Calcula a diferença em dias entre hoje e a data de conclusão
    $diffDays = (int)$today->diff($concluido)->format('%a');
    
    $debug[] = [
        'cliente' => $row['cliente'],
        'data_conclusao' => $row['data_conclusao'],
        'diffDays' => $diffDays
    ];
    
    // Verifica se diffDays está dentro dos intervalos para 15, 30 ou 45 dias com uma margem
    if (($diffDays >= 14 && $diffDays <= 16) ||
        ($diffDays >= 29 && $diffDays <= 31) ||
        ($diffDays >= 44 && $diffDays <= 46)) {

        $to = "whatsapp:" . $row['whatsapp'];
        $messageBody = "Olá {$row['cliente']}, como está o funcionamento do sistema após o seu treinamento concluído em {$row['data_conclusao']}?";
        try {
            $message = $client->messages->create(
                $to,
                [
                    "from" => $from,
                    "body" => $messageBody
                ]
            );
            $notified[] = "{$row['cliente']} (SID: {$message->sid})";
        } catch (Exception $e) {
            $errors[] = "{$row['cliente']}: " . $e->getMessage();
        }
    }
}

echo json_encode([
    'status'   => 'success',
    'message'  => 'Notificações enviadas.',
    'notified' => $notified,
    'errors'   => $errors,
    'debug'    => $debug
]);
exit();
?>
