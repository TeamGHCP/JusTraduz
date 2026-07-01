<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Services\AuditService;
use App\Services\MailerService;
use PDO;
use Throwable;

class PasswordResetService
{
    private const MIN_PASSWORD_LENGTH = 10;
    private PDO $pdo;
    private AuditService $audit;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->audit = new AuditService($pdo);
    }

    public function requestResetCode(string $email): string
    {
        $email = $this->normalizeEmail($email);
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Informe um e-mail válido.');
        }

        $stmt = $this->pdo->prepare("SELECT id, nome, email FROM users WHERE email = ? AND status = 'ativo'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Emulate success to prevent user enumeration
            return 'Se o e-mail estiver cadastrado, enviaremos um código de recuperação.';
        }

        if ($this->tooManyPasswordResetRequests((int) $user['id'], $email)) {
            throw new ValidationException('Muitas solicitações recentes. Aguarde alguns minutos antes de pedir outro código.');
        }

        if (!$this->issuePasswordResetCode($user)) {
            throw new ValidationException('Não foi possível enviar o e-mail agora. Verifique a configuração de e-mail do servidor.');
        }

        $this->audit->log('auth.password_reset_code_sent', 'user', (int) $user['id'], ['email' => $email]);
        return 'Código enviado por e-mail. Ele expira em 15 minutos.';
    }

    public function confirmResetCode(string $email, string $codigo, string $senha, string $senha2): int
    {
        $email = $this->normalizeEmail($email);
        $codigo = preg_replace('/\D+/', '', $codigo) ?? '';
        $senha = trim($senha);
        $senha2 = trim($senha2);

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Informe um e-mail válido.');
        }

        if (strlen($codigo) !== 6) {
            throw new ValidationException('Informe o código de 6 dígitos enviado por e-mail.');
        }

        $passwordError = $this->passwordValidationError($senha);
        if ($passwordError !== null) {
            throw new ValidationException($passwordError);
        }

        if ($senha !== $senha2) {
            throw new ValidationException('As senhas não coincidem.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT pr.id AS reset_id, pr.code_hash, pr.attempts, u.id AS user_id
             FROM password_reset_codes pr
             INNER JOIN users u ON u.id = pr.user_id
             WHERE pr.email = ?
             AND u.status = 'ativo'
             AND pr.used_at IS NULL
             AND pr.expires_at >= ?
             ORDER BY pr.created_at DESC
             LIMIT 1"
        );
        $stmt->execute([$email, date('Y-m-d H:i:s')]);
        $reset = $stmt->fetch();

        if (!$reset) {
            throw new ValidationException('Código inválido ou expirado. Solicite um novo código.');
        }

        if ((int) ($reset['attempts'] ?? 0) >= 5) {
            throw new ValidationException('Muitas tentativas incorretas. Solicite um novo código.');
        }

        if (!password_verify($codigo, (string) $reset['code_hash'])) {
            $stmt = $this->pdo->prepare('UPDATE password_reset_codes SET attempts = attempts + 1 WHERE id = ?');
            $stmt->execute([(int) $reset['reset_id']]);
            throw new ValidationException('Código incorreto.');
        }

        $userId = (int) $reset['user_id'];
        $this->pdo->beginTransaction();
        try {
            $this->updateUserPassword($userId, $senha);

            $stmt = $this->pdo->prepare('UPDATE password_reset_codes SET used_at = ? WHERE id = ?');
            $stmt->execute([date('Y-m-d H:i:s'), (int) $reset['reset_id']]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->audit->log('auth.password_reset', 'user', $userId, ['email' => $email]);
        return $userId;
    }

    public function requestProfileResetCode(int $userId): string
    {
        $stmt = $this->pdo->prepare("SELECT id, nome, email FROM users WHERE id = ? AND status = 'ativo'");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new ValidationException('Conta ativa não encontrada.');
        }

        if ($this->tooManyPasswordResetRequests((int) $user['id'], (string) $user['email'])) {
            throw new ValidationException('Muitas solicitações recentes. Aguarde alguns minutos antes de pedir outro código.');
        }

        if (!$this->issuePasswordResetCode($user)) {
            throw new ValidationException('Não foi possível enviar o e-mail agora. Verifique a configuração de e-mail do servidor.');
        }

        $this->audit->log('profile.password_reset_code_sent', 'user', (int) $user['id'], ['email' => $user['email']]);
        return 'Código enviado por e-mail. Ele expira em 15 minutos.';
    }

    public function confirmProfileResetCode(int $userId, string $codigo, string $senha, string $senha2): void
    {
        $codigo = preg_replace('/\D+/', '', $codigo) ?? '';
        $senha = trim($senha);
        $senha2 = trim($senha2);

        if (strlen($codigo) !== 6) {
            throw new ValidationException('Informe o código de 6 dígitos enviado por e-mail.');
        }

        $passwordError = $this->passwordValidationError($senha);
        if ($passwordError !== null) {
            throw new ValidationException($passwordError);
        }

        if ($senha !== $senha2) {
            throw new ValidationException('As senhas não coincidem.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT pr.id AS reset_id, pr.code_hash, pr.attempts, u.id AS user_id, u.email
             FROM password_reset_codes pr
             INNER JOIN users u ON u.id = pr.user_id
             WHERE pr.user_id = ?
             AND u.status = 'ativo'
             AND pr.used_at IS NULL
             AND pr.expires_at >= ?
             ORDER BY pr.created_at DESC
             LIMIT 1"
        );
        $stmt->execute([$userId, date('Y-m-d H:i:s')]);
        $reset = $stmt->fetch();

        if (!$reset) {
            throw new ValidationException('Código inválido ou expirado. Solicite um novo código.');
        }

        if ((int) ($reset['attempts'] ?? 0) >= 5) {
            throw new ValidationException('Muitas tentativas incorretas. Solicite um novo código.');
        }

        if (!password_verify($codigo, (string) $reset['code_hash'])) {
            $stmt = $this->pdo->prepare('UPDATE password_reset_codes SET attempts = attempts + 1 WHERE id = ?');
            $stmt->execute([(int) $reset['reset_id']]);
            throw new ValidationException('Código incorreto.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->updateUserPassword($userId, $senha);

            $stmt = $this->pdo->prepare('UPDATE password_reset_codes SET used_at = ? WHERE id = ?');
            $stmt->execute([date('Y-m-d H:i:s'), (int) $reset['reset_id']]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->audit->log('profile.password_reset', 'user', $userId, ['email' => $reset['email']]);
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function tooManyPasswordResetRequests(int $userId, string $email): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM password_reset_codes
             WHERE user_id = ?
             AND email = ?
             AND created_at >= ?'
        );
        $stmt->execute([$userId, $email, date('Y-m-d H:i:s', time() - 900)]);

        return (int) $stmt->fetchColumn() >= 3;
    }

    private function issuePasswordResetCode(array $user): bool
    {
        $code = (string) random_int(100000, 999999);
        $email = (string) $user['email'];

        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + 900);

        $stmt = $this->pdo->prepare('UPDATE password_reset_codes SET used_at = ? WHERE user_id = ? AND used_at IS NULL');
        $stmt->execute([$now, (int) $user['id']]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO password_reset_codes (user_id, email, code_hash, expires_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([(int) $user['id'], $email, password_hash($code, PASSWORD_DEFAULT), $expiresAt]);

        if (class_exists('App\Services\MailerService')) {
            $mailer = new MailerService();
            return $mailer->sendPasswordResetEmail($email, (string) $user['nome'], $code);
        }

        return false;
    }

    public function passwordValidationError(string $password): ?string
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return 'A senha deve conter no mínimo ' . self::MIN_PASSWORD_LENGTH . ' caracteres.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return 'A senha deve conter pelo menos uma letra maiúscula.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            return 'A senha deve conter pelo menos uma letra minúscula.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            return 'A senha deve conter pelo menos um número.';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'A senha deve conter pelo menos um caractere especial.';
        }

        return null;
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

    public function passwordHashAlgorithm()
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    public function passwordHashOptions(): array
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
}
