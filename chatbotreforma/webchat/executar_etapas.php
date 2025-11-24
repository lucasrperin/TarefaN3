<?php
set_time_limit(600);

// 1) Conexão DB
require_once __DIR__ . '/../../Config/Database.php';  // ajusta o caminho se necessário

date_default_timezone_set('America/Sao_Paulo');

ignore_user_abort(true);
@set_time_limit(0);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$etapa = $_GET['etapa'] ?? '';

// caminhos base
$chatbotDir = realpath(__DIR__ . '/..'); // .../ChatBot
$baseDir    = $chatbotDir;               // manter compatibilidade

// Detecta Python do venv (se você usa .venv em ChatBot/)
$venvPyWin = $chatbotDir . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe';
$venvPyNix = $chatbotDir . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python';
if (file_exists($venvPyWin)) {
  $python = '"' . $venvPyWin . '"';
} elseif (file_exists($venvPyNix)) {
  $python = escapeshellarg($venvPyNix);
} else {
  $python = stripos(PHP_OS_FAMILY ?? php_uname('s'), 'Windows') !== false ? 'python' : 'python3';
}
putenv('PYTHONIOENCODING=utf-8');

// caminhos dos scripts/arquivos (ARTIGOS)
$embScript     = $chatbotDir . '/scripts/gerar_embeddings.py';
$backupDir     = $chatbotDir . '/embeddings/backups';
$embPath       = $chatbotDir . '/embeddings/embeddings.json';
$uploadScript  = $chatbotDir . '/scripts/upload_embeddings.py';

// *** VÍDEOS ***
$transDir           = $chatbotDir . '/embeddings/transcricoes';
$backupVideoDir     = $transDir . '/backup';
$backupZipPath      = $backupVideoDir . '/transcricoes_backup_' . date('Ymd_His') . '.zip';
$uploadVideoScript  = $chatbotDir . '/scripts/video/upload_embeddings_video.py';

// *** WEBSITES ***
$sitesDir           = $chatbotDir . '/embeddings/sites';
$backupSitesDir     = $sitesDir . '/backup';
$backupSitesZipPath = $backupSitesDir . '/sites_backup_' . date('Ymd_His') . '.zip';
$uploadSiteScript   = $chatbotDir . '/scripts/website/upload_embeddings_site.py';

/**
 * Compacta TODO o conteúdo da pasta $srcDir (arquivos de qualquer extensão),
 * preservando a estrutura de subpastas, EXCETO qualquer caminho que contenha
 * "/$excludeDirName/" (por padrão "backup").
 *
 * Retorna a quantidade de ARQUIVOS adicionados.
 */
function zipFolderExcluding(string $srcDir, string $destZip, string $excludeDirName = 'backup'): int {
  if (!class_exists('ZipArchive')) {
    throw new Exception("❌ Extensão ZipArchive não habilitada no PHP.");
  }
  if (!is_dir($srcDir)) {
    throw new Exception("❌ Pasta não encontrada: $srcDir");
  }

  $zip = new ZipArchive();
  if ($zip->open($destZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    throw new Exception("❌ Não foi possível criar o arquivo ZIP: $destZip");
  }

  $srcReal = realpath($srcDir);
  $count = 0;

  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
  );

  foreach ($it as $file) {
    /** @var SplFileInfo $file */
    $path = $file->getPathname();

    // pula itens dentro de /backup/
    if (stripos($path, DIRECTORY_SEPARATOR . $excludeDirName . DIRECTORY_SEPARATOR) !== false) {
      continue;
    }

    if ($file->isFile()) {
      // caminho relativo dentro do zip (preserva subpastas)
      $rel = ltrim(str_replace($srcReal, '', realpath($path)), DIRECTORY_SEPARATOR);
      $zip->addFile($path, $rel);
      $count++;
    }
  }

  $zip->close();

  if ($count === 0) {
    // se preferir não considerar erro, troque para apenas retornar 0
    throw new Exception("❌ Nenhum arquivo encontrado em $srcDir para compactar.");
  }

  return $count;
}

/**
 * Versão anterior (somente JSON). Mantida porque é usada em outros cases.
 * Retorna a quantidade de JSONs adicionados.
 */
function zipTranscricoesJson(string $srcDir, string $destZip, string $excludeDirName = 'backup'): int {
  if (!class_exists('ZipArchive')) {
    throw new Exception("❌ Extensão ZipArchive não habilitada no PHP.");
  }
  if (!is_dir($srcDir)) {
    throw new Exception("❌ Pasta não encontrada: $srcDir");
  }

  $zip = new ZipArchive();
  if ($zip->open($destZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    throw new Exception("❌ Não foi possível criar o arquivo ZIP: $destZip");
  }

  $srcReal = realpath($srcDir);
  $count = 0;

  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
  );

  foreach ($it as $file) {
    /** @var SplFileInfo $file */
    $path = $file->getPathname();

    // pula itens dentro de /backup/
    if (stripos($path, DIRECTORY_SEPARATOR . $excludeDirName . DIRECTORY_SEPARATOR) !== false) {
      continue;
    }

    if ($file->isFile() && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'json') {
      $rel = ltrim(str_replace($srcReal, '', realpath($path)), DIRECTORY_SEPARATOR);
      $zip->addFile($path, $rel);
      $count++;
    }
  }

  $zip->close();

  if ($count === 0) {
    throw new Exception("❌ Nenhum arquivo .json encontrado em $srcDir para compactar.");
  }

  return $count;
}

try {
  switch ($etapa) {
    // ========= ARTIGOS =========
    case 'backup':
      if (!file_exists($backupDir)) {
        if (!mkdir($backupDir, 0777, true)) {
          throw new Exception("❌ Não foi possível criar a pasta de backup: $backupDir");
        }
      }
      $ts = date('Ymd_His');
      $dest = "$backupDir/embeddings_backup_$ts.json";
      if (!file_exists($embPath)) {
        throw new Exception("❌ Arquivo não encontrado: $embPath");
      }
      if (!copy($embPath, $dest)) {
        throw new Exception("❌ Falha no backup para $dest");
      }
      echo "✅ Backup realizado em " . basename($dest) . ".";
      break;

    case 'gerar':
      // executa geração
      $cmd = "$python " . escapeshellarg($embScript);
      exec($cmd . " 2>&1", $out, $ret);
      if ($ret !== 0) throw new Exception("❌ Erro: " . implode("\n", $out));

      // grava data + tipo (artigos)
      $now  = date('Y-m-d H:i:s');
      $tipo = 'artigos';
      $stmt = $conn->prepare("INSERT INTO TB_EMBEDDINGS_REFORMA (data_geracao, tipo) VALUES (?, ?)");
      if (!$stmt) throw new Exception("❌ Erro prepare: ".$conn->error);
      $stmt->bind_param('ss', $now, $tipo);
      if (!$stmt->execute()) throw new Exception("❌ Erro ao gravar data de geração");
      $stmt->close();

      echo "✅ Embeddings gerados (artigos).";
      break;

    case 'upload':
      $cmd = "$python " . escapeshellarg($uploadScript);
      exec($cmd . " 2>&1", $out2, $ret2);
      if ($ret2 !== 0) throw new Exception("❌ Erro Upload: " . implode("\n", $out2));

      // grava data + tipo (artigos)
      $now  = date('Y-m-d H:i:s');
      $tipo = 'artigos';
      $stmt = $conn->prepare("INSERT INTO TB_EMBEDDINGS_REFORMA (data_geracao, tipo) VALUES (?, ?)");
      if (!$stmt) throw new Exception("❌ Erro prepare: ".$conn->error);
      $stmt->bind_param('ss', $now, $tipo);
      if (!$stmt->execute()) throw new Exception("❌ Erro ao gravar data do upload");
      $stmt->close();

      echo "✅ Upload realizado (artigos).";
      break;

    // ========= VÍDEOS =========
    case 'backup_videos':
      if (!is_dir($transDir)) {
        throw new Exception("❌ Pasta de transcrições não encontrada: $transDir");
      }
      if (!is_dir($backupVideoDir) && !mkdir($backupVideoDir, 0777, true)) {
        throw new Exception("❌ Não foi possível criar a pasta de backup: $backupVideoDir");
      }
      $ts = date('Ymd_His');
      $zipPath = $backupVideoDir . "/transcricoes_backup_{$ts}.zip";
      $qtde = zipTranscricoesJson($transDir, $zipPath, 'backup'); // ignora a própria /backup
      echo "✅ Backup de vídeos concluído: " . basename($zipPath) . " ({$qtde} JSON).";
      break;

    case 'upload_video':
      if (!file_exists($uploadVideoScript)) {
        throw new Exception("❌ Script não encontrado: $uploadVideoScript");
      }
      $cmd = "$python " . escapeshellarg($uploadVideoScript);
      exec($cmd . " 2>&1", $out3, $ret3);
      if ($ret3 !== 0) throw new Exception("❌ Erro Upload Vídeos: " . implode("\n", $out3));

      // grava data + tipo (video)
      $now  = date('Y-m-d H:i:s');
      $tipo = 'video';
      $stmt = $conn->prepare("INSERT INTO TB_EMBEDDINGS_REFORMA (data_geracao, tipo) VALUES (?, ?)");
      if (!$stmt) throw new Exception("❌ Erro prepare: ".$conn->error);
      $stmt->bind_param('ss', $now, $tipo);
      if (!$stmt->execute()) throw new Exception("❌ Erro ao gravar data do upload de vídeos");
      $stmt->close();

      echo "✅ Upload realizado (vídeos).";
      break;

    // ========= WEBSITES =========
    case 'backup_sites':
      if (!is_dir($sitesDir)) {
        throw new Exception("❌ Pasta de sites não encontrada: $sitesDir");
      }
      if (!is_dir($backupSitesDir) && !mkdir($backupSitesDir, 0777, true)) {
        throw new Exception("❌ Não foi possível criar a pasta de backup: $backupSitesDir");
      }
      $ts = date('Ymd_His');
      $zipPath = $backupSitesDir . "/sites_backup_{$ts}.zip";
      $qtde = zipFolderExcluding($sitesDir, $zipPath, 'backup'); // tudo exceto /backup
      echo "✅ Backup de sites concluído: " . basename($zipPath) . " ({$qtde} arquivos).";
      break;

    case 'upload_site':
      if (!is_dir($sitesDir)) {
        throw new Exception("❌ Pasta de sites não encontrada: $sitesDir");
      }
      if (!is_dir($backupSitesDir) && !mkdir($backupSitesDir, 0777, true)) {
        throw new Exception("❌ Não foi possível criar a pasta de backup: $backupSitesDir");
      }
      // 1) BACKUP COMPLETO (tudo exceto /backup)
      $ts = date('Ymd_His');
      $zipPath = $backupSitesDir . "/sites_backup_{$ts}.zip";
      $qtde = zipFolderExcluding($sitesDir, $zipPath, 'backup');

      // 2) UPLOAD (executa script Python)
      if (!file_exists($uploadSiteScript)) {
        throw new Exception("❌ Script não encontrado: $uploadSiteScript");
      }
      $cmd = "$python " . escapeshellarg($uploadSiteScript);
      exec($cmd . " 2>&1", $out4, $ret4);
      if ($ret4 !== 0) throw new Exception("❌ Erro Upload Sites: " . implode("\n", $out4));

      // 3) REGISTRO da execução (TB_EMBEDDINGS_REFORMA.tipo = 'website')
      $now  = date('Y-m-d H:i:s');
      $tipo = 'website';
      $stmt = $conn->prepare("INSERT INTO TB_EMBEDDINGS_REFORMA (data_geracao, tipo) VALUES (?, ?)");
      if (!$stmt) throw new Exception("❌ Erro prepare: ".$conn->error);
      $stmt->bind_param('ss', $now, $tipo);
      if (!$stmt->execute()) throw new Exception("❌ Erro ao gravar data do upload de websites");
      $stmt->close();

      echo "✅ Backup criado (" . basename($zipPath) . ", {$qtde} arquivos) e Upload realizado (websites).";
      break;

    default:
      http_response_code(400);
      echo "❌ Etapa inválida.";
  }
} catch (Exception $e) {
  http_response_code(500);
  echo $e->getMessage();
}
