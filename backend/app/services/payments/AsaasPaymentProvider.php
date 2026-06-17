<?php

require_once __DIR__ . '/PaymentProviderInterface.php';
require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__) . '/SubscriptionService.php';

class AsaasPaymentProvider implements PaymentProviderInterface
{
    private PDO $pdo;
    private SubscriptionService $subscriptions;
    private string $apiUrl;
    private string $apiKey;
    private string $billingType;

    public function __construct(PDO $pdo, SubscriptionService $subscriptions)
    {
        $this->pdo = $pdo;
        $this->subscriptions = $subscriptions;
        $this->apiUrl = rtrim($this->env('ASAAS_API_URL', 'https://api-sandbox.asaas.com/v3'), '/');
        $this->apiKey = $this->env('ASAAS_API_KEY', '');
        $this->billingType = strtoupper($this->env('ASAAS_BILLING_TYPE', 'UNDEFINED'));
    }

    public function name(): string
    {
        return 'asaas';
    }

    public function createCheckout(int $userId, int $planId, string $billingCycle): PaymentCheckoutResult
    {
        $this->assertConfigured();

        if (!$this->subscriptions->userCanSubscribe($userId)) {
            return PaymentCheckoutResult::error('Usuario nao pode assinar planos.');
        }

        $user = $this->fetchUser($userId);
        $plan = $this->fetchPlan($planId);
        if (!$user || !$plan) {
            return PaymentCheckoutResult::error('Plano invalido.');
        }

        if (!in_array($billingCycle, ['monthly', 'yearly'], true)) {
            $billingCycle = 'monthly';
        }

        $amount = $billingCycle === 'yearly'
            ? (int) $plan['yearly_price_cents']
            : (int) $plan['monthly_price_cents'];

        $customerId = $this->findOrCreateCustomer($user);
        $externalReference = 'justraduz_subscription_' . $userId . '_' . $planId . '_' . $billingCycle;
        $subscription = $this->request('POST', '/subscriptions', [
            'customer' => $customerId,
            'billingType' => $this->billingType,
            'value' => $amount / 100,
            'nextDueDate' => date('Y-m-d'),
            'cycle' => $billingCycle === 'yearly' ? 'YEARLY' : 'MONTHLY',
            'description' => 'JusTraduz - Plano ' . (string) $plan['name'],
            'externalReference' => $externalReference,
        ]);

        $providerSubscriptionId = (string) ($subscription['id'] ?? '');
        if ($providerSubscriptionId === '') {
            return PaymentCheckoutResult::error('Asaas nao retornou o ID da assinatura.');
        }

        $firstPayment = $this->firstPaymentForSubscription($providerSubscriptionId);

        $this->recordPaymentEvent(null, $userId, 'subscription.created', $amount, 'pending', [
            'provider_subscription_id' => $providerSubscriptionId,
            'provider_customer_id' => $customerId,
            'plan_id' => $planId,
            'billing_cycle' => $billingCycle,
            'external_reference' => $externalReference,
            'asaas_response' => $subscription,
            'asaas_first_payment' => $firstPayment,
        ], $providerSubscriptionId);

        $paymentSource = $firstPayment ?: $subscription;
        $redirectUrl = $this->checkoutUrlFromResponse($paymentSource);
        if ($redirectUrl === '') {
            $redirectUrl = app_url('/frontend/subir-plano.php?sucesso=' . urlencode('Assinatura criada no Asaas. Aguarde a confirmacao do pagamento.'));
        }

        return PaymentCheckoutResult::success($redirectUrl, null, [
            'provider_subscription_id' => $providerSubscriptionId,
            'provider_customer_id' => $customerId,
            'provider_payment_id' => (string) ($firstPayment['id'] ?? ''),
            'amount_cents' => $amount,
            'checkout_url' => $redirectUrl,
            'invoice_url' => (string) ($paymentSource['invoiceUrl'] ?? ''),
            'bank_slip_url' => (string) ($paymentSource['bankSlipUrl'] ?? ''),
            'payment_link' => (string) ($paymentSource['paymentLink'] ?? ''),
            'due_date' => (string) ($paymentSource['dueDate'] ?? $subscription['nextDueDate'] ?? ''),
        ]);
    }

    public function handleWebhook(string $rawPayload, array $headers): array
    {
        $this->validateWebhookToken($headers);

        $payload = json_decode($rawPayload, true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Payload do webhook Asaas invalido.');
        }

        $event = (string) ($payload['event'] ?? $payload['event_type'] ?? 'asaas.webhook');
        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
        $subscriptionPayload = is_array($payload['subscription'] ?? null) ? $payload['subscription'] : [];

        $providerSubscriptionId = (string) (
            $payment['subscription']
            ?? $subscriptionPayload['id']
            ?? $payload['subscription']
            ?? ''
        );
        $providerEventId = (string) ($payment['id'] ?? $subscriptionPayload['id'] ?? $payload['id'] ?? '');
        $amount = (int) round(((float) ($payment['value'] ?? $subscriptionPayload['value'] ?? 0)) * 100);
        $paymentStatus = $this->paymentStatusFromEvent($event);

        $subscription = $this->findLocalSubscription($providerSubscriptionId);
        if (!$subscription && $paymentStatus === 'paid') {
            $subscription = $this->createLocalSubscriptionFromPendingEvent($providerSubscriptionId);
        }

        $subscriptionId = $subscription ? (int) $subscription['id'] : null;
        $userId = $subscription ? (int) $subscription['user_id'] : null;

        $this->recordPaymentEvent($subscriptionId, $userId, $event, $amount, $paymentStatus, $payload, $providerEventId ?: null);

        $newStatus = $this->subscriptionStatusFromPaymentStatus($paymentStatus);
        if ($subscriptionId && $newStatus !== null) {
            $stmt = $this->pdo->prepare('UPDATE subscriptions SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$newStatus, $subscriptionId]);
        }

        return [
            'provider' => $this->name(),
            'event_type' => $event,
            'status' => $paymentStatus,
            'subscription_id' => $subscriptionId,
            'user_id' => $userId,
            'provider_subscription_id' => $providerSubscriptionId ?: null,
        ];
    }

    public function syncCheckoutPayment(int $userId, string $providerSubscriptionId): array
    {
        $this->assertConfigured();

        $providerSubscriptionId = trim($providerSubscriptionId);
        if ($providerSubscriptionId === '') {
            throw new InvalidArgumentException('Assinatura Asaas nao informada.');
        }

        $local = $this->findLocalSubscription($providerSubscriptionId);
        if ($local && (int) ($local['user_id'] ?? 0) === $userId && (string) ($local['status'] ?? '') === 'active') {
            return [
                'ok' => true,
                'provider' => $this->name(),
                'status' => 'paid',
                'subscription_id' => (int) $local['id'],
                'provider_subscription_id' => $providerSubscriptionId,
                'already_active' => true,
            ];
        }

        $pending = $this->pendingEventForProviderSubscription($providerSubscriptionId);
        if (!$pending || (int) ($pending['user_id'] ?? 0) !== $userId) {
            throw new RuntimeException('Cobrança Asaas não encontrada para este usuário.');
        }

        $payment = $this->latestPaymentForSubscription($providerSubscriptionId);
        if (!$payment) {
            return [
                'ok' => true,
                'provider' => $this->name(),
                'status' => 'pending',
                'subscription_id' => null,
                'provider_subscription_id' => $providerSubscriptionId,
            ];
        }

        $paymentStatus = $this->paymentStatusFromAsaasStatus((string) ($payment['status'] ?? ''));
        $amount = (int) round(((float) ($payment['value'] ?? 0)) * 100);
        $providerPaymentId = (string) ($payment['id'] ?? '');

        $subscription = $this->findLocalSubscription($providerSubscriptionId);
        if (!$subscription && $paymentStatus === 'paid') {
            $subscription = $this->createLocalSubscriptionFromPendingEvent($providerSubscriptionId);
        }

        $subscriptionId = $subscription ? (int) $subscription['id'] : null;
        $this->recordPaymentEvent($subscriptionId, $userId, 'payment.sync_' . $paymentStatus, $amount, $paymentStatus, [
            'provider_subscription_id' => $providerSubscriptionId,
            'asaas_payment' => $payment,
            'source' => 'billing.sync',
        ], $providerPaymentId !== '' ? $providerPaymentId : null);

        if ($subscriptionId && $paymentStatus === 'paid') {
            $stmt = $this->pdo->prepare('UPDATE subscriptions SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute(['active', $subscriptionId]);
        }

        return [
            'ok' => true,
            'provider' => $this->name(),
            'status' => $paymentStatus,
            'subscription_id' => $subscriptionId,
            'provider_subscription_id' => $providerSubscriptionId,
            'provider_payment_id' => $providerPaymentId ?: null,
        ];
    }

    public function cancelSubscription(int $userId): array
    {
        $this->assertConfigured();

        $subscription = $this->subscriptions->currentForUser($userId);
        if (!$subscription) {
            return ['ok' => true, 'provider' => $this->name(), 'already_free' => true];
        }

        $providerSubscriptionId = (string) ($subscription['provider_subscription_id'] ?? '');
        $remoteCanceled = false;

        if ((string) ($subscription['provider'] ?? '') === $this->name() && $providerSubscriptionId !== '') {
            $this->request('DELETE', '/subscriptions/' . rawurlencode($providerSubscriptionId));
            $remoteCanceled = true;
        }

        if (!$this->subscriptions->cancelCurrentForUser($userId)) {
            throw new RuntimeException('Nao foi possivel cancelar a assinatura local.');
        }

        $this->recordPaymentEvent(
            (int) $subscription['id'],
            $userId,
            'subscription.canceled',
            0,
            'refunded',
            [
                'source' => 'frontend/perfil.php',
                'provider_subscription_id' => $providerSubscriptionId,
                'remote_canceled' => $remoteCanceled,
                'previous_plan_id' => (int) ($subscription['plan_id'] ?? 0),
            ],
            $providerSubscriptionId !== '' ? $providerSubscriptionId : null
        );

        return [
            'ok' => true,
            'provider' => $this->name(),
            'subscription_id' => (int) $subscription['id'],
            'provider_subscription_id' => $providerSubscriptionId ?: null,
            'remote_canceled' => $remoteCanceled,
        ];
    }

    public function ping(): array
    {
        $this->assertConfigured();
        return $this->request('GET', '/customers', ['limit' => 1]);
    }

    private function findOrCreateCustomer(array $user): string
    {
        $externalReference = 'justraduz_user_' . (int) $user['id'];
        $existing = $this->request('GET', '/customers', [
            'externalReference' => $externalReference,
            'limit' => 1,
        ]);

        if (!empty($existing['data'][0]['id'])) {
            return (string) $existing['data'][0]['id'];
        }

        $payload = [
            'name' => (string) $user['nome'],
            'email' => (string) $user['email'],
            'externalReference' => $externalReference,
            'notificationDisabled' => false,
        ];

        $cpfCnpj = $this->cpfCnpjForCustomer($user);
        if ($cpfCnpj !== '') {
            $payload['cpfCnpj'] = $cpfCnpj;
        }

        $phone = preg_replace('/\D+/', '', (string) ($user['telefone'] ?? '')) ?: '';
        if ($phone !== '') {
            $payload['mobilePhone'] = $phone;
        }

        $created = $this->request('POST', '/customers', $payload);
        $customerId = (string) ($created['id'] ?? '');
        if ($customerId === '') {
            throw new RuntimeException('Asaas nao retornou o ID do cliente.');
        }

        return $customerId;
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extensao cURL do PHP nao esta habilitada.');
        }

        $method = strtoupper($method);
        $url = $this->apiUrl . '/' . ltrim($path, '/');
        if ($method === 'GET' && $payload) {
            $url .= '?' . http_build_query($payload);
        }

        $curl = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'content-type: application/json',
                'User-Agent: JusTraduz/1.0 billing-integration',
                'access_token: ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 25,
        ];

        if ($method !== 'GET') {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false) {
            throw new RuntimeException('Falha cURL Asaas: ' . $error);
        }

        $data = json_decode((string) $response, true);
        if (!is_array($data)) {
            $data = ['raw' => (string) $response];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $message = $this->errorMessage($data) ?: 'Erro HTTP ' . $httpCode . ' no Asaas.';
            throw new RuntimeException($message, $httpCode);
        }

        return $data;
    }

    private function fetchUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, nome, email, cpf, telefone FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    private function fetchPlan(int $planId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM plans WHERE id = ? AND active = 1 LIMIT 1');
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();

        return $plan ?: null;
    }

    private function findLocalSubscription(string $providerSubscriptionId): ?array
    {
        if ($providerSubscriptionId === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM subscriptions WHERE provider = ? AND provider_subscription_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$this->name(), $providerSubscriptionId]);
        $subscription = $stmt->fetch();

        return $subscription ?: null;
    }

    private function firstPaymentForSubscription(string $providerSubscriptionId): array
    {
        try {
            $payments = $this->request('GET', '/payments', [
                'subscription' => $providerSubscriptionId,
                'limit' => 1,
            ]);
        } catch (Throwable) {
            return [];
        }

        return is_array($payments['data'][0] ?? null) ? $payments['data'][0] : [];
    }

    private function latestPaymentForSubscription(string $providerSubscriptionId): array
    {
        $payments = $this->request('GET', '/payments', [
            'subscription' => $providerSubscriptionId,
            'limit' => 10,
        ]);

        $items = is_array($payments['data'] ?? null) ? $payments['data'] : [];
        foreach ($items as $payment) {
            if (is_array($payment) && $this->paymentStatusFromAsaasStatus((string) ($payment['status'] ?? '')) === 'paid') {
                return $payment;
            }
        }

        return is_array($items[0] ?? null) ? $items[0] : [];
    }

    private function createLocalSubscriptionFromPendingEvent(string $providerSubscriptionId): ?array
    {
        $pending = $this->pendingEventForProviderSubscription($providerSubscriptionId);
        if (!$pending) {
            return null;
        }

        $payload = json_decode((string) ($pending['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            return null;
        }

        $userId = (int) ($pending['user_id'] ?? 0);
        $planId = (int) ($payload['plan_id'] ?? 0);
        $cycle = (string) ($payload['billing_cycle'] ?? 'monthly');
        if ($userId <= 0 || $planId <= 0 || !$this->subscriptions->changePlan($userId, $planId, $cycle, 'active')) {
            return null;
        }

        $subscription = $this->subscriptions->currentForUser($userId);
        if (!$subscription) {
            return null;
        }

        $stmt = $this->pdo->prepare('UPDATE subscriptions SET provider = ?, provider_subscription_id = ? WHERE id = ?');
        $stmt->execute([$this->name(), $providerSubscriptionId, (int) $subscription['id']]);

        return $this->findLocalSubscription($providerSubscriptionId);
    }

    private function pendingEventForProviderSubscription(string $providerSubscriptionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM payment_events WHERE provider = ? AND provider_event_id = ? AND event_type = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$this->name(), $providerSubscriptionId, 'subscription.created']);
        $event = $stmt->fetch();

        return $event ?: null;
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
            mb_substr($eventType, 0, 120),
            $amount,
            $status,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            date('Y-m-d H:i:s'),
        ]);
    }

    private function paymentStatusFromEvent(string $event): string
    {
        return match ($event) {
            'PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED' => 'paid',
            'PAYMENT_OVERDUE', 'PAYMENT_DELETED', 'PAYMENT_RESTORED', 'PAYMENT_REFUNDED', 'PAYMENT_CHARGEBACK_REQUESTED', 'PAYMENT_CHARGEBACK_DISPUTE', 'PAYMENT_AWAITING_CHARGEBACK_REVERSAL' => 'failed',
            default => 'pending',
        };
    }

    private function paymentStatusFromAsaasStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH' => 'paid',
            'OVERDUE', 'REFUNDED', 'REFUND_REQUESTED', 'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE', 'AWAITING_CHARGEBACK_REVERSAL' => 'failed',
            default => 'pending',
        };
    }

    private function subscriptionStatusFromPaymentStatus(string $paymentStatus): ?string
    {
        return match ($paymentStatus) {
            'paid' => 'active',
            'failed' => 'past_due',
            'refunded' => 'canceled',
            default => null,
        };
    }

    private function checkoutUrlFromResponse(array $response): string
    {
        foreach (['invoiceUrl', 'paymentLink', 'bankSlipUrl', 'url'] as $key) {
            if (!empty($response[$key]) && is_string($response[$key])) {
                return $response[$key];
            }
        }

        return '';
    }

    private function validateWebhookToken(array $headers): void
    {
        $secret = $this->env('ASAAS_WEBHOOK_TOKEN', '');
        if ($secret === '') {
            return;
        }

        $received = (string) ($headers['asaas-access-token'] ?? $headers['access-token'] ?? $headers['x-asaas-token'] ?? '');
        if ($received === '' || !hash_equals($secret, $received)) {
            throw new RuntimeException('Token do webhook Asaas invalido.', 401);
        }
    }

    private function cpfCnpjForCustomer(array $user): string
    {
        $sandboxDocument = preg_replace('/\D+/', '', $this->env('ASAAS_SANDBOX_CPF_CNPJ', '')) ?: '';
        if ($sandboxDocument !== '' && str_contains($this->apiUrl, 'api-sandbox.asaas.com')) {
            return $sandboxDocument;
        }

        return preg_replace('/\D+/', '', (string) ($user['cpf'] ?? '')) ?: '';
    }

    private function assertConfigured(): void
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('Configure ASAAS_API_KEY no backend/.env.');
        }
    }

    private function env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value !== false && (string) $value !== '') {
            return (string) $value;
        }

        $env = function_exists('database_env_values') ? database_env_values(dirname(__DIR__, 3) . '/.env') : [];
        return (string) ($env[$key] ?? $default);
    }

    private function errorMessage(array $data): string
    {
        if (!empty($data['errors'][0]['description'])) {
            return (string) $data['errors'][0]['description'];
        }

        if (!empty($data['message'])) {
            return (string) $data['message'];
        }

        return '';
    }
}
