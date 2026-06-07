<?php

function security_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
}

function security_headers(bool $allowInlineScripts = false): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');

    $inline = $allowInlineScripts ? " 'unsafe-inline'" : '';
    header(
        "Content-Security-Policy: "
        . "default-src 'self'; "
        . "script-src 'self'{$inline} https://www.google.com https://www.gstatic.com; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data: blob: https:; "
        . "connect-src 'self'; "
        . "font-src 'self' data:; "
        . "object-src 'none'; "
        . "frame-src 'self' https://www.google.com; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'self';"
    );

    if (security_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

function security_expire_cookie(string $name): void
{
    if ($name === '' || headers_sent()) {
        return;
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $domain = $host !== '' && str_contains($host, ':') ? explode(':', $host, 2)[0] : $host;

    foreach (['', $domain] as $cookieDomain) {
        setcookie($name, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => (string) $cookieDomain,
            'secure' => security_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
