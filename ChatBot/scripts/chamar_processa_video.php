<?php 
set_time_limit(600);
header('Content-Type: text/plain; charset=utf-8');

// Carrega variáveis do .env
$dotenv = __DIR__ . '/../../.env';
if (file_exists($dotenv)) {
  foreach (file($dotenv) as $line) {
    if (strpos(trim($line), '=') !== false) {
      list($key, $value) = explode('=', trim($line), 2);
      putenv("$key=$value");
    }
  }
}

// Caminho do Python
$python = '"C:\Users\Guilherme\AppData\Local\Programs\Python\Python312\python.exe"';
$script = __DIR__ . '/processa_video.py';

$videoTmp  = $_FILES['video']['tmp_name'] ?? null;
$videoName = $_FILES['video']['name'] ?? '';
$link      = $_POST['link'] ?? '';

if (!$videoTmp && !$link) {
  http_response_code(400);
  echo "❌ Nenhum vídeo ou link recebido.";
  exit;
}

if ($link) {
  $cmd = "$python \"$script\" \"$link\"";
} else {
  $uploadDir = __DIR__ . '/temp_uploads';
  if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
  $dest = $uploadDir . '/' . basename($videoName);
  if (!move_uploaded_file($videoTmp, $dest)) {
    http_response_code(500);
    echo "❌ Falha ao salvar vídeo enviado.";
    exit;
  }
  $cmd = "$python \"$script\" \"$dest\"";
}

// Executa o script Python
exec($cmd . " 2>&1", $output, $ret);

// Exibe saída
if ($ret !== 0) {
  http_response_code(500);
  echo "❌ Erro ao processar vídeo:\n";
  echo implode("\n", $output);
  echo "\n\nComando executado: $cmd\n";
} else {
  echo implode("\n", $output);
}

// Apaga vídeo enviado (se for upload)
if (isset($dest) && file_exists($dest)) {
  unlink($dest);
}

// Limpa arquivos temporários da pasta scripts/temp
$tempDir = __DIR__ . "/temp";
if (is_dir($tempDir)) {
  foreach (glob($tempDir . "/*") as $file) {
    if (is_file($file)) unlink($file);
  }
}
