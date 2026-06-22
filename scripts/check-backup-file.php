<?php

$path = $argv[1] ?? '';
if ($path === '') {
    fwrite(STDERR, "Uso: php scripts/check-backup-file.php backups/arquivo.sql\n");
    exit(1);
}

if (!is_file($path)) {
    fwrite(STDERR, "Backup nao encontrado: $path\n");
    exit(1);
}

$size = filesize($path);
if ($size === false || $size <= 0) {
    fwrite(STDERR, "Backup vazio: $path\n");
    exit(1);
}

$sample = (string) file_get_contents($path, false, null, 0, 1024 * 256);
$requiredNeedles = ['CREATE TABLE', 'users', 'documents'];
$missing = [];

foreach ($requiredNeedles as $needle) {
    if (!str_contains($sample, $needle)) {
        $missing[] = $needle;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "Backup nao contem marcadores esperados: " . implode(', ', $missing) . "\n");
    exit(1);
}

echo "Backup file check: OK ($path, $size bytes)\n";
