<?php

require_once __DIR__ . '/bootstrap.php';

reset_test_state();
$pdo = test_pdo();
putenv('RATE_LIMIT_DRIVER=db');

// Ensure clean database state
if (database_table_exists($pdo, 'rate_limits')) {
    $pdo->exec('DROP TABLE rate_limits');
}

// Call check once
$_SERVER['REMOTE_ADDR'] = '192.168.1.50';
App\Middlewares\RateLimiterMiddleware::check('/some/test/route');

// Assert table was created
assertTrue(database_table_exists($pdo, 'rate_limits'), 'Tabela rate_limits deve ser criada.');

// Assert record was created
$stmt = $pdo->query("SELECT * FROM rate_limits");
$rows = $stmt->fetchAll();
assertEquals(1, count($rows), 'Deve haver exatamente 1 registro de rate limit.');
assertEquals('rl:' . md5('ip:192.168.1.50:/some/test/route:100:60'), $rows[0]['key'], 'Chave do rate limit deve corresponder ao md5.');
assertEquals(1, (int)$rows[0]['hits'], 'Contador de hits deve iniciar em 1.');

// Increment hit
App\Middlewares\RateLimiterMiddleware::check('/some/test/route');
$stmt = $pdo->query("SELECT hits FROM rate_limits");
$hits = (int)$stmt->fetchColumn();
assertEquals(2, $hits, 'Contador de hits deve ser 2 após segunda requisição.');

putenv('RATE_LIMIT_DRIVER');
echo "RateLimiterTest: OK\n";
