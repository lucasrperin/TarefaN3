<?php
// history.php — só API de JSON, sem debug nem scripts
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
session_start();

require __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__)); 
$dotenv->load();

$dbHost = $_ENV['PG_HOST_HISTORY'];
$dbPort = $_ENV['PG_PORT_HISTORY'];
$dbName = $_ENV['PG_DATABASE_HISTORY'];
$dbUser = $_ENV['PG_USER_HISTORY'];
$dbPass = $_ENV['PG_PASSWORD_HISTORY'];

/* 0) diagnóstico rápido em produção (log, não echo) */
function jfail(int $code, string $msg) {
  http_response_code($code);
  echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

// 1) valida sessão
if (!isset($_SESSION['usuario_id']) || !is_numeric($_SESSION['usuario_id'])) {
  http_response_code(401);
  echo json_encode(['error'=>'Usuário não autenticado']);
  exit;
}

/* Opcional: garanta string limpa do ID */
$userId = trim((string)$_SESSION['usuario_id']);
if ($userId === '') {
  jfail(401, 'Sessão inválida');
}

/* 2) garante extensão pgsql */
if (!function_exists('pg_connect')) {
  jfail(500, 'Extensão pgsql não está habilitada no PHP (pg_connect ausente)');
}

$userId = (int) $_SESSION['usuario_id'];

// 2) conecta no Postgres (pooler transaction)
$connStr = sprintf(
  "host=%s port=%s dbname=%s user=%s password=%s options='-c pool_mode=transaction'",
  $dbHost, $dbPort, $dbName, $dbUser, $dbPass
);
$db = pg_connect($connStr);
if (!$db) {
  http_response_code(500);
  echo json_encode(['error'=>'Falha conexão: '.pg_last_error()]);
  exit;
}

// 3) busca 15 últimas mensagens DO USUÁRIO
$sql = <<<SQL
WITH last_humans AS (
  SELECT id
  FROM public.n8n_chat_histories
  WHERE (session_id::numeric) = \$1
    AND (message->>'type') = 'human'
  ORDER BY id DESC
  LIMIT 15
),
cut AS (
  SELECT MIN(id) AS min_id
  FROM last_humans
)
SELECT message
FROM public.n8n_chat_histories, cut   
WHERE (session_id::numeric) = \$1
  AND id >= cut.min_id
ORDER BY id ASC;
SQL;


// 4) executa passando o ID da sessão (que aqui é o ID do usuário)
$res = pg_query_params($db, $sql, [$userId]);
if (!$res) {
  http_response_code(500);
  echo json_encode(['error'=>pg_last_error($db)]);
  exit;
}

// 5) monta e devolve JSON puro
$rows = pg_fetch_all_columns($res, 0);
$history = [];
foreach ($rows as $json) {
  $msg = json_decode($json, true);
  if (isset($msg['type'], $msg['content'])) {
    $history[] = ['type'=>$msg['type'], 'content'=>$msg['content']];
  }
}
echo json_encode($history, JSON_UNESCAPED_UNICODE);
