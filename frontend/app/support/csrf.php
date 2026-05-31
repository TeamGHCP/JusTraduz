<?php

defined('PROJECT_ROOT_PATH') || define('PROJECT_ROOT_PATH', dirname(__DIR__, 3));
require_once PROJECT_ROOT_PATH . '/backend/app/support/session.php';

function csrf_token(): string
{
    secure_session_start();

    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_input(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<input type="hidden" name="_csrf" value="' . $token . '">';
}
