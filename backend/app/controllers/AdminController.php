<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/MailerService.php';
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

    public function updateProfessionalOab(): void
    {
        $this->requireAdmin();

        $userId = (int) $this->request->post('user_id', 0);
        $action = (string) $this->request->post('action', '');
        $justification = trim((string) $this->request->post('justificativa', ''));
        $justification = mb_substr($justification, 0, 500);

        if ($userId <= 0 || !in_array($action, ['approve', 'reject', 'pending'], true)) {
            $this->response->redirect(app_url('/frontend/admin/validar-oab.php?erro=' . urlencode('Dados invalidos para revisar OAB.')));
        }

        if (in_array($action, ['approve', 'reject'], true) && $justification === '') {
            $this->response->redirect(app_url('/frontend/admin/validar-oab.php?erro=' . urlencode('Informe a justificativa da decisao OAB.')));
        }

        $stmt = $this->pdo->prepare("SELECT id, nome, email, tipo, status_cna, oab_status, oab, oab_uf, oab_parametro FROM users WHERE id = ? AND tipo IN ('advogado', 'estagiario')");
        $stmt->execute([$userId]);
        $professional = $stmt->fetch();

        if (!$professional) {
            $this->response->redirect(app_url('/frontend/admin/validar-oab.php?erro=' . urlencode('Profissional nao encontrado.')));
        }

        $hasOab = trim((string) ($professional['oab'] ?? '')) !== '' && trim((string) ($professional['oab_uf'] ?? '')) !== '';
        if (!$hasOab && $action !== 'reject') {
            $this->response->redirect(app_url('/frontend/admin/validar-oab.php?erro=' . urlencode('Profissional sem OAB e UF informadas.')));
        }

        $previousStatus = (string) ($professional['status_cna'] ?? '');
        $adminId = (int) ($_SESSION['id'] ?? 0);

        if ($action === 'reject') {
            $message = $justification !== '' ? $justification : 'OAB reprovada na revisao manual administrativa.';

            $this->pdo->beginTransaction();
            try {
                $stmt = $this->pdo->prepare(
                    "UPDATE users
                     SET oab_verificado = 0,
                         status_cna = 'invalido',
                         oab_status = 'rejected',
                         cna_validado_em = NOW(),
                         cna_origem = 'admin_manual',
                         cna_ultimo_erro = ?,
                         oab_rejection_reason = ?,
                         oab_validated_at = NOW(),
                         oab_validated_by = ?,
                         updated_at = NOW()
                     WHERE id = ? AND tipo IN ('advogado', 'estagiario')"
                );
                $stmt->execute([$message, $message, $adminId > 0 ? $adminId : null, $userId]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Nao foi possivel rejeitar o profissional.');
                }

                $this->logOabReview($userId, $adminId, $action, $previousStatus, 'invalido', 'admin_manual', $message, $justification);
                $this->audit->log('admin.professional_oab_reject', 'user', $userId, [
                    'nome' => $professional['nome'] ?? null,
                    'email' => $professional['email'] ?? null,
                    'tipo' => $professional['tipo'] ?? null,
                    'oab' => $professional['oab'] ?? null,
                    'oab_uf' => $professional['oab_uf'] ?? null,
                    'status_anterior' => $previousStatus,
                    'justificativa' => $message,
                ]);

                $this->pdo->commit();
            } catch (Throwable $exception) {
                $this->pdo->rollBack();
                $this->response->redirect(app_url('/frontend/admin/validar-oab.php?erro=' . urlencode('Nao foi possivel rejeitar o cadastro profissional.')));
            }

            $this->notifications->notify($userId, 'Seu cadastro profissional nao foi aprovado. Motivo: ' . $message);
            $this->sendProfessionalRejectedEmail((string) $professional['email'], (string) $professional['nome'], $message);
            $this->response->redirect(app_url('/frontend/admin/validar-oab.php?sucesso=' . urlencode('Cadastro profissional rejeitado e usuario notificado.')));
        }

        if ($action === 'approve') {
            $newStatus = 'verificado';
            $verified = 1;
            $origin = 'admin_manual';
            $message = 'approved';
            $notification = 'Seu cadastro profissional foi validado pela administracao.';
        } else {
            $newStatus = 'pendente';
            $verified = 0;
            $origin = 'admin_manual';
            $message = 'Marcado para revisao manual pela administracao.';
            $notification = 'Sua OAB voltou para revisao manual.';
        }

        $stmt = $this->pdo->prepare(
            'UPDATE users
            SET oab_verificado = ?,
                status_cna = ?,
                oab_status = ?,
                cna_validado_em = CASE WHEN ? = \'verificado\' THEN CURRENT_TIMESTAMP ELSE cna_validado_em END,
                cna_origem = ?,
                cna_ultimo_erro = CASE WHEN ? = \'invalido\' THEN ? ELSE NULL END,
                oab_rejection_reason = NULL,
                oab_validated_at = CASE WHEN ? = \'verificado\' THEN CURRENT_TIMESTAMP ELSE oab_validated_at END,
                oab_validated_by = CASE WHEN ? = \'verificado\' THEN ? ELSE oab_validated_by END,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?'
        );
        $stmt->execute([$verified, $newStatus, $action === 'approve' ? 'approved' : 'pending', $newStatus, $origin, $newStatus, $message, $newStatus, $newStatus, $adminId > 0 ? $adminId : null, $userId]);

        $this->logOabReview($userId, $adminId, $action, $previousStatus, $newStatus, $origin, $message, $justification);
        $this->notifications->notify($userId, $notification);
        $this->audit->log('admin.professional_oab_' . $action, 'user', $userId, [
            'status_anterior' => $previousStatus,
            'status_novo' => $newStatus,
            'justificativa' => $justification,
        ]);

        if ($action === 'approve') {
            $this->sendProfessionalApprovedEmail((string) $professional['email'], (string) $professional['nome']);
        }

        $this->response->redirect(app_url('/frontend/admin/validar-oab.php?sucesso=' . urlencode('Revisao profissional atualizada.')));
    }

    private function sendProfessionalApprovedEmail(string $email, string $name): void
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $this->sendSystemEmail($email, 'Cadastro aprovado no JusTraduz', "<p>Ola, {$safeName}.</p><p>Seu acesso profissional no JusTraduz foi liberado.</p>");
    }

    private function sendProfessionalRejectedEmail(string $email, string $name, string $reason): void
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
        $this->sendSystemEmail($email, 'Cadastro profissional nao aprovado', "<p>Ola, {$safeName}.</p><p>Seu cadastro profissional nao foi aprovado.</p><p><strong>Motivo:</strong> {$safeReason}</p>");
    }

    private function sendSystemEmail(string $email, string $subject, string $message): void
    {
        try {
            if (!(new MailerService())->send($email, $subject, $message, true)) {
                error_log('MailerService failed for subject: ' . $subject);
            }
        } catch (Throwable $exception) {
            error_log('MailerService error: ' . $exception->getMessage());
        }
    }

    private function logOabReview(
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
            $this->audit->log('admin.oab_log_error', 'user', $professionalId, ['error' => $exception->getMessage()]);
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
