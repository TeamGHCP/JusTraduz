<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Services\AuditService;
use App\Services\OrganizationInviteService;
use PDO;
use PDOException;

class LoginService
{
    private PDO $pdo;
    private AuditService $audit;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->audit = new AuditService($pdo);
    }

    public function attemptLogin(string $email, string $senha): array
    {
        $email = $this->normalizeEmail($email);
        $senha = trim($senha);

        if (!$email || !$senha) {
            throw new ValidationException('Preencha todos os campos.');
        }

        if ($this->tooManyLoginFailures('auth.login_failed')) {
            throw new ValidationException('Muitas tentativas recentes. Aguarde alguns minutos e tente novamente.');
        }

        $deletionSelect = $this->accountDeletionSelectSql();
        $stmt = $this->pdo->prepare(
            "SELECT id, nome, email, senha, tipo, status{$deletionSelect},
                    oab_verificado, oab_status, status_cna, cna_ultimo_erro, oab_rejection_reason, profile_completed
             FROM users
             WHERE email = ?"
        );
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            $this->audit->log('auth.login_failed', 'user', null, ['email' => $email, 'reason' => 'not_found']);
            throw new ValidationException('Email ou senha incorretos.');
        }

        if (!password_verify($senha, $usuario['senha'])) {
            $this->audit->log('auth.login_failed', 'user', (int) $usuario['id'], ['email' => $email, 'reason' => 'wrong_password']);
            throw new ValidationException('Email ou senha incorretos.');
        }

        if (!$this->recoverScheduledAccountDeletion($usuario)) {
            $this->audit->log('auth.login_failed', 'user', (int) $usuario['id'], ['email' => $email, 'reason' => 'inactive']);
            throw new ValidationException('Esta conta está inativa.');
        }

        if ((string) ($usuario['tipo'] ?? '') === 'admin') {
            $this->audit->log('auth.login_failed', 'user', (int) $usuario['id'], ['email' => $email, 'reason' => 'admin_used_common_login']);
            throw new ValidationException('Email ou senha incorretos.');
        }

        $this->rehashUserPasswordIfNeeded((int) $usuario['id'], $senha, (string) $usuario['senha']);

        if ((int) ($usuario['profile_completed'] ?? 1) !== 1) {
            // Will require profile completion redirect handled by controller
            return [
                'user' => $usuario,
                'profile_pending' => true,
                'invite_accepted' => false
            ];
        }

        $professionalBlockMessage = $this->professionalBlockMessage($usuario);
        if ($professionalBlockMessage !== null) {
            $this->audit->log('auth.login_failed', 'user', (int) $usuario['id'], ['email' => $email, 'reason' => 'oab_blocked']);
            throw new ValidationException($professionalBlockMessage);
        }

        $this->audit->log('auth.login', 'user', (int) $usuario['id'], ['tipo' => $usuario['tipo']]);
        $inviteResult = $this->acceptPendingOrganizationInvite((int) $usuario['id']);

        return [
            'user' => $usuario,
            'profile_pending' => false,
            'invite_accepted' => ($inviteResult['ok'] ?? false) === true
        ];
    }

    public function attemptAdminLogin(string $email, string $senha): array
    {
        $email = $this->normalizeEmail($email);
        $senha = trim($senha);

        if (!$email || !$senha) {
            throw new ValidationException('Preencha todos os campos.');
        }

        if ($this->tooManyLoginFailures('auth.login_failed')) {
            throw new ValidationException('Muitas tentativas recentes. Aguarde alguns minutos e tente novamente.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, nome, email, senha, tipo, status, profile_completed
             FROM users
             WHERE email = ?"
        );
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if (!$usuario || (string) ($usuario['tipo'] ?? '') !== 'admin') {
            $this->audit->log('auth.login_failed', 'user', null, ['email' => $email, 'reason' => 'not_found_or_not_admin']);
            throw new ValidationException('Acesso negado.');
        }

        if (!password_verify($senha, $usuario['senha'])) {
            $this->audit->log('auth.login_failed', 'user', (int) $usuario['id'], ['email' => $email, 'reason' => 'wrong_password']);
            throw new ValidationException('Acesso negado.');
        }

        $this->rehashUserPasswordIfNeeded((int) $usuario['id'], $senha, (string) $usuario['senha']);
        $this->audit->log('auth.login', 'user', (int) $usuario['id'], ['tipo' => 'admin']);

        return [
            'user' => $usuario,
            'profile_pending' => false,
            'invite_accepted' => false
        ];
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function tooManyLoginFailures(string $action): bool
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $cutoff = date('Y-m-d H:i:s', time() - 900); // 15 min

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM audit_logs WHERE ip_address = ? AND action = ? AND created_at >= ?'
        );
        $stmt->execute([$ip, $action, $cutoff]);
        $attempts = (int) $stmt->fetchColumn();

        return $attempts >= 10;
    }

    private function accountDeletionSelectSql(): string
    {
        if (database_table_has_column($this->pdo, 'users', 'deletion_scheduled_at')) {
            return ', deletion_scheduled_at';
        }
        return ', NULL AS deletion_scheduled_at';
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

    public function rehashUserPasswordIfNeeded(int $userId, string $plainPassword, string $currentHash): void
    {
        $algorithm = $this->passwordHashAlgorithm();
        $options = $this->passwordHashOptions();

        if (password_needs_rehash($currentHash, $algorithm, $options)) {
            $this->updateUserPassword($userId, $plainPassword);
        }
    }

    private function passwordHashAlgorithm()
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    private function passwordHashOptions(): array
    {
        $options = [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 2,
        ];

        if (defined('PASSWORD_ARGON2ID') && password_algos() && in_array('argon2id', password_algos(), true)) {
            return $options;
        }

        return [];
    }

    private function updateUserPassword(int $userId, string $plainPassword): void
    {
        $hash = $this->hashUserPassword($plainPassword);
        $stmt = $this->pdo->prepare('UPDATE users SET senha = ? WHERE id = ?');
        $stmt->execute([$hash, $userId]);
    }

    private function hashUserPassword(string $plainPassword): string
    {
        return password_hash($plainPassword, $this->passwordHashAlgorithm(), $this->passwordHashOptions());
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

    private function acceptPendingOrganizationInvite(int $userId): ?array
    {
        if (!class_exists('App\Services\OrganizationInviteService')) {
            return null;
        }
        $inviteService = new OrganizationInviteService($this->pdo);
        try {
            return $inviteService->acceptPendingByUserId($userId);
        } catch (\Throwable $e) {
            error_log('Error accepting pending invite on login: ' . $e->getMessage());
            return null;
        }
    }
}
