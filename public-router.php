<?php

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '/';
$path = str_replace('\\', '/', $path);
$documentRoot = __DIR__;
$frontendRoot = __DIR__ . '/frontend';

if (str_starts_with($path, '/frontend/')) {
    $target = substr($path, strlen('/frontend'));
    $query = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
    header('Location: ' . ($target === '' ? '/' : $target) . ($query ? '?' . $query : ''), true, 301);
    return true;
}

$blockedPaths = [
    '/backend/.env',
    '/backend/app/',
    '/backend/routes/',
    '/backend/storage/documents/',
    '/backend/storage/message-attachments/',
    '/database/',
    '/docs/',
    '/.git/',
];

foreach ($blockedPaths as $blockedPath) {
    if (!str_contains($path, $blockedPath)) {
        continue;
    }

    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Acesso direto bloqueado.';
    return true;
}

$requestedFile = realpath($documentRoot . rawurldecode($path));
$rootPrefix = rtrim((string) realpath($documentRoot), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$frontendPrefix = rtrim((string) realpath($frontendRoot), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

if (
    $requestedFile !== false
    && str_starts_with($requestedFile, $rootPrefix)
    && is_file($requestedFile)
) {
    return false;
}

$decodedPath = rawurldecode($path);
foreach (['.html', '.php'] as $extension) {
    $extensionFile = realpath($documentRoot . $decodedPath . $extension);

    if (
        $extensionFile !== false
        && str_starts_with($extensionFile, $rootPrefix)
        && is_file($extensionFile)
    ) {
        if ($extension === '.php') {
            $_SERVER['SCRIPT_FILENAME'] = $extensionFile;
            require $extensionFile;
            return true;
        }

        header('Content-Type: text/html; charset=UTF-8');
        readfile($extensionFile);
        return true;
    }
}

$frontendFile = realpath($frontendRoot . $decodedPath);
if (
    $frontendFile !== false
    && str_starts_with($frontendFile, $frontendPrefix)
    && is_file($frontendFile)
) {
    readfile($frontendFile);
    return true;
}

foreach (['.html', '.php'] as $extension) {
    $extensionFile = realpath($frontendRoot . $decodedPath . $extension);

    if (
        $extensionFile !== false
        && str_starts_with($extensionFile, $frontendPrefix)
        && is_file($extensionFile)
    ) {
        if ($extension === '.php') {
            $_SERVER['SCRIPT_FILENAME'] = $extensionFile;
            require $extensionFile;
            return true;
        }

        header('Content-Type: text/html; charset=UTF-8');
        readfile($extensionFile);
        return true;
    }
}

$notFoundPage = $documentRoot . '/frontend/404.php';
http_response_code(404);

if (is_file($notFoundPage)) {
    $_SERVER['SCRIPT_FILENAME'] = $notFoundPage;
    require $notFoundPage;
    return true;
}

echo 'Recurso nao encontrado.';
return true;
