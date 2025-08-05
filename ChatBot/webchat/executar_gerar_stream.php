<?php
set_time_limit(600); // tempo suficiente

$baseDir = realpath(__DIR__ . '/../../ChatBot');
$script = "$baseDir/scripts/gerar_embeddings.py";
$python = '"C:\\Users\\LucasP\\AppData\\Local\\Programs\\Python\\Python313\\python.exe"';

$cmd = "$python \"$script\"";

header('Content-Type: text/plain');
ob_implicit_flush(true);
ob_end_flush();

$descriptorspec = [
  1 => ['pipe', 'w'], // stdout
  2 => ['pipe', 'w'], // stderr
];

$process = proc_open($cmd, $descriptorspec, $pipes, null, null);

if (is_resource($process)) {
  while (!feof($pipes[1])) {
    $line = fgets($pipes[1]);
    if ($line !== false) echo $line;
    flush();
    usleep(100000); // 0.1s
  }

  while (!feof($pipes[2])) {
    $line = fgets($pipes[2]);
    if ($line !== false) echo $line;
    flush();
    usleep(100000);
  }

  proc_close($process);
}
