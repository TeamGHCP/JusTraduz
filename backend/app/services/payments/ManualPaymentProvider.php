<?php

require_once __DIR__ . '/PaymentProviderInterface.php';
require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__) . '/SubscriptionService.php';

class ManualPaymentProvider implements PaymentProviderInterface
{
    private PDO $pdo;
    private SubscriptionService $subscriptions;

    public function __construct(PDO $pdo, SubscriptionService $subscriptions)
    {
        $this->pdo = $pdo;
        $this->subscriptions = $subscriptions;
    }

    public function name(): string
    {
        return 'manual_checkout';
    }

    public function createCheckout(int $userId, int $planId, string $billingCycle): PaymentCheckoutResult
    {
        if (!$this->subscriptions->changePlan($userId, $planId, $billingCycle, 'active')) {
            return PaymentCheckoutResult::error('Plano invalido.');
        }

        $subscription = $this->subscriptions->currentForUser($userId);
        if (!$subscription) {
            return PaymentCheckoutResult::error('Nao foi possivel localizar a assinatura criada.');
        }

        $amount = $this->amountForPlan($planId, $billingCycle);
        $this->recordPaymentEvent(
            (int) $subscription['id'],
            $userId,
            'checkout.paid',
            $amount,
            'paid',
            [
                'billing_cycle' => $billingCycle,
                'source' => 'frontend/subir-plano.php',
                'mode' => 'manual_immediate',
            ]
        );

        return PaymentCheckoutResult::success(
            app_url('/frontend/subir-plano.php?sucesso=' . urlencode('Plano atualizado com sucesso.')),
            (int) $subscription['id'],
            ['amount_cents' => $amount]
        );
    }

    public function handleWebhook(string $rawPayload, array $headers): array
    {
        $this->validateSignature($rawPayload, $headers);

        $payload = json_decode($rawPayload, true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $subscriptionId = max(0, (int) ($payload['subscription_id'] ?? 0));
        $userId = max(0, (int) ($payload['user_id'] ?? 0));
        $eventType = mb_substr((string) ($payload['event_type'] ?? 'webhook.received'), 0, 120);
        $paymentStatus = $this->normalizePaymentStatus((string) ($payload['status'] ?? 'paid'));
        $amount = max(0, (int) ($payload['amount_cents'] ?? 0));
        $providerEventId = trim((string) ($payload['provider_event_id'] ?? ''));

        if ($subscriptionId <= 0 && $userId <= 0) {
            throw new InvalidArgumentException('Webhook sem usuario ou assinatura.');
        }

        $this->recordPaymentEvent(
            $subscriptionId > 0 ? $subscriptionId : null,
            $userId > 0 ? $userId : null,
            $eventType,
            $amount,
            $paymentStatus,
            $payload,
            $providerEventId !== '' ? $providerEventId : null
        );

        $subscriptionStatus = $this->subscriptionStatusFromPayload($payload, $paymentStatus);
        if ($subscriptionId > 0 && $subscriptionStatus !== null) {
            $stmt = $this->pdo->prepare('UPDATE subscriptions SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$subscriptionStatus, $subscriptionId]);
        }

        return [
            'provider' => $this->name(),
            'event_type' => $eventType,
            'status' => $paymentStatus,
            'subscription_id' => $subscriptionId ?: null,
            'user_id' => $userId ?: null,
        ];
    }

    private function amountForPlan(int $planId, string $billingCycle): int
    {
        $priceColumn = $billingCycle === 'yearly' ? 'yearly_price_cents' : 'monthly_price_cents';
        $stmt = $this->pdo->prepare("SELECT {$priceColumn} FROM plans WHERE id = ? AND active = 1");
        $stmt->execute([$planId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function recordPaymentEvent(?int $subscriptionId, ?int $userId, string $eventType, int $amount, string $status, array $payload, ?string $providerEventId = null): void
    {
        if (!database_table_exists($this->pdo, 'payment_events')) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO payment_events (subscription_id, user_id, provider, provider_event_id, event_type, amount_cents, status, payload_json, processed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $subscriptionId,
            $userId,
            $this->name(),
            $providerEventId,
            $eventType,
            $amount,
            $status,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            date('Y-m-d H:i:s'),
        ]);
    }

    private function validateSignature(string $rawPayload, array $headers): void
    {
        $secret = getenv('BILLING_WEBHOOK_SECRET');
        if ($secret === false || trim((string) $secret) === '') {
            return;
        }

        $signature = (string) ($headers['x-justraduz-signature'] ?? $headers['x-manual-signature'] ?? '');
        $expected = hash_hmac('sha256', $rawPayload, (string) $secret);
        if ($signature === '' || !hash_equals($expected, $signature)) {
            throw new RuntimeException('Assinatura do webhook invalida.', 401);
        }
    }

    private function normalizePaymentStatus(string $status): string
    {
        return match ($status) {
            'paid', 'failed', 'refunded' => $status,
            default => 'pending',
        };
    }

    private function subscriptionStatusFromPayload(array $payload, string $paymentStatus): ?string
    {
        $explicit = (string) ($payload['subscription_status'] ?? '');
        if (in_array($explicit, ['trialing', 'active', 'past_due', 'canceled', 'expired'], true)) {
            return $explicit;
        }

        return match ($paymentStatus) {
            'paid' => 'active',
            'failed' => 'past_due',
            'refunded' => 'canceled',
            default => null,
        };
    }
}
