<?php
// history.php — só API de JSON, sem debug nem scripts
header('Content-Type: application/json; charset=utf-8');
session_start();

// 1) valida sessão
if (!isset($_SESSION['usuario_id']) || !is_numeric($_SESSION['usuario_id'])) {
  http_response_code(401);
  echo json_encode(['error'=>'Usuário não autenticado']);
  exit;
}
$userId = (int) $_SESSION['usuario_id'];

// 2) conecta no Postgres (pooler transaction)
$connStr = sprintf(
  "host=%s port=%s dbname=%s user=%s password=%s options='-c pool_mode=transaction'",
  'aws-0-sa-east-1.pooler.supabase.com',
  '6543',
  'postgres',
  'postgres.lyfueqcjqsznblxlaoej',
  'ZucchettiIA@1'
);
$db = pg_connect($connStr);
if (!$db) {
  http_response_code(500);
  echo json_encode(['error'=>'Falha conexão: '.pg_last_error()]);
  exit;
}

// 3) busca 15 últimas mensagens DO USUÁRIO
//    * substitua "session_id" por "user_id" se você criar essa coluna
// 3) busca 15 últimas mensagens DO USUÁRIO
$sql = <<<SQL
WITH last_humans AS (
  SELECT id
  FROM public.n8n_chat_histories
  WHERE session_id = \$1
    AND (message->>'type') = 'human'
  ORDER BY id DESC
  LIMIT 15
),
cut AS (
  SELECT MIN(id) AS min_id
  FROM last_humans
)
SELECT message
FROM public.n8n_chat_histories, cut    -- <— aqui inclui a CTE "cut"
WHERE session_id = \$1
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
