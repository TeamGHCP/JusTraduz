<?php

require_once dirname(__DIR__) . '/backend/app/config/database.php';

try {
    $pdo = database_connection();
} catch (Throwable $e) {
    echo "Erro de conexão com o banco de dados: " . $e->getMessage() . "\n";
    exit(1);
}

// 1. Ensure schema_migrations table exists
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($driver === 'sqlite') {
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        version TEXT PRIMARY KEY,
        applied_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
} else {
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(120) PRIMARY KEY,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) DEFAULT CHARSET=utf8mb4;");
}

// 2. Scan database/migrations/ for .sql files
$migrationsDir = dirname(__DIR__) . '/database/migrations';
if (!is_dir($migrationsDir)) {
    mkdir($migrationsDir, 0775, true);
}

$files = glob($migrationsDir . '/*.sql');
sort($files);

$executed = $pdo->query("SELECT version FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);

echo "Iniciando execução de migrações...\n";

$count = 0;
foreach ($files as $file) {
    $version = basename($file);
    if (in_array($version, $executed, true)) {
        continue;
    }

    echo "Executando migração: {$version}...\n";

    $sql = file_get_contents($file);
    
    $pdo->beginTransaction();
    try {
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if ($stmt !== '') {
                $pdo->exec($stmt);
            }
        }
        
        $insert = $pdo->prepare("INSERT INTO schema_migrations (version) VALUES (?)");
        $insert->execute([$version]);
        
        $pdo->commit();
        echo "Migração {$version} executada com sucesso.\n";
        $count++;
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "Falha ao executar migração {$version}: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "Migrações concluídas. Total executado: {$count}.\n";
