<?php

$root = dirname(__DIR__);
$format = 'markdown';
$outputPath = null;

foreach ($argv as $argument) {
    if ($argument === '--json') {
        $format = 'json';
    } elseif (str_starts_with($argument, '--output=')) {
        $outputPath = substr($argument, 9);
    }
}

require_once $root . '/backend/app/config/app.php';
require_once $root . '/backend/app/controllers/HealthController.php';
require_once $root . '/backend/app/services/SlaService.php';

$pdo = report_database_connection($root);

$report = [
    'generated_at' => date(DATE_ATOM),
    'health' => capture_healthcheck(),
    'database' => [
        'connected' => $pdo instanceof PDO,
        'users' => count_rows($pdo, 'users'),
        'documents' => count_rows($pdo, 'documents'),
        'cases_open' => count_where($pdo, 'cases', "status <> 'finalizado'"),
        'cases_unassigned' => count_where($pdo, 'cases', "status <> 'finalizado' AND advogado_id IS NULL"),
        'oab_pending' => count_where($pdo, 'users', "tipo = 'advogado' AND status = 'ativo' AND oab_verificado = 0 AND COALESCE(status_cna, 'pendente') = 'pendente'"),
        'job_queue_pending' => count_where($pdo, 'job_queue', "status = 'pending'"),
        'job_queue_failed' => count_where($pdo, 'job_queue', "status = 'failed'"),
        'mail_failed' => count_where($pdo, 'mail_logs', "status = 'failed'"),
        'ai_errors' => count_where($pdo, 'audit_logs', "action = 'document.ai_error'"),
    ],
    'sla' => summarize_sla($pdo),
    'storage' => summarize_storage($root),
];

$content = $format === 'json'
    ? json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
    : render_markdown_report($report);

if ($outputPath !== null && trim($outputPath) !== '') {
    $absoluteOutput = absolute_output_path($root, $outputPath);
    $dir = dirname($absoluteOutput);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($absoluteOutput, $content);
    echo "Operational health report written: {$absoluteOutput}" . PHP_EOL;
    exit(0);
}

echo $content;

function capture_healthcheck(): array
{
    ob_start();
    (new HealthController())->show();
    $raw = ob_get_clean();
    $decoded = json_decode((string) $raw, true);

    if (!is_array($decoded)) {
        return [
            'status' => 'error',
            'raw' => trim((string) $raw),
        ];
    }

    return $decoded;
}

function report_database_connection(string $root): ?PDO
{
    $env = report_env_values($root . '/backend/.env');

    try {
        $dsn = getenv('DB_DSN') ?: ($env['DB_DSN'] ?? '');
        $user = getenv('DB_USER') ?: ($env['DB_USER'] ?? 'root');
        $password = getenv('DB_PASS') ?: ($env['DB_PASS'] ?? '');

        if (is_string($dsn) && $dsn !== '') {
            $pdo = new PDO($dsn, $user, $password);
        } else {
            $host = getenv('DB_HOST') ?: ($env['DB_HOST'] ?? 'localhost');
            $dbname = getenv('DB_NAME') ?: ($env['DB_NAME'] ?? 'justraduz');
            $port = getenv('DB_PORT') ?: ($env['DB_PORT'] ?? '3306');
            $charset = getenv('DB_CHARSET') ?: ($env['DB_CHARSET'] ?? 'utf8mb4');
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}", $user, $password);
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Aviso: banco indisponivel para o relatorio operacional: ' . $exception->getMessage() . PHP_EOL);
        return null;
    }
}

function count_rows(?PDO $pdo, string $table): int
{
    return count_where($pdo, $table, '1 = 1');
}

function count_where(?PDO $pdo, string $table, string $where): int
{
    if (!$pdo) {
        return 0;
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return 0;
    }

    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        return (int) ($stmt ? $stmt->fetchColumn() : 0);
    } catch (Throwable) {
        return 0;
    }
}

function summarize_sla(?PDO $pdo): array
{
    if (!$pdo) {
        return [
            'overdue' => 0,
            'due_soon' => 0,
            'on_track' => 0,
            'without_sla' => 0,
        ];
    }

    try {
        $stmt = $pdo->query("SELECT id, titulo, status, prioridade, created_at, advogado_id, sla_due_at FROM cases WHERE status <> 'finalizado'");
        $cases = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable) {
        $cases = [];
    }

    $summary = [
        'overdue' => 0,
        'due_soon' => 0,
        'on_track' => 0,
        'without_sla' => 0,
    ];

    foreach ($cases as $case) {
        $state = SlaService::status($case['sla_due_at'] ?? null, (string) ($case['status'] ?? ''));
        $reportState = match ($state) {
            'vencido' => 'overdue',
            'em_risco' => 'due_soon',
            'sem_sla' => 'without_sla',
            default => 'on_track',
        };
        $summary[$reportState]++;
    }

    return $summary;
}

function summarize_storage(string $root): array
{
    $paths = [
        'documents' => configured_report_path('DOCUMENT_STORAGE_PATH', $root . '/storage-private/documents'),
        'attachments' => configured_report_path('ATTACHMENT_STORAGE_PATH', $root . '/storage-private/message-attachments'),
    ];

    $summary = [];
    foreach ($paths as $name => $path) {
        $summary[$name] = [
            'path' => $path,
            'exists' => is_dir($path),
            'files' => is_dir($path) ? count_storage_files($path) : 0,
            'bytes' => is_dir($path) ? storage_size($path) : 0,
        ];
    }

    return $summary;
}

function configured_report_path(string $key, string $default): string
{
    $value = getenv($key);
    if ($value === false || trim((string) $value) === '') {
        $env = report_env_values(dirname(__DIR__) . '/backend/.env');
        $value = $env[$key] ?? $default;
    }

    $path = trim((string) $value) ?: $default;
    if (!preg_match('#^[A-Za-z]:[\\\\/]#', $path) && !str_starts_with($path, '/')) {
        $path = dirname(__DIR__) . '/' . $path;
    }

    return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

function report_env_values(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $values = [];
    foreach ((array) file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim(trim($value), "\"'");
    }

    return $values;
}

function count_storage_files(string $path): int
{
    $count = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && !in_array($file->getFilename(), ['.gitkeep', '.htaccess'], true)) {
            $count++;
        }
    }

    return $count;
}

function storage_size(string $path): int
{
    $bytes = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && !in_array($file->getFilename(), ['.gitkeep', '.htaccess'], true)) {
            $bytes += $file->getSize();
        }
    }

    return $bytes;
}

function render_markdown_report(array $report): string
{
    $lines = [
        '# Relatorio de saude operacional',
        '',
        '- Gerado em: ' . $report['generated_at'],
        '- Healthcheck: ' . ($report['health']['status'] ?? 'indisponivel'),
        '',
        '## Banco',
    ];

    foreach ($report['database'] as $label => $value) {
        $lines[] = '- ' . $label . ': ' . $value;
    }

    $lines[] = '';
    $lines[] = '## SLA';
    foreach ($report['sla'] as $label => $value) {
        $lines[] = '- ' . $label . ': ' . $value;
    }

    $lines[] = '';
    $lines[] = '## Storage';
    foreach ($report['storage'] as $label => $storage) {
        $lines[] = '- ' . $label . ': ' . ($storage['exists'] ? 'OK' : 'ausente') . ' | arquivos=' . $storage['files'] . ' | bytes=' . $storage['bytes'] . ' | path=' . $storage['path'];
    }

    $lines[] = '';
    $lines[] = '## Checks brutos';
    foreach (($report['health']['checks'] ?? []) as $label => $ok) {
        $lines[] = '- ' . $label . ': ' . ($ok ? 'OK' : 'falhou');
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

function absolute_output_path(string $root, string $path): string
{
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    if (preg_match('#^[A-Za-z]:[\\\\/]#', $path) || str_starts_with($path, DIRECTORY_SEPARATOR)) {
        return $path;
    }

    return $root . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}
