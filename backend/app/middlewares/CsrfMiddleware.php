<?php

class CsrfMiddleware
{
    public static function generateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    public static function token(): string
    {
        return self::generateToken();
    }

    public static function validate(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = '';
        if (!empty($_POST['_csrf'])) {
            $token = (string) $_POST['_csrf'];
        } elseif (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        $stored = $_SESSION['_csrf_token'] ?? '';

        if (!is_string($stored) || !is_string($token) || !hash_equals($stored, $token)) {
            throw new RuntimeException('CSRF token inválido', 403);
        }
    }
}
