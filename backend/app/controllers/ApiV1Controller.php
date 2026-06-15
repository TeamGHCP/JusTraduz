<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/SlaService.php';
require_once dirname(__DIR__) . '/services/SubscriptionService.php';

class ApiV1Controller extends BaseController
{
    public function me(): void
    {
        $this->requireJsonLogin();
        $subscriptions = new SubscriptionService($this->pdo);
        $subscription = $subscriptions->currentForUser((int) $_SESSION['id']);

        $this->response->json([
            'api_version' => 'v1',
            'data' => [
                'id' => (int) $_SESSION['id'],
                'nome' => (string) ($_SESSION['nome'] ?? ''),
                'tipo' => (string) ($_SESSION['tipo'] ?? ''),
                'subscription' => $subscription ? [
                    'status' => $subscription['status'],
                    'plan' => $subscription['plan_slug'],
                    'billing_cycle' => $subscription['billing_cycle'],
                    'current_period_end' => $subscription['current_period_end'],
                ] : null,
            ],
        ]);
    }

    public function cases(): void
    {
        $this->requireJsonLogin();
        $userId = (int) $_SESSION['id'];
        $type = (string) ($_SESSION['tipo'] ?? '');

        if ($type === 'admin') {
            $where = '1=1';
            $params = [];
        } elseif ($type === 'cliente') {
            $where = 'c.cliente_id = ?';
            $params = [$userId];
        } else {
            $where = 'c.advogado_id = ?';
            $params = [$userId];
        }

        $hasSla = database_table_has_column($this->pdo, 'cases', 'sla_due_at');
        $slaSelect = $hasSla ? ', c.sla_due_at, c.sla_status' : ", NULL AS sla_due_at, 'sem_sla' AS sla_status";
        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.titulo, c.status, c.prioridade, c.created_at {$slaSelect},
                    cli.nome AS cliente, adv.nome AS advogado
             FROM cases c
             INNER JOIN users cli ON cli.id = c.cliente_id
             LEFT JOIN users adv ON adv.id = c.advogado_id
             WHERE {$where}
             ORDER BY c.created_at DESC
             LIMIT 100"
        );
        $stmt->execute($params);

        $this->response->json(['api_version' => 'v1', 'data' => $stmt->fetchAll()]);
    }

    public function reports(): void
    {
        $this->requireJsonLogin();
        if (($_SESSION['tipo'] ?? '') !== 'admin') {
            $this->response->json(['api_version' => 'v1', 'error' => ['code' => 'forbidden', 'message' => 'Acesso negado.']], 403);
            return;
        }

        $this->refreshSlaStatuses();
        $data = [
            'users_total' => (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'cases_open' => (int) $this->pdo->query("SELECT COUNT(*) FROM cases WHERE status = 'aberto'")->fetchColumn(),
            'cases_in_progress' => (int) $this->pdo->query("SELECT COUNT(*) FROM cases WHERE status = 'em_andamento'")->fetchColumn(),
            'cases_done' => (int) $this->pdo->query("SELECT COUNT(*) FROM cases WHERE status = 'finalizado'")->fetchColumn(),
            'sla_overdue' => database_table_has_column($this->pdo, 'cases', 'sla_status')
                ? (int) $this->pdo->query("SELECT COUNT(*) FROM cases WHERE sla_status = 'vencido'")->fetchColumn()
                : 0,
            'active_subscriptions' => database_table_exists($this->pdo, 'subscriptions')
                ? (int) $this->pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status IN ('trialing', 'active')")->fetchColumn()
                : 0,
        ];

        $this->response->json(['api_version' => 'v1', 'data' => $data]);
    }

    private function requireJsonLogin(): void
    {
        $this->startSession();
        if (empty($_SESSION['logado'])) {
            $this->response->json(['api_version' => 'v1', 'error' => ['code' => 'auth_required', 'message' => 'Faça login.']], 401);
            exit;
        }
    }

    private function refreshSlaStatuses(): void
    {
        if (!database_table_has_column($this->pdo, 'cases', 'sla_status')) {
            return;
        }

        $this->pdo->exec("UPDATE cases SET sla_status = 'vencido' WHERE status <> 'finalizado' AND sla_due_at IS NOT NULL AND sla_due_at < NOW()");
        $this->pdo->exec("UPDATE cases SET sla_status = 'em_risco' WHERE status <> 'finalizado' AND sla_due_at IS NOT NULL AND sla_due_at >= NOW() AND sla_due_at <= DATE_ADD(NOW(), INTERVAL 12 HOUR)");
        $this->pdo->exec("UPDATE cases SET sla_status = 'ok' WHERE status <> 'finalizado' AND sla_due_at IS NOT NULL AND sla_due_at > DATE_ADD(NOW(), INTERVAL 12 HOUR)");
        $this->pdo->exec("UPDATE cases SET sla_status = 'ok' WHERE status = 'finalizado'");
    }
}
