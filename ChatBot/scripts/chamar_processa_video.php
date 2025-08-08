<?php
// ChatBot/scripts/chamar_processa_video.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
ignore_user_abort(true);
@set_time_limit(0);

// ===== Carrega DB =====
$baseDir = dirname(__DIR__, 2); // .../TarefaN3
$dbFile  = $baseDir . '/Config/Database.php';
if (!file_exists($dbFile)) {
  http_response_code(500);
  echo json_encode(["ok" => false, "message" => "Config de banco não encontrada em $dbFile"], JSON_UNESCAPED_UNICODE);
  exit;
}
require_once $dbFile; // deve expor $conn (mysqli)

// ===== Validação básica =====
$titulo  = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
$link    = isset($_POST['link'])   ? trim($_POST['link'])   : '';
$hasFile = isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK;

if ($titulo === '') {
  http_response_code(400);
  echo json_encode(["ok" => false, "message" => "Informe o título do treinamento."], JSON_UNESCAPED_UNICODE);
  exit;
}
if ((!$hasFile && $link === '') || ($hasFile && $link !== '')) {
  http_response_code(400);
  echo json_encode(["ok" => false, "message" => "Envie apenas ARQUIVO OU LINK (um dos dois)."], JSON_UNESCAPED_UNICODE);
  exit;
}

// ===== Determina origem =====
$origem = $hasFile ? 'upload' : 'url';

// ===== Monta entrada (arquivo local ou link) =====
$entrada = '';
$tmpToDelete = null;

if ($hasFile) {
  $origName = $_FILES['video']['name'] ?? 'video';
  $tmpPath  = $_FILES['video']['tmp_name'] ?? '';

  $destDir = __DIR__ . '/temp_uploads';
  if (!is_dir($destDir)) @mkdir($destDir, 0777, true);
  if (!is_writable($destDir)) {
    http_response_code(500);
    echo json_encode(["ok" => false, "message" => "Sem permissão para escrever em: $destDir"], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $origName);
  $destPath = $destDir . DIRECTORY_SEPARATOR . (uniqid('vid_') . '_' . $safeName);
  if (!@move_uploaded_file($tmpPath, $destPath)) {
    http_response_code(500);
    echo json_encode(["ok" => false, "message" => "Falha ao salvar o arquivo enviado."], JSON_UNESCAPED_UNICODE);
    exit;
  }
  $entrada = $destPath;
  $tmpToDelete = $destPath;
} else {
  $entrada = $link;
}

// ===== Abre registro no histórico (PROCESSANDO) =====
$now = date('Y-m-d H:i:s');
$stmt = $conn->prepare("
  INSERT INTO TB_TREINAMENTOS_BOT (titulo, origem, link, status, data_inicio)
  VALUES (?, ?, ?, 'PROCESSANDO', ?)
");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(["ok" => false, "message" => "Erro no prepare: ".$conn->error], JSON_UNESCAPED_UNICODE);
  exit;
}
$stmt->bind_param('ssss', $titulo, $origem, $link, $now);
if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(["ok" => false, "message" => "Erro ao inserir histórico: ".$stmt->error], JSON_UNESCAPED_UNICODE);
  exit;
}
$treinoId = (int)$stmt->insert_id;
$stmt->close();

// ===== Executa Python =====
$python = stripos(PHP_OS_FAMILY ?? php_uname('s'), 'Windows') !== false ? 'python' : 'python3';
$script = __DIR__ . DIRECTORY_SEPARATOR . 'processa_video.py';

if (!file_exists($script)) {
  // marca erro no histórico
  $err = "Script não encontrado: $script";
  $stmt = $conn->prepare("UPDATE TB_TREINAMENTOS_BOT SET status='ERRO', data_fim=NOW(), log=? WHERE id=?");
  if ($stmt) {
    $stmt->bind_param('si', $err, $treinoId);
    $stmt->execute();
    $stmt->close();
  }

  http_response_code(500);
  echo json_encode(["ok" => false, "id" => $treinoId, "message" => $err], JSON_UNESCAPED_UNICODE);
  exit;
}

// Monta comando
$cmd = $python . ' ' .
       escapeshellarg($script) . ' ' .
       escapeshellarg($entrada) . ' ' .
       escapeshellarg($titulo) . ' 2>&1';

exec($cmd, $outputLines, $ret);
$fullOut = implode("\n", $outputLines);

// limpeza do arquivo temporário (se houver)
if ($tmpToDelete && file_exists($tmpToDelete)) {
  @unlink($tmpToDelete);
}

// ===== Parse do caminho do JSON gerado (se sucesso) =====
$arquivoFs = null;
if ($ret === 0) {
  // pega a última ocorrência de "Arquivo gerado: <caminho>"
  if (preg_match('/Arquivo gerado:\s*(.+)\s*$/mi', $fullOut, $m)) {
    $arquivoFs = trim($m[1]);
  }
}

// Mapeia caminho FS -> URL pública (supõe estrutura /TarefaN3/ChatBot/embeddings/transcricoes/<nome>.json)
$arquivoUrl = null;
if ($arquivoFs && file_exists($arquivoFs)) {
  $nome = basename($arquivoFs);
  $arquivoUrl = '/TarefaN3/ChatBot/embeddings/transcricoes/' . $nome;
}

// ===== Atualiza histórico =====
$status = ($ret === 0) ? 'CONCLUIDO' : 'ERRO';
$stmt = $conn->prepare("
  UPDATE TB_TREINAMENTOS_BOT
     SET status = ?,
         arquivo_json = ?,
         data_fim = NOW(),
         log = ?
   WHERE id = ?
");
if ($stmt) {
  $stmt->bind_param('sssi', $status, $arquivoUrl, $fullOut, $treinoId);
  $stmt->execute();
  $stmt->close();
}

// ===== Respostas JSON amigáveis para o front =====
if ($ret !== 0) {
  http_response_code(500);
  // amostra final do log para toast
  $lines = preg_split("/\r\n|\n|\r/", $fullOut);
  $excerpt = implode("\n", array_slice($lines, max(0, count($lines)-10))); // últimas 10 linhas
  echo json_encode([
    "ok" => false,
    "id" => $treinoId,
    "message" => "Falha no treinamento.",
    "log_excerpt" => $excerpt
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// sucesso
echo json_encode([
  "ok" => true,
  "id" => $treinoId,
  "message" => "Treinado com sucesso!",
  "json_url" => $arquivoUrl
], JSON_UNESCAPED_UNICODE);
