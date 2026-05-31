<?php

class NotificationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function notify(int $userId, string $message): void
    {
        if ($userId <= 0 || trim($message) === '') {
            return;
        }

        $stmt = $this->pdo->prepare('INSERT INTO notifications (user_id, mensagem) VALUES (?, ?)');
        $stmt->execute([$userId, $message]);
    }

    public function notifyMany(array $userIds, string $message): void
    {
        foreach (array_unique(array_filter(array_map('intval', $userIds))) as $userId) {
            $this->notify($userId, $message);
        }
    }

    public function caseParticipantIds(int $caseId): array
    {
        $stmt = $this->pdo->prepare('SELECT cliente_id, advogado_id FROM cases WHERE id = ?');
        $stmt->execute([$caseId]);
        $case = $stmt->fetch();

        if (!$case) {
            return [];
        }

        return array_values(array_filter([
            (int) $case['cliente_id'],
            (int) ($case['advogado_id'] ?? 0),
        ]));
    }

    public function activeAdmins(): array
    {
        return $this->idsBySql("SELECT id FROM users WHERE tipo = 'admin' AND status = 'ativo'");
    }

    public function activeLawyers(): array
    {
        return $this->idsBySql("SELECT id FROM users WHERE tipo = 'advogado' AND status = 'ativo'");
    }

    private function idsBySql(string $sql): array
    {
        return array_map('intval', $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
    }
}
