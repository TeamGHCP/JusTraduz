<?php

require_once dirname(__DIR__) . '/support/session.php';

class CsrfMiddleware
{
    public static function generateToken(): string
    {
        secure_session_start();

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
        secure_session_start();

        $token = '';

        // 1) Prefer token from POST body
        if (!empty($_POST['_csrf'])) {
            $token = (string) $_POST['_csrf'];
        }

        // 2) Then check standard PHP-populated server var (fast)
        if ($token === '' && !empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        // 3) As a compatibility fallback, accept the X-CSRF-Token HTTP header
        //    checking headers case-insensitively (some servers/clients differ)
        if ($token === '') {
            $headers = [];
            if (function_exists('getallheaders')) {
                $headers = getallheaders();
            } elseif (!empty($_SERVER)) {
                // Build a simple headers list from $_SERVER when getallheaders isn't available
                foreach ($_SERVER as $k => $v) {
                    if (strpos($k, 'HTTP_') === 0) {
                        // Convert HTTP_HEADER_NAME to Header-Name
                        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
                        $headers[$name] = $v;
                    }
                }
            }

            foreach ($headers as $hName => $hVal) {
                if (!is_string($hName)) continue;
                $lower = strtolower($hName);
                if ($lower === 'x-csrf-token' || $lower === 'x-csrf' || $lower === 'x-csrf-token') {
                    $token = (string) $hVal;
                    break;
                }
            }
        }

        $stored = $_SESSION['_csrf_token'] ?? '';

        if (!is_string($stored) || !is_string($token) || !hash_equals($stored, $token)) {
            throw new RuntimeException('CSRF token inválido', 403);
        }
    }
}
