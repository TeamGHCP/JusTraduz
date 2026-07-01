<?php

namespace App\Middlewares;

use PDO;
use Throwable;

class RateLimiterMiddleware
{
    public static function check(string $path): void
    {
        try {
            require_once dirname(__DIR__) . '/config/database.php';
            if (function_exists('database_connection')) {
                $pdo = database_connection();
            } else {
                global $pdo;
            }
        } catch (Throwable) {
            // Bypass rate limiting if database is unavailable
            return;
        }

        if (!$pdo instanceof PDO) {
            return;
        }

        self::ensureTableExists($pdo);
        self::gc($pdo);

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = 'rl:' . md5($ip . ':' . $path);

        // Sensitive paths have lower limit (10 hits per min)
        $isSensitive = (in_array($path, [
            '/login', '/register', '/recuperar-senha', '/pagamento', '/checkout', '/billing/webhook',
            '/auth/login', '/auth/registrar', '/auth/reset-password', '/auth/admin-login'
        ], true) || str_contains($path, '/auth/')) && $path !== '/auth/csrf';

        $maxHits = $isSensitive ? 10 : 100;
        $window = 60; // 1 minute
        $now = time();

        try {
            $stmt = $pdo->prepare('SELECT hits, expires_at FROM rate_limits WHERE `key` = ?');
            $stmt->execute([$key]);
            $row = $stmt->fetch();

            if ($row) {
                $hits = (int) $row['hits'];
                $expiresAt = (int) $row['expires_at'];

                if ($now > $expiresAt) {
                    // Expired: reset window
                    $stmt = $pdo->prepare('UPDATE rate_limits SET hits = 1, expires_at = ? WHERE `key` = ?');
                    $stmt->execute([$now + $window, $key]);
                } elseif ($hits >= $maxHits) {
                    // Limit exceeded
                    $retryAfter = $expiresAt - $now;
                    self::reject($retryAfter);
                } else {
                    // Increment hits
                    $stmt = $pdo->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE `key` = ?');
                    $stmt->execute([$key]);
                }
            } else {
                // First hit in this window
                $stmt = $pdo->prepare('INSERT INTO rate_limits (`key`, hits, expires_at) VALUES (?, 1, ?)');
                $stmt->execute([$key, $now + $window]);
            }
        } catch (Throwable) {
            // Bypass rate limiting on sql exceptions to avoid breaking application
            return;
        }
    }

    private static function ensureTableExists(PDO $pdo): void
    {
        try {
            if (!database_table_exists($pdo, 'rate_limits')) {
                $sql = 'CREATE TABLE rate_limits (
                    `key` VARCHAR(255) PRIMARY KEY,
                    hits INT NOT NULL,
                    expires_at INT NOT NULL
                )';
                $pdo->exec($sql);
            }
        } catch (Throwable) {
            // Ignore creation errors
        }
    }

    private static function gc(PDO $pdo): void
    {
        try {
            // 5% chance of garbage collection
            if (random_int(1, 100) <= 5) {
                $stmt = $pdo->prepare('DELETE FROM rate_limits WHERE expires_at < ?');
                $stmt->execute([time()]);
            }
        } catch (Throwable) {
            // Ignore gc errors
        }
    }

    private static function reject(int $retryAfter): void
    {
        http_response_code(429);
        header('Retry-After: ' . $retryAfter);

        $expectsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');

        if ($expectsJson) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Too Many Requests',
                'message' => "Muitas requisições. Tente novamente em {$retryAfter} segundos.",
                'retry_after' => $retryAfter
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Muitas Requisições</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; background: #f6f8fb; color: #202124; }
        h1 { color: #d93025; }
        p { font-size: 18px; }
    </style>
</head>
<body>
    <h1>Muitas Requisições (429)</h1>
    <p>Você excedeu o limite de requisições permitidas para esta página.</p>
    <p>Por favor, tente novamente em <strong>{$retryAfter}</strong> segundos.</p>
</body>
</html>";
        exit;
    }
}
