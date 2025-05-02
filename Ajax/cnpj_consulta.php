<?php
header('Content-Type: application/json');

$cnpj = preg_replace('/\D/','', $_GET['cnpj'] ?? '');
if (!$cnpj || strlen($cnpj) !== 14) {
  http_response_code(400);
  echo json_encode(['status'=>'ERROR','message'=>'CNPJ inválido']);
  exit;
}

$url = "https://www.receitaws.com.br/v1/cnpj/$cnpj";

$ch = curl_init($url);
curl_setopt_array($ch,[
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT        => 8,
  CURLOPT_USERAGENT      => 'Painel‑N3/1.0',
  CURLOPT_SSL_VERIFYPEER => true,
]);
$body   = curl_exec($ch);
$http   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$errMsg = curl_error($ch);
curl_close($ch);

if ($body === false || $http !== 200) {
  http_response_code(500);
  echo json_encode([
    'status'  =>'ERROR',
    'message' => $errMsg ?: "Serviço externo retornou HTTP $http"
  ]);
  exit;
}

echo $body;   // devolve JSON original
