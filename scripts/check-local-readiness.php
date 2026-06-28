<?php

$root = dirname(__DIR__);

require_once $root . '/backend/app/config/app.php';
require_once $root . '/backend/app/controllers/HealthController.php';

$failures = [];
$warnings = [];

$envPath = $root . '/backend/.env';
$env = read_local_env($envPath);

foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER'] as $key) {
    if (trim((string) ($env[$key] ?? '')) === '') {
        $failures[] = "$key deve estar configurado no backend/.env local.";
    }
}

$appUrl = (string) (($env['APP_URL'] ?? '') ?: ($env['APP_PUBLIC_URL'] ?? ''));
if (trim($appUrl) === '') {
    $failures[] = 'APP_URL ou APP_PUBLIC_URL deve estar configurado no backend/.env local.';
} elseif (!preg_match('#^https?://#i', $appUrl)) {
    $warnings[] = 'APP_URL/APP_PUBLIC_URL local nao parece uma URL HTTP/HTTPS.';
}

if (($env['APP_DEBUG'] ?? '') !== 'false') {
    $warnings[] = 'APP_DEBUG nao esta false; use true apenas durante depuracao controlada.';
}

$originalHealthToken = $_GET['token'] ?? null;
if (trim((string) ($env['HEALTHCHECK_TOKEN'] ?? '')) !== '') {
    $_GET['token'] = (string) $env['HEALTHCHECK_TOKEN'];
}

ob_start();
(new HealthController())->show();
$healthRaw = ob_get_clean();
if ($originalHealthToken === null) {
    unset($_GET['token']);
} else {
    $_GET['token'] = $originalHealthToken;
}
$health = json_decode($healthRaw, true);

if (!is_array($health)) {
    $failures[] = 'Healthcheck local nao retornou JSON valido.';
} elseif (($health['status'] ?? '') !== 'ok') {
    $failures[] = 'Healthcheck local nao esta OK: ' . $healthRaw;
}

if ($warnings !== []) {
    fwrite(STDERR, "Avisos locais:\n- " . implode("\n- ", $warnings) . "\n");
}

if ($failures !== []) {
    fwrite(STDERR, "Readiness local falhou:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Local readiness: OK\n";

function read_local_env(string $path): array
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
