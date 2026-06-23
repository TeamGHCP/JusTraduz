<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/middlewares/CsrfMiddleware.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/StorageService.php';

class PrivacyController extends BaseController
{
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

        $confirmation = trim((string) $this->request->post('confirmacao', ''));
        if ($confirmation !== 'EXCLUIR') {
            $this->response->redirect(app_url('/frontend/perfil.php?erro=' . urlencode('Digite EXCLUIR para confirmar o encerramento da conta.')));
        }

        $userId = $this->currentUserId();
        $userType = $this->currentUserType();
        if ($userType === 'admin' && $this->activeAdminCount() <= 1) {
            $this->response->redirect(app_url('/frontend/perfil.php?erro=' . urlencode('Não é possível encerrar o último administrador ativo.')));
        }

        $documents = $this->fetchAll('SELECT id, caminho FROM documents WHERE user_id = ?', [$userId]);

        $this->pdo->beginTransaction();
        try {
            $this->anonymizeUserContent($userId);
            $this->deleteUserDocuments($userId);
            $this->deleteUserAuxiliaryRows($userId);
            $this->anonymizeUserRow($userId);
            $this->audit->log('privacy.delete_account', 'user', $userId, ['tipo' => $userType]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        foreach ($documents as $document) {
            $this->deleteStoredFile((string) ($document['caminho'] ?? ''));
        }

        secure_session_destroy_current();
        $this->response->redirect(app_url('/frontend/login.html?sucesso=' . urlencode('Conta encerrada e dados pessoais removidos conforme politica LGPD.')));
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

    private function activeAdminCount(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM users WHERE tipo = 'admin' AND status = 'ativo'");
        return (int) $stmt->fetchColumn();
    }

    private function deleteStoredFile(string $relativePath): void
    {
        $absolutePath = $this->storage->documentPathFromReference($relativePath);
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
