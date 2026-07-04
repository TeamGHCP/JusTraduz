<?php

if (!function_exists('database_env_values')) {
    function database_env_values(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $values = [];
        foreach ((array) file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim(trim($value), "\"'");
        }

        return $values;
    }
}

if (!function_exists('database_connection')) {
    function database_connection(): PDO
    {
        static $connection = null;

        if ($connection instanceof PDO) {
            return $connection;
        }

        $env = database_env_values(dirname(__DIR__, 2) . '/.env');

        $dsn = getenv('DB_DSN') ?: ($env['DB_DSN'] ?? '');
        if (is_string($dsn) && $dsn !== '') {
            $connection = new PDO($dsn);
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $connection;
        }

        $host = getenv('DB_HOST') ?: ($env['DB_HOST'] ?? 'localhost');
        $dbname = getenv('DB_NAME') ?: ($env['DB_NAME'] ?? 'justraduz');
        $usuario = getenv('DB_USER') ?: ($env['DB_USER'] ?? 'root');
        $senha = getenv('DB_PASS') ?: ($env['DB_PASS'] ?? '');
        $port = getenv('DB_PORT') ?: ($env['DB_PORT'] ?? '3306');

        try {
            $connection = new PDO(
                "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
                $usuario,
                $senha
            );
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw new RuntimeException('Erro na conexão com o banco de dados');
        }

        return $connection;
    }
}

if (!function_exists('database_table_has_column')) {
    function database_table_has_column(PDO $pdo, string $table, string $column): bool
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);

        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info(" . $safeTable . ")");
            foreach ($stmt->fetchAll() as $row) {
                if (($row['name'] ?? '') === $column) {
                    return true;
                }
            }

            return false;
        }

        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$safeTable` WHERE Field = ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetch();
    }
}

if (!function_exists('database_table_exists')) {
    function database_table_exists(PDO $pdo, string $table): bool
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);

        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
            $stmt->execute([$safeTable]);
            return (bool) $stmt->fetch();
        }

        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$safeTable]);
        return (bool) $stmt->fetch();
    }
}

$pdo = database_connection();
