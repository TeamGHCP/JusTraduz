<?php

require_once dirname(__DIR__) . '/backend/app/config/database.php';
require_once dirname(__DIR__) . '/backend/app/config/app.php';
require_once dirname(__DIR__) . '/backend/app/services/SubscriptionService.php';
require_once dirname(__DIR__) . '/backend/app/services/payments/AsaasPaymentProvider.php';

$options = getopt('', ['user-id:', 'plan-id:', 'cycle::']);
$userId = (int) ($options['user-id'] ?? 0);
$planId = (int) ($options['plan-id'] ?? 0);
$cycle = (string) ($options['cycle'] ?? 'monthly');

if ($userId <= 0 || $planId <= 0) {
    fwrite(STDERR, "Uso: php scripts/create-asaas-checkout.php --user-id=1 --plan-id=2 [--cycle=monthly|yearly]\n");
    exit(1);
}

if (!in_array($cycle, ['monthly', 'yearly'], true)) {
    fwrite(STDERR, "Ciclo invalido. Use monthly ou yearly.\n");
    exit(1);
}

$pdo = database_connection();
$subscriptions = new SubscriptionService($pdo);
$provider = new AsaasPaymentProvider($pdo, $subscriptions);

try {
    $checkout = $provider->createCheckout($userId, $planId, $cycle);
    if (!$checkout->ok) {
        fwrite(STDERR, 'Checkout recusado: ' . ($checkout->errorMessage ?? 'erro desconhecido') . PHP_EOL);
        exit(1);
    }

    echo json_encode([
        'ok' => true,
        'provider' => $provider->name(),
        'redirectUrl' => $checkout->redirectUrl,
        'subscriptionId' => $checkout->subscriptionId,
        'metadata' => [
            'provider_subscription_id' => $checkout->metadata['provider_subscription_id'] ?? null,
            'provider_customer_id' => $checkout->metadata['provider_customer_id'] ?? null,
            'amount_cents' => $checkout->metadata['amount_cents'] ?? null,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Asaas checkout erro: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
