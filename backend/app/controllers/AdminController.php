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
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ? AND tipo = 'advogado' AND status = 'ativo' AND (oab_verificado = TRUE OR (status_cna = 'pendente' AND COALESCE(oab, '') <> '' AND COALESCE(oab_uf, '') <> ''))");
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

    public function updateProfessionalOab(): void
    {
        $this->requireAdmin();

        $userId = (int) $this->request->post('user_id', 0);
        $action = (string) $this->request->post('action', '');
        $justification = trim((string) $this->request->post('justificativa', ''));

        if ($userId <= 0 || !in_array($action, ['approve', 'reject', 'pending'], true)) {
            $this->response->redirect(app_url('/frontend/admin/usuarios.php?erro=' . urlencode('Dados inválidos para revisar OAB.')));
        }

        $stmt = $this->pdo->prepare("SELECT id, nome, tipo, status_cna, oab, oab_uf FROM users WHERE id = ? AND tipo IN ('advogado', 'estagiario')");
        $stmt->execute([$userId]);
        $professional = $stmt->fetch();

        if (!$professional) {
            $this->response->redirect(app_url('/frontend/admin/usuarios.php?erro=' . urlencode('Profissional não encontrado.')));
        }

        $hasOab = trim((string) ($professional['oab'] ?? '')) !== '' && trim((string) ($professional['oab_uf'] ?? '')) !== '';
        if (!$hasOab) {
            $this->response->redirect(app_url('/frontend/admin/usuarios.php?erro=' . urlencode('Profissional sem OAB e UF informadas.')));
        }

        $previousStatus = (string) ($professional['status_cna'] ?? '');
        $adminId = (int) ($_SESSION['id'] ?? 0);

        if ($action === 'approve') {
            $newStatus = 'verificado';
            $verified = 1;
            $origin = 'admin_manual';
            $message = 'Validado manualmente pela administração.';
            $notification = 'Seu cadastro profissional foi validado pela administração.';
        } elseif ($action === 'reject') {
            $newStatus = 'invalido';
            $verified = 0;
            $origin = 'admin_manual';
            $message = $justification !== '' ? $justification : 'Reprovado manualmente pela administração.';
            $notification = 'Sua validação profissional precisa de correção: ' . $message;
        } else {
            $newStatus = 'pendente';
            $verified = 0;
            $origin = 'admin_manual';
            $message = 'Marcado para revisão manual pela administração.';
            $notification = 'Sua validação profissional voltou para revisão manual.';
        }

        $stmt = $this->pdo->prepare(
            'UPDATE users
             SET oab_verificado = ?,
                 status_cna = ?,
                 oab_status = ?,
                 cna_validado_em = CASE WHEN ? = \'verificado\' THEN NOW() ELSE cna_validado_em END,
                 cna_origem = ?,
                 cna_ultimo_erro = CASE WHEN ? = \'invalido\' THEN ? ELSE NULL END
             WHERE id = ?'
        );
        $stmt->execute([$verified, $newStatus, $message, $newStatus, $origin, $newStatus, $message, $userId]);

        $this->logCnaReview($userId, $adminId, $action, $previousStatus, $newStatus, $origin, $message, $justification);
        $this->notifications->notify($userId, $notification);
        $this->audit->log('admin.professional_oab_' . $action, 'user', $userId, [
            'status_anterior' => $previousStatus,
            'status_novo' => $newStatus,
            'justificativa' => $justification,
        ]);

        $this->response->redirect(app_url('/frontend/admin/usuarios.php?tipo=' . urlencode((string) $professional['tipo']) . '&sucesso=' . urlencode('Revisão profissional atualizada.')));
    }

    private function logCnaReview(
        int $professionalId,
        int $adminId,
        string $action,
        string $previousStatus,
        string $newStatus,
        string $origin,
        string $message,
        string $justification
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO cna_validacao_logs
                    (profissional_id, admin_id, acao, status_anterior, status_novo, origem, mensagem, justificativa)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $professionalId,
                $adminId > 0 ? $adminId : null,
                'admin_' . $action,
                $previousStatus ?: null,
                $newStatus,
                $origin,
                $message,
                $justification !== '' ? $justification : null,
            ]);
        } catch (Throwable $exception) {
            $this->audit->log('admin.cna_log_error', 'user', $professionalId, ['error' => $exception->getMessage()]);
        }
    }

    private function requireAdmin(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado']) || ($_SESSION['tipo'] ?? '') !== 'admin') {
            $this->response->redirect(app_url('/frontend/admin/login-admin.html?erro=' . urlencode('Acesso administrativo obrigatório.')));
        }
    }
}
