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

        $host = getenv('DB_HOST') ?: ($env['DB_HOST'] ?? 'localhost');
        $dbname = getenv('DB_NAME') ?: ($env['DB_NAME'] ?? 'justraduz');
        $usuario = getenv('DB_USER') ?: ($env['DB_USER'] ?? 'root');
        $senha = getenv('DB_PASS') ?: ($env['DB_PASS'] ?? '');
        $port = getenv('DB_PORT') ?: ($env['DB_PORT'] ?? '3307');

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

$pdo = database_connection();
