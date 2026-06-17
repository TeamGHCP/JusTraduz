<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
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

    public function subscribe(): void
    {
        $this->startSession();
        if (empty($_SESSION['logado'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faca login para escolher um plano.')));
        }

        if (($_SESSION['tipo'] ?? '') !== 'cliente') {
            $this->response->redirect($this->dashboardUrlFor((string) ($_SESSION['tipo'] ?? '')) . '?erro=' . urlencode('Planos sao exclusivos para clientes.'));
        }

        $planId = (int) $this->request->post('plan_id', 0);
        $cycle = (string) $this->request->post('billing_cycle', 'monthly');
        $userId = (int) $_SESSION['id'];

        $checkout = $this->payments->createCheckout($userId, $planId, $cycle);
        if (!$checkout->ok) {
            $this->response->redirect(app_url('/frontend/subir-plano.php?erro=' . urlencode($checkout->errorMessage ?: 'Plano invalido.')));
        }

        $this->audit->log('billing.checkout_create', 'subscription', (int) ($checkout->subscriptionId ?? 0), [
            'plan_id' => $planId,
            'billing_cycle' => $cycle,
            'provider' => $this->payments->name(),
            'metadata' => $checkout->metadata,
        ]);

        if ($this->payments->name() === 'asaas') {
            $_SESSION['billing_checkout'] = [
                'reference' => bin2hex(random_bytes(12)),
                'provider' => $this->payments->name(),
                'plan_id' => $planId,
                'billing_cycle' => in_array($cycle, ['monthly', 'yearly'], true) ? $cycle : 'monthly',
                'redirect_url' => $checkout->redirectUrl,
                'metadata' => $checkout->metadata,
                'created_at' => time(),
            ];

            $this->response->redirect(app_url('/frontend/pagamento-plano.php'));
        }

        $this->response->redirect($checkout->redirectUrl);
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

    public function cancel(): void
    {
        $this->startSession();
        if (empty($_SESSION['logado'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faca login para gerenciar seu plano.')));
        }

        if (($_SESSION['tipo'] ?? '') !== 'cliente') {
            $this->response->redirect($this->dashboardUrlFor((string) ($_SESSION['tipo'] ?? '')) . '?erro=' . urlencode('Planos sao exclusivos para clientes.'));
        }

        $userId = (int) $_SESSION['id'];

        try {
            $result = $this->payments->cancelSubscription($userId);
            $this->audit->log('billing.subscription_cancel', 'subscription', (int) ($result['subscription_id'] ?? 0), $result);
            $this->response->redirect(app_url('/frontend/perfil.php?tab=faturamento&sucesso=' . urlencode('Plano cancelado. Sua conta voltou para o modo gratuito.')));
        } catch (Throwable $exception) {
            $this->response->redirect(app_url('/frontend/perfil.php?tab=faturamento&erro=' . urlencode($exception->getMessage())));
        }
    }

    public function sync(): void
    {
        $this->startSession();
        if (empty($_SESSION['logado'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faca login para verificar seu pagamento.')));
        }

        if (($_SESSION['tipo'] ?? '') !== 'cliente') {
            $this->response->redirect($this->dashboardUrlFor((string) ($_SESSION['tipo'] ?? '')) . '?erro=' . urlencode('Planos sao exclusivos para clientes.'));
        }

        $checkout = is_array($_SESSION['billing_checkout'] ?? null) ? $_SESSION['billing_checkout'] : [];
        $metadata = is_array($checkout['metadata'] ?? null) ? $checkout['metadata'] : [];
        $providerSubscriptionId = trim((string) ($metadata['provider_subscription_id'] ?? ''));
        if (($checkout['provider'] ?? '') !== $this->payments->name() || $providerSubscriptionId === '') {
            $this->response->redirect(app_url('/frontend/subir-plano.php?erro=' . urlencode('Nenhuma cobrança pendente foi encontrada.')));
        }

        $userId = (int) $_SESSION['id'];

        try {
            $result = $this->payments->syncCheckoutPayment($userId, $providerSubscriptionId);
            $this->audit->log('billing.checkout_sync', 'subscription', (int) ($result['subscription_id'] ?? 0), $result);

            if (($result['status'] ?? '') === 'paid') {
                unset($_SESSION['billing_checkout']);
                $this->response->redirect(app_url('/frontend/perfil.php?tab=faturamento&sucesso=' . urlencode('Pagamento confirmado. Seu plano foi ativado.')));
            }

            $this->response->redirect(app_url('/frontend/pagamento-plano.php?erro=' . urlencode('Pagamento ainda não confirmado no Asaas. Se você ainda não efetuou o pagamento, clique em Pagar com segurança para concluir.')));
        } catch (Throwable $exception) {
            $this->response->redirect(app_url('/frontend/pagamento-plano.php?erro=' . urlencode($exception->getMessage())));
        }
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
            'estagiario' => app_url('/frontend/dashboard-estagiario.php'),
            'admin' => app_url('/frontend/admin/dashboard-admin.php'),
            default => app_url('/frontend/dashboard-cliente.php'),
        };
    }
}
