<?php

namespace App\Services;

use PDO;

class EmailVerificationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function isEmailVerified(int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT email_verified_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $verifiedAt = $stmt->fetchColumn();
        return $verifiedAt !== null && $verifiedAt !== false;
    }

    public function markAsVerified(int $userId): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $nowValue = $driver === 'sqlite' ? "datetime('now')" : 'NOW()';

        $stmt = $this->pdo->prepare("UPDATE users SET email_verified_at = {$nowValue} WHERE id = ?");
        $stmt->execute([$userId]);
    }
}
