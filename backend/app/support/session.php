<?php

function secure_session_configure(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function secure_session_start(): void
{
    secure_session_configure();

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $timeoutSeconds = 30 * 60;
    $now = time();
    $lastActivity = (int) ($_SESSION['_last_activity'] ?? 0);

    if (!empty($_SESSION['logado']) && $lastActivity > 0 && ($now - $lastActivity) > $timeoutSeconds) {
        session_unset();
        session_destroy();
        secure_session_configure();
        session_start();
    }

    $_SESSION['_last_activity'] = $now;
}
