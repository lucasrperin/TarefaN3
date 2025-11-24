<?php
// Permite requisições de qualquer origem (CORS)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Permite método OPTIONS para preflight CORS (importante para chamadas POST com JSON)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    exit(0);
}

// Recebe o JSON enviado pelo frontend
$input = file_get_contents("php://input");

// URL do webhook do n8n (substitua pelo seu webhook real)
$n8n_webhook_url = 'https://n8n.zucchetti.com.br/webhook/reforma';

// Inicializa curl para encaminhar a requisição
$ch = curl_init($n8n_webhook_url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($input)
]);

// Executa a chamada CURL
$result = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if(curl_errno($ch)) {
    // Se der erro no CURL, responde com erro 500 e mensagem
    http_response_code(500);
    echo json_encode([
        "error" => true,
        "message" => curl_error($ch)
    ]);
} else {
    // Resposta normal do n8n, repassa o código e o corpo
    http_response_code($httpcode);
    echo $result;
}

curl_close($ch);
