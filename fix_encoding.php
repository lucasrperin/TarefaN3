<?php
// Script para verificar e remover BOM de arquivos PHP
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $file) {
    if ($file->isFile() && in_array($file->getExtension(), ['php', 'html', 'htm'])) {
        $content = file_get_contents($file->getPathname());
        
        // Verifica se há BOM (\xEF\xBB\xBF)
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            echo "Removendo BOM de: " . $file->getPathname() . "\n";
            $content = substr($content, 3);
            file_put_contents($file->getPathname(), $content);
        }
        
        // Verifica por espaços em branco no início do arquivo
        if (preg_match('/^\s*<\?php/', $content)) {
            echo "Removendo espaços em branco do início de: " . $file->getPathname() . "\n";
            $content = preg_replace('/^\s*<\?php/', '<?php', $content);
            file_put_contents($file->getPathname(), $content);
        }
    }
}

echo "Verificação concluída.\n";
?>
