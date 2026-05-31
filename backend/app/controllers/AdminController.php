<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/NotificationService.php';

class AdminController extends BaseController
{
    private NotificationService $notifications;
    private AuditService $audit;

    public function __construct()
    {
        parent::__construct();
        $this->notifications = new NotificationService($this->pdo);
        $this->audit = new AuditService($this->pdo);
    }

    public function updateUserStatus(): void
    {
        $this->requireAdmin();

        $userId = (int) $this->request->post('user_id', 0);
        $status = (string) $this->request->post('status', '');

        if ($userId <= 0 || !in_array($status, ['ativo', 'inativo'], true)) {
            $this->response->redirect(app_url('/frontend/admin/usuarios.php?erro=' . urlencode('Dados inválidos para atualizar usuário.')));
        }

        if ($userId === (int) ($_SESSION['id'] ?? 0) && $status === 'inativo') {
            $this->response->redirect(app_url('/frontend/admin/usuarios.php?erro=' . urlencode('Você não pode inativar sua própria conta.')));
        }

        $stmt = $this->pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
        $stmt->execute([$status, $userId]);
        $this->notifications->notify($userId, 'Seu status de conta foi atualizado para: ' . $status);
        $this->audit->log('admin.user_status_update', 'user', $userId, ['status' => $status]);

        $this->response->redirect(app_url('/frontend/admin/usuarios.php?sucesso=' . urlencode('Status do usuário atualizado.')));
    }

    public function updateCase(): void
    {
        $this->requireAdmin();

        $caseId = (int) $this->request->post('case_id', 0);
        $status = (string) $this->request->post('status', '');
        $prioridade = (string) $this->request->post('prioridade', '');
        $advogadoId = $this->request->post('advogado_id', '');
        $advogadoId = $advogadoId === '' ? null : (int) $advogadoId;

        if (
            $caseId <= 0 ||
            !in_array($status, ['aberto', 'em_andamento', 'finalizado'], true) ||
            !in_array($prioridade, ['baixa', 'media', 'alta'], true)
        ) {
            $this->response->redirect(app_url('/frontend/admin/solicitacoes.php?erro=' . urlencode('Dados inválidos para atualizar solicitação.')));
        }

        if ($advogadoId !== null) {
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ? AND tipo = 'advogado' AND status = 'ativo' AND oab_verificado = TRUE");
            $stmt->execute([$advogadoId]);
            if (!$stmt->fetch()) {
                $this->response->redirect(app_url('/frontend/admin/solicitacoes.php?erro=' . urlencode('Advogado inválido ou inativo.')));
            }

            if ($status === 'aberto') {
                $status = 'em_andamento';
            }
        }

        if ($advogadoId === null && $status === 'em_andamento') {
            $this->response->redirect(app_url('/frontend/admin/solicitacoes.php?erro=' . urlencode('Casos em andamento precisam de advogado responsável.')));
        }

        $stmt = $this->pdo->prepare('UPDATE cases SET status = ?, prioridade = ?, advogado_id = ? WHERE id = ?');
        $stmt->execute([$status, $prioridade, $advogadoId, $caseId]);
        $this->notifications->notifyMany(
            $this->notifications->caseParticipantIds($caseId),
            'Uma solicitação foi atualizada pela administração.'
        );
        $this->audit->log('admin.case_update', 'case', $caseId, [
            'status' => $status,
            'prioridade' => $prioridade,
            'advogado_id' => $advogadoId,
        ]);

        $this->response->redirect(app_url('/frontend/admin/solicitacoes.php?sucesso=' . urlencode('Solicitação atualizada.')));
    }

    private function requireAdmin(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado']) || ($_SESSION['tipo'] ?? '') !== 'admin') {
            $this->response->redirect(app_url('/frontend/admin/login-admin.html?erro=' . urlencode('Acesso administrativo obrigatório.')));
        }
    }
}
