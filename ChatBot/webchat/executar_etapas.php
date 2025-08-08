<?php
set_time_limit(600);

// 1) Conexão DB
require_once __DIR__ . '/../../Config/Database.php';  // ajusta o caminho se necessário

$etapa = $_GET['etapa'] ?? '';

// caminhos...
$baseDir = realpath(__DIR__ . '/../../ChatBot');
$python  = '"C:\\Users\\LucasP\\AppData\\Local\\Programs\\Python\\Python313\\python.exe"';
$embScript   = "$baseDir/scripts/gerar_embeddings.py";
$backupDir   = "$baseDir/embeddings/backups";
$embPath     = "$baseDir/embeddings/embeddings.json";
$uploadScript= "$baseDir/scripts/upload_embeddings.py";

try {
  switch ($etapa) {
    case 'backup':
      if (!file_exists($backupDir)) mkdir($backupDir, 0777, true);
      $ts = date('Ymd_His');
      copy($embPath, "$backupDir/embeddings_backup_$ts.json") ||
        throw new Exception("❌ Falha no backup");
      echo "✅ Backup realizado.";
      break;

    case 'gerar':
      // executa geração
      $cmd = "$python \"$embScript\"";
      exec($cmd . " 2>&1", $out, $ret);
      if ($ret !== 0) throw new Exception("❌ Erro: " . implode("\n", $out));

      // grava data no banco
      $now = date('Y-m-d H:i:s');
      $sql = "INSERT INTO TB_EMBEDDINGS (data_geracao) VALUES ('$now')";
      $conn->query($sql) ||
        throw new Exception("❌ Erro ao gravar data de geração");

      echo "✅ Embeddings gerados.";
      break;

    case 'upload':
      $cmd = "$python \"$uploadScript\"";
      exec($cmd . " 2>&1", $out2, $ret2);
      if ($ret2 !== 0) throw new Exception("❌ Erro Upload: " . implode("\n", $out2));
      echo "✅ Upload realizado.";
      break;

    default:
      http_response_code(400);
      echo "❌ Etapa inválida.";
  }
} catch (Exception $e) {
  http_response_code(500);
  echo $e->getMessage();
}
