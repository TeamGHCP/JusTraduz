<?php

namespace App\Services\Payments {
    use App\Services\BillingEmailService;
    use App\Services\NotificationService;
    use App\Services\OrganizationInviteService;
    use App\Services\SubscriptionService;
    use DateTimeImmutable;
    use InvalidArgumentException;
    use PDO;
    use RuntimeException;

    class PixPaymentProvider implements PaymentProviderInterface
    {
        private PDO $pdo;
        private BillingEmailService $billingEmails;
        private NotificationService $notifications;
        private SubscriptionService $subscriptions;
        private PixPayloadService $payloads;
        private PixQrCodeService $qrCodes;

        public function __construct(PDO $pdo, SubscriptionService $subscriptions)
        {
            $this->pdo = $pdo;
            $this->billingEmails = new BillingEmailService();
            $this->notifications = new NotificationService($pdo);
            $this->subscriptions = $subscriptions;
            $this->payloads = new PixPayloadService();
            $this->qrCodes = new PixQrCodeService();
        }

        public function name(): string
        {
            return 'pix';
        }

        public function createCheckout(int $userId, int $planId, string $billingCycle, array $paymentData = []): PaymentCheckoutResult
        {
            if (!$this->subscriptions->userCanSubscribe($userId)) {
                return PaymentCheckoutResult::error('Usuario nao pode assinar planos.');
            }

            if (!$this->subscriptions->planAvailableForUser($userId, $planId)) {
                return PaymentCheckoutResult::error('Plano invalido.');
            }

            $plan = $this->fetchPlan($planId);
            if (!$plan) {
                return PaymentCheckoutResult::error('Plano invalido.');
            }

            if (!in_array($billingCycle, ['monthly', 'yearly'], true)) {
                $billingCycle = 'monthly';
            }

            $amount = $billingCycle === 'yearly'
                ? (int) ($plan['yearly_price_cents'] ?? 0)
                : (int) ($plan['monthly_price_cents'] ?? 0);

            if ($amount <= 0) {
                return PaymentCheckoutResult::error('Nao foi possivel calcular o valor do plano.');
            }

            $providerCheckoutId = 'pix_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
            $pixPayload = $this->payloads->build(
                $amount / 100,
                $this->env('PIX_CHAVE', ''),
                $this->env('PIX_NOME', ''),
                $this->env('PIX_CIDADE', '')
            );
            $qrCode = $this->qrCodes->render($pixPayload);
            $teamInvites = is_array($paymentData['team_invites'] ?? null) ? $paymentData['team_invites'] : [];
            $expiresAt = (new DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s');

            $payload = [
                'plan_id' => $planId,
                'billing_cycle' => $billingCycle,
                'amount_cents' => $amount,
                'payment_method' => 'pix',
                'provider_subscription_id' => $providerCheckoutId,
                'pix_qr_code' => $qrCode,
                'pix_expires_at' => $expiresAt,
                'team_invites' => $teamInvites,
            ];

            $this->recordPaymentEvent(null, $userId, 'subscription.created', $amount, 'pending', $payload, $providerCheckoutId);

            return PaymentCheckoutResult::success(app_url('/frontend/pagamento-plano.php'), null, [
                'provider_subscription_id' => $providerCheckoutId,
                'provider_payment_id' => $providerCheckoutId,
                'amount_cents' => $amount,
                'checkout_url' => app_url('/frontend/pagamento-plano.php'),
                'payment_method' => 'pix',
                'payment_status' => 'pending',
                'billing_type' => 'PIX',
                'due_date' => date('Y-m-d'),
                'pix_qr_code' => $qrCode,
                'pix_expires_at' => $expiresAt,
                'team_invites' => $teamInvites,
                'team_invites_sent' => [],
            ]);
        }

        public function syncCheckoutPayment(int $userId, string $providerSubscriptionId): array
        {
            $providerSubscriptionId = trim($providerSubscriptionId);
            if ($providerSubscriptionId === '') {
                throw new InvalidArgumentException('Checkout PIX nao informado.');
            }

            $active = $this->findLocalSubscription($providerSubscriptionId);
            if ($active && (int) ($active['user_id'] ?? 0) === $userId && (string) ($active['status'] ?? '') === 'active') {
                return [
                    'ok' => true,
                    'provider' => $this->name(),
                    'status' => 'paid',
                    'subscription_id' => (int) $active['id'],
                    'provider_subscription_id' => $providerSubscriptionId,
                    'provider_payment_id' => $providerSubscriptionId,
                    'team_invites_sent' => [],
                ];
            }

            $pending = $this->pendingEventForProviderSubscription($providerSubscriptionId);
            if (!$pending || (int) ($pending['user_id'] ?? 0) !== $userId) {
                throw new RuntimeException('Cobranca PIX nao encontrada para este usuario.');
            }

            $subscription = $this->activateSubscriptionFromPendingEvent($providerSubscriptionId, $pending);
            if (!$subscription) {
                throw new RuntimeException('Nao foi possivel confirmar o pagamento PIX.');
            }

            $payload = json_decode((string) ($pending['payload_json'] ?? ''), true);
            $amount = (int) ($pending['amount_cents'] ?? ($payload['amount_cents'] ?? 0));
            $teamInvites = is_array($payload['team_invites'] ?? null) ? $payload['team_invites'] : [];
            $teamInvitesSent = (new OrganizationInviteService($this->pdo))->issueForOfficeSubscription($userId, $subscription, $teamInvites);

            $this->recordPaymentEvent((int) $subscription['id'], $userId, 'payment.manual_confirmed', $amount, 'paid', [
                'source' => 'billing.sync',
                'provider_subscription_id' => $providerSubscriptionId,
                'team_invites_sent' => $teamInvitesSent,
            ], $providerSubscriptionId);

            $user = $this->fetchUser($userId);
            $message = 'Pagamento confirmado (' . $this->money($amount) . '): o plano ' . (string) ($subscription['plan_name'] ?? 'contratado') . ' esta ativo.';
            if ($this->notifyRecentUnique($userId, $message) && $user) {
                $this->billingEmails->sendPlanPaid($user, $subscription, $amount);
            }

            return [
                'ok' => true,
                'provider' => $this->name(),
                'status' => 'paid',
                'subscription_id' => (int) $subscription['id'],
                'provider_subscription_id' => $providerSubscriptionId,
                'provider_payment_id' => $providerSubscriptionId,
                'team_invites_sent' => $teamInvitesSent,
            ];
        }

        public function cancelCheckout(int $userId, string $providerSubscriptionId): array
        {
            $providerSubscriptionId = trim($providerSubscriptionId);
            $this->recordPaymentEvent(null, $userId, 'checkout.canceled', 0, 'refunded', [
                'provider_subscription_id' => $providerSubscriptionId !== '' ? $providerSubscriptionId : null,
                'source' => 'frontend/pagamento-plano.php',
            ], $providerSubscriptionId !== '' ? $providerSubscriptionId : null);

            return [
                'ok' => true,
                'provider' => $this->name(),
                'provider_subscription_id' => $providerSubscriptionId !== '' ? $providerSubscriptionId : null,
                'remote_canceled' => false,
            ];
        }

        public function cancelSubscription(int $userId): array
        {
            $subscription = $this->subscriptions->currentForUser($userId);
            if (!$subscription) {
                return ['ok' => true, 'provider' => $this->name(), 'already_free' => true];
            }

            if (!$this->subscriptions->cancelCurrentForUser($userId)) {
                throw new RuntimeException('Nao foi possivel cancelar a assinatura.');
            }

            $freeSubscription = $this->subscriptions->ensureDefaultForUser($userId);
            $this->recordPaymentEvent((int) ($subscription['id'] ?? 0), $userId, 'subscription.canceled', 0, 'refunded', [
                'source' => 'frontend/perfil.php',
                'previous_plan_id' => (int) ($subscription['plan_id'] ?? 0),
            ]);

            return [
                'ok' => true,
                'provider' => $this->name(),
                'subscription_id' => (int) ($subscription['id'] ?? 0),
                'free_subscription_id' => $freeSubscription ? (int) ($freeSubscription['id'] ?? 0) : null,
            ];
        }

        public function handleWebhook(string $rawPayload, array $headers): array
        {
            throw new RuntimeException('PIX manual nao utiliza webhook.', 405);
        }

        private function activateSubscriptionFromPendingEvent(string $providerSubscriptionId, array $pending): ?array
        {
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

        private function findLocalSubscription(string $providerSubscriptionId): ?array
        {
            if ($providerSubscriptionId === '' || !database_table_exists($this->pdo, 'subscriptions')) {
                return null;
            }

            $stmt = $this->pdo->prepare(
                'SELECT s.*, p.name AS plan_name, p.slug AS plan_slug
                 FROM subscriptions s
                 LEFT JOIN plans p ON p.id = s.plan_id
                 WHERE s.provider = ? AND s.provider_subscription_id = ?
                 ORDER BY s.id DESC
                 LIMIT 1'
            );
            $stmt->execute([$this->name(), $providerSubscriptionId]);
            $subscription = $stmt->fetch();

            return $subscription ?: null;
        }

        private function pendingEventForProviderSubscription(string $providerSubscriptionId): ?array
        {
            if (!database_table_exists($this->pdo, 'payment_events')) {
                return null;
            }

            $stmt = $this->pdo->prepare(
                'SELECT * FROM payment_events WHERE provider = ? AND provider_event_id = ? AND event_type = ? ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([$this->name(), $providerSubscriptionId, 'subscription.created']);
            $event = $stmt->fetch();

            return $event ?: null;
        }

        private function fetchPlan(int $planId): ?array
        {
            $stmt = $this->pdo->prepare('SELECT * FROM plans WHERE id = ? AND active = 1 LIMIT 1');
            $stmt->execute([$planId]);
            $plan = $stmt->fetch();

            return $plan ?: null;
        }

        private function fetchUser(int $userId): ?array
        {
            $stmt = $this->pdo->prepare('SELECT id, nome, email, tipo FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            return $user ?: null;
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

        private function notifyRecentUnique(int $userId, string $message): bool
        {
            if ($userId <= 0 || !database_table_exists($this->pdo, 'notifications')) {
                return false;
            }

            $cutoff = date('Y-m-d H:i:s', time() - 600);
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND mensagem = ? AND created_at >= ?'
            );
            $stmt->execute([$userId, $message, $cutoff]);
            if ((int) $stmt->fetchColumn() > 0) {
                return false;
            }

            $this->notifications->notify($userId, $message);
            return true;
        }

        private function money(int $cents): string
        {
            return 'R$ ' . number_format($cents / 100, 2, ',', '.');
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
    }
}

namespace {
    if (!class_exists('PixPaymentProvider')) {
        class_alias('App\Services\Payments\PixPaymentProvider', 'PixPaymentProvider');
    }
}
