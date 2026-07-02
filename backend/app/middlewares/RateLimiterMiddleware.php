<?php

namespace App\Middlewares;

use PDO;
use Throwable;

class RateLimiterMiddleware
{
    private const PROFILES = [
        'auth' => ['max_hits' => 8, 'window' => 60],
        'register' => ['max_hits' => 5, 'window' => 300],
        'password_reset' => ['max_hits' => 5, 'window' => 300],
        'payment' => ['max_hits' => 20, 'window' => 60],
        'upload' => ['max_hits' => 12, 'window' => 300],
        'public_api' => ['max_hits' => 120, 'window' => 60],
        'invite' => ['max_hits' => 20, 'window' => 300],
        'oauth' => ['max_hits' => 10, 'window' => 300],
        'webhook' => ['max_hits' => 300, 'window' => 60],
        'admin' => ['max_hits' => 60, 'window' => 60],
        'default' => ['max_hits' => 100, 'window' => 60],
    ];

    public static function check(string $path, array $options = []): void
    {
        if (self::driver() !== 'db') {
            self::checkFile($path, $options);
            return;
        }

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

        self::applyShortLockTimeout($pdo);
        self::ensureTableExists($pdo);
        self::gc($pdo);

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $profile = self::profileForPath($path, (string) ($options['profile'] ?? ''));
        $maxHits = max(1, (int) ($options['max_hits'] ?? $profile['max_hits']));
        $window = max(1, (int) ($options['window'] ?? $profile['window']));
        $identity = self::identity($path);
        $key = 'rl:' . md5($identity . ':' . $path . ':' . $maxHits . ':' . $window);
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

    private static function driver(): string
    {
        $driver = strtolower(trim((string) getenv('RATE_LIMIT_DRIVER')));
        return $driver !== '' ? $driver : 'file';
    }

    private static function checkFile(string $path, array $options = []): void
    {
        $profile = self::profileForPath($path, (string) ($options['profile'] ?? ''));
        $maxHits = max(1, (int) ($options['max_hits'] ?? $profile['max_hits']));
        $window = max(1, (int) ($options['window'] ?? $profile['window']));
        $identity = self::identity($path);
        $key = md5($identity . ':' . $path . ':' . $maxHits . ':' . $window);
        $now = time();

        $dir = dirname(__DIR__, 2) . '/storage/rate-limits';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        self::gcFiles($dir, $now);

        $file = $dir . '/' . $key . '.json';
        $handle = @fopen($file, 'c+');
        if (!$handle) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }

            $contents = stream_get_contents($handle);
            $row = json_decode($contents !== false ? $contents : '', true);
            $hits = (int) ($row['hits'] ?? 0);
            $expiresAt = (int) ($row['expires_at'] ?? 0);

            if ($expiresAt <= $now) {
                $hits = 1;
                $expiresAt = $now + $window;
            } elseif ($hits >= $maxHits) {
                self::reject(max(1, $expiresAt - $now));
            } else {
                $hits++;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode(['hits' => $hits, 'expires_at' => $expiresAt]));
        } catch (Throwable) {
            return;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function gcFiles(string $dir, int $now): void
    {
        try {
            if (random_int(1, 100) > 5) {
                return;
            }

            foreach (glob($dir . '/*.json') ?: [] as $file) {
                $row = json_decode((string) @file_get_contents($file), true);
                if ((int) ($row['expires_at'] ?? 0) < $now) {
                    @unlink($file);
                }
            }
        } catch (Throwable) {
            // Ignore cleanup failures.
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

    private static function applyShortLockTimeout(PDO $pdo): void
    {
        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
                return;
            }

            $pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
            $pdo->exec('SET SESSION lock_wait_timeout = 1');
        } catch (Throwable) {
            // Ignore unsupported drivers/settings. Rate limiting must never block routing.
        }
    }

    private static function profileForPath(string $path, string $requestedProfile): array
    {
        if (isset(self::PROFILES[$requestedProfile])) {
            return self::PROFILES[$requestedProfile];
        }

        if (str_starts_with($path, '/api/v1/')) {
            return self::PROFILES['public_api'];
        }

        if (in_array($path, ['/auth/login', '/auth/admin-login'], true)) {
            return self::PROFILES['auth'];
        }

        if ($path === '/auth/registrar') {
            return self::PROFILES['register'];
        }

        if (in_array($path, ['/auth/reset-password', '/profile/password-code', '/profile/password-reset'], true)) {
            return self::PROFILES['password_reset'];
        }

        if (str_starts_with($path, '/billing/')) {
            return $path === '/billing/webhook' ? self::PROFILES['webhook'] : self::PROFILES['payment'];
        }

        if (str_starts_with($path, '/documents/')) {
            return self::PROFILES['upload'];
        }

        if (str_starts_with($path, '/organization/invite')) {
            return self::PROFILES['invite'];
        }

        if (str_starts_with($path, '/auth/google')) {
            return self::PROFILES['oauth'];
        }

        if (str_starts_with($path, '/admin/')) {
            return self::PROFILES['admin'];
        }

        return self::PROFILES['default'];
    }

    private static function identity(string $path): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $userId = (string) ($_SESSION['id'] ?? '');

        if ($userId !== '' && !in_array($path, ['/auth/login', '/auth/admin-login', '/auth/registrar', '/auth/reset-password'], true)) {
            return 'user:' . $userId . '|ip:' . $ip;
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if ($email !== '' && in_array($path, ['/auth/login', '/auth/admin-login', '/auth/registrar', '/auth/reset-password'], true)) {
            return 'email:' . hash('sha256', $email) . '|ip:' . $ip;
        }

        $apiKey = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if ($apiKey !== '' && str_starts_with($path, '/api/v1/')) {
            return 'api:' . hash('sha256', $apiKey) . '|ip:' . $ip;
        }

        return 'ip:' . $ip;
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
