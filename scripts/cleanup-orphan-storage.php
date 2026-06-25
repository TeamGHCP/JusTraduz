<?php

$root = dirname(__DIR__);
$delete = in_array('--delete', $argv, true);
$confirm = in_array('--confirm-reviewed-report', $argv, true);
$reportPath = null;

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--report=')) {
        $reportPath = substr($argument, 9);
    }
}

if (!$delete) {
    passthru(PHP_BINARY . ' ' . escapeshellarg($root . '/scripts/check-orphan-storage.php'), $exitCode);
    exit((int) $exitCode);
}

if (!$confirm) {
    fwrite(STDERR, "Use --confirm-reviewed-report depois de revisar o relatorio de orfaos.\n");
    exit(2);
}

if ($reportPath === null || trim($reportPath) === '') {
    fwrite(STDERR, "Informe --report=caminho/do/relatorio.txt.\n");
    exit(2);
}

$absoluteReport = absolute_cleanup_path($root, $reportPath);
if (!is_file($absoluteReport)) {
    fwrite(STDERR, "Relatorio nao encontrado: {$absoluteReport}\n");
    exit(2);
}

$ageSeconds = time() - filemtime($absoluteReport);
if ($ageSeconds > 86400) {
    fwrite(STDERR, "Relatorio tem mais de 24 horas. Gere e revise novamente antes de limpar.\n");
    exit(2);
}

passthru(PHP_BINARY . ' ' . escapeshellarg($root . '/scripts/check-orphan-storage.php') . ' --delete', $exitCode);
exit((int) $exitCode);

function absolute_cleanup_path(string $root, string $path): string
{
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    if (preg_match('#^[A-Za-z]:[\\\\/]#', $path) || str_starts_with($path, DIRECTORY_SEPARATOR)) {
        return $path;
    }

    return $root . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}
