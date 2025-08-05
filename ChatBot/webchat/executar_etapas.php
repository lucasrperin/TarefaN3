<?php
set_time_limit(600); // aumenta limite para cada etapa
$etapa = $_GET['etapa'] ?? '';

$baseDir = realpath(__DIR__ . '/../../ChatBot');
$python  = '"C:\\Users\\LucasP\\AppData\\Local\\Programs\\Python\\Python313\\python.exe"';

$embeddingsPath = "$baseDir/embeddings/embeddings.json";
$backupDir      = "$baseDir/embeddings/backups";
$gerarScript    = "$baseDir/scripts/gerar_embeddings.py";
$uploadScript   = "$baseDir/scripts/upload_embeddings.py";

try {
  switch ($etapa) {
    case 'backup':
      if (!file_exists($backupDir)) mkdir($backupDir, 0777, true);
      $timestamp = date('Ymd_His');
      $backupFile = "$backupDir/embeddings_backup_$timestamp.json";
      if (!copy($embeddingsPath, $backupFile)) {
        throw new Exception("❌ Falha ao copiar arquivo.");
      }
      echo "✅ Backup realizado.";
      break;

    case 'gerar':
      $cmd = "$python \"$gerarScript\"";
      exec($cmd . " 2>&1", $out, $ret);
      if ($ret !== 0) throw new Exception("❌ Erro: " . implode("\n", $out));
      echo "✅ Embeddings gerados.";
      break;

    case 'upload':
      $cmd = "$python \"$uploadScript\"";
      exec($cmd . " 2>&1", $out, $ret);
      if ($ret !== 0) throw new Exception("❌ Erro: " . implode("\n", $out));
      echo "✅ Embeddings enviados.";
      break;

    default:
      http_response_code(400);
      echo "❌ Etapa inválida.";
  }
} catch (Exception $e) {
  http_response_code(500);
  echo $e->getMessage();
}
