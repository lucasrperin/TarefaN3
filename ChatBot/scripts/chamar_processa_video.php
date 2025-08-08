<?php
// ChatBot/scripts/chamar_processa_video.php
header('Content-Type: text/plain; charset=utf-8');

// ===== Carrega DB =====
$baseDir = dirname(__DIR__, 2); // .../TarefaN3
$dbFile  = $baseDir . '/Config/Database.php';
if (!file_exists($dbFile)) {
  http_response_code(500);
  echo "❌ Config de banco não encontrada em $dbFile";
  exit;
}
require_once $dbFile; // fornece $conn (mysqli)

// ===== Validação básica =====
$titulo  = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
$link    = isset($_POST['link'])   ? trim($_POST['link'])   : '';
$hasFile = isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK;

if ($titulo === '') {
  http_response_code(400);
  echo "❌ Informe o título do treinamento.";
  exit;
}
if ((!$hasFile && $link === '') || ($hasFile && $link !== '')) {
  http_response_code(400);
  echo "❌ Envie apenas ARQUIVO OU LINK (um dos dois).";
  exit;
}

// ===== Determina origem =====
$origem = $hasFile ? 'upload' : 'url';

// ===== Monta entrada (arquivo local ou link) =====
$entrada = '';
$tmpToDelete = null;

if ($hasFile) {
  $origName = $_FILES['video']['name'];
  $tmpPath  = $_FILES['video']['tmp_name'];

  $destDir = __DIR__ . '/temp_uploads';
  if (!is_dir($destDir)) @mkdir($destDir, 0777, true);
  if (!is_writable($destDir)) {
    http_response_code(500);
    echo "❌ Sem permissão para escrever em: $destDir";
    exit;
  }

  $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $origName);
  $destPath = $destDir . DIRECTORY_SEPARATOR . (uniqid('vid_') . '_' . $safeName);
  if (!move_uploaded_file($tmpPath, $destPath)) {
    http_response_code(500);
    echo "❌ Falha ao salvar o arquivo enviado.";
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
$stmt->bind_param('ssss', $titulo, $origem, $link, $now);
if (!$stmt->execute()) {
  http_response_code(500);
  echo "❌ Erro ao inserir histórico: " . $stmt->error;
  exit;
}
$treinoId = $stmt->insert_id;
$stmt->close();

// ===== Executa Python =====
$python = stripos(PHP_OS_FAMILY, 'Windows') !== false ? 'python' : 'python3';
$script = __DIR__ . DIRECTORY_SEPARATOR . 'processa_video.py';

if (!file_exists($script)) {
  // marca erro no histórico
  $err = "Script não encontrado: $script";
  $stmt = $conn->prepare("UPDATE TB_TREINAMENTOS_BOT SET status='ERRO', data_fim=NOW(), log=? WHERE id=?");
  $stmt->bind_param('si', $err, $treinoId);
  $stmt->execute(); $stmt->close();

  http_response_code(500);
  echo "❌ $err";
  exit;
}

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
if ($ret !== 0) {
  $status = 'ERRO';
} else {
  $status = 'CONCLUIDO';
}
$stmt = $conn->prepare("
  UPDATE TB_TREINAMENTOS_BOT
     SET status = ?,
         arquivo_json = ?,
         data_fim = NOW(),
         log = ?
   WHERE id = ?
");
$stmt->bind_param('sssi', $status, $arquivoUrl, $fullOut, $treinoId);
$stmt->execute(); $stmt->close();

// ===== Retorno HTTP =====
if ($ret !== 0) {
  http_response_code(500);
  echo "❌ Erro ao processar o vídeo/link (ID #$treinoId).\n\n$fullOut";
  exit;
}

$okMsg = "✅ Treinamento #$treinoId concluído.\n";
if ($arquivoUrl) $okMsg .= "JSON: " . $arquivoUrl . "\n";
echo $okMsg . $fullOut;
