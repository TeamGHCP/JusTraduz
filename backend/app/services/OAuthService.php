<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Services\AuditService;
use App\Services\GoogleOAuthService;
use App\Services\OrganizationInviteService;
use PDO;
use PDOException;
use Throwable;

class OAuthService
{
    private PDO $pdo;
    private AuditService $audit;
    private GoogleOAuthService $google;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->audit = new AuditService($pdo);
        $this->google = new GoogleOAuthService();
    }

    public function isConfigured(): bool
    {
        return $this->google->isConfigured();
    }

    public function getAuthorizationUrl(string $redirectUri, string $state, string $nonce): string
    {
        return $this->google->authorizationUrl($redirectUri, $state, $nonce);
    }

    public function handleCallback(string $code, string $expectedNonce, string $redirectUri): array
    {
        if (!$this->isConfigured()) {
            throw new ValidationException('Login com Google não configurado.');
        }

        $token = $this->google->fetchToken($code, $redirectUri);
        $claims = $this->google->validateIdToken((string) ($token['id_token'] ?? ''), $expectedNonce);
        $userInfo = [];

        try {
            $userInfo = $this->google->fetchUserInfo((string) ($token['access_token'] ?? ''));
        } catch (Throwable $userInfoError) {
            error_log('Google userinfo error: ' . $userInfoError->getMessage());
        }

        if ($userInfo !== []) {
            $claims = array_merge($claims, array_filter([
                'name' => $userInfo['name'] ?? null,
                'email' => $userInfo['email'] ?? null,
                'picture' => $userInfo['picture'] ?? null,
            ], static fn ($value) => $value !== null && $value !== ''));
        }

        $usuario = $this->findOrCreateGoogleUser($claims);

        if (!$this->recoverScheduledAccountDeletion($usuario)) {
            throw new ValidationException('Esta conta está inativa.');
        }

        if ((string) ($usuario['tipo'] ?? '') === 'admin') {
            $this->audit->log('auth.google_login_blocked', 'user', (int) $usuario['id'], ['email' => $usuario['email'] ?? null, 'reason' => 'admin_used_common_google_login']);
            throw new ValidationException('Email ou senha incorretos.');
        }

        return $usuario;
    }

    public function completeProfile(int $pendingUserId, array $data): array
    {
        $tipo = (string) ($data['tipo'] ?? '');
        $telefone = preg_replace('/[^\d()+\-\s]/', '', trim((string) ($data['telefone'] ?? ''))) ?? '';
        $dataNascimento = trim((string) ($data['data_nascimento'] ?? ''));
        $maioridadeConfirmada = ($data['maioridade_confirmada'] ?? '') === '1';
        $cpf = preg_replace('/\D+/', '', (string) ($data['cpf'] ?? '')) ?? '';
        $oab = preg_replace('/\D+/', '', (string) ($data['inscricao'] ?? '')) ?? '';
        $oabUf = strtoupper(trim((string) ($data['oab_uf'] ?? '')));

        if (!in_array($tipo, ['cliente', 'advogado'], true)) {
            throw new ValidationException('Escolha o tipo de conta.');
        }

        $pendingOfficeInvite = $this->pendingOfficeInviteRequirement();
        if ($pendingOfficeInvite !== null && $tipo !== 'advogado') {
            throw new ValidationException('Convites do plano Escritório são exclusivos para cadastro de advogado.');
        }

        if ($telefone === '' || strlen(preg_replace('/\D+/', '', $telefone) ?? '') < 10) {
            throw new ValidationException('Informe um telefone valido com DDD.');
        }

        $ageError = $this->ageValidationError($dataNascimento, $maioridadeConfirmada);
        if ($ageError !== null) {
            throw new ValidationException($ageError);
        }

        $validUfs = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
        $isProfessional = $tipo === 'advogado';

        if ($tipo === 'cliente') {
            if (!$this->isValidCpf($cpf)) {
                throw new ValidationException('Informe um CPF valido para consultar seus processos.');
            }
            $oab = null;
            $oabUf = null;
            $oabStatus = 'not_required';
            $statusCna = null;
            $oabVerified = 0;
            $submittedAt = null;
        } else {
            $cpf = null;
            if ($oab === '') {
                throw new ValidationException('Numero da OAB e obrigatorio.');
            }
            if (!in_array($oabUf, $validUfs, true)) {
                throw new ValidationException('Informe a UF da OAB.');
            }
            $oabStatus = 'pending';
            $statusCna = 'pendente';
            $oabVerified = 0;
            $submittedAt = date('Y-m-d H:i:s');
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, nome, email, tipo, profile_completed
             FROM users
             WHERE id = ? AND status = 'ativo'
             LIMIT 1"
        );
        $stmt->execute([$pendingUserId]);
        $usuario = $stmt->fetch();

        if (!$usuario || (int) ($usuario['profile_completed'] ?? 1) === 1) {
            throw new ValidationException('Cadastro Google já foi concluído.');
        }

        if ($cpf && $this->cpfExists($cpf, $pendingUserId)) {
            throw new ValidationException('CPF já cadastrado.');
        }

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowValue = $driver === 'sqlite' ? "datetime('now')" : 'NOW()';

        $stmt = $this->pdo->prepare(
            "UPDATE users
             SET tipo = ?, telefone = ?, cpf = ?, oab = ?, oab_uf = ?, oab_status = ?,
                 oab_verificado = ?, oab_tipo = ?, status_cna = ?, oab_submitted_at = ?,
                 profile_completed = 1, updated_at = {$nowValue}
             WHERE id = ? AND profile_completed = 0"
        );
        $stmt->execute([
            $tipo,
            $telefone,
            $cpf ?: null,
            $oab,
            $oabUf,
            $oabStatus,
            $oabVerified,
            $isProfessional ? $tipo : null,
            $statusCna,
            $submittedAt,
            $pendingUserId,
        ]);

        $usuario['tipo'] = $tipo;
        $usuario['telefone'] = $telefone;
        $usuario['oab_verificado'] = $oabVerified;
        $usuario['oab_status'] = $oabStatus;
        $usuario['status_cna'] = $statusCna;
        $usuario['profile_completed'] = 1;

        if ($isProfessional) {
            $this->logOabValidation($pendingUserId, 'google_cadastro', null, 'pendente', 'admin_manual', 'pending');
            if (class_exists('App\Services\MailerService')) {
                $mailer = new MailerService();
                $mailer->sendProfessionalPendingEmail((string) $usuario['email'], (string) $usuario['nome'], $tipo);
            }
        }

        $this->audit->log('auth.google_profile_completed', 'user', $pendingUserId, ['tipo' => $tipo]);

        return $usuario;
    }

    private function findOrCreateGoogleUser(array $claims): array
    {
        $sub = (string) ($claims['sub'] ?? '');
        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        $name = trim((string) ($claims['name'] ?? ''));
        $picture = $this->normalizeGooglePictureUrl((string) ($claims['picture'] ?? ''));

        if ($sub === '' || $email === '') {
            throw new ValidationException('Claims do Google OAuth incompletos.');
        }

        // 1. Tenta achar por google_sub
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE google_sub = ? LIMIT 1');
        $stmt->execute([$sub]);
        $user = $stmt->fetch();
        if ($user) {
            $this->updateGoogleProfile((int) $user['id'], $sub, $picture);
            return $user;
        }

        // 2. Tenta achar por email
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $this->updateGoogleProfile((int) $user['id'], $sub, $picture);
            return $user;
        }

        // 3. Cria novo usuário com profile pendente
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowValue = $driver === 'sqlite' ? "datetime('now')" : 'NOW()';

        $stmt = $this->pdo->prepare(
            "INSERT INTO users (nome, email, senha, tipo, google_sub, google_picture, google_linked_at, provider, oab_status, profile_completed, email_verified_at, status)
             VALUES (?, ?, ?, 'cliente', ?, ?, {$nowValue}, 'google', 'not_required', 0, {$nowValue}, 'ativo')"
        );
        $stmt->execute([
            $name ?: 'Google User',
            $email,
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            $sub,
            $picture ?: null,
        ]);

        $newId = (int) $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$newId]);

        return $stmt->fetch() ?: [];
    }

    private function updateGoogleProfile(int $userId, string $googleSub, string $picture): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowValue = $driver === 'sqlite' ? "datetime('now')" : 'NOW()';

        $stmt = $this->pdo->prepare(
            "UPDATE users
             SET google_sub = ?,
                 google_picture = ?,
                 google_linked_at = COALESCE(google_linked_at, {$nowValue}),
                 email_verified_at = COALESCE(email_verified_at, {$nowValue}),
                 provider = 'google',
                 updated_at = {$nowValue}
             WHERE id = ?"
        );
        $stmt->execute([$googleSub, $picture ?: null, $userId]);
    }

    private function normalizeGooglePictureUrl(string $picture): string
    {
        $url = trim($picture);
        if ($url === '') {
            return '';
        }

        $parsed = parse_url($url);
        if (!empty($parsed['host']) && str_ends_with((string) $parsed['host'], 'googleusercontent.com')) {
            return $url;
        }

        return '';
    }

    private function recoverScheduledAccountDeletion(array &$user): bool
    {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $status = (string) ($user['status'] ?? 'ativo');
        $scheduledDeletion = $user['deletion_scheduled_at'] ?? null;

        if ($status === 'inativo' && $scheduledDeletion === null) {
            return false;
        }

        if ($scheduledDeletion !== null) {
            try {
                $stmt = $this->pdo->prepare(
                    'UPDATE users
                     SET status = "ativo", deletion_scheduled_at = NULL
                     WHERE id = ?'
                );
                $stmt->execute([$userId]);
                $user['status'] = 'ativo';
                $user['deletion_scheduled_at'] = null;

                $this->audit->log('auth.recovery_deletion', 'user', $userId, [
                    'email' => $user['email'],
                    'message' => 'Exclusão agendada revertida no login.',
                ]);
            } catch (PDOException $e) {
                error_log('Falha ao recuperar conta agendada para exclusão: ' . $e->getMessage());
            }
        }

        return true;
    }

    private function ageValidationError(string $birthDate, bool $confirmed): ?string
    {
        if (trim($birthDate) === '') {
            return 'Data de nascimento é obrigatória.';
        }

        $timestamp = strtotime($birthDate);
        if ($timestamp === false) {
            return 'Data de nascimento inválida.';
        }

        $age = (int) date('Y') - (int) date('Y', $timestamp);
        if (date('md') < date('md', $timestamp)) {
            $age--;
        }

        if ($age < 18 && !$confirmed) {
            return 'Menores de 18 anos precisam de confirmação de maioridade ou assistência legal.';
        }

        return null;
    }

    private function isValidCpf(?string $cpf): bool
    {
        if ($cpf === null) {
            return false;
        }

        $cpf = preg_replace('/\D+/', '', $cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }

    private function cpfExists(string $cpf, ?int $exceptUserId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE cpf = ?';
        $params = [$cpf];
        if ($exceptUserId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptUserId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function professionalBlockMessage(array $usuario): ?string
    {
        if ((string) ($usuario['tipo'] ?? '') !== 'advogado') {
            return null;
        }

        $oabStatus = (string) ($usuario['oab_status'] ?? 'pending');
        if ($oabStatus === 'approved') {
            return null;
        }

        if ($oabStatus === 'rejected') {
            $reason = trim((string) ($usuario['oab_rejection_reason'] ?? ''));
            $reasonText = $reason !== '' ? ' Motivo: ' . $reason : '';
            return 'Seu cadastro de advogado foi recusado na auditoria.' . $reasonText . ' Entre em contato com o suporte.';
        }

        return 'Seu acesso profissional de advogado está aguardando validação pela administração.';
    }

    private function pendingOfficeInviteRequirement(): ?array
    {
        if (!class_exists('App\Services\OrganizationInviteService')) {
            return null;
        }
        $inviteService = new OrganizationInviteService($this->pdo);
        try {
            return $inviteService->pendingOfficeInviteRequirement();
        } catch (\Throwable) {
            return null;
        }
    }

    private function logOabValidation(
        int $professionalId,
        string $action,
        ?string $previousStatus,
        string $newStatus,
        string $origin,
        ?string $message
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO cna_validacao_logs (profissional_id, acao, status_anterior, status_novo, origem, mensagem)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$professionalId, $action, $previousStatus, $newStatus, $origin, $message]);
        } catch (PDOException $e) {
            error_log('OAB validation log error: ' . $e->getMessage());
        }
    }
}
