<?php

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '/';
$path = str_replace('\\', '/', $path);

if (str_contains($path, '/backend/storage/documents/')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Acesso direto ao storage bloqueado.';
    return true;
}

return false;
