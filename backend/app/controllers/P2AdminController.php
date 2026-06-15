<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/OrganizationService.php';
require_once dirname(__DIR__) . '/services/SubscriptionService.php';

class P2AdminController extends BaseController
{
    private AuditService $audit;
    private OrganizationService $organizations;
    private SubscriptionService $subscriptions;

    public function __construct()
    {
        parent::__construct();
        $this->audit = new AuditService($this->pdo);
        $this->organizations = new OrganizationService($this->pdo);
        $this->subscriptions = new SubscriptionService($this->pdo);
    }

    public function updateSubscription(): void
    {
        $this->requireAdmin();

        $userId = (int) $this->request->post('user_id', 0);
        $planId = (int) $this->request->post('plan_id', 0);
        $cycle = (string) $this->request->post('billing_cycle', 'monthly');
        $status = (string) $this->request->post('status', 'active');

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ? AND tipo = 'cliente' AND status = 'ativo'");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            $this->response->redirect(app_url('/frontend/admin/assinaturas.php?erro=' . urlencode('Assinaturas são permitidas apenas para clientes ativos.')));
        }

        if ($userId <= 0 || !$this->subscriptions->changePlan($userId, $planId, $cycle, $status)) {
            $this->response->redirect(app_url('/frontend/admin/assinaturas.php?erro=' . urlencode('Dados inválidos para assinatura.')));
        }

        $subscription = $this->subscriptions->currentForUser($userId);
        $this->audit->log('admin.subscription_update', 'subscription', (int) ($subscription['id'] ?? 0), [
            'user_id' => $userId,
            'plan_id' => $planId,
            'billing_cycle' => $cycle,
            'status' => $status,
        ]);

        $this->response->redirect(app_url('/frontend/admin/assinaturas.php?sucesso=' . urlencode('Assinatura atualizada.')));
    }

    public function createOrganization(): void
    {
        $this->requireAdmin();

        $name = trim((string) $this->request->post('name', ''));
        $ownerId = (int) $this->request->post('owner_user_id', 0);
        if ($name === '' || $ownerId <= 0) {
            $this->response->redirect(app_url('/frontend/admin/organizacoes.php?erro=' . urlencode('Informe nome e proprietário.')));
        }

        $organizationId = $this->organizations->create($name, $ownerId);
        $this->audit->log('admin.organization_create', 'organization', $organizationId, ['owner_user_id' => $ownerId]);
        $this->response->redirect(app_url('/frontend/admin/organizacoes.php?sucesso=' . urlencode('Organização criada.')));
    }

    public function addOrganizationMember(): void
    {
        $this->requireAdmin();
        if (!database_table_exists($this->pdo, 'organization_members')) {
            $this->response->redirect(app_url('/frontend/admin/organizacoes.php?erro=' . urlencode('Estrutura de organizações indisponível.')));
        }

        $organizationId = (int) $this->request->post('organization_id', 0);
        $userId = (int) $this->request->post('user_id', 0);
        $role = (string) $this->request->post('role', 'member');
        if ($organizationId <= 0 || $userId <= 0 || !in_array($role, ['admin', 'member', 'viewer'], true)) {
            $this->response->redirect(app_url('/frontend/admin/organizacoes.php?erro=' . urlencode('Dados inválidos do membro.')));
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO organization_members (organization_id, user_id, role, status, invited_by)
             VALUES (?, ?, ?, 'active', ?)
             ON DUPLICATE KEY UPDATE role = VALUES(role), status = 'active', updated_at = NOW()"
        );
        $stmt->execute([$organizationId, $userId, $role, (int) ($_SESSION['id'] ?? 0)]);
        $this->audit->log('admin.organization_member_upsert', 'organization', $organizationId, ['user_id' => $userId, 'role' => $role]);
        $this->response->redirect(app_url('/frontend/admin/organizacoes.php?sucesso=' . urlencode('Membro atualizado.')));
    }

    public function updatePermission(): void
    {
        $this->requireAdmin();
        if (!database_table_exists($this->pdo, 'user_permissions')) {
            $this->response->redirect(app_url('/frontend/admin/permissoes.php?erro=' . urlencode('Estrutura de permissões indisponível.')));
        }

        $userId = (int) $this->request->post('user_id', 0);
        $permission = trim((string) $this->request->post('permission_key', ''));
        $allowed = (string) $this->request->post('allowed', '1') === '1' ? 1 : 0;
        if ($userId <= 0 || $permission === '') {
            $this->response->redirect(app_url('/frontend/admin/permissoes.php?erro=' . urlencode('Permissão inválida.')));
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_permissions (user_id, permission_key, allowed, granted_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE allowed = VALUES(allowed), granted_by = VALUES(granted_by)'
        );
        $stmt->execute([$userId, $permission, $allowed, (int) ($_SESSION['id'] ?? 0)]);
        $this->audit->log('admin.permission_update', 'user', $userId, ['permission' => $permission, 'allowed' => (bool) $allowed]);
        $this->response->redirect(app_url('/frontend/admin/permissoes.php?sucesso=' . urlencode('Permissão atualizada.')));
    }

    private function requireAdmin(): void
    {
        $this->startSession();
        if (empty($_SESSION['logado']) || ($_SESSION['tipo'] ?? '') !== 'admin') {
            $this->response->redirect(app_url('/frontend/admin/login-admin.html?erro=' . urlencode('Acesso administrativo obrigatório.')));
        }
    }
}
