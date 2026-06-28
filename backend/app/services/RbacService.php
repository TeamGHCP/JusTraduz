<?php

class RbacService
{
    private PDO $pdo;

    private const DEFAULT_PERMISSIONS = [
        'cliente' => [
            'documents.view_own', 'documents.create', 'documents.delete_own',
            'cases.view_own', 'cases.create', 'cases.message',
            'agenda.book', 'profile.manage',
        ],
        'advogado' => [
            'documents.view_assigned', 'cases.view_assigned', 'cases.manage_assigned',
            'cases.message', 'tasks.manage_assigned', 'agenda.manage_own', 'reports.view_own',
            'profile.manage',
        ],
        'admin' => ['*'],
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function can(int $userId, string $permission, ?string $fallbackType = null): bool
    {
        $type = $fallbackType ?: $this->userType($userId);
        if ($type === 'admin') {
            return true;
        }

        $defaults = self::DEFAULT_PERMISSIONS[$type] ?? [];
        if (in_array('*', $defaults, true) || in_array($permission, $defaults, true)) {
            return true;
        }

        if (!database_table_exists($this->pdo, 'user_permissions')) {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT allowed FROM user_permissions WHERE user_id = ? AND permission_key = ? LIMIT 1');
        $stmt->execute([$userId, $permission]);
        $row = $stmt->fetch();

        return $row ? (int) $row['allowed'] === 1 : false;
    }

    public function permissionsForType(string $type): array
    {
        return self::DEFAULT_PERMISSIONS[$type] ?? [];
    }

    private function userType(int $userId): string
    {
        $stmt = $this->pdo->prepare('SELECT tipo FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return (string) ($stmt->fetchColumn() ?: '');
    }
}
