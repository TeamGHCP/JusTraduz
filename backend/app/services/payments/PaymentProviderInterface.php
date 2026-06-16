<?php

require_once __DIR__ . '/PaymentCheckoutResult.php';

interface PaymentProviderInterface
{
    public function name(): string;

    public function createCheckout(int $userId, int $planId, string $billingCycle): PaymentCheckoutResult;

    public function handleWebhook(string $rawPayload, array $headers): array;
}
