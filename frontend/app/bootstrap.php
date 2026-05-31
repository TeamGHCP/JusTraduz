<?php

// Configurações seguras de cookie de sessão
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

defined('FRONTEND_APP_PATH') || define('FRONTEND_APP_PATH', __DIR__);
defined('PROJECT_ROOT_PATH') || define('PROJECT_ROOT_PATH', dirname(__DIR__, 2));

require_once PROJECT_ROOT_PATH . '/backend/app/config/app.php';
require_once PROJECT_ROOT_PATH . '/backend/app/config/database.php';

require_once FRONTEND_APP_PATH . '/support/http.php';
require_once FRONTEND_APP_PATH . '/support/database.php';
require_once FRONTEND_APP_PATH . '/support/session.php';
require_once FRONTEND_APP_PATH . '/support/csrf.php';

require_once FRONTEND_APP_PATH . '/ui/icons.php';
require_once FRONTEND_APP_PATH . '/ui/navigation.php';
require_once FRONTEND_APP_PATH . '/ui/components.php';
