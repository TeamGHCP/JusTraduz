<?php

require_once __DIR__ . '/security.php';

function secure_session_configure(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => security_is_https(),
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

    if (!empty($_SESSION['logado'])) {
        secure_session_regenerate_if_due($now);
    }
}

function secure_session_regenerate_if_due(?int $now = null): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
        return;
    }

    $now ??= time();
    $lastRegeneration = (int) ($_SESSION['_session_regenerated_at'] ?? 0);
    if ($lastRegeneration <= 0) {
        $_SESSION['_session_regenerated_at'] = $now;
        return;
    }

    if (($now - $lastRegeneration) < (15 * 60)) {
        return;
    }

    session_regenerate_id(true);
    $_SESSION['_session_regenerated_at'] = $now;
}

function secure_session_regenerate_now(): void
{
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
        $_SESSION['_session_regenerated_at'] = time();
    }
}

function secure_session_destroy_current(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }

    security_expire_cookie(session_name() ?: 'PHPSESSID');
    security_expire_cookie('PHPSESSID');
    security_expire_cookie('session');
}
