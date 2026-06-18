<?php

require_once __DIR__ . '/PaymentCheckoutResult.php';

interface PaymentProviderInterface
{
    public function name(): string;

    public function createCheckout(int $userId, int $planId, string $billingCycle, array $paymentData = []): PaymentCheckoutResult;

    public function syncCheckoutPayment(int $userId, string $providerSubscriptionId): array;

    public function cancelCheckout(int $userId, string $providerSubscriptionId): array;

    public function cancelSubscription(int $userId): array;

    public function handleWebhook(string $rawPayload, array $headers): array;
}
