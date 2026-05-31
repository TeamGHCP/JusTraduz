<?php

$appTimezone = getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo';
if (is_string($appTimezone) && $appTimezone !== '') {
    date_default_timezone_set($appTimezone);
}

function app_base_path(): string
{
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    $override = getenv('APP_BASE_PATH');
    if ($override !== false) {
        $basePath = rtrim('/' . trim((string) $override, '/'), '/');
        return $basePath === '/' ? '' : $basePath;
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    foreach (['/backend/', '/frontend/'] as $marker) {
        $position = strpos($scriptName, $marker);
        if ($position !== false) {
            $basePath = rtrim(substr($scriptName, 0, $position), '/');
            return $basePath === '/' ? '' : $basePath;
        }
    }

    $basePath = '';
    return $basePath;
}

function app_url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return app_base_path() . ($path === '/' ? '' : $path);
}
