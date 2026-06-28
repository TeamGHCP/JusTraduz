<?php

require_once dirname(__DIR__) . '/backend/app/config/database.php';
require_once dirname(__DIR__) . '/backend/app/services/SubscriptionService.php';
require_once dirname(__DIR__) . '/backend/app/services/payments/AsaasPaymentProvider.php';

$provider = new AsaasPaymentProvider(database_connection(), new SubscriptionService(database_connection()));

try {
    $result = $provider->ping();
    echo 'Asaas HTTP OK' . PHP_EOL;
    echo json_encode([
        'object' => $result['object'] ?? null,
        'totalCount' => $result['totalCount'] ?? null,
        'hasMore' => $result['hasMore'] ?? null,
        'sampleSize' => isset($result['data']) && is_array($result['data']) ? count($result['data']) : null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Asaas erro: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
