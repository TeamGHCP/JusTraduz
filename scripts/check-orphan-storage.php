<?php

$root = dirname(__DIR__);
$delete = in_array('--delete', $argv, true);

try {
    require_once $root . '/backend/app/config/database.php';
} catch (Throwable $exception) {
    fwrite(STDERR, 'Orphan storage check: banco indisponivel: ' . $exception->getMessage() . "\n");
    exit(2);
}

require_once $root . '/backend/app/services/StorageService.php';

$storage = new StorageService();
$knownFiles = [];

foreach (fetch_storage_references($pdo) as $reference) {
    $path = resolve_storage_reference($storage, $reference);
    if ($path !== null) {
        $knownFiles[normalize_path($path)] = true;
    }
}

$roots = [
    configured_path('DOCUMENT_STORAGE_PATH', $root . '/storage-private/documents'),
    configured_path('ATTACHMENT_STORAGE_PATH', $root . '/storage-private/message-attachments'),
    $root . '/storage-private/documents',
    $root . '/storage-private/message-attachments',
    $root . '/backend/storage/documents',
    $root . '/backend/storage/message-attachments',
];
$roots = array_values(array_unique(array_map('normalize_path', $roots)));

$orphans = [];
foreach ($roots as $storageRoot) {
    if (!is_dir($storageRoot)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($storageRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || should_ignore_storage_file($file)) {
            continue;
        }

        $path = normalize_path($file->getPathname());
        if (!isset($knownFiles[$path])) {
            $orphans[] = $file->getPathname();
        }
    }
}

if ($orphans === []) {
    echo "Orphan storage check: OK\n";
    exit(0);
}

echo "Arquivos orfaos no storage:\n- " . implode("\n- ", $orphans) . "\n";

if ($delete) {
    foreach ($orphans as $orphan) {
        @unlink($orphan);
    }

    echo "Arquivos orfaos removidos: " . count($orphans) . "\n";
    exit(0);
}

exit(1);

function fetch_storage_references(PDO $pdo): array
{
    $references = [];
    foreach ([
        'SELECT caminho FROM documents WHERE caminho IS NOT NULL AND caminho <> ""',
        'SELECT attachment_path FROM messages WHERE attachment_path IS NOT NULL AND attachment_path <> ""',
    ] as $sql) {
        try {
            foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $reference) {
                $references[] = (string) $reference;
            }
        } catch (Throwable $exception) {
            fwrite(STDERR, 'Aviso: consulta de storage falhou: ' . $exception->getMessage() . "\n");
        }
    }

    return $references;
}

function resolve_storage_reference(StorageService $storage, string $reference): ?string
{
    return str_contains($reference, 'message-attachments')
        ? $storage->attachmentPathFromReference($reference)
        : $storage->documentPathFromReference($reference);
}

function configured_path(string $key, string $default): string
{
    $value = getenv($key);
    if ($value === false || trim((string) $value) === '') {
        $env = database_env_values(dirname(__DIR__) . '/backend/.env');
        $value = $env[$key] ?? $default;
    }

    $path = trim((string) $value);
    if ($path === '') {
        $path = $default;
    }

    if (!preg_match('#^[A-Za-z]:[\\\\/]#', $path) && !str_starts_with($path, '/')) {
        $path = dirname(__DIR__) . '/' . $path;
    }

    return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

function normalize_path(string $path): string
{
    return str_replace('\\', '/', realpath($path) ?: $path);
}

function should_ignore_storage_file(SplFileInfo $file): bool
{
    $path = str_replace('\\', '/', $file->getPathname());
    if (in_array($file->getFilename(), ['.gitkeep', '.htaccess'], true)) {
        return true;
    }

    return str_contains($path, '/backend/storage/documents/demo/');
}
