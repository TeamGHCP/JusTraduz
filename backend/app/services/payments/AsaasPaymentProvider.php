<?php

require_once __DIR__ . '/PaymentProviderInterface.php';
require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__) . '/BillingEmailService.php';
require_once dirname(__DIR__) . '/NotificationService.php';
require_once dirname(__DIR__) . '/OrganizationInviteService.php';
require_once dirname(__DIR__) . '/SubscriptionService.php';

class AsaasPaymentProvider implements PaymentProviderInterface
{
    private PDO $pdo;
    private BillingEmailService $billingEmails;
    private NotificationService $notifications;
    private SubscriptionService $subscriptions;
    private string $apiUrl;
    private string $apiKey;
    private string $billingType;

    public function __construct(PDO $pdo, SubscriptionService $subscriptions)
    {
        $this->pdo = $pdo;
        $this->billingEmails = new BillingEmailService();
        $this->notifications = new NotificationService($pdo);
        $this->subscriptions = $subscriptions;
        $this->apiUrl = rtrim($this->env('ASAAS_API_URL', 'https://api-sandbox.asaas.com/v3'), '/');
        $this->apiKey = $this->env('ASAAS_API_KEY', '');
        $this->billingType = strtoupper($this->env('ASAAS_BILLING_TYPE', 'UNDEFINED'));
    }

    public function name(): string
    {
        return 'asaas';
    }

    public function createCheckout(int $userId, int $planId, string $billingCycle, array $paymentData = []): PaymentCheckoutResult
    {
        $this->assertConfigured();

        if (!$this->subscriptions->userCanSubscribe($userId)) {
            return PaymentCheckoutResult::error('Usuário não pode assinar planos.');
        }

        $user = $this->fetchUser($userId);
        if (!$user || !$this->subscriptions->planAvailableForUser($userId, $planId)) {
            return PaymentCheckoutResult::error('Plano inválido.');
        }

        $plan = $this->fetchPlan($planId);
        if (!$plan) {
            return PaymentCheckoutResult::error('Plano inválido.');
        }

        if (!in_array($billingCycle, ['monthly', 'yearly'], true)) {
            $billingCycle = 'monthly';
        }

        $amount = $billingCycle === 'yearly'
            ? (int) $plan['yearly_price_cents']
            : (int) $plan['monthly_price_cents'];
        $teamInvites = (new OrganizationInviteService($this->pdo))->validateOfficeInviteRequest(
            $plan,
            is_array($paymentData['team_invites'] ?? null) ? $paymentData['team_invites'] : []
        );

        $paymentMethod = $this->normalizePaymentMethod((string) ($paymentData['method'] ?? ''));
        $billingType = $this->billingTypeForMethod($paymentMethod);
        $customerId = $this->findOrCreateCustomer($user);
        $externalReference = 'justraduz_subscription_' . $userId . '_' . $planId . '_' . $billingCycle;
        $payload = [
            'customer' => $customerId,
            'billingType' => $billingType,
            'value' => $amount / 100,
            'nextDueDate' => date('Y-m-d'),
            'cycle' => $billingCycle === 'yearly' ? 'YEARLY' : 'MONTHLY',
            'description' => 'JusTraduz - Plano ' . (string) $plan['name'],
            'externalReference' => $externalReference,
        ];

        if ($paymentMethod === 'credit_card') {
            $payload = array_merge($payload, $this->creditCardPayload($paymentData, $user));
        }

        $subscription = $this->request('POST', '/subscriptions', $payload);

        $providerSubscriptionId = (string) ($subscription['id'] ?? '');
        if ($providerSubscriptionId === '') {
            return PaymentCheckoutResult::error('Asaas não retornou o ID da assinatura.');
        }

        $firstPayment = $this->firstPaymentForSubscription($providerSubscriptionId);

        $this->recordPaymentEvent(null, $userId, 'subscription.created', $amount, 'pending', [
            'provider_subscription_id' => $providerSubscriptionId,
            'provider_customer_id' => $customerId,
            'plan_id' => $planId,
            'billing_cycle' => $billingCycle,
            'team_invites' => $teamInvites,
            'external_reference' => $externalReference,
            'asaas_response' => $subscription,
            'asaas_first_payment' => $firstPayment,
        ], $providerSubscriptionId);

        $paymentSource = $firstPayment ?: $subscription;
        $providerPaymentId = (string) ($firstPayment['id'] ?? '');
        $paymentStatus = $firstPayment
            ? $this->paymentStatusFromAsaasStatus((string) ($firstPayment['status'] ?? ''))
            : 'pending';
        $activatedSubscription = null;
        if ($paymentMethod === 'credit_card' && $paymentStatus === 'paid') {
            $activatedSubscription = $this->activatePaidCheckout(
                $providerSubscriptionId,
                $userId,
                $amount,
                $firstPayment,
                $providerPaymentId,
                $teamInvites
            );
        }

        if ($paymentMethod === 'credit_card' && $paymentStatus === 'failed') {
            $this->recordPaymentEvent(null, $userId, 'payment.card_immediate_failed', $amount, 'failed', [
                'provider_subscription_id' => $providerSubscriptionId,
                'asaas_payment' => $firstPayment,
                'source' => 'billing.checkout',
            ], $providerPaymentId !== '' ? $providerPaymentId : null);

            try {
                $this->request('DELETE', '/subscriptions/' . rawurlencode($providerSubscriptionId));
            } catch (Throwable $exception) {
                error_log('Asaas failed card subscription cleanup failed: ' . $exception->getMessage());
            }

            return PaymentCheckoutResult::error($this->errorMessage($firstPayment) ?: 'Cartao recusado pelo Asaas. Confira os dados ou tente outro cartao.');
        }

        $pixQrCode = $providerPaymentId !== '' ? $this->pixQrCodeForPayment($providerPaymentId) : [];
        $redirectUrl = $this->checkoutUrlFromResponse($paymentSource);
        if ($redirectUrl === '') {
            $redirectUrl = app_url('/frontend/subir-plano.php?sucesso=' . urlencode('Assinatura criada no Asaas. Aguarde a confirmacao do pagamento.'));
        }

        return PaymentCheckoutResult::success($redirectUrl, $activatedSubscription ? (int) $activatedSubscription['id'] : null, [
            'provider_subscription_id' => $providerSubscriptionId,
            'provider_customer_id' => $customerId,
            'provider_payment_id' => $providerPaymentId,
            'amount_cents' => $amount,
            'checkout_url' => $redirectUrl,
            'invoice_url' => (string) ($paymentSource['invoiceUrl'] ?? ''),
            'payment_link' => (string) ($paymentSource['paymentLink'] ?? ''),
            'due_date' => (string) ($paymentSource['dueDate'] ?? $subscription['nextDueDate'] ?? ''),
            'billing_type' => (string) ($paymentSource['billingType'] ?? $billingType),
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'asaas_payment_status' => (string) ($paymentSource['status'] ?? 'PENDING'),
            'local_subscription_activated' => $activatedSubscription !== null,
            'previous_subscription_id' => (int) ($activatedSubscription['previous_subscription_id'] ?? 0),
            'previous_plan_id' => (int) ($activatedSubscription['previous_plan_id'] ?? 0),
            'previous_plan_name' => (string) ($activatedSubscription['previous_plan_name'] ?? ''),
            'previous_remote_cancel_error' => (string) ($activatedSubscription['previous_remote_cancel_error'] ?? ''),
            'team_invites' => $teamInvites,
            'team_invites_sent' => (array) ($activatedSubscription['team_invites_sent'] ?? []),
            'pix_qr_code' => $pixQrCode,
        ]);
    }

    public function handleWebhook(string $rawPayload, array $headers): array
    {
        $this->validateWebhookToken($headers);

        $payload = json_decode($rawPayload, true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Payload do webhook Asaas inválido.');
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
        $hadLocalSubscription = (bool) $subscription;
        $alreadyProcessedPaidEvent = $paymentStatus === 'paid'
            && $providerEventId !== ''
            && $this->hasProcessedPaidEvent($providerEventId);
        if (!$subscription && $paymentStatus === 'paid') {
            $subscription = $this->createLocalSubscriptionFromPendingEvent($providerSubscriptionId);
        }

        $subscriptionId = $subscription ? (int) $subscription['id'] : null;
        $userId = $subscription ? (int) $subscription['user_id'] : null;
        $teamInvitesSent = [];
        if ($subscriptionId && $userId && $paymentStatus === 'paid') {
            $teamInvitesSent = is_array($subscription['team_invites_sent'] ?? null)
                ? $subscription['team_invites_sent']
                : $this->issuePendingTeamInvites($providerSubscriptionId, (int) $userId, (int) $subscriptionId, $subscription);
            $payload['team_invites_sent'] = $teamInvitesSent;
        }

        $this->recordPaymentEvent($subscriptionId, $userId, $event, $amount, $paymentStatus, $payload, $providerEventId ?: null);

        $newStatus = $this->subscriptionStatusFromEvent($event) ?? $this->subscriptionStatusFromPaymentStatus($paymentStatus);
        if ($subscriptionId && $newStatus !== null) {
            $stmt = $this->pdo->prepare('UPDATE subscriptions SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$newStatus, $subscriptionId]);
        }

        if ($subscriptionId && $paymentStatus === 'paid' && $hadLocalSubscription && !$alreadyProcessedPaidEvent) {
            $this->subscriptions->renewCurrentPeriod($subscriptionId);
        }

        if ($userId && $paymentStatus === 'paid') {
            $this->notifyPlanPaid((int) $userId, $subscriptionId, $amount);
        }

        return [
            'provider' => $this->name(),
            'event_type' => $event,
            'status' => $paymentStatus,
            'subscription_id' => $subscriptionId,
            'user_id' => $userId,
            'provider_subscription_id' => $providerSubscriptionId ?: null,
            'previous_subscription_id' => (int) ($subscription['previous_subscription_id'] ?? 0) ?: null,
            'previous_plan_id' => (int) ($subscription['previous_plan_id'] ?? 0) ?: null,
            'previous_plan_name' => (string) ($subscription['previous_plan_name'] ?? '') ?: null,
            'previous_remote_cancel_error' => (string) ($subscription['previous_remote_cancel_error'] ?? '') ?: null,
            'team_invites_sent' => $teamInvitesSent,
        ];
    }

    public function syncCheckoutPayment(int $userId, string $providerSubscriptionId): array
    {
        $this->assertConfigured();

        $providerSubscriptionId = trim($providerSubscriptionId);
        if ($providerSubscriptionId === '') {
            throw new InvalidArgumentException('Assinatura Asaas não informada.');
        }

        $local = $this->findLocalSubscription($providerSubscriptionId);
        if ($local && (int) ($local['user_id'] ?? 0) === $userId && (string) ($local['status'] ?? '') === 'active') {
            $alreadySent = $this->teamInvitesAlreadySent($providerSubscriptionId);
            $teamInvitesSent = $this->issuePendingTeamInvites($providerSubscriptionId, $userId, (int) $local['id'], $local);
            if ($alreadySent === [] && $teamInvitesSent !== []) {
                $this->recordPaymentEvent((int) $local['id'], $userId, 'team_invites.sent', 0, 'paid', [
                    'provider_subscription_id' => $providerSubscriptionId,
                    'source' => 'billing.sync.already_active',
                    'team_invites_sent' => $teamInvitesSent,
                ]);
            }
            return [
                'ok' => true,
                'provider' => $this->name(),
                'status' => 'paid',
                'subscription_id' => (int) $local['id'],
                'provider_subscription_id' => $providerSubscriptionId,
                'already_active' => true,
                'team_invites_sent' => $teamInvitesSent,
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
        $hadLocalSubscription = (bool) $subscription;
        $alreadyProcessedPaidEvent = $paymentStatus === 'paid'
            && $providerPaymentId !== ''
            && $this->hasProcessedPaidEvent($providerPaymentId);
        if (!$subscription && $paymentStatus === 'paid') {
            $subscription = $this->createLocalSubscriptionFromPendingEvent($providerSubscriptionId);
        }

        $subscriptionId = $subscription ? (int) $subscription['id'] : null;
        $teamInvitesSent = [];
        if ($subscriptionId && $paymentStatus === 'paid') {
            $teamInvitesSent = is_array($subscription['team_invites_sent'] ?? null)
                ? $subscription['team_invites_sent']
                : $this->issuePendingTeamInvites($providerSubscriptionId, $userId, $subscriptionId, $subscription);
        }
        $this->recordPaymentEvent($subscriptionId, $userId, 'payment.sync_' . $paymentStatus, $amount, $paymentStatus, [
            'provider_subscription_id' => $providerSubscriptionId,
            'asaas_payment' => $payment,
            'source' => 'billing.sync',
            'team_invites_sent' => $teamInvitesSent,
        ], $providerPaymentId !== '' ? $providerPaymentId : null);

        if ($subscriptionId && $paymentStatus === 'paid') {
            $stmt = $this->pdo->prepare('UPDATE subscriptions SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute(['active', $subscriptionId]);
            if ($hadLocalSubscription && !$alreadyProcessedPaidEvent) {
                $this->subscriptions->renewCurrentPeriod($subscriptionId);
            }
            $this->notifyPlanPaid($userId, $subscriptionId, $amount);
        }

        return [
            'ok' => true,
            'provider' => $this->name(),
            'status' => $paymentStatus,
            'subscription_id' => $subscriptionId,
            'provider_subscription_id' => $providerSubscriptionId,
            'provider_payment_id' => $providerPaymentId ?: null,
            'previous_subscription_id' => (int) ($subscription['previous_subscription_id'] ?? 0) ?: null,
            'previous_plan_id' => (int) ($subscription['previous_plan_id'] ?? 0) ?: null,
            'previous_plan_name' => (string) ($subscription['previous_plan_name'] ?? '') ?: null,
            'previous_remote_cancel_error' => (string) ($subscription['previous_remote_cancel_error'] ?? '') ?: null,
            'team_invites_sent' => $teamInvitesSent,
        ];
    }

    public function confirmSandboxPayment(string $providerPaymentId): array
    {
        $this->assertConfigured();

        $providerPaymentId = trim($providerPaymentId);
        if ($providerPaymentId === '') {
            return [
                'ok' => false,
                'provider' => $this->name(),
                'sandbox_confirmed' => false,
                'reason' => 'missing_payment_id',
            ];
        }

        if (!str_contains($this->apiUrl, 'api-sandbox.asaas.com')) {
            return [
                'ok' => true,
                'provider' => $this->name(),
                'sandbox_confirmed' => false,
                'reason' => 'not_sandbox',
            ];
        }

        $payment = $this->request('POST', '/sandbox/payment/' . rawurlencode($providerPaymentId) . '/confirm');

        return [
            'ok' => true,
            'provider' => $this->name(),
            'sandbox_confirmed' => true,
            'provider_payment_id' => $providerPaymentId,
            'asaas_status' => (string) ($payment['status'] ?? ''),
        ];
    }

    public function cancelSubscription(int $userId): array
    {
        $this->assertConfigured();

        $subscription = $this->subscriptions->currentForUser($userId);
        if (!$subscription) {
            return ['ok' => true, 'provider' => $this->name(), 'already_free' => true];
        }

        if ($this->isFreePlan($subscription)) {
            return [
                'ok' => true,
                'provider' => $this->name(),
                'already_free' => true,
                'subscription_id' => (int) $subscription['id'],
            ];
        }

        $providerSubscriptionId = (string) ($subscription['provider_subscription_id'] ?? '');
        $remoteCanceled = false;

        if ((string) ($subscription['provider'] ?? '') === $this->name() && $providerSubscriptionId !== '') {
            $this->request('DELETE', '/subscriptions/' . rawurlencode($providerSubscriptionId));
            $remoteCanceled = true;
        }

        if (!$this->subscriptions->cancelCurrentForUser($userId)) {
            throw new RuntimeException('Não foi possível cancelar a assinatura local.');
        }

        $freeSubscription = $this->subscriptions->ensureDefaultForUser($userId);

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

        $this->notifyPlanCanceled($userId, (string) ($subscription['plan_name'] ?? ''));

        return [
            'ok' => true,
            'provider' => $this->name(),
            'subscription_id' => (int) $subscription['id'],
            'free_subscription_id' => $freeSubscription ? (int) $freeSubscription['id'] : null,
            'provider_subscription_id' => $providerSubscriptionId ?: null,
            'remote_canceled' => $remoteCanceled,
        ];
    }

    public function cancelCheckout(int $userId, string $providerSubscriptionId): array
    {
        $this->assertConfigured();

        $providerSubscriptionId = trim($providerSubscriptionId);
        if ($providerSubscriptionId === '') {
            return [
                'ok' => true,
                'provider' => $this->name(),
                'provider_subscription_id' => null,
                'remote_canceled' => false,
            ];
        }

        $local = $this->findLocalSubscription($providerSubscriptionId);
        if ($local && (int) ($local['user_id'] ?? 0) !== $userId) {
            throw new RuntimeException('Cobrança Asaas não pertence a este usuário.');
        }

        $pending = $this->pendingEventForProviderSubscription($providerSubscriptionId);
        if (!$local && (!$pending || (int) ($pending['user_id'] ?? 0) !== $userId)) {
            throw new RuntimeException('Cobrança Asaas não encontrada para este usuário.');
        }

        $remoteCanceled = false;
        if (!$local || (string) ($local['status'] ?? '') !== 'active') {
            $this->request('DELETE', '/subscriptions/' . rawurlencode($providerSubscriptionId));
            $remoteCanceled = true;
        }

        $this->recordPaymentEvent(
            $local ? (int) $local['id'] : null,
            $userId,
            'checkout.canceled',
            0,
            'refunded',
            [
                'source' => 'frontend/pagamento-plano.php',
                'provider_subscription_id' => $providerSubscriptionId,
                'remote_canceled' => $remoteCanceled,
            ],
            $providerSubscriptionId
        );

        return [
            'ok' => true,
            'provider' => $this->name(),
            'subscription_id' => $local ? (int) $local['id'] : null,
            'provider_subscription_id' => $providerSubscriptionId,
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
            throw new RuntimeException('Asaas não retornou o ID do cliente.');
        }

        return $customerId;
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extensão cURL do PHP não está habilitada.');
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
        $stmt = $this->pdo->prepare('SELECT id, nome, email, tipo, cpf, telefone FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    private function isFreePlan(array $subscription): bool
    {
        return in_array((string) ($subscription['plan_slug'] ?? ''), ['gratuito', 'free', 'profissional_basico', 'advogado_basico'], true);
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

    private function pixQrCodeForPayment(string $providerPaymentId): array
    {
        try {
            $qrCode = $this->request('GET', '/payments/' . rawurlencode($providerPaymentId) . '/pixQrCode');
        } catch (Throwable) {
            return [];
        }

        return [
            'encoded_image' => (string) ($qrCode['encodedImage'] ?? ''),
            'payload' => (string) ($qrCode['payload'] ?? ''),
            'expiration_date' => (string) ($qrCode['expirationDate'] ?? ''),
        ];
    }

    private function normalizePaymentMethod(string $method): string
    {
        return match (strtolower(trim($method))) {
            'pix' => 'pix',
            'card', 'credit_card' => 'credit_card',
            default => 'pix',
        };
    }

    private function billingTypeForMethod(string $method): string
    {
        return match ($method) {
            'pix' => 'PIX',
            'credit_card' => 'CREDIT_CARD',
            default => $this->billingType,
        };
    }

    private function creditCardPayload(array $paymentData, array $user): array
    {
        $card = is_array($paymentData['card'] ?? null) ? $paymentData['card'] : [];
        $holder = is_array($paymentData['holder'] ?? null) ? $paymentData['holder'] : [];
        $number = preg_replace('/\D+/', '', (string) ($card['number'] ?? '')) ?: '';
        $expiryMonth = str_pad((string) (int) preg_replace('/\D+/', '', (string) ($card['expiry_month'] ?? '')), 2, '0', STR_PAD_LEFT);
        $expiryYear = preg_replace('/\D+/', '', (string) ($card['expiry_year'] ?? '')) ?: '';
        if (strlen($expiryYear) === 2) {
            $expiryYear = '20' . $expiryYear;
        }

        $ccv = preg_replace('/\D+/', '', (string) ($card['ccv'] ?? '')) ?: '';
        $holderName = trim((string) ($card['holder_name'] ?? $holder['name'] ?? $user['nome'] ?? ''));
        $cpfCnpj = preg_replace('/\D+/', '', (string) ($holder['cpf_cnpj'] ?? $user['cpf'] ?? '')) ?: '';
        $postalCode = preg_replace('/\D+/', '', (string) ($holder['postal_code'] ?? '')) ?: '';
        $addressNumber = trim((string) ($holder['address_number'] ?? ''));
        $phone = preg_replace('/\D+/', '', (string) ($holder['phone'] ?? $user['telefone'] ?? '')) ?: '';

        if ($number === '' || strlen($number) < 13 || $holderName === '' || $expiryMonth === '00' || $expiryYear === '' || $ccv === '') {
            throw new InvalidArgumentException('Informe os dados do cartão corretamente.');
        }

        if ($cpfCnpj === '' || $postalCode === '' || $addressNumber === '' || $phone === '') {
            throw new InvalidArgumentException('Informe CPF, CEP, número do endereço e telefone do titular do cartão.');
        }

        return [
            'creditCard' => [
                'holderName' => $holderName,
                'number' => $number,
                'expiryMonth' => $expiryMonth,
                'expiryYear' => $expiryYear,
                'ccv' => $ccv,
            ],
            'creditCardHolderInfo' => [
                'name' => trim((string) ($holder['name'] ?? $holderName)),
                'email' => trim((string) ($holder['email'] ?? $user['email'] ?? '')),
                'cpfCnpj' => $cpfCnpj,
                'postalCode' => $postalCode,
                'addressNumber' => $addressNumber,
                'addressComplement' => trim((string) ($holder['address_complement'] ?? '')),
                'phone' => $phone,
                'mobilePhone' => $phone,
            ],
            'remoteIp' => trim((string) ($paymentData['remote_ip'] ?? '127.0.0.1')),
        ];
    }

    private function activatePaidCheckout(
        string $providerSubscriptionId,
        int $userId,
        int $amount,
        array $payment,
        string $providerPaymentId,
        array $teamInvites = []
    ): ?array {
        $subscription = $this->findLocalSubscription($providerSubscriptionId);
        if (!$subscription) {
            $subscription = $this->createLocalSubscriptionFromPendingEvent($providerSubscriptionId);
        }

        if (!$subscription) {
            return null;
        }

        $subscriptionId = (int) $subscription['id'];
        $alreadyProcessedPaidEvent = $providerPaymentId !== '' && $this->hasProcessedPaidEvent($providerPaymentId);

        if (!$alreadyProcessedPaidEvent) {
            $this->recordPaymentEvent($subscriptionId, $userId, 'payment.card_immediate_confirmed', $amount, 'paid', [
                'provider_subscription_id' => $providerSubscriptionId,
                'asaas_payment' => $payment,
                'source' => 'billing.checkout',
            ], $providerPaymentId !== '' ? $providerPaymentId : null);
        }

        $stmt = $this->pdo->prepare('UPDATE subscriptions SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute(['active', $subscriptionId]);
        $sentInvites = is_array($subscription['team_invites_sent'] ?? null)
            ? $subscription['team_invites_sent']
            : (new OrganizationInviteService($this->pdo))->issueForOfficeSubscription($userId, $this->subscriptionWithPlan($subscriptionId) ?: $subscription, $teamInvites);
        $this->notifyPlanPaid($userId, $subscriptionId, $amount);

        $withPlan = $this->subscriptionWithPlan($subscriptionId) ?: $subscription;
        foreach (['previous_subscription_id', 'previous_plan_id', 'previous_plan_name', 'previous_remote_cancel_error'] as $key) {
            if (array_key_exists($key, $subscription)) {
                $withPlan[$key] = $subscription[$key];
            }
        }
        $withPlan['team_invites_sent'] = $sentInvites;

        return $withPlan;
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
        $teamInvites = is_array($payload['team_invites'] ?? null) ? $payload['team_invites'] : [];
        $previousSubscription = $this->subscriptions->currentForUser($userId);
        $isReplacement = $previousSubscription
            && in_array((string) ($previousSubscription['status'] ?? ''), ['trialing', 'active', 'past_due'], true)
            && (
                (int) ($previousSubscription['plan_id'] ?? 0) !== $planId
                || (string) ($previousSubscription['billing_cycle'] ?? '') !== $cycle
                || trim((string) ($previousSubscription['provider_subscription_id'] ?? '')) !== ''
            );

        if ($userId <= 0 || $planId <= 0 || !$this->subscriptions->changePlan($userId, $planId, $cycle, 'active')) {
            return null;
        }

        $subscription = $this->subscriptions->currentForUser($userId);
        if (!$subscription) {
            return null;
        }

        $stmt = $this->pdo->prepare('UPDATE subscriptions SET provider = ?, provider_subscription_id = ? WHERE id = ?');
        $stmt->execute([$this->name(), $providerSubscriptionId, (int) $subscription['id']]);

        $activated = $this->findLocalSubscription($providerSubscriptionId);
        if ($activated && $isReplacement && $previousSubscription) {
            $remoteCancelError = $this->finalizePlanReplacement($userId, $previousSubscription, $activated, $providerSubscriptionId);
            $activated['previous_subscription_id'] = (int) ($previousSubscription['id'] ?? 0);
            $activated['previous_plan_id'] = (int) ($previousSubscription['plan_id'] ?? 0);
            $activated['previous_plan_name'] = (string) ($previousSubscription['plan_name'] ?? '');
            $activated['previous_remote_cancel_error'] = $remoteCancelError;
        }
        if ($activated) {
            $activated['team_invites_sent'] = (new OrganizationInviteService($this->pdo))->issueForOfficeSubscription($userId, $activated, $teamInvites);
        }

        return $activated;
    }

    private function issuePendingTeamInvites(string $providerSubscriptionId, int $userId, int $subscriptionId, ?array $subscription = null): array
    {
        $alreadySent = $this->teamInvitesAlreadySent($providerSubscriptionId);
        if ($alreadySent !== []) {
            return $alreadySent;
        }

        $teamInvites = $this->pendingTeamInvites($providerSubscriptionId);
        if ($teamInvites === []) {
            return [];
        }

        $subscriptionWithPlan = $this->subscriptionWithPlan($subscriptionId) ?: ($subscription ?: []);
        return (new OrganizationInviteService($this->pdo))->issueForOfficeSubscription($userId, $subscriptionWithPlan, $teamInvites);
    }

    private function pendingTeamInvites(string $providerSubscriptionId): array
    {
        $pending = $this->pendingEventForProviderSubscription($providerSubscriptionId);
        if (!$pending) {
            return [];
        }

        $payload = json_decode((string) ($pending['payload_json'] ?? ''), true);
        if (!is_array($payload) || !is_array($payload['team_invites'] ?? null)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $payload['team_invites'])));
    }

    private function teamInvitesAlreadySent(string $providerSubscriptionId): array
    {
        if (!database_table_exists($this->pdo, 'payment_events')) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT payload_json
             FROM payment_events
             WHERE provider = ?
               AND payload_json LIKE ?
             ORDER BY id DESC
             LIMIT 20"
        );
        $stmt->execute([$this->name(), '%' . $providerSubscriptionId . '%']);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $json) {
            $payload = json_decode((string) $json, true);
            $sent = is_array($payload) && is_array($payload['team_invites_sent'] ?? null)
                ? array_values(array_filter(array_map('strval', $payload['team_invites_sent'])))
                : [];
            if ($sent !== []) {
                return $sent;
            }
        }

        return [];
    }

    private function finalizePlanReplacement(int $userId, array $previousSubscription, array $newSubscription, string $newProviderSubscriptionId): string
    {
        $previousProviderSubscriptionId = trim((string) ($previousSubscription['provider_subscription_id'] ?? ''));
        $remoteCanceled = false;
        $remoteCancelError = '';

        if (
            (string) ($previousSubscription['provider'] ?? '') === $this->name()
            && $previousProviderSubscriptionId !== ''
            && $previousProviderSubscriptionId !== $newProviderSubscriptionId
        ) {
            try {
                $this->request('DELETE', '/subscriptions/' . rawurlencode($previousProviderSubscriptionId));
                $remoteCanceled = true;
            } catch (Throwable $exception) {
                $remoteCancelError = $exception->getMessage();
            }
        }

        $this->recordPaymentEvent(
            (int) ($previousSubscription['id'] ?? 0),
            $userId,
            $remoteCancelError === '' ? 'subscription.replaced' : 'subscription.replace_cancel_failed',
            0,
            $remoteCancelError === '' ? 'refunded' : 'failed',
            [
                'source' => 'billing.plan_replacement',
                'previous_subscription_id' => (int) ($previousSubscription['id'] ?? 0),
                'previous_plan_id' => (int) ($previousSubscription['plan_id'] ?? 0),
                'previous_plan_name' => (string) ($previousSubscription['plan_name'] ?? ''),
                'previous_provider_subscription_id' => $previousProviderSubscriptionId ?: null,
                'new_subscription_id' => (int) ($newSubscription['id'] ?? 0),
                'new_plan_id' => (int) ($newSubscription['plan_id'] ?? 0),
                'new_plan_name' => (string) ($newSubscription['plan_name'] ?? ''),
                'new_provider_subscription_id' => $newProviderSubscriptionId,
                'remote_canceled' => $remoteCanceled,
                'remote_cancel_error' => $remoteCancelError ?: null,
            ],
            $previousProviderSubscriptionId !== '' ? $previousProviderSubscriptionId : null
        );

        $this->notifyPlanReplaced($userId, $previousSubscription, $newSubscription, $remoteCancelError);
        return $remoteCancelError;
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
            substr($eventType, 0, 120),
            $amount,
            $status,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            date('Y-m-d H:i:s'),
        ]);
    }

    private function hasProcessedPaidEvent(string $providerEventId): bool
    {
        if ($providerEventId === '' || !database_table_exists($this->pdo, 'payment_events')) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM payment_events WHERE provider = ? AND provider_event_id = ? AND status = ?'
        );
        $stmt->execute([$this->name(), $providerEventId, 'paid']);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function notifyPlanPaid(int $userId, ?int $subscriptionId, int $amount): void
    {
        if ($userId <= 0 || !database_table_exists($this->pdo, 'notifications')) {
            return;
        }

        $subscription = $subscriptionId ? $this->subscriptionWithPlan($subscriptionId) : $this->subscriptions->currentForUser($userId);
        $planName = (string) ($subscription['plan_name'] ?? 'seu plano');
        $periodEnd = (string) ($subscription['current_period_end'] ?? '');
        $until = $periodEnd !== '' ? ' até ' . date('d/m/Y', strtotime($periodEnd)) : '';
        $amountText = $amount > 0 ? ' (' . $this->money($amount) . ')' : '';

        $message = 'Pagamento confirmado' . $amountText . ': o plano ' . $planName . ' está ativo' . $until . '.';
        if ($this->notifyRecentUnique($userId, $message)) {
            $user = $this->fetchUser($userId);
            if ($user) {
                $this->billingEmails->sendPlanPaid($user, $subscription ?: [], $amount);
            }
        }
    }

    private function notifyPlanCanceled(int $userId, string $planName): void
    {
        if ($userId <= 0 || !database_table_exists($this->pdo, 'notifications')) {
            return;
        }

        $label = trim($planName) !== '' ? ' ' . trim($planName) : '';
        $user = $this->fetchUser($userId);
        $message = (string) ($user['tipo'] ?? '') === 'advogado'
            ? 'Plano' . $label . ' cancelado. Sua conta esta sem plano profissional ativo.'
            : 'Plano' . $label . ' cancelado. Sua conta voltou para o modo gratuito.';
        if ($this->notifyRecentUnique($userId, $message)) {
            if ($user) {
                $this->billingEmails->sendPlanCanceled($user, ['plan_name' => $planName]);
            }
        }
    }

    private function notifyPlanReplaced(int $userId, array $previousSubscription, array $newSubscription, string $remoteCancelError = ''): void
    {
        if ($userId <= 0 || !database_table_exists($this->pdo, 'notifications')) {
            return;
        }

        $previousPlan = trim((string) ($previousSubscription['plan_name'] ?? 'plano anterior'));
        $newPlan = trim((string) ($newSubscription['plan_name'] ?? 'novo plano'));
        $message = 'Seu plano ' . $previousPlan . ' foi substituído pelo plano ' . $newPlan . '.';
        if ($remoteCancelError !== '') {
            $message .= ' A troca está ativa, mas não foi possível confirmar o cancelamento remoto da assinatura anterior automaticamente.';
        }

        if ($this->notifyRecentUnique($userId, $message)) {
            $user = $this->fetchUser($userId);
            if ($user && $remoteCancelError === '') {
                $this->billingEmails->sendPlanChanged($user, $previousSubscription, $newSubscription);
            }
        }
    }

    private function notifyRecentUnique(int $userId, string $message): bool
    {
        $cutoff = date('Y-m-d H:i:s', time() - 600);
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM notifications
             WHERE user_id = ?
               AND mensagem = ?
               AND created_at >= ?'
        );
        $stmt->execute([$userId, $message, $cutoff]);
        if ((int) $stmt->fetchColumn() > 0) {
            return false;
        }

        $this->notifications->notify($userId, $message);
        return true;
    }

    private function subscriptionWithPlan(int $subscriptionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, p.name AS plan_name, p.slug AS plan_slug
             FROM subscriptions s
             LEFT JOIN plans p ON p.id = s.plan_id
             WHERE s.id = ?
             LIMIT 1'
        );
        $stmt->execute([$subscriptionId]);
        $subscription = $stmt->fetch();

        return $subscription ?: null;
    }

    private function money(int $cents): string
    {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }

    private function paymentStatusFromEvent(string $event): string
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

    private function paymentStatusFromAsaasStatus(string $status): string
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

    private function subscriptionStatusFromEvent(string $event): ?string
    {
        return match ($event) {
            'SUBSCRIPTION_DELETED', 'SUBSCRIPTION_INACTIVATED' => 'canceled',
            'SUBSCRIPTION_SPLIT_DIVERGENCE_BLOCK' => 'past_due',
            default => null,
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
        foreach (['invoiceUrl', 'paymentLink', 'url'] as $key) {
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
            throw new RuntimeException('Token do webhook Asaas inválido.', 401);
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
