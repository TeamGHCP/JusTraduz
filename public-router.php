<?php

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '/';
$path = str_replace('\\', '/', $path);

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

return false;
