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
        . "script-src 'self'{$inline} 'wasm-unsafe-eval' blob: https://www.google.com https://www.gstatic.com https://vlibras.gov.br https://www.vlibras.gov.br https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' https://vlibras.gov.br https://www.vlibras.gov.br https://cdn.jsdelivr.net; "
        . "img-src 'self' data: blob: https:; "
        . "connect-src 'self' https://vlibras.gov.br https://www.vlibras.gov.br https://dicionario2.vlibras.gov.br https://cdn.jsdelivr.net; "
        . "font-src 'self' data: https://vlibras.gov.br https://www.vlibras.gov.br https://cdn.jsdelivr.net; "
        . "media-src 'self' blob: https://vlibras.gov.br https://www.vlibras.gov.br https://cdn.jsdelivr.net; "
        . "object-src 'none'; "
        . "worker-src 'self' blob:; "
        . "frame-src 'self' https://www.google.com https://vlibras.gov.br https://www.vlibras.gov.br; "
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
