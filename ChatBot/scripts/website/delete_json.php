<?php
// ChatBot/scripts/website/delete_json.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../Includes/auth.php';

function norm(string $p): string {
  $p = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $p);
  return rtrim($p, DIRECTORY_SEPARATOR);
}
function is_path_inside(string $path, string $base): bool {
  $rp = realpath($path);
  $rb = realpath($base);
  if ($rp === false || $rb === false) return false;
  $rp = strtolower(norm($rp));
  $rb = strtolower(norm($rb));
  return strncmp($rp, $rb, strlen($rb)) === 0 && (strlen($rp) === strlen($rb) || $rp[strlen($rb)] === DIRECTORY_SEPARATOR);
}
function b64url_decode(string $s): string {
  $s = strtr($s, '-_', '+/');
  $pad = strlen($s) % 4;
  if ($pad) $s .= str_repeat('=', 4 - $pad);
  return base64_decode($s);
}

$p = $_REQUEST['p'] ?? ''; // aceita GET/POST
if (!$p) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Parâmetro ausente.']);
  exit;
}
$abs = b64url_decode($p);
$chatbotDir = realpath(__DIR__ . '/../../..');
$baseSites  = $chatbotDir . DIRECTORY_SEPARATOR . 'embeddings' . DIRECTORY_SEPARATOR . 'sites';

if (!is_path_inside($abs, $baseSites) || !is_file($abs)) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'message'=>'Acesso negado.']);
  exit;
}

try {
  $ok = @unlink($abs);
  if (!$ok) {
    throw new Exception('Não foi possível excluir o arquivo.');
  }
  echo json_encode(['ok'=>true,'message'=>'Arquivo excluído com sucesso.','file'=>basename($abs)]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
}
