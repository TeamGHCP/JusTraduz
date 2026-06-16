<?php

require_once dirname(__DIR__) . '/backend/app/config/database.php';

$pdo = database_connection();
$stmt = $pdo->query(
    'SELECT id, subscription_id, user_id, provider, provider_event_id, event_type, amount_cents, status, created_at
     FROM payment_events
     ORDER BY id DESC
     LIMIT 10'
);

foreach ($stmt as $row) {
    echo sprintf(
        "#%d provider=%s event=%s status=%s amount=%d user=%s subscription=%s provider_event=%s created=%s\n",
        (int) $row['id'],
        (string) $row['provider'],
        (string) $row['event_type'],
        (string) $row['status'],
        (int) $row['amount_cents'],
        (string) ($row['user_id'] ?? '-'),
        (string) ($row['subscription_id'] ?? '-'),
        (string) ($row['provider_event_id'] ?? '-'),
        (string) $row['created_at']
    );
}
