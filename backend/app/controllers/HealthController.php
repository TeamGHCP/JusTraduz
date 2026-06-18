<?php

require_once dirname(__DIR__) . '/core/Response.php';

class HealthController
{
    private Response $response;
    private array $env;
    private ?PDO $pdo = null;
    private bool $connectionAttempted = false;

    public function __construct()
    {
        $this->response = new Response();
        $this->env = $this->loadEnv(dirname(__DIR__, 2) . '/.env');
    }

    public function show(): void
    {
        if (!$this->authorized()) {
            $this->response->json([
                'status' => 'error',
                'message' => 'Healthcheck token invalido.',
                'timestamp' => date(DATE_ATOM),
            ], 403);
            return;
        }

        $checks = [
            'app_debug' => !$this->envEnabled('APP_DEBUG'),
            'database' => $this->databaseOk(),
            'storage_documents' => $this->storageOk('DOCUMENT_STORAGE_PATH', 'storage-private/documents'),
            'storage_attachments' => $this->storageOk('ATTACHMENT_STORAGE_PATH', 'storage-private/message-attachments'),
            'job_queue' => $this->tableOk('job_queue'),
            'mail_logs' => $this->tableOk('mail_logs'),
            'usage_events' => $this->tableOk('usage_events'),
        ];

        $ok = !in_array(false, $checks, true);
        $this->response->json([
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => date(DATE_ATOM),
        ], $ok ? 200 : 503);
    }

    private function authorized(): bool
    {
        $expected = $this->envValue('HEALTHCHECK_TOKEN');
        if ($expected === '') {
            return true;
        }

        $received = (string) ($_GET['token'] ?? ($_SERVER['HTTP_X_HEALTHCHECK_TOKEN'] ?? ''));
        return $received !== '' && hash_equals($expected, $received);
    }

    private function databaseOk(): bool
    {
        $pdo = $this->connection();
        if (!$pdo) {
            return false;
        }

        try {
            return $pdo->query('SELECT 1')->fetchColumn() !== false;
        } catch (Throwable $exception) {
            error_log('Health database check failed: ' . $exception->getMessage());
            return false;
        }
    }

    private function tableOk(string $table): bool
    {
        $pdo = $this->connection();
        if (!$pdo || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }

        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $statement = $pdo->query("PRAGMA table_info(" . $table . ")");
                foreach ($statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [] as $column) {
                    if (($column['name'] ?? '') === 'id') {
                        return true;
                    }
                }
                return false;
            }

            $statement = $pdo->query('SHOW COLUMNS FROM `' . $table . "` WHERE Field = 'id'");
            return (bool) ($statement && $statement->fetch(PDO::FETCH_ASSOC));
        } catch (Throwable) {
            return false;
        }
    }

    private function connection(): ?PDO
    {
        if ($this->connectionAttempted) {
            return $this->pdo;
        }

        $this->connectionAttempted = true;

        try {
            $dsn = $this->envValue('DB_DSN');
            $user = $this->envValue('DB_USER', 'root');
            $password = $this->envValue('DB_PASS');

            if ($dsn !== '') {
                $this->pdo = new PDO($dsn, $user, $password);
            } else {
                $host = $this->envValue('DB_HOST', '127.0.0.1');
                $port = $this->envValue('DB_PORT', '3306');
                $name = $this->envValue('DB_NAME', 'justraduz');
                $charset = $this->envValue('DB_CHARSET', 'utf8mb4');
                $mysqlDsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);
                $this->pdo = new PDO($mysqlDsn, $user, $password);
            }

            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            error_log('Health database connection failed: ' . $exception->getMessage());
            $this->pdo = null;
        }

        return $this->pdo;
    }

    private function storageOk(string $key, string $default): bool
    {
        $path = $this->envValue($key, $default);
        $absolute = $this->absolutePath($path);

        return is_dir($absolute) || is_dir(dirname($absolute));
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) || str_starts_with($path, '/') || str_starts_with($path, '\\\\')) {
            return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        }

        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
    }

    private function envEnabled(string $key): bool
    {
        return in_array(strtolower($this->envValue($key)), ['1', 'true', 'yes', 'on'], true);
    }

    private function envValue(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value !== false) {
            return (string) $value;
        }

        return array_key_exists($key, $this->env) ? $this->env[$key] : $default;
    }

    private function loadEnv(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $values[$key] = trim($value, "\"'");
        }

        return $values;
    }
}
