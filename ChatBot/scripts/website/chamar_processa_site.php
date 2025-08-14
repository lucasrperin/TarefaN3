<?php
// ChatBot/scripts/website/chamar_processa_site.php

// --- Capture TUDO que for ecoado pelos requires (auth.php etc.) para não quebrar o JSON ---
ob_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../Includes/auth.php';
require_once __DIR__ . '/../../../Config/Database.php';

// Limpa qualquer saída que tenha sido gerada pelos requires (ex.: <script>console.log(...)</script>)
if (ob_get_length()) { ob_clean(); }

/** Converte caminho absoluto em URL pública baseada no DOCUMENT_ROOT */
function fs_to_url(string $abs): string {
  $doc = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
  $abs = realpath($abs);
  if (!$doc || !$abs) return '';
  $doc = rtrim(str_replace('\\','/',$doc),'/');
  $abs = str_replace('\\','/',$abs);
  if (strpos($abs,$doc) !== 0) return '';
  $rel = substr($abs, strlen($doc));
  return $rel === '' ? '/' : ($rel[0] === '/' ? $rel : '/'.$rel);
}

/** Normaliza path para comparação */
function norm(string $p): string {
  $p = str_replace(['\\','/'], DIRECTORY_SEPARATOR, $p);
  return rtrim($p, DIRECTORY_SEPARATOR);
}

/** Verifica se $path está dentro de $base (ambos absolutos) */
function is_path_inside(string $path, string $base): bool {
  $rp = realpath($path);
  $rb = realpath($base);
  if ($rp === false || $rb === false) return false;
  $rp = strtolower(norm($rp));
  $rb = strtolower(norm($rb));
  return strncmp($rp, $rb, strlen($rb)) === 0 && (strlen($rp) === strlen($rb) || $rp[strlen($rb)] === DIRECTORY_SEPARATOR);
}

/** Base64 URL-safe para caminhos */
function base64url_encode_path(string $p): string {
  return rtrim(strtr(base64_encode($p), '+/', '-_'), '=');
}

/** Lista JSONs do diretório de saída com URLs de preview/exclusão */
function list_json_files(string $dir, string $previewEndpoint, string $deleteEndpoint): array {
  $out = [];
  if (!is_dir($dir)) return $out;

  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
  );

  $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');

  foreach ($it as $fileInfo) {
    /** @var SplFileInfo $fileInfo */
    if (!$fileInfo->isFile()) continue;
    if (strtolower($fileInfo->getExtension()) !== 'json') continue;

    $abs = $fileInfo->getRealPath();
    $p64 = base64url_encode_path($abs);

    $publicUrl = '';
    if ($docRoot && is_path_inside($abs, $docRoot)) {
      $publicUrl = fs_to_url($abs);
    }

    $out[] = [
      'name'        => $fileInfo->getBasename(),
      'bytes'       => $fileInfo->getSize(),
      'mtime'       => date('Y-m-d H:i:s', $fileInfo->getMTime()),
      'path_b64'    => $p64,
      'public_url'  => $publicUrl,
      'preview_url' => $previewEndpoint . '?p=' . urlencode($p64),
      'delete_url'  => $deleteEndpoint   . '?p=' . urlencode($p64),
      'abs_path'    => $abs,
    ];
  }

  // Ordena por data modificação desc
  usort($out, function($a,$b){ return strcmp($b['mtime'], $a['mtime']); });
  return $out;
}

/**
 * Resolve o Python a ser usado.
 * Ordem:
 *  1) Variável de ambiente PYTHON_BIN (se definida e válida)
 *  2) .venv dentro de ChatBot (tenta subir 3 níveis e 2 níveis a partir deste arquivo)
 *  3) fallback 'python'
 */
function find_python_bin(): string {
  // 1) override via env var
  $env = getenv('PYTHON_BIN');
  if (!$env && isset($_ENV['PYTHON_BIN'])) $env = $_ENV['PYTHON_BIN'];
  if (!$env && isset($_SERVER['PYTHON_BIN'])) $env = $_SERVER['PYTHON_BIN'];
  if ($env && is_file($env)) return $env;

  // 2a) tenta .../ChatBot (sobe 3 níveis: scripts/website -> scripts -> ChatBot)
  $chatbotBase = realpath(__DIR__ . '/../../..');
  if ($chatbotBase) {
    $win = $chatbotBase . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe';
    $nix = $chatbotBase . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'bin'     . DIRECTORY_SEPARATOR . 'python';
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0 && is_file($win)) return $win;
    if (is_file($nix) && is_executable($nix)) return $nix;
  }
  // 2b) fallback antigo (caso sua árvore seja diferente)
  $chatbotBase2 = realpath(__DIR__ . '/../../');
  if ($chatbotBase2) {
    $win = $chatbotBase2 . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe';
    $nix = $chatbotBase2 . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'bin'     . DIRECTORY_SEPARATOR . 'python';
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0 && is_file($win)) return $win;
    if (is_file($nix) && is_executable($nix)) return $nix;
  }

  // 3) fallback
  return 'python';
}

// Melhora IO do Python
putenv('PYTHONIOENCODING=utf-8');

$url        = $_POST['url']        ?? '';
$max_pages  = (int)($_POST['max_pages'] ?? 10);
$same_dom   = (int)($_POST['same_domain'] ?? 1);
$use_map    = (int)($_POST['use_sitemap'] ?? 0);

if (!$url) {
  if (ob_get_length()) ob_clean();
  http_response_code(400);
  echo json_encode(['ok'=>false, 'message'=>'Informe a URL inicial.']);
  exit;
}

$max_pages = max(1, min(100, $max_pages));
$same_dom  = $same_dom ? 1 : 0;
$use_map   = $use_map ? 1 : 0;

$jobTitulo = 'Treinamento Website - ' . (parse_url($url, PHP_URL_HOST) ?: $url);
$inicio = date('Y-m-d H:i:s');

$stmt = $conn->prepare("INSERT INTO TB_TREINAMENTOS_BOT (titulo, origem, link, status, data_inicio) VALUES (?, 'url', ?, 'PROCESSANDO', ?)");
$stmt->bind_param('sss', $jobTitulo, $url, $inicio);
$stmt->execute();
$jobId = $stmt->insert_id;
$stmt->close();

// Monta comando
$pyResolved = find_python_bin();
$py         = escapeshellarg($pyResolved);
$scriptPath = __DIR__ . '/crawl_site.py';
$script     = escapeshellarg($scriptPath);
$cmd = $py . ' ' . $script . ' ' .
       escapeshellarg($url) . ' ' .
       escapeshellarg((string)$max_pages) . ' ' .
       escapeshellarg((string)$same_dom) . ' ' .
       escapeshellarg((string)$use_map);

// Executa
$desc = [
  0 => ['pipe', 'r'],
  1 => ['pipe', 'w'],
  2 => ['pipe', 'w']
];
$proc = proc_open($cmd, $desc, $pipes, __DIR__, null);

$log  = "Iniciando coleta em {$url}\n";
$log .= "Python: {$pyResolved}\n";
$log .= "CMD: {$cmd}\n";
$saidaDir = '';
$zipPublicUrl = '';

if (is_resource($proc)) {
  fclose($pipes[0]);
  while (!feof($pipes[1])) {
    $line = fgets($pipes[1]);
    if ($line === false) break;
    $log .= $line;
    if (preg_match('/^DIR_SAIDA:\s*(.+)\s*$/', $line, $m)) {
      $saidaDir = trim($m[1]);
    }
  }
  $err = stream_get_contents($pipes[2]);
  if ($err) $log .= "\n[STDERR]\n".$err;
  fclose($pipes[1]); fclose($pipes[2]);
  $ret = proc_close($proc);

  $status = ($ret === 0) ? 'CONCLUIDO' : 'ERRO';
  $arquivo_json = '';

  // Compacta diretório de saída (em pasta pública dentro do projeto)
  if ($status === 'CONCLUIDO' && $saidaDir && is_dir($saidaDir) && class_exists('ZipArchive')) {
    $zipBase = __DIR__ . '/../../../embeddings/zips';
    if (!is_dir($zipBase)) @mkdir($zipBase, 0777, true);
    $zipBase = realpath($zipBase);
    if ($zipBase) {
      $zipName = $zipBase . DIRECTORY_SEPARATOR . "treino_site_{$jobId}.zip";
      $zip = new ZipArchive();
      if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $it = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($saidaDir, FilesystemIterator::SKIP_DOTS),
          RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $path) {
          $rel = substr($path, strlen($saidaDir) + 1);
          if (is_dir($path)) $zip->addEmptyDir($rel); else $zip->addFile($path, $rel);
        }
        $zip->close();

        // URL pública
        $arquivo_json = fs_to_url($zipName);
        if (!$arquivo_json) $arquivo_json = $zipName;
        $zipPublicUrl = $arquivo_json;

        $log .= "\nArquivo compactado: $zipName\n";
      } else {
        $log .= "\n[WARN] Falha ao criar ZIP do diretório de saída.\n";
      }
    }
  }

  // Monta lista de arquivos JSON gerados (para o front renderizar com preview/exclusão)
  $files = [];
  if ($status === 'CONCLUIDO' && $saidaDir && is_dir($saidaDir)) {
    $files = list_json_files(
      $saidaDir,
      './preview_json.php',
      './delete_json.php'
    );
  }

  $fim = date('Y-m-d H:i:s');
  $stmt = $conn->prepare("UPDATE TB_TREINAMENTOS_BOT SET status=?, data_fim=?, arquivo_json=?, log=? WHERE id=?");
  $stmt->bind_param('ssssi', $status, $fim, $arquivo_json, $log, $jobId);
  $stmt->execute();
  $stmt->close();

  if (ob_get_length()) ob_clean();
  echo json_encode([
    'ok'        => ($status === 'CONCLUIDO'),
    'id'        => $jobId,
    'message'   => $status === 'CONCLUIDO' ? 'Coleta concluída.' : 'Falha no processamento. Veja o log.',
    'status'    => $status,
    'dir_saida' => $saidaDir,
    'zip'       => $zipPublicUrl,
    'files'     => $files
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// Falha ao criar processo
if (ob_get_length()) ob_clean();
http_response_code(500);
echo json_encode(['ok'=>false,'message'=>'Falha ao iniciar processo.']);
