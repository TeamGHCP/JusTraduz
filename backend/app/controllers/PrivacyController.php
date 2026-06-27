<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/middlewares/CsrfMiddleware.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/StorageService.php';
require_once dirname(__DIR__) . '/services/SubscriptionService.php';
require_once dirname(__DIR__) . '/services/payments/PaymentProviderFactory.php';

class PrivacyController extends BaseController
{
    private const ACCOUNT_DELETION_RETENTION_DAYS = 30;

    private AuditService $audit;
    private StorageService $storage;

    public function __construct()
    {
        parent::__construct();
        $this->audit = new AuditService($this->pdo);
        $this->storage = new StorageService();
    }

    public function export(): void
    {
        $this->requireLoggedPrivacyAction();

        $userId = $this->currentUserId();
        $payload = $this->buildUserExport($userId);
        $filename = 'justraduz-dados-' . $userId . '-' . date('Ymd-His') . '.json';

        $this->audit->log('privacy.export', 'user', $userId);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: private, no-store, max-age=0');
            header('X-Content-Type-Options: nosniff');
        }

        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function deleteAccount(): void
    {
        $this->requireLoggedPrivacyAction();
        $this->ensureAccountDeletionColumns();

        $confirmation = trim((string) $this->request->post('confirmacao', ''));
        $currentPassword = (string) $this->request->post('senha_atual', '');
        if ($confirmation !== 'EXCLUIR') {
            $this->response->redirect(app_url('/frontend/perfil.php?erro=' . urlencode('Digite EXCLUIR para confirmar o encerramento da conta.')));
        }

        $userId = $this->currentUserId();
        $userType = $this->currentUserType();
        if ($userType === 'admin' && $this->activeAdminCount() <= 1) {
            $this->response->redirect(app_url('/frontend/perfil.php?erro=' . urlencode('Não é possível encerrar o último administrador ativo.')));
        }

        $user = $this->fetchOne('SELECT senha, provider, deletion_scheduled_at FROM users WHERE id = ?', [$userId]);
        $requiresPassword = strtolower((string) ($user['provider'] ?? '')) !== 'google';
        if (!$user || ($requiresPassword && !password_verify($currentPassword, (string) ($user['senha'] ?? '')))) {
            $this->response->redirect(app_url('/frontend/perfil.php?tab=privacidade&erro=' . urlencode('Senha atual incorreta.')));
        }
        if (!empty($user['deletion_scheduled_at'])) {
            $this->response->redirect(app_url('/frontend/perfil.php?sucesso=' . urlencode('A exclusao da conta ja esta agendada. Voce ainda pode cancelar antes do prazo final.')));
        }

        try {
            $subscriptions = new SubscriptionService($this->pdo);
            $billingResult = PaymentProviderFactory::make($this->pdo, $subscriptions)->cancelSubscription($userId);
        } catch (Throwable $exception) {
            error_log('Account deletion billing cancellation failed: ' . $exception->getMessage());
            $this->response->redirect(app_url('/frontend/perfil.php?tab=privacidade&erro=' . urlencode('Não foi possível cancelar sua cobrança. A exclusão não foi agendada; tente novamente.')));
        }

        $now = date('Y-m-d H:i:s');
        $scheduledAt = date('Y-m-d H:i:s', strtotime('+' . self::ACCOUNT_DELETION_RETENTION_DAYS . ' days'));
        $this->pdo->beginTransaction();
        try {
            $organizationResult = $this->prepareOrganizationsForAccountClosure($userId);
            $stmt = $this->pdo->prepare(
                "UPDATE users
                 SET deletion_requested_at = ?, deletion_scheduled_at = ?, status = 'inativo', updated_at = ?
                 WHERE id = ?"
            );
            $stmt->execute([$now, $scheduledAt, $now, $userId]);
            $this->audit->log('privacy.delete_account_scheduled', 'user', $userId, [
                'tipo' => $userType,
                'scheduled_at' => $scheduledAt,
                'billing' => $billingResult,
                'organizations' => $organizationResult,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Account deletion scheduling failed: ' . $exception->getMessage());
            $this->response->redirect(app_url('/frontend/perfil.php?tab=privacidade&erro=' . urlencode('A cobrança foi cancelada, mas não foi possível agendar a exclusão. Entre em contato com o suporte.')));
        }

        secure_session_destroy_current();
        $this->response->redirect(app_url('/frontend/login.html?sucesso=' . urlencode('Conta bloqueada e exclusão agendada para 30 dias. Entre novamente antes do prazo para recuperar a conta.')));
    }

    public function cancelAccountDeletion(): void
    {
        $this->requireLoggedPrivacyAction();
        $this->ensureAccountDeletionColumns();

        $userId = $this->currentUserId();
        $stmt = $this->pdo->prepare(
            "UPDATE users
             SET deletion_requested_at = NULL,
                 deletion_scheduled_at = NULL,
                 status = 'ativo',
                 updated_at = ?
             WHERE id = ? AND deletion_scheduled_at IS NOT NULL"
        );
        $stmt->execute([date('Y-m-d H:i:s'), $userId]);

        if ($stmt->rowCount() < 1) {
            $this->response->redirect(app_url('/frontend/perfil.php?erro=' . urlencode('Nao havia exclusao de conta agendada para cancelar.')));
        }

        $this->audit->log('privacy.delete_account_cancelled', 'user', $userId);
        $this->response->redirect(app_url('/frontend/perfil.php?sucesso=' . urlencode('Exclusao cancelada. Sua conta permanece ativa.')));
    }

    public function finalizeExpiredDeletions(int $limit = 50): int
    {
        $this->ensureAccountDeletionColumns();
        $limit = max(1, min(500, $limit));
        $rows = $this->fetchAll(
            "SELECT id, tipo
             FROM users
             WHERE deletion_scheduled_at IS NOT NULL
               AND deletion_scheduled_at <= ?
             ORDER BY deletion_scheduled_at ASC
             LIMIT {$limit}",
            [date('Y-m-d H:i:s')]
        );

        $finalized = 0;
        foreach ($rows as $row) {
            $this->finalizeAccountDeletionNow((int) $row['id'], (string) ($row['tipo'] ?? 'cliente'));
            $finalized++;
        }

        return $finalized;
    }

    private function finalizeAccountDeletionNow(int $userId, string $userType): void
    {
        $documents = $this->fetchAll('SELECT id, caminho FROM documents WHERE user_id = ?', [$userId]);
        $attachments = $this->fetchAll('SELECT id, attachment_path FROM messages WHERE sender_id = ? AND attachment_path IS NOT NULL', [$userId]);
        $profilePhoto = (string) ($this->fetchOne('SELECT foto_perfil FROM users WHERE id = ?', [$userId])['foto_perfil'] ?? '');

        $this->pdo->beginTransaction();
        try {
            $this->anonymizeUserContent($userId);
            $this->deleteUserDocuments($userId);
            $this->deleteUserAuxiliaryRows($userId);
            $this->anonymizeUserRow($userId);
            $this->audit->log('privacy.delete_account_finalized', 'user', $userId, ['tipo' => $userType]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        foreach ($documents as $document) {
            $this->deleteStoredDocument((string) ($document['caminho'] ?? ''));
        }

        foreach ($attachments as $attachment) {
            $this->deleteStoredAttachment((string) ($attachment['attachment_path'] ?? ''));
        }

        if ($profilePhoto !== '') {
            $this->deleteStoredProfilePhoto($profilePhoto);
        }
    }

    private function prepareOrganizationsForAccountClosure(int $userId): array
    {
        if (!database_table_exists($this->pdo, 'organizations') || !database_table_exists($this->pdo, 'organization_members')) {
            return ['transferred' => [], 'suspended' => []];
        }

        $transferred = [];
        $suspended = [];
        if (database_table_has_column($this->pdo, 'organizations', 'owner_user_id')) {
            $stmt = $this->pdo->prepare('SELECT id FROM organizations WHERE owner_user_id = ?');
            $stmt->execute([$userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $organizationIdValue) {
                $organizationId = (int) $organizationIdValue;
                $candidate = $this->fetchOne(
                    "SELECT om.user_id
                     FROM organization_members om
                     INNER JOIN users u ON u.id = om.user_id
                     WHERE om.organization_id = ? AND om.user_id <> ?
                       AND om.status = 'active' AND u.status = 'ativo' AND u.tipo = 'advogado'
                     ORDER BY CASE om.role WHEN 'admin' THEN 1 WHEN 'member' THEN 2 ELSE 3 END, om.created_at ASC
                     LIMIT 1",
                    [$organizationId, $userId]
                );

                if ($candidate) {
                    $newOwnerId = (int) $candidate['user_id'];
                    $this->pdo->prepare('UPDATE organizations SET owner_user_id = ? WHERE id = ?')->execute([$newOwnerId, $organizationId]);
                    $this->pdo->prepare("UPDATE organization_members SET role = 'member' WHERE organization_id = ? AND user_id = ?")->execute([$organizationId, $userId]);
                    $this->pdo->prepare("UPDATE organization_members SET role = 'owner', status = 'active' WHERE organization_id = ? AND user_id = ?")->execute([$organizationId, $newOwnerId]);
                    $transferred[] = ['organization_id' => $organizationId, 'new_owner_user_id' => $newOwnerId];
                    continue;
                }

                if (database_table_has_column($this->pdo, 'organizations', 'status')) {
                    $this->pdo->prepare("UPDATE organizations SET status = 'inativo' WHERE id = ?")->execute([$organizationId]);
                }
                $this->pdo->prepare("UPDATE organization_members SET status = 'suspended' WHERE organization_id = ?")->execute([$organizationId]);
                $suspended[] = $organizationId;
            }
        }

        if (database_table_exists($this->pdo, 'organization_invites')) {
            $this->pdo->prepare("UPDATE organization_invites SET status = 'revoked' WHERE invited_by = ? AND status = 'pending'")->execute([$userId]);
        }

        return ['transferred' => $transferred, 'suspended' => $suspended];
    }

    private function requireLoggedPrivacyAction(): void
    {
        $this->startSession();
        CsrfMiddleware::validate();

        if (empty($_SESSION['logado']) || $this->currentUserId() <= 0) {
            $this->response->redirect(app_url('/frontend/login.html?erro=' . urlencode('Faça login para continuar.')));
        }
    }

    private function buildUserExport(int $userId): array
    {
        return [
            'exported_at' => date(DATE_ATOM),
            'user' => $this->fetchOne('SELECT id, nome, email, tipo, telefone, cpf, oab, oab_uf, oab_status, oab_verificado, status, created_at, updated_at FROM users WHERE id = ?', [$userId]),
            'documents' => $this->fetchAll('SELECT id, nome_arquivo, tipo_arquivo, texto_extraido, created_at FROM documents WHERE user_id = ? ORDER BY id', [$userId]),
            'cases_as_client' => $this->fetchAll('SELECT id, advogado_id, document_id, titulo, descricao, status, prioridade, created_at FROM cases WHERE cliente_id = ? ORDER BY id', [$userId]),
            'cases_as_professional' => $this->fetchAll('SELECT id, cliente_id, document_id, titulo, status, prioridade, created_at FROM cases WHERE advogado_id = ? ORDER BY id', [$userId]),
            'messages' => $this->fetchAll('SELECT id, case_id, sender_id, mensagem, attachment_original_name, attachment_mime, attachment_size, created_at FROM messages WHERE sender_id = ? ORDER BY id', [$userId]),
            'appointments' => $this->fetchAll(
                'SELECT a.id, a.slot_id, a.client_id, a.case_id, a.assunto, a.observacoes, a.status, a.created_at
                 FROM appointments a
                 LEFT JOIN schedule_slots s ON s.id = a.slot_id
                 WHERE a.client_id = ? OR s.professional_id = ?
                 ORDER BY a.id',
                [$userId, $userId]
            ),
            'external_processes' => $this->fetchAll('SELECT id, source, query_type, query_value, process_number, tribunal, uf, status_normalizado, last_synced_at, created_at FROM external_processes WHERE user_id = ? ORDER BY id', [$userId]),
            'notifications' => $this->fetchAll('SELECT id, mensagem, lida, created_at FROM notifications WHERE user_id = ? ORDER BY id', [$userId]),
            'audit_logs' => $this->fetchAll('SELECT id, action, entity_type, entity_id, details, ip_address, user_agent, created_at FROM audit_logs WHERE user_id = ? ORDER BY id', [$userId]),
        ];
    }

    private function anonymizeUserContent(int $userId): void
    {
        $this->pdo->prepare("UPDATE messages SET mensagem = '[mensagem removida por solicitação LGPD]', attachment_original_name = NULL, attachment_path = NULL, attachment_mime = NULL, attachment_size = NULL WHERE sender_id = ?")->execute([$userId]);
        $this->pdo->prepare("UPDATE cases SET titulo = '[solicitação removida por solicitação LGPD]', descricao = NULL WHERE cliente_id = ?")->execute([$userId]);
        $this->pdo->prepare("UPDATE appointments SET assunto = '[agendamento removido por solicitação LGPD]', observacoes = NULL WHERE client_id = ?")->execute([$userId]);
    }

    private function deleteUserDocuments(int $userId): void
    {
        $documentIds = array_map('intval', array_column($this->fetchAll('SELECT id FROM documents WHERE user_id = ?', [$userId]), 'id'));
        if ($documentIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($documentIds), '?'));
        $this->pdo->prepare("DELETE FROM ai_results WHERE document_id IN ($placeholders)")->execute($documentIds);
        $this->pdo->prepare("UPDATE cases SET document_id = NULL WHERE document_id IN ($placeholders)")->execute($documentIds);
        $this->pdo->prepare("DELETE FROM documents WHERE id IN ($placeholders)")->execute($documentIds);
    }

    private function deleteUserAuxiliaryRows(int $userId): void
    {
        if ($this->tableExists('organization_members')) {
            $this->pdo->prepare("UPDATE organization_members SET status = 'suspended' WHERE user_id = ?")->execute([$userId]);
        }
        if ($this->tableExists('organization_invites')) {
            $this->pdo->prepare("UPDATE organization_invites SET status = 'revoked' WHERE invited_by = ? AND status = 'pending'")->execute([$userId]);
        }
        if ($this->tableExists('user_organizations')) {
            $this->pdo->prepare('DELETE FROM user_organizations WHERE user_id = ?')->execute([$userId]);
        }
        if ($this->tableExists('subscriptions')) {
            $this->pdo->prepare("UPDATE subscriptions SET status = 'canceled', canceled_at = COALESCE(canceled_at, ?) WHERE user_id = ? AND status IN ('trialing', 'active', 'past_due')")
                ->execute([date('Y-m-d H:i:s'), $userId]);
        }

        foreach ([
            'notifications',
            'password_reset_codes',
            'user_onboarding_progress',
            'external_processes',
        ] as $table) {
            if ($this->tableExists($table)) {
                $this->pdo->prepare("DELETE FROM `$table` WHERE user_id = ?")->execute([$userId]);
            }
        }
    }

    private function anonymizeUserRow(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users
             SET nome = ?,
                 email = ?,
                 senha = ?,
                 telefone = NULL,
                 cpf = NULL,
                 foto_perfil = NULL,
                 google_sub = NULL,
                 google_picture = NULL,
                 google_linked_at = NULL,
                 provider = NULL,
                 oab = NULL,
                 oab_uf = NULL,
                 oab_status = 'deleted',
                 oab_rejection_reason = NULL,
                 oab_parametro = NULL,
                 oab_verificado = 0,
                 oab_tipo = NULL,
                 status_cna = NULL,
                 cna_payload_cache = NULL,
                 cna_ultimo_erro = NULL,
                 deletion_requested_at = NULL,
                 deletion_scheduled_at = NULL,
                 status = 'inativo',
                 updated_at = ?
             WHERE id = ?"
        );
        $stmt->execute([
            'Usuário removido',
            'deleted+' . $userId . '@justraduz.invalid',
            password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
            date('Y-m-d H:i:s'),
            $userId,
        ]);
    }

    private function ensureAccountDeletionColumns(): void
    {
        foreach (['deletion_requested_at', 'deletion_scheduled_at'] as $column) {
            if (database_table_has_column($this->pdo, 'users', $column)) {
                continue;
            }

            $safeColumn = preg_replace('/[^A-Za-z0-9_]/', '', $column);
            $type = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'TEXT' : 'DATETIME NULL';
            $this->pdo->exec("ALTER TABLE users ADD COLUMN {$safeColumn} {$type}");
        }
    }

    private function activeAdminCount(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM users WHERE tipo = 'admin' AND status = 'ativo'");
        return (int) $stmt->fetchColumn();
    }

    private function deleteStoredDocument(string $reference): void
    {
        $absolutePath = $this->storage->documentPathFromReference($reference);
        $this->unlinkIfFile($absolutePath);
    }

    private function deleteStoredAttachment(string $reference): void
    {
        $absolutePath = $this->storage->attachmentPathFromReference($reference);
        $this->unlinkIfFile($absolutePath);
    }

    private function deleteStoredProfilePhoto(string $relativePath): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $absolutePath = realpath($projectRoot . '/' . ltrim(str_replace('\\', '/', $relativePath), '/'));
        $profileRoot = realpath($this->profilePhotoRoot($projectRoot));

        if (!$absolutePath || !$profileRoot) {
            return;
        }

        $insideProfileRoot = str_starts_with(
            str_replace('\\', '/', $absolutePath),
            rtrim(str_replace('\\', '/', $profileRoot), '/') . '/'
        );

        if ($insideProfileRoot) {
            $this->unlinkIfFile($absolutePath);
        }
    }

    private function profilePhotoRoot(string $projectRoot): string
    {
        $configuredPath = trim((string) getenv('PROFILE_PHOTO_STORAGE_PATH'));
        if ($configuredPath === '' && function_exists('database_env_values')) {
            $env = database_env_values($projectRoot . '/backend/.env');
            $configuredPath = trim((string) ($env['PROFILE_PHOTO_STORAGE_PATH'] ?? ''));
        }

        $configuredPath = $configuredPath !== '' ? $configuredPath : 'backend/storage/profile_photos';
        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configuredPath);
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $normalizedPath) === 1 || str_starts_with($normalizedPath, DIRECTORY_SEPARATOR)) {
            return $normalizedPath;
        }

        return $projectRoot . DIRECTORY_SEPARATOR . ltrim($normalizedPath, DIRECTORY_SEPARATOR);
    }

    private function unlinkIfFile(?string $absolutePath): void
    {
        if ($absolutePath === null) {
            return;
        }

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function tableExists(string $table): bool
    {
        $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
            $stmt->execute([$safeTable]);
            return (bool) $stmt->fetch();
        }

        $stmt = $this->pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$safeTable]);
        return (bool) $stmt->fetch();
    }

    private function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
