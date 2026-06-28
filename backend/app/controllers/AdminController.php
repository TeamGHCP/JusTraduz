<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/MailerService.php';
require_once dirname(__DIR__) . '/services/NotificationService.php';
require_once dirname(__DIR__) . '/services/OrganizationService.php';
require_once dirname(__DIR__) . '/services/SlaService.php';

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
        $this->requirePermission('users.manage', app_url('/frontend/admin/login-admin.html'), 'Acesso administrativo obrigatório.');

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
        $this->requirePermission('cases.manage', app_url('/frontend/admin/login-admin.html'), 'Acesso administrativo obrigatório.');

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
        $this->requirePermission('oab.validate', app_url('/frontend/admin/login-admin.html'), 'Acesso administrativo obrigatório.');

        $userId = (int) $this->request->post('user_id', 0);
        $action = (string) $this->request->post('action', '');
        $justification = trim((string) $this->request->post('justificativa', ''));
        $justification = mb_substr($justification, 0, 500);

        if ($userId <= 0 || !in_array($action, ['approve', 'reject', 'pending'], true)) {
            $this->response->redirect(app_url('/frontend/admin/validar-oab.php?erro=' . urlencode('Dados inválidos para revisar OAB.')));
        }

        if (in_array($action, ['approve', 'reject'], true) && $justification === '') {
            $this->response->redirect(app_url('/frontend/admin/validar-oab.php?erro=' . urlencode('Informe a justificativa da decisao OAB.')));
        }

        $stmt = $this->pdo->prepare("SELECT id, nome, email, tipo, status_cna, oab_status, oab, oab_uf, oab_parametro FROM users WHERE id = ? AND tipo = 'advogado'");
        $stmt->execute([$userId]);
        $professional = $stmt->fetch();

        if (!$professional) {
            $this->response->redirect(app_url('/frontend/admin/validar-oab.php?erro=' . urlencode('Profissional não encontrado.')));
        }

        $hasOab = trim((string) ($professional['oab'] ?? '')) !== '' && trim((string) ($professional['oab_uf'] ?? '')) !== '';
        if (!$hasOab && $action !== 'reject') {
            $this->response->redirect(app_url('/frontend/admin/validar-oab.php?erro=' . urlencode('Profissional sem OAB e UF informadas.')));
        }

        $previousStatus = (string) ($professional['status_cna'] ?? '');
        $adminId = (int) ($_SESSION['id'] ?? 0);

        if ($action === 'reject') {
            $message = $justification !== '' ? $justification : 'OAB reprovada na revisão manual administrativa.';

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
                     WHERE id = ? AND tipo = 'advogado'"
                );
                $stmt->execute([$message, $message, $adminId > 0 ? $adminId : null, $userId]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Não foi possível rejeitar o profissional.');
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
                $this->response->redirect(app_url('/frontend/admin/validar-oab.php?erro=' . urlencode('Não foi possível rejeitar o cadastro profissional.')));
            }

            $this->notifications->notify($userId, 'Seu cadastro profissional não foi aprovado. Motivo: ' . $message);
            $this->sendProfessionalRejectedEmail((string) $professional['email'], (string) $professional['nome'], $message);
            $this->response->redirect(app_url('/frontend/admin/validar-oab.php?sucesso=' . urlencode('Cadastro profissional rejeitado e usuário notificado.')));
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

        $this->response->redirect(app_url('/frontend/admin/validar-oab.php?sucesso=' . urlencode('Revisão profissional atualizada.')));
    }

    public function reportsSummary(): void
    {
        $this->requirePermission('reports.view', app_url('/frontend/admin/login-admin.html'), 'Acesso a relatórios obrigatório.');

        $cases = $this->fetchAll(
            "SELECT id, titulo, status, prioridade, created_at, advogado_id
             FROM cases
             WHERE status <> 'finalizado'"
        );
        $overdueCases = 0;
        $dueSoonCases = 0;
        foreach ($cases as $case) {
            $sla = SlaService::statusForCase($case);
            if ($sla['state'] === 'overdue') {
                $overdueCases++;
            } elseif ($sla['state'] === 'due_soon') {
                $dueSoonCases++;
            }
        }

        $payload = [
            'generated_at' => date(DATE_ATOM),
            'users_by_role' => $this->keyValueRows('SELECT tipo AS label, COUNT(*) AS total FROM users GROUP BY tipo ORDER BY tipo'),
            'documents' => [
                'total' => $this->count('SELECT COUNT(*) FROM documents'),
                'analyzed' => $this->count('SELECT COUNT(DISTINCT document_id) FROM ai_results'),
                'last_7_days' => $this->keyValueRows(
                    "SELECT DATE(created_at) AS label, COUNT(*) AS total
                     FROM documents
                     WHERE created_at >= ?
                     GROUP BY DATE(created_at)
                     ORDER BY label",
                    [date('Y-m-d 00:00:00', strtotime('-6 days'))]
                ),
            ],
            'cases_by_status' => $this->keyValueRows('SELECT status AS label, COUNT(*) AS total FROM cases GROUP BY status ORDER BY status'),
            'cases_by_priority' => $this->keyValueRows('SELECT prioridade AS label, COUNT(*) AS total FROM cases GROUP BY prioridade ORDER BY prioridade'),
            'sla' => [
                'overdue' => $overdueCases,
                'due_soon' => $dueSoonCases,
                'unassigned' => $this->count("SELECT COUNT(*) FROM cases WHERE status <> 'finalizado' AND advogado_id IS NULL"),
            ],
            'oab' => [
                'pending' => $this->count(
                    "SELECT COUNT(*) FROM users
                     WHERE tipo = 'advogado'
                       AND status = 'ativo'
                       AND oab_verificado = FALSE
                       AND COALESCE(status_cna, 'pendente') = 'pendente'"
                ),
                'validated' => $this->count("SELECT COUNT(*) FROM users WHERE tipo = 'advogado' AND oab_verificado = TRUE"),
            ],
            'ai' => [
                'analyses' => $this->count('SELECT COUNT(*) FROM ai_results'),
                'errors' => $this->count("SELECT COUNT(*) FROM audit_logs WHERE action = 'document.ai_error'"),
            ],
        ];

        if (OrganizationService::enabled($this->pdo)) {
            $payload['organizations'] = [
                'total' => $this->count('SELECT COUNT(*) FROM organizations'),
                'active' => $this->count("SELECT COUNT(*) FROM organizations WHERE status = 'ativo'"),
                'users_by_organization' => $this->keyValueRows(
                    "SELECT COALESCE(o.nome, 'Sem empresa/escritorio') AS label, COUNT(u.id) AS total
                     FROM users u
                     LEFT JOIN organizations o ON o.id = u.organization_id
                     GROUP BY COALESCE(o.nome, 'Sem empresa/escritorio')
                     ORDER BY total DESC, label"
                ),
            ];
        }

        $this->response->json($payload);
    }

    public function reportsExport(): void
    {
        $this->requirePermission('reports.export', app_url('/frontend/admin/login-admin.html'), 'Acesso a exportacao de relatorios obrigatorio.');

        $type = (string) $this->request->get('type', 'cases');
        [$filename, $headers, $rows] = match ($type) {
            'users' => [
                'usuarios.csv',
                ['id', 'nome', 'email', 'tipo', 'status', 'organizacao'],
                $this->reportUsersRows(),
            ],
            'documents' => [
                'documentos.csv',
                ['id', 'arquivo', 'tipo', 'usuario', 'organizacao', 'created_at'],
                $this->reportDocumentsRows(),
            ],
            default => [
                'solicitacoes.csv',
                ['id', 'titulo', 'status', 'prioridade', 'sla_estado', 'responsavel', 'organizacao', 'created_at'],
                $this->reportCasesRows(),
            ],
        };

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($out, $row, ';');
        }
        fclose($out);
    }

    public function updatePermission(): void
    {
        $this->requirePermission('permissions.manage', app_url('/frontend/admin/login-admin.html'), 'Acesso administrativo obrigatorio.');

        if (!OrganizationService::tableExists($this->pdo, 'role_permission_overrides')) {
            $this->response->redirect(app_url('/frontend/admin/permissoes.php?erro=' . urlencode('A migration de permissoes dinamicas ainda nao foi aplicada.')));
        }

        $role = (string) $this->request->post('role', '');
        $permission = (string) $this->request->post('permission', '');
        $effect = (string) $this->request->post('effect', 'inherit');

        try {
            PermissionService::setOverride($this->pdo, $role, $permission, $effect, $this->currentUserId());
            $this->audit->log('admin.permission_override', 'permission', null, compact('role', 'permission', 'effect'));
            $this->response->redirect(app_url('/frontend/admin/permissoes.php?sucesso=' . urlencode('Permissao atualizada.')));
        } catch (Throwable $exception) {
            $this->response->redirect(app_url('/frontend/admin/permissoes.php?erro=' . urlencode('Nao foi possivel atualizar permissao.')));
        }
    }

    public function createOrganization(): void
    {
        $this->requirePermission('organizations.manage', app_url('/frontend/admin/login-admin.html'), 'Acesso administrativo obrigatorio.');
        $this->requireOrganizationsEnabled();

        $name = trim((string) $this->request->post('nome', ''));
        $type = (string) $this->request->post('tipo', 'empresa');
        $documentInput = trim((string) $this->request->post('documento', ''));
        $document = $this->normalizeOrganizationDocument($documentInput);

        if ($name === '' || !in_array($type, ['empresa', 'escritorio'], true)) {
            $this->response->redirect(app_url('/frontend/admin/organizacoes.php?erro=' . urlencode('Dados invalidos para organizacao.')));
        }

        if ($documentInput !== '' && $document === null) {
            $this->response->redirect(app_url('/frontend/admin/organizacoes.php?erro=' . urlencode('Informe um CNPJ valido. O campo ja aceita o padrao alfanumerico da Receita Federal para julho de 2026.')));
        }

        $stmt = $this->pdo->prepare('INSERT INTO organizations (nome, tipo, documento, status) VALUES (?, ?, ?, "ativo")');
        $stmt->execute([$name, $type, $document]);
        $this->audit->log('admin.organization_create', 'organization', (int) $this->pdo->lastInsertId(), ['nome' => $name, 'tipo' => $type]);

        $this->response->redirect(app_url('/frontend/admin/organizacoes.php?sucesso=' . urlencode('Organizacao criada.')));
    }

    public function assignOrganization(): void
    {
        $this->requirePermission('organizations.manage', app_url('/frontend/admin/login-admin.html'), 'Acesso administrativo obrigatorio.');
        $this->requireOrganizationsEnabled();

        $userId = (int) $this->request->post('user_id', 0);
        $organizationId = (int) $this->request->post('organization_id', 0);

        if ($userId <= 0) {
            $this->response->redirect(app_url('/frontend/admin/organizacoes.php?erro=' . urlencode('Usuario invalido.')));
        }

        $stmt = $this->pdo->prepare("SELECT id, tipo FROM users WHERE id = ? AND tipo = 'advogado'");
        $stmt->execute([$userId]);
        $professional = $stmt->fetch();
        if (!$professional) {
            $this->response->redirect(app_url('/frontend/admin/organizacoes.php?erro=' . urlencode('Somente advogados podem ser vinculados a organizações.')));
        }

        $organizationId = $organizationId > 0 ? $organizationId : null;
        $stmt = $this->pdo->prepare('UPDATE users SET organization_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$organizationId, $userId]);

        if ($organizationId !== null) {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $sql = $driver === 'sqlite'
                ? 'INSERT INTO user_organizations (user_id, organization_id, papel, is_primary) VALUES (?, ?, "membro", 1) ON CONFLICT(user_id, organization_id) DO UPDATE SET is_primary = 1'
                : 'INSERT INTO user_organizations (user_id, organization_id, papel, is_primary) VALUES (?, ?, "membro", 1) ON DUPLICATE KEY UPDATE is_primary = 1';
            $this->pdo->prepare($sql)->execute([$userId, $organizationId]);
        }

        $this->audit->log('admin.organization_assign_user', 'user', $userId, ['organization_id' => $organizationId]);
        $this->response->redirect(app_url('/frontend/admin/organizacoes.php?sucesso=' . urlencode('Vinculo atualizado.')));
    }

    private function normalizeOrganizationDocument(string $document): ?string
    {
        $normalized = strtoupper((string) preg_replace('/[^0-9A-Za-z]+/', '', $document));
        if ($normalized === '') {
            return null;
        }

        if (!preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $normalized)) {
            return null;
        }

        if (!$this->isValidOrganizationDocument($normalized)) {
            return null;
        }

        return $normalized;
    }

    private function isValidOrganizationDocument(string $document): bool
    {
        if (strlen($document) !== 14 || !preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $document)) {
            return false;
        }

        if (preg_match('/^(\d)\1{13}$/', $document)) {
            return false;
        }

        $base = substr($document, 0, 12);
        $firstDigit = $this->organizationDocumentDigit($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $secondDigit = $this->organizationDocumentDigit($base . (string) $firstDigit, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return substr($document, 12, 2) === ((string) $firstDigit . (string) $secondDigit);
    }

    private function organizationDocumentDigit(string $base, array $weights): int
    {
        $sum = 0;
        foreach ($weights as $index => $weight) {
            $sum += (ord($base[$index]) - 48) * $weight;
        }

        $remainder = $sum % 11;
        return $remainder < 2 ? 0 : 11 - $remainder;
    }

    private function sendProfessionalApprovedEmail(string $email, string $name): void
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $this->sendSystemEmail($email, 'Cadastro aprovado no JusTraduz', "<p>Ola, {$safeName}.</p><p>Seu acesso profissional no JusTraduz foi liberado.</p>");
    }

    private function requireOrganizationsEnabled(): void
    {
        if (!OrganizationService::enabled($this->pdo)) {
            $this->response->redirect(app_url('/frontend/admin/organizacoes.php?erro=' . urlencode('A migration de multiempresa ainda nao foi aplicada.')));
        }
    }

    private function reportCasesRows(): array
    {
        $organizationJoin = OrganizationService::enabled($this->pdo)
            ? 'LEFT JOIN organizations o ON o.id = c.organization_id'
            : '';
        $organizationSelect = OrganizationService::enabled($this->pdo) ? 'o.nome AS organizacao,' : "'' AS organizacao,";
        $rows = $this->fetchAll(
            "SELECT c.id, c.titulo, c.status, c.prioridade, c.created_at, u.nome AS responsavel, {$organizationSelect} c.advogado_id
             FROM cases c
             LEFT JOIN users u ON u.id = c.advogado_id
             {$organizationJoin}
             ORDER BY c.created_at DESC
             LIMIT 1000"
        );

        return array_map(function (array $row): array {
            $sla = SlaService::statusForCase($row);
            return [
                $row['id'] ?? '',
                $row['titulo'] ?? '',
                $row['status'] ?? '',
                $row['prioridade'] ?? '',
                $sla['state'] ?? '',
                $row['responsavel'] ?? '',
                $row['organizacao'] ?? '',
                $row['created_at'] ?? '',
            ];
        }, $rows);
    }

    private function reportUsersRows(): array
    {
        $organizationJoin = OrganizationService::enabled($this->pdo)
            ? 'LEFT JOIN organizations o ON o.id = u.organization_id'
            : '';
        $organizationSelect = OrganizationService::enabled($this->pdo) ? 'o.nome AS organizacao' : "'' AS organizacao";

        return array_map(static fn (array $row): array => [
            $row['id'] ?? '',
            $row['nome'] ?? '',
            $row['email'] ?? '',
            $row['tipo'] ?? '',
            $row['status'] ?? '',
            $row['organizacao'] ?? '',
        ], $this->fetchAll(
            "SELECT u.id, u.nome, u.email, u.tipo, u.status, {$organizationSelect}
             FROM users u
             {$organizationJoin}
             ORDER BY u.created_at DESC
             LIMIT 1000"
        ));
    }

    private function reportDocumentsRows(): array
    {
        $organizationJoin = OrganizationService::enabled($this->pdo)
            ? 'LEFT JOIN organizations o ON o.id = d.organization_id'
            : '';
        $organizationSelect = OrganizationService::enabled($this->pdo) ? 'o.nome AS organizacao' : "'' AS organizacao";

        return array_map(static fn (array $row): array => [
            $row['id'] ?? '',
            $row['nome_arquivo'] ?? '',
            $row['tipo_arquivo'] ?? '',
            $row['usuario'] ?? '',
            $row['organizacao'] ?? '',
            $row['created_at'] ?? '',
        ], $this->fetchAll(
            "SELECT d.id, d.nome_arquivo, d.tipo_arquivo, d.created_at, u.nome AS usuario, {$organizationSelect}
             FROM documents d
             LEFT JOIN users u ON u.id = d.user_id
             {$organizationJoin}
             ORDER BY d.created_at DESC
             LIMIT 1000"
        ));
    }

    private function sendProfessionalRejectedEmail(string $email, string $name, string $reason): void
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
        $this->sendSystemEmail($email, 'Cadastro profissional não aprovado', "<p>Olá, {$safeName}.</p><p>Seu cadastro profissional não foi aprovado.</p><p><strong>Motivo:</strong> {$safeReason}</p>");
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

        if (empty($_SESSION['logado']) || !PermissionService::sessionHas('admin.access')) {
            $this->response->redirect(app_url('/frontend/admin/login-admin.html?erro=' . urlencode('Acesso administrativo obrigatório.')));
        }
    }

    private function count(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function keyValueRows(string $sql, array $params = []): array
    {
        $rows = [];
        foreach ($this->fetchAll($sql, $params) as $row) {
            $rows[] = [
                'label' => (string) ($row['label'] ?? ''),
                'total' => (int) ($row['total'] ?? 0),
            ];
        }

        return $rows;
    }
}
