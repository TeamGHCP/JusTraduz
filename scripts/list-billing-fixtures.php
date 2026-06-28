<?php

require_once dirname(__DIR__) . '/backend/app/config/database.php';

$pdo = database_connection();

echo "Clientes ativos:\n";
$stmt = $pdo->query(
    "SELECT id, tipo, status, CASE WHEN cpf IS NULL OR cpf = '' THEN 0 ELSE 1 END AS has_cpf
     FROM users
     WHERE tipo = 'cliente' AND status = 'ativo'
     ORDER BY id
     LIMIT 10"
);
foreach ($stmt as $row) {
    echo sprintf(
        "- id=%d tipo=%s status=%s cpf=%s\n",
        (int) $row['id'],
        (string) $row['tipo'],
        (string) $row['status'],
        ((int) $row['has_cpf']) === 1 ? 'sim' : 'nao'
    );
}

echo "\nPlanos ativos:\n";
$stmt = $pdo->query(
    'SELECT id, slug, name, monthly_price_cents, yearly_price_cents
     FROM plans
     WHERE active = 1
     ORDER BY sort_order
     LIMIT 10'
);
foreach ($stmt as $row) {
    echo sprintf(
        "- id=%d slug=%s mensal=%d anual=%d\n",
        (int) $row['id'],
        (string) $row['slug'],
        (int) $row['monthly_price_cents'],
        (int) $row['yearly_price_cents']
    );
}
