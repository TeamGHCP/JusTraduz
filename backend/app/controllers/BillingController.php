<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/SubscriptionService.php';

class BillingController extends BaseController
{
    private AuditService $audit;
    private SubscriptionService $subscriptions;

    public function __construct()
    {
        parent::__construct();
        $this->audit = new AuditService($this->pdo);
        $this->subscriptions = new SubscriptionService($this->pdo);
    }

    public function subscribe(): void
    {
        $this->startSession();
        if (empty($_SESSION['logado'])) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faça login para escolher um plano.')));
        }

        if (($_SESSION['tipo'] ?? '') !== 'cliente') {
            $this->response->redirect($this->dashboardUrlFor((string) ($_SESSION['tipo'] ?? '')) . '?erro=' . urlencode('Planos são exclusivos para clientes.'));
        }

        $planId = (int) $this->request->post('plan_id', 0);
        $cycle = (string) $this->request->post('billing_cycle', 'monthly');
        $userId = (int) $_SESSION['id'];

        if (!$this->subscriptions->changePlan($userId, $planId, $cycle, 'active')) {
            $this->response->redirect(app_url('/frontend/subir-plano.php?erro=' . urlencode('Plano inválido.')));
        }

        $subscription = $this->subscriptions->currentForUser($userId);
        if ($subscription && database_table_exists($this->pdo, 'payment_events')) {
            $priceColumn = $cycle === 'yearly' ? 'yearly_price_cents' : 'monthly_price_cents';
            $stmt = $this->pdo->prepare("SELECT {$priceColumn} FROM plans WHERE id = ?");
            $stmt->execute([$planId]);
            $amount = (int) ($stmt->fetchColumn() ?: 0);

            $stmt = $this->pdo->prepare(
                "INSERT INTO payment_events (subscription_id, user_id, provider, event_type, amount_cents, status, payload_json, processed_at)
                 VALUES (?, ?, 'manual_checkout', 'checkout.paid', ?, 'paid', ?, ?)"
            );
            $stmt->execute([
                (int) $subscription['id'],
                $userId,
                $amount,
                json_encode(['billing_cycle' => $cycle, 'source' => 'frontend/subir-plano.php'], JSON_UNESCAPED_UNICODE),
                date('Y-m-d H:i:s'),
            ]);
        }

        $this->audit->log('billing.subscribe', 'subscription', (int) ($subscription['id'] ?? 0), [
            'plan_id' => $planId,
            'billing_cycle' => $cycle,
            'provider' => 'manual_checkout',
        ]);

        $this->response->redirect(app_url('/frontend/subir-plano.php'));
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
