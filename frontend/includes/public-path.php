<?php
/**
 * Public URL prefix for pages that can run at domain root in production
 * or under /JusTraduz/frontend locally.
 */
$requestPath = str_replace('\\', '/', parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '/');
$frontendPos = stripos($requestPath, '/frontend/');

if ($frontendPos === false) {
    $publicPathPrefix = '/';
} else {
    $publicPathPrefix = substr($requestPath, 0, $frontendPos + strlen('/frontend/'));
}

$assetPrefix = $publicPathPrefix;
