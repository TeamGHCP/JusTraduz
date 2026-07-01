<?php

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Services\AuditService;
use PDO;
use Throwable;

class ProfileService
{
    private const MIN_PASSWORD_LENGTH = 10;
    private PDO $pdo;
    private AuditService $audit;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->audit = new AuditService($pdo);
    }

    public function updateProfile(int $userId, array $data, ?string $profilePhotoPath): bool
    {
        $nome = trim((string) ($data['nome'] ?? ''));
        $email = $this->normalizeEmail((string) ($data['email'] ?? ''));
        $telefone = trim((string) ($data['telefone'] ?? ''));
        $cpf = preg_replace('/\D+/', '', (string) ($data['cpf'] ?? '')) ?? '';
        $senhaAtual = trim((string) ($data['senha_atual'] ?? ''));
        $novaSenha = trim((string) ($data['nova_senha'] ?? ''));
        $novaSenha2 = trim((string) ($data['nova_senha2'] ?? ''));
        $passwordUpdated = false;

        if (!$nome || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Informe nome e e-mail válidos.');
        }

        $stmt = $this->pdo->prepare('SELECT id, tipo, foto_perfil FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new ValidationException('Usuário não encontrado.');
        }

        $currentType = (string) ($user['tipo'] ?? '');
        if ($currentType === 'cliente' && $cpf !== '' && !$this->isValidCpf($cpf)) {
            throw new ValidationException('Informe um CPF valido.');
        }

        if ($currentType !== 'cliente') {
            $cpf = '';
        }

        // Email check
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            throw new ValidationException('E-mail já cadastrado por outro usuário.');
        }

        // CPF check
        if ($cpf && $this->cpfExists($cpf, $userId)) {
            throw new ValidationException('CPF já cadastrado por outro usuário.');
        }

        if ($novaSenha !== '' || $novaSenha2 !== '' || $senhaAtual !== '') {
            $passwordError = $this->passwordValidationError($novaSenha);
            if ($passwordError !== null) {
                throw new ValidationException($passwordError);
            }

            if ($novaSenha !== $novaSenha2) {
                throw new ValidationException('As novas senhas não coincidem.');
            }

            $stmt = $this->pdo->prepare('SELECT senha FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $hash = (string) $stmt->fetchColumn();

            if (!password_verify($senhaAtual, $hash)) {
                throw new ValidationException('Senha atual incorreta.');
            }

            $this->updateUserPassword($userId, $novaSenha);
            $passwordUpdated = true;
        }

        if ($profilePhotoPath !== null) {
            $oldPhoto = (string) ($user['foto_perfil'] ?? '');
            $stmt = $this->pdo->prepare('UPDATE users SET nome = ?, email = ?, telefone = ?, cpf = ?, foto_perfil = ? WHERE id = ?');
            $stmt->execute([$nome, $email, $telefone ?: null, $cpf ?: null, $profilePhotoPath, $userId]);
            $this->deleteOldProfilePhoto($oldPhoto, $profilePhotoPath);
        } else {
            $stmt = $this->pdo->prepare('UPDATE users SET nome = ?, email = ?, telefone = ?, cpf = ? WHERE id = ?');
            $stmt->execute([$nome, $email, $telefone ?: null, $cpf ?: null, $userId]);
        }

        $this->audit->log('profile.update', 'user', $userId, [
            'email' => $email,
            'telefone_informado' => $telefone !== '',
            'cpf_informado' => $cpf !== '',
            'foto_atualizada' => $profilePhotoPath !== null,
            'senha_atualizada' => $passwordUpdated,
        ]);

        return $passwordUpdated;
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
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

    private function passwordValidationError(string $password): ?string
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

    private function deleteOldProfilePhoto(string $oldPhoto, string $newPhoto): void
    {
        if ($oldPhoto === '' || $oldPhoto === $newPhoto) {
            return;
        }

        $cleanOld = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $oldPhoto), DIRECTORY_SEPARATOR);
        $cleanNew = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $newPhoto), DIRECTORY_SEPARATOR);

        if (str_starts_with($cleanOld, 'frontend') || str_contains($cleanOld, '..')) {
            return;
        }

        $base = dirname(__DIR__, 3);
        $path = $base . DIRECTORY_SEPARATOR . $cleanOld;

        if (is_file($path) && str_starts_with($path, $base)) {
            @unlink($path);
        }
    }
}
