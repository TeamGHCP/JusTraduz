<?php

namespace App\Services\Payments\Asaas;

use App\Services\Payments\Asaas\AsaasClient;
use RuntimeException;
use InvalidArgumentException;

class AsaasWebhook
{
    private AsaasClient $client;
    private string $webhookToken;

    public function __construct(AsaasClient $client, string $webhookToken)
    {
        $this->client = $client;
        $this->webhookToken = $webhookToken;
    }

    public function validateWebhookToken(array $headers): void
    {
        $secret = $this->webhookToken;
        if ($secret === '') {
            return;
        }

        $received = (string) ($headers['asaas-access-token'] ?? $headers['access-token'] ?? $headers['x-asaas-token'] ?? '');
        if ($received === '' || !hash_equals($secret, $received)) {
            throw new RuntimeException('Token do webhook Asaas inválido.', 401);
        }
    }

    public function paymentStatusFromEvent(string $event): string
    {
        return match ($event) {
            'PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED' => 'paid',
            'PAYMENT_OVERDUE',
            'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED',
            'PAYMENT_REPROVED_BY_RISK_ANALYSIS',
            'PAYMENT_DELETED',
            'PAYMENT_REFUNDED',
            'PAYMENT_PARTIALLY_REFUNDED',
            'PAYMENT_REFUND_IN_PROGRESS',
            'PAYMENT_RECEIVED_IN_CASH_UNDONE',
            'PAYMENT_CHARGEBACK_REQUESTED',
            'PAYMENT_CHARGEBACK_DISPUTE',
            'PAYMENT_AWAITING_CHARGEBACK_REVERSAL',
            'PAYMENT_BANK_SLIP_CANCELLED' => 'failed',
            default => 'pending',
        };
    }

    public function paymentStatusFromAsaasStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH' => 'paid',
            'OVERDUE',
            'REFUNDED',
            'REFUND_REQUESTED',
            'REFUND_IN_PROGRESS',
            'CHARGEBACK_REQUESTED',
            'CHARGEBACK_DISPUTE',
            'AWAITING_CHARGEBACK_REVERSAL',
            'DELETED',
            'CANCELLED',
            'CANCELED',
            'FAILED' => 'failed',
            default => 'pending',
        };
    }

    public function subscriptionStatusFromEvent(string $event): ?string
    {
        return match ($event) {
            'SUBSCRIPTION_DELETED', 'SUBSCRIPTION_INACTIVATED' => 'canceled',
            'SUBSCRIPTION_SPLIT_DIVERGENCE_BLOCK' => 'past_due',
            default => null,
        };
    }

    public function subscriptionStatusFromPaymentStatus(string $paymentStatus): ?string
    {
        return match ($paymentStatus) {
            'paid' => 'active',
            'failed' => 'past_due',
            'refunded' => 'canceled',
            default => null,
        };
    }

    public function checkoutUrlFromResponse(array $response): string
    {
        foreach (['invoiceUrl', 'paymentLink', 'bankSlipUrl', 'url'] as $key) {
            if (!empty($response[$key]) && is_string($response[$key])) {
                return $response[$key];
            }
        }

        return '';
    }
}
