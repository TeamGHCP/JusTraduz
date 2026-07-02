<?php

require_once dirname(__DIR__) . '/backend/app/config/database.php';

$command = $argv[1] ?? 'up';
$allowedCommands = ['up', 'status', 'rollback'];

if (!in_array($command, $allowedCommands, true)) {
    fwrite(STDERR, "Uso: php scripts/run-migrations.php [up|status|rollback]\n");
    exit(1);
}

try {
    $pdo = database_connection();
} catch (Throwable $exception) {
    fwrite(STDERR, "Erro de conexao com o banco de dados: " . $exception->getMessage() . "\n");
    exit(1);
}

$migrationsDir = dirname(__DIR__) . '/database/migrations';

ensure_schema_migrations($pdo);

if ($command === 'status') {
    show_migration_status($pdo, $migrationsDir);
    exit(0);
}

if ($command === 'rollback') {
    rollback_last_migration($pdo, $migrationsDir);
    exit(0);
}

run_pending_migrations($pdo, $migrationsDir);

function ensure_schema_migrations(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            version TEXT PRIMARY KEY,
            checksum TEXT,
            applied_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(120) PRIMARY KEY,
            checksum CHAR(64) NULL,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) DEFAULT CHARSET=utf8mb4;");
    }

    if (function_exists('database_table_has_column') && !database_table_has_column($pdo, 'schema_migrations', 'checksum')) {
        $pdo->exec('ALTER TABLE schema_migrations ADD COLUMN checksum ' . ($driver === 'sqlite' ? 'TEXT' : 'CHAR(64) NULL'));
    }
}

function migration_files(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }

    $files = glob($dir . '/*.sql') ?: [];
    $files = array_values(array_filter($files, static fn (string $file): bool => !str_ends_with($file, '.down.sql')));
    sort($files);
    return $files;
}

function executed_migrations(PDO $pdo): array
{
    $rows = $pdo->query('SELECT version, checksum, applied_at FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_ASSOC);
    $executed = [];
    foreach ($rows as $row) {
        $executed[(string) $row['version']] = $row;
    }
    return $executed;
}

function run_pending_migrations(PDO $pdo, string $dir): void
{
    $executed = executed_migrations($pdo);
    $count = 0;

    echo "Iniciando execucao de migracoes...\n";

    foreach (migration_files($dir) as $file) {
        $version = basename($file);
        $checksum = hash_file('sha256', $file);

        if (isset($executed[$version])) {
            if (($executed[$version]['checksum'] ?? null) && $executed[$version]['checksum'] !== $checksum) {
                fwrite(STDERR, "Checksum divergente para migration ja aplicada: {$version}\n");
                exit(1);
            }
            continue;
        }

        echo "Executando migration: {$version}...\n";
        execute_sql_file($pdo, $file);

        $insert = $pdo->prepare('INSERT INTO schema_migrations (version, checksum) VALUES (?, ?)');
        $insert->execute([$version, $checksum]);

        echo "Migration {$version} executada com sucesso.\n";
        $count++;
    }

    echo "Migracoes concluidas. Total executado: {$count}.\n";
}

function show_migration_status(PDO $pdo, string $dir): void
{
    $executed = executed_migrations($pdo);
    foreach (migration_files($dir) as $file) {
        $version = basename($file);
        $status = isset($executed[$version]) ? 'aplicada' : 'pendente';
        echo "{$status} {$version}\n";
    }
}

function rollback_last_migration(PDO $pdo, string $dir): void
{
    $row = $pdo->query('SELECT version FROM schema_migrations ORDER BY version DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo "Nenhuma migration aplicada para rollback.\n";
        return;
    }

    $version = (string) $row['version'];
    $downFile = $dir . DIRECTORY_SEPARATOR . preg_replace('/\.sql$/', '.down.sql', $version);

    if (!is_file($downFile)) {
        fwrite(STDERR, "Rollback indisponivel: arquivo ausente " . basename($downFile) . "\n");
        exit(1);
    }

    echo "Executando rollback: {$version}...\n";
    execute_sql_file($pdo, $downFile);

    $delete = $pdo->prepare('DELETE FROM schema_migrations WHERE version = ?');
    $delete->execute([$version]);

    echo "Rollback {$version} executado com sucesso.\n";
}

function execute_sql_file(PDO $pdo, string $file): void
{
    $sql = (string) file_get_contents($file);
    $statements = sql_statements($sql);

    $pdo->beginTransaction();
    try {
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        fwrite(STDERR, "Falha em " . basename($file) . ': ' . $exception->getMessage() . "\n");
        exit(1);
    }
}

function sql_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $sql[$i + 1] ?? '';

        if ($quote !== null) {
            $buffer .= $char;
            if ($char === $quote && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $quote = null;
            }
            continue;
        }

        if (($char === '-' && $next === '-') || $char === '#') {
            while ($i < $length && !in_array($sql[$i], ["\n", "\r"], true)) {
                $i++;
            }
            $buffer .= "\n";
            continue;
        }

        if ($char === '/' && $next === '*') {
            $i += 2;
            while ($i < $length && !(($sql[$i] ?? '') === '*' && ($sql[$i + 1] ?? '') === '/')) {
                $i++;
            }
            $i++;
            continue;
        }

        if ($char === "'" || $char === '"') {
            $quote = $char;
            $buffer .= $char;
            continue;
        }

        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }

    return $statements;
}
