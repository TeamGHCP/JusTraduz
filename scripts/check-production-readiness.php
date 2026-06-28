<?php

$root = dirname(__DIR__);
$envPath = $root . '/backend/.env';
$allowPlaceholders = in_array('--allow-placeholders', $argv, true);

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--env=')) {
        $envPath = $root . '/' . ltrim(substr($argument, 6), '/\\');
    }
}

$env = read_env_file($envPath);
$failures = [];
$warnings = [];

require_value($env, 'APP_DEBUG', 'false', $failures, 'APP_DEBUG deve ser false.');
require_https_url($env['APP_URL'] ?? '', $failures, $warnings, $allowPlaceholders, 'APP_URL');
require_https_url($env['HEALTHCHECK_URL'] ?? '', $failures, $warnings, $allowPlaceholders, 'HEALTHCHECK_URL');

foreach (['DB_HOST', 'DB_NAME', 'DB_USER'] as $key) {
    if (($env[$key] ?? '') === '') {
        $failures[] = "$key deve estar configurado.";
    }
}

if (($env['BACKUP_ENCRYPTION_PASSWORD'] ?? '') === '') {
    $warnings[] = 'BACKUP_ENCRYPTION_PASSWORD vazio: backups não serão criptografados.';
}

foreach (['USAGE_DAILY_DOCUMENT_UPLOADS', 'USAGE_DAILY_DOCUMENT_AI', 'USAGE_DAILY_AI_CHAT', 'USAGE_DAILY_DATAJUD_CNJ', 'USAGE_DAILY_OCR'] as $key) {
    if ((int) ($env[$key] ?? 0) <= 0) {
        $warnings[] = "$key não define limite positivo.";
    }
}

if (($env['CLAMAV_BINARY'] ?? '') === '') {
    $warnings[] = 'CLAMAV_BINARY vazio: sera usada apenas varredura heuristica interna.';
}

if (filter_var($env['OCR_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN) && ($env['OCR_TESSERACT_BINARY'] ?? '') === '') {
    $warnings[] = 'OCR_ENABLED=true sem OCR_TESSERACT_BINARY: o sistema tentara usar tesseract do PATH.';
}

$requiredFiles = [
    '.htaccess',
    'backend/storage/documents/.htaccess',
    'backend/storage/message-attachments/.htaccess',
    'storage-private/documents/.htaccess',
    'storage-private/message-attachments/.htaccess',
    '.github/workflows/ci.yml',
    'backend/tests/run.php',
    'scripts/backup-database.ps1',
    'scripts/restore-database.ps1',
    'scripts/backup-storage.ps1',
    'scripts/restore-storage.ps1',
    'scripts/check-local-readiness.php',
    'scripts/check-orphan-storage.php',
    'scripts/cleanup-orphan-storage.php',
    'scripts/operational-health-report.php',
    'scripts/check-backup-file.php',
    'scripts/run-jobs.php',
    'docs/O_QUE_FALTA_AGORA.md',
    'docs/ROTEIRO_QA_MANUAL.md',
    'docs/API_PUBLICA.md',
    'docs/MANUAL_OPERACIONAL_INTERNO.md',
    'docs/apache-justraduz-production.conf',
    'docs/REGISTRO_REVISAO_JURIDICA.md',
    'backend/app/services/StorageService.php',
    'backend/app/services/UploadScannerService.php',
    'backend/app/services/OcrService.php',
    'backend/app/services/JobQueueService.php',
    'backend/app/services/UsageLimiter.php',
    'backend/app/services/OrganizationService.php',
    'backend/app/services/EscalationService.php',
    'backend/app/services/PublicApiClientService.php',
    'backend/app/controllers/PublicApiController.php',
    'backend/app/controllers/IntegrationController.php',
    'scripts/create-api-client.php',
    'database/justraduz_completo_com_demo.sql',
    'database/justraduz_completo_sem_demo.sql',
    'frontend/admin/organizacoes.php',
    'frontend/admin/permissoes.php',
];

foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        $failures[] = "Arquivo obrigatorio ausente: $file";
    }
}

$rootHtaccess = is_file($root . '/.htaccess') ? file_get_contents($root . '/.htaccess') : '';
foreach (['Options -Indexes', 'Header set X-Content-Type-Options', 'storage-private(?:/|$)', 'backend/storage/(?:documents|message-attachments)'] as $needle) {
    if (!str_contains((string) $rootHtaccess, $needle)) {
        $failures[] = ".htaccess raiz não contém proteção esperada: $needle";
    }
}

if ($warnings !== []) {
    fwrite(STDERR, "Avisos de produção:\n- " . implode("\n- ", $warnings) . "\n");
}

if ($failures !== []) {
    fwrite(STDERR, "Prontidao P0/P1 falhou:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Production readiness: OK\n";

function read_env_file(string $path): array
{
    if (!is_file($path)) {
        fwrite(STDERR, "Arquivo .env não encontrado: $path\n");
        exit(1);
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

function require_value(array $env, string $key, string $expected, array &$failures, string $message): void
{
    if (strtolower((string) ($env[$key] ?? '')) !== strtolower($expected)) {
        $failures[] = $message;
    }
}

function require_https_url(string $value, array &$failures, array &$warnings, bool $allowPlaceholders, string $key): void
{
    if ($value === '') {
        $failures[] = "$key deve estar configurado com HTTPS.";
        return;
    }

    if (!preg_match('#^https://#i', $value)) {
        $failures[] = "$key deve comecar com https://.";
        return;
    }

    if (str_contains($value, 'seudominio.com.br')) {
        $message = "$key ainda usa placeholder seudominio.com.br.";
        if ($allowPlaceholders) {
            $warnings[] = $message;
            return;
        }

        $failures[] = $message;
    }
}
