<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/OrganizationInviteService.php';
require_once dirname(__DIR__) . '/services/SubscriptionService.php';
require_once dirname(__DIR__) . '/services/payments/PaymentProviderFactory.php';

class BillingController extends BaseController
{
    private AuditService $audit;
    private SubscriptionService $subscriptions;
    private PaymentProviderInterface $payments;

    public function __construct()
    {
        parent::__construct();
        $this->audit = new AuditService($this->pdo);
        $this->subscriptions = new SubscriptionService($this->pdo);
        $this->payments = PaymentProviderFactory::make($this->pdo, $this->subscriptions);
    }

    public function createPixCheckout(): void
    {
        $this->startSession();
        if (empty($_SESSION['logado'])) {
            $this->response->json(['success' => false, 'error' => 'Faca login para finalizar seu pagamento.'], 401);
            return;
        }

        if (!$this->canCurrentUserSubscribe()) {
            $this->response->json(['success' => false, 'error' => $this->billingAccessMessage()], 403);
            return;
        }

        $checkoutSession = is_array($_SESSION['billing_checkout'] ?? null) ? $_SESSION['billing_checkout'] : [];
        $planId = (int) ($checkoutSession['plan_id'] ?? 0);
        $cycle = (string) ($checkoutSession['billing_cycle'] ?? 'monthly');
        if (!in_array($cycle, ['monthly', 'yearly'], true)) {
            $cycle = 'monthly';
        }

        if (!$this->planExists($planId)) {
            $this->response->json(['success' => false, 'error' => 'Plano invalido.'], 422);
            return;
        }

        $provider = PaymentProviderFactory::makeNamed($this->pdo, $this->subscriptions, 'pix');

        try {
            $plan = $this->fetchPlan($planId);
            if (!$plan) {
                $this->response->json(['success' => false, 'error' => 'Plano invalido.'], 422);
                return;
            }

            $paymentData = [
                'method' => 'pix',
                'remote_ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                'team_invites' => (new OrganizationInviteService($this->pdo))->validateOfficeInviteRequest(
                    $plan,
                    $this->request->post('team_invites', [])
                ),
            ];
            $checkout = $provider->createCheckout((int) $_SESSION['id'], $planId, $cycle, $paymentData);
        } catch (Throwable $exception) {
            $this->response->json(['success' => false, 'error' => $exception->getMessage()], 422);
            return;
        }

        if (!$checkout->ok) {
            $this->response->json(['success' => false, 'error' => $checkout->errorMessage ?: 'Nao foi possivel gerar o PIX.'], 422);
            return;
        }

        $_SESSION['billing_checkout'] = [
            'reference' => (string) ($checkoutSession['reference'] ?? bin2hex(random_bytes(12))),
            'provider' => $provider->name(),
            'plan_id' => $planId,
            'billing_cycle' => $cycle,
            'redirect_url' => $checkout->redirectUrl,
            'metadata' => $checkout->metadata,
            'created_at' => time(),
            'status' => 'created',
            'payment_method' => 'pix',
            'team_invites' => (array) ($paymentData['team_invites'] ?? []),
        ];

        $this->audit->log('billing.pix_checkout_create', 'subscription', 0, [
            'plan_id' => $planId,
            'billing_cycle' => $cycle,
            'provider' => $provider->name(),
            'metadata' => $checkout->metadata,
        ]);

        $pixQrCode = is_array($checkout->metadata['pix_qr_code'] ?? null) ? $checkout->metadata['pix_qr_code'] : [];
        $amountCents = (int) ($checkout->metadata['amount_cents'] ?? 0);

        $this->response->json([
            'success' => true,
            'provider' => $provider->name(),
            'amount' => round($amountCents / 100, 2),
            'amountCents' => $amountCents,
            'pixCode' => (string) ($pixQrCode['payload'] ?? ''),
            'qrCode' => (string) ($pixQrCode['data_uri'] ?? ''),
            'expiresAt' => (string) ($checkout->metadata['pix_expires_at'] ?? ''),
            'providerCheckoutId' => (string) ($checkout->metadata['provider_subscription_id'] ?? ''),
        ]);
    }

    public function subscribe(): void
    {
        $this->startSession();
        if (empty($_SESSION['logado'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faca login para escolher um plano.')));
        }

        if (!$this->canCurrentUserSubscribe()) {
            $this->response->redirect($this->dashboardUrlFor((string) ($_SESSION['tipo'] ?? '')) . '?erro=' . urlencode($this->billingAccessMessage()));
        }

        $planId = (int) $this->request->post('plan_id', 0);
        $cycle = (string) $this->request->post('billing_cycle', 'monthly');
        $userId = (int) $_SESSION['id'];
        if (!in_array($cycle, ['monthly', 'yearly'], true)) {
            $cycle = 'monthly';
        }

        if (!$this->planExists($planId)) {
            $this->response->redirect(app_url('/frontend/subir-plano.php?erro=' . urlencode('Plano inválido.')));
        }

        $_SESSION['billing_checkout'] = [
            'reference' => bin2hex(random_bytes(12)),
            'provider' => $this->payments->name(),
            'plan_id' => $planId,
            'billing_cycle' => $cycle,
            'redirect_url' => '',
            'metadata' => [],
            'created_at' => time(),
            'status' => 'draft',
        ];

        $this->audit->log('billing.checkout_prepare', 'subscription', 0, [
            'plan_id' => $planId,
            'billing_cycle' => $cycle,
            'provider' => $this->payments->name(),
        ]);

        $this->response->redirect(app_url('/frontend/pagamento-plano.php'));
    }

    public function checkout(): void
    {
        $this->startSession();
        if (empty($_SESSION['logado'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faca login para finalizar seu pagamento.')));
        }

        if (!$this->canCurrentUserSubscribe()) {
            $this->response->redirect($this->dashboardUrlFor((string) ($_SESSION['tipo'] ?? '')) . '?erro=' . urlencode($this->billingAccessMessage()));
        }

        $userId = (int) $_SESSION['id'];
        $checkoutSession = is_array($_SESSION['billing_checkout'] ?? null) ? $_SESSION['billing_checkout'] : [];
        $metadata = is_array($checkoutSession['metadata'] ?? null) ? $checkoutSession['metadata'] : [];
        if (($checkoutSession['provider'] ?? '') === $this->payments->name() && !empty($metadata['provider_subscription_id'])) {
            $this->response->redirect(app_url('/frontend/pagamento-plano.php'));
        }

        $planId = (int) ($checkoutSession['plan_id'] ?? 0);
        $cycle = (string) ($checkoutSession['billing_cycle'] ?? 'monthly');
        if (!in_array($cycle, ['monthly', 'yearly'], true)) {
            $cycle = 'monthly';
        }

        if (!$this->planExists($planId)) {
            $this->response->redirect(app_url('/frontend/subir-plano.php?erro=' . urlencode('Plano inválido.')));
        }

        try {
            $plan = $this->fetchPlan($planId);
            if (!$plan) {
                $this->response->redirect(app_url('/frontend/subir-plano.php?erro=' . urlencode('Plano inválido.')));
            }

            $paymentData = $this->checkoutPaymentData();
            $paymentData['team_invites'] = (new OrganizationInviteService($this->pdo))->validateOfficeInviteRequest(
                $plan,
                $this->request->post('team_invites', [])
            );
            $checkout = $this->payments->createCheckout($userId, $planId, $cycle, $paymentData);
        } catch (Throwable $exception) {
            $this->response->redirect(app_url('/frontend/pagamento-plano.php?erro=' . urlencode($exception->getMessage())));
        }

        if (!$checkout->ok) {
            $this->response->redirect(app_url('/frontend/pagamento-plano.php?erro=' . urlencode($checkout->errorMessage ?: 'Plano inválido.')));
        }

        $this->audit->log('billing.checkout_create', 'subscription', (int) ($checkout->subscriptionId ?? 0), [
            'plan_id' => $planId,
            'billing_cycle' => $cycle,
            'provider' => $this->payments->name(),
            'metadata' => $checkout->metadata,
        ]);

        if (
            $this->payments->name() === 'asaas'
            && $checkout->subscriptionId !== null
            && (string) ($checkout->metadata['payment_status'] ?? '') === 'paid'
        ) {
            $subscription = $this->subscriptions->currentForUser($userId);
            $_SESSION['payment_confirmed'] = [
                'confirmed_at' => time(),
                'plan_id' => (int) ($subscription['plan_id'] ?? $planId),
                'plan_name' => (string) ($subscription['plan_name'] ?? 'Plano ativo'),
                'billing_cycle' => (string) ($subscription['billing_cycle'] ?? $cycle),
                'amount_cents' => (int) ($checkout->metadata['amount_cents'] ?? 0),
                'subscription_id' => (int) $checkout->subscriptionId,
                'provider' => $this->payments->name(),
                'provider_subscription_id' => (string) ($checkout->metadata['provider_subscription_id'] ?? ''),
                'provider_payment_id' => (string) ($checkout->metadata['provider_payment_id'] ?? ''),
                'previous_subscription_id' => (int) ($checkout->metadata['previous_subscription_id'] ?? 0),
                'previous_plan_id' => (int) ($checkout->metadata['previous_plan_id'] ?? 0),
                'previous_plan_name' => (string) ($checkout->metadata['previous_plan_name'] ?? ''),
                'previous_remote_cancel_error' => (string) ($checkout->metadata['previous_remote_cancel_error'] ?? ''),
                'team_invites_sent' => (array) ($checkout->metadata['team_invites_sent'] ?? []),
            ];
            unset($_SESSION['billing_checkout']);
            $this->response->redirect(app_url('/frontend/pagamento-confirmado.php'));
        }

        if ($this->payments->name() === 'asaas') {
            $_SESSION['billing_checkout'] = [
                'reference' => (string) ($checkoutSession['reference'] ?? bin2hex(random_bytes(12))),
                'provider' => $this->payments->name(),
                'plan_id' => $planId,
                'billing_cycle' => in_array($cycle, ['monthly', 'yearly'], true) ? $cycle : 'monthly',
                'redirect_url' => $checkout->redirectUrl,
                'metadata' => $checkout->metadata,
                'created_at' => time(),
                'status' => 'created',
                'payment_method' => (string) ($paymentData['method'] ?? 'pix'),
                'team_invites' => (array) ($paymentData['team_invites'] ?? []),
            ];

            $this->response->redirect(app_url('/frontend/pagamento-plano.php'));
        }

        $this->response->redirect($checkout->redirectUrl);
    }

    public function cancelCheckout(): void
    {
        $this->startSession();
        if (empty($_SESSION['logado'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faca login para cancelar seu pagamento.')));
        }

        if (!$this->canCurrentUserSubscribe()) {
            $this->response->redirect($this->dashboardUrlFor((string) ($_SESSION['tipo'] ?? '')) . '?erro=' . urlencode($this->billingAccessMessage()));
        }

        $checkout = is_array($_SESSION['billing_checkout'] ?? null) ? $_SESSION['billing_checkout'] : [];
        $metadata = is_array($checkout['metadata'] ?? null) ? $checkout['metadata'] : [];
        $providerSubscriptionId = trim((string) ($metadata['provider_subscription_id'] ?? ''));
        $provider = $this->providerForCheckout($checkout);

        try {
            $result = $provider->cancelCheckout((int) $_SESSION['id'], $providerSubscriptionId);
            $this->audit->log('billing.checkout_cancel', 'subscription', (int) ($result['subscription_id'] ?? 0), $result);
            unset($_SESSION['billing_checkout']);

            $message = !empty($result['remote_canceled'])
                ? 'Pagamento cancelado e cobrança removida no Asaas.'
                : 'Pagamento cancelado.';
            $this->response->redirect(app_url('/frontend/subir-plano.php?sucesso=' . urlencode($message)));
        } catch (Throwable $exception) {
            $this->response->redirect(app_url('/frontend/pagamento-plano.php?erro=' . urlencode($exception->getMessage())));
        }
    }

    public function webhook(): void
    {
        $rawPayload = (string) (file_get_contents('php://input') ?: '');

        try {
            $result = $this->payments->handleWebhook($rawPayload, $this->headers());
            $this->audit->log('billing.webhook_processed', 'subscription', (int) ($result['subscription_id'] ?? 0), $result);
            $this->response->json(['ok' => true, 'result' => $result]);
        } catch (Throwable $exception) {
            $status = (int) $exception->getCode();
            if ($status < 400 || $status > 599) {
                $status = 400;
            }

            $this->response->json(['ok' => false, 'error' => $exception->getMessage()], $status);
        }
    }

    public function webhookStatus(): void
    {
        $this->response->json([
            'ok' => true,
            'provider' => $this->payments->name(),
            'endpoint' => 'billing.webhook',
            'accepts' => 'POST',
        ]);
    }

    public function cancel(): void
    {
        $this->startSession();
        if (empty($_SESSION['logado'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faca login para gerenciar seu plano.')));
        }

        if (!$this->canCurrentUserSubscribe()) {
            $this->response->redirect($this->dashboardUrlFor((string) ($_SESSION['tipo'] ?? '')) . '?erro=' . urlencode($this->billingAccessMessage()));
        }

        $userId = (int) $_SESSION['id'];
        $currentSubscription = $this->subscriptions->currentForUser($userId);
        $planName = trim((string) ($currentSubscription['plan_name'] ?? ''));
        $provider = $this->providerForSubscription($currentSubscription);

        try {
            $result = $provider->cancelSubscription($userId);
            $this->audit->log('billing.subscription_cancel', 'subscription', (int) ($result['subscription_id'] ?? 0), $result);
        } catch (Throwable $exception) {
            $this->response->redirect(app_url('/frontend/perfil.php?tab=faturamento&erro=' . urlencode($exception->getMessage())));
        }

        $message = !empty($result['already_free'])
            ? 'Seu plano gratuito ja esta ativo.'
            : ($planName !== '' ? 'Plano ' . $planName . ' cancelado' : 'Plano cancelado');
        $this->response->redirect(app_url('/frontend/perfil.php?tab=faturamento&sucesso=' . urlencode($message)));
    }

    public function sync(): void
    {
        $this->startSession();
        if (empty($_SESSION['logado'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faca login para verificar seu pagamento.')));
        }

        if (!$this->canCurrentUserSubscribe()) {
            $this->response->redirect($this->dashboardUrlFor((string) ($_SESSION['tipo'] ?? '')) . '?erro=' . urlencode($this->billingAccessMessage()));
        }

        $checkout = is_array($_SESSION['billing_checkout'] ?? null) ? $_SESSION['billing_checkout'] : [];
        $metadata = is_array($checkout['metadata'] ?? null) ? $checkout['metadata'] : [];
        $providerSubscriptionId = trim((string) ($metadata['provider_subscription_id'] ?? ''));
        $providerPaymentId = trim((string) ($metadata['provider_payment_id'] ?? ''));
        if ($providerSubscriptionId === '') {
            $this->response->redirect(app_url('/frontend/subir-plano.php?erro=' . urlencode('Nenhuma cobrança pendente foi encontrada.')));
        }

        $userId = (int) $_SESSION['id'];
        $provider = $this->providerForCheckout($checkout);

        try {
            if ($providerPaymentId !== '' && method_exists($provider, 'confirmSandboxPayment')) {
                try {
                    $sandboxResult = $provider->confirmSandboxPayment($providerPaymentId);
                    if (!empty($sandboxResult['sandbox_confirmed'])) {
                        $this->audit->log('billing.sandbox_payment_confirm', 'subscription', 0, $sandboxResult);
                    }
                } catch (Throwable $sandboxException) {
                    $this->audit->log('billing.sandbox_payment_confirm_failed', 'subscription', 0, [
                        'provider' => $provider->name(),
                        'provider_payment_id' => $providerPaymentId,
                        'error' => $sandboxException->getMessage(),
                    ]);
                }
            }

            $result = $provider->syncCheckoutPayment($userId, $providerSubscriptionId);
            $this->audit->log('billing.checkout_sync', 'subscription', (int) ($result['subscription_id'] ?? 0), $result);

            if (($result['status'] ?? '') === 'paid') {
                $subscription = $this->subscriptions->currentForUser($userId);
                $_SESSION['payment_confirmed'] = [
                    'confirmed_at' => time(),
                    'plan_id' => (int) ($subscription['plan_id'] ?? $checkout['plan_id'] ?? 0),
                    'plan_name' => (string) ($subscription['plan_name'] ?? 'Plano ativo'),
                    'billing_cycle' => (string) ($subscription['billing_cycle'] ?? $checkout['billing_cycle'] ?? 'monthly'),
                    'amount_cents' => (int) ($metadata['amount_cents'] ?? 0),
                    'subscription_id' => (int) ($result['subscription_id'] ?? $subscription['id'] ?? 0),
                    'provider' => (string) ($result['provider'] ?? $provider->name()),
                    'provider_subscription_id' => $providerSubscriptionId,
                    'provider_payment_id' => (string) ($result['provider_payment_id'] ?? ''),
                    'previous_subscription_id' => (int) ($result['previous_subscription_id'] ?? 0),
                    'previous_plan_id' => (int) ($result['previous_plan_id'] ?? 0),
                    'previous_plan_name' => (string) ($result['previous_plan_name'] ?? ''),
                    'previous_remote_cancel_error' => (string) ($result['previous_remote_cancel_error'] ?? ''),
                    'team_invites_sent' => (array) ($result['team_invites_sent'] ?? []),
                ];
                unset($_SESSION['billing_checkout']);
                $this->response->redirect(app_url('/frontend/pagamento-confirmado.php'));
            }

            $this->response->redirect(app_url('/frontend/pagamento-plano.php?erro=' . urlencode('Pagamento ainda não confirmado no Asaas. Se você ainda não efetuou o pagamento, clique em Pagar com segurança para concluir.')));
        } catch (Throwable $exception) {
            $this->response->redirect(app_url('/frontend/pagamento-plano.php?erro=' . urlencode($exception->getMessage())));
        }
    }

    private function providerForCheckout(array $checkout): PaymentProviderInterface
    {
        $providerName = strtolower(trim((string) ($checkout['provider'] ?? '')));
        if ($providerName === '') {
            return $this->payments;
        }

        return PaymentProviderFactory::makeNamed($this->pdo, $this->subscriptions, $providerName);
    }

    private function providerForSubscription(?array $subscription): PaymentProviderInterface
    {
        $providerName = strtolower(trim((string) ($subscription['provider'] ?? '')));
        if ($providerName === '') {
            return $this->payments;
        }

        return PaymentProviderFactory::makeNamed($this->pdo, $this->subscriptions, $providerName);
    }

    private function headers(): array
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $value) {
                $headers[strtolower((string) $key)] = (string) $value;
            }
            return $headers;
        }

        foreach ($_SERVER as $key => $value) {
            if (!str_starts_with((string) $key, 'HTTP_')) {
                continue;
            }

            $name = strtolower(str_replace('_', '-', substr((string) $key, 5)));
            $headers[$name] = (string) $value;
        }

        return $headers;
    }

    private function dashboardUrlFor(string $type): string
    {
        return match ($type) {
            'advogado' => app_url('/frontend/dashboard-advogado.php'),
            'admin' => app_url('/frontend/admin/dashboard-admin.php'),
            default => app_url('/frontend/dashboard-cliente.php'),
        };
    }

    private function planExists(int $planId): bool
    {
        if ($planId <= 0) {
            return false;
        }

        return $this->subscriptions->planAvailableForUser((int) ($_SESSION['id'] ?? 0), $planId);
    }

    private function fetchPlan(int $planId): ?array
    {
        if ($planId <= 0 || !database_table_exists($this->pdo, 'plans')) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM plans WHERE id = ? AND active = 1 LIMIT 1');
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();

        return $plan ?: null;
    }

    private function canCurrentUserSubscribe(): bool
    {
        return $this->subscriptions->userCanSubscribe((int) ($_SESSION['id'] ?? 0));
    }

    private function billingAccessMessage(): string
    {
        return ($_SESSION['tipo'] ?? '') === 'advogado'
            ? 'Planos profissionais exigem OAB validada e conta ativa.'
            : 'Planos estao disponiveis para clientes e advogados verificados.';
    }

    private function checkoutPaymentData(): array
    {
        $method = strtolower(trim((string) $this->request->post('payment_method', 'pix')));
        if (!in_array($method, ['pix', 'credit_card'], true)) {
            $method = 'pix';
        }

        $data = [
            'method' => $method,
            'remote_ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
        ];

        if ($method !== 'credit_card') {
            return $data;
        }

        $data['card'] = [
            'holder_name' => trim((string) $this->request->post('card_holder_name', '')),
            'number' => preg_replace('/\D+/', '', (string) $this->request->post('card_number', '')) ?: '',
            'expiry_month' => preg_replace('/\D+/', '', (string) $this->request->post('card_expiry_month', '')) ?: '',
            'expiry_year' => preg_replace('/\D+/', '', (string) $this->request->post('card_expiry_year', '')) ?: '',
            'ccv' => preg_replace('/\D+/', '', (string) $this->request->post('card_ccv', '')) ?: '',
        ];
        $data['holder'] = [
            'name' => trim((string) $this->request->post('holder_name', '')),
            'email' => trim((string) $this->request->post('holder_email', '')),
            'cpf_cnpj' => preg_replace('/\D+/', '', (string) $this->request->post('holder_cpf_cnpj', '')) ?: '',
            'postal_code' => preg_replace('/\D+/', '', (string) $this->request->post('holder_postal_code', '')) ?: '',
            'address_number' => trim((string) $this->request->post('holder_address_number', '')),
            'address_complement' => trim((string) $this->request->post('holder_address_complement', '')),
            'phone' => preg_replace('/\D+/', '', (string) $this->request->post('holder_phone', '')) ?: '',
        ];

        if (!$this->isValidCpfCnpj($data['holder']['cpf_cnpj'])) {
            throw new InvalidArgumentException('Informe um CPF ou CNPJ real para o titular do cartão.');
        }

        if (!$this->isValidCep($data['holder']['postal_code'])) {
            throw new InvalidArgumentException('Informe um CEP real e existente para o titular do cartão.');
        }

        if (!filter_var($data['holder']['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Informe um e-mail válido para o titular do cartão.');
        }

        if (strlen($data['holder']['phone']) < 10 || strlen($data['holder']['phone']) > 11) {
            throw new InvalidArgumentException('Informe um telefone real com DDD para o titular do cartão.');
        }

        return $data;
    }

    private function isValidCpfCnpj(string $document): bool
    {
        $digits = preg_replace('/\D+/', '', $document) ?: '';

        return strlen($digits) === 11
            ? $this->isValidCpf($digits)
            : (strlen($digits) === 14 && $this->isValidCnpj($digits));
    }

    private function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($position = 9; $position < 11; $position++) {
            $sum = 0;
            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $cpf[$index] * (($position + 1) - $index);
            }

            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$position] !== $digit) {
                return false;
            }
        }

        return true;
    }

    private function isValidCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $weights = [
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];

        for ($digitIndex = 12; $digitIndex < 14; $digitIndex++) {
            $sum = 0;
            foreach ($weights[$digitIndex - 12] as $index => $weight) {
                $sum += (int) $cnpj[$index] * $weight;
            }

            $rest = $sum % 11;
            $digit = $rest < 2 ? 0 : 11 - $rest;
            if ((int) $cnpj[$digitIndex] !== $digit) {
                return false;
            }
        }

        return true;
    }

    private function isValidCep(string $cep): bool
    {
        $digits = preg_replace('/\D+/', '', $cep) ?: '';
        if (strlen($digits) !== 8 || preg_match('/^(\d)\1{7}$/', $digits)) {
            return false;
        }

        if (function_exists('curl_init')) {
            $curl = curl_init('https://viacep.com.br/ws/' . $digits . '/json/');
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $response = curl_exec($curl);
            $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($response === false || $httpCode < 200 || $httpCode >= 300) {
                return false;
            }

            $data = json_decode((string) $response, true);
            return is_array($data) && empty($data['erro']);
        }

        $response = @file_get_contents('https://viacep.com.br/ws/' . $digits . '/json/');
        if ($response === false) {
            return false;
        }

        $data = json_decode((string) $response, true);
        return is_array($data) && empty($data['erro']);
    }
}
