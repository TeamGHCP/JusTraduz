<?php

class PermissionService
{
    private static array $overrideCache = [];

    private const ROLE_PERMISSIONS = [
        'cliente' => [
            'documents.view_own',
            'documents.delete_own',
            'cases.view_own',
            'cases.create',
            'profile.manage_own',
        ],
        'advogado' => [
            'documents.view_assigned',
            'cases.view_assigned',
            'cases.manage_assigned',
            'tasks.manage_assigned',
            'schedule.manage_own',
            'profile.manage_own',
        ],
        'estagiario' => [
            'cases.view_assigned',
            'schedule.view_own',
            'profile.manage_own',
        ],
        'admin' => [
            'admin.access',
            'users.view',
            'users.manage',
            'documents.view_all',
            'documents.delete_all',
            'cases.view_all',
            'cases.manage',
            'oab.validate',
            'reports.view',
            'reports.export',
            'audit.view',
            'organizations.view',
            'organizations.manage',
            'permissions.manage',
            'api.manage',
            'schedule.manage_all',
            'profile.manage_own',
        ],
    ];

    public static function permissionsForRole(string $role): array
    {
        $permissions = self::defaultPermissionsForRole($role);
        $overrides = self::overridesForRole($role);

        foreach ($overrides as $permission => $effect) {
            if ($effect === 'allow' && !in_array($permission, $permissions, true)) {
                $permissions[] = $permission;
            }

            if ($effect === 'deny') {
                $permissions = array_values(array_filter($permissions, static fn (string $item): bool => $item !== $permission));
            }
        }

        return $permissions;
    }

    public static function defaultPermissionsForRole(string $role): array
    {
        return self::ROLE_PERMISSIONS[$role] ?? [];
    }

    public static function roleHas(string $role, string $permission): bool
    {
        return in_array($permission, self::permissionsForRole($role), true);
    }

    public static function sessionHas(string $permission): bool
    {
        return self::roleHas((string) ($_SESSION['tipo'] ?? ''), $permission);
    }

    public static function availablePermissions(): array
    {
        $permissions = [];
        foreach (self::ROLE_PERMISSIONS as $rolePermissions) {
            foreach ($rolePermissions as $permission) {
                $permissions[$permission] = true;
            }
        }

        foreach ([
            'organizations.view',
            'organizations.manage',
            'permissions.manage',
            'reports.export',
            'api.manage',
        ] as $permission) {
            $permissions[$permission] = true;
        }

        ksort($permissions);
        return array_keys($permissions);
    }

    public static function roles(): array
    {
        return array_keys(self::ROLE_PERMISSIONS);
    }

    public static function setOverride(PDO $pdo, string $role, string $permission, string $effect, int $adminId): void
    {
        if (!in_array($role, self::roles(), true) || !in_array($permission, self::availablePermissions(), true)) {
            throw new InvalidArgumentException('Permissao ou perfil invalido.');
        }

        if ($effect === 'inherit') {
            $stmt = $pdo->prepare('DELETE FROM role_permission_overrides WHERE role_name = ? AND permission = ?');
            $stmt->execute([$role, $permission]);
            unset(self::$overrideCache[$role]);
            return;
        }

        if (!in_array($effect, ['allow', 'deny'], true)) {
            throw new InvalidArgumentException('Efeito de permissao invalido.');
        }

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare(
                'INSERT INTO role_permission_overrides (role_name, permission, effect, updated_by, updated_at)
                 VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                 ON CONFLICT(role_name, permission) DO UPDATE SET effect = excluded.effect, updated_by = excluded.updated_by, updated_at = CURRENT_TIMESTAMP'
            );
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO role_permission_overrides (role_name, permission, effect, updated_by)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE effect = VALUES(effect), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP'
            );
        }

        $stmt->execute([$role, $permission, $effect, $adminId > 0 ? $adminId : null]);
        unset(self::$overrideCache[$role]);
    }

    public static function canViewDocument(string $role, int $userId, array $document): bool
    {
        if (self::roleHas($role, 'documents.view_all')) {
            return true;
        }

        if (self::roleHas($role, 'documents.view_own') && (int) ($document['user_id'] ?? 0) === $userId) {
            return true;
        }

        return self::roleHas($role, 'documents.view_assigned');
    }

    public static function canDeleteDocument(string $role, int $userId, array $document): bool
    {
        if (self::roleHas($role, 'documents.delete_all')) {
            return true;
        }

        return self::roleHas($role, 'documents.delete_own') && (int) ($document['user_id'] ?? 0) === $userId;
    }

    public static function canViewCase(string $role, int $userId, array $case): bool
    {
        if (self::roleHas($role, 'cases.view_all')) {
            return true;
        }

        if (self::roleHas($role, 'cases.view_own') && (int) ($case['cliente_id'] ?? 0) === $userId) {
            return true;
        }

        return self::roleHas($role, 'cases.view_assigned') && (int) ($case['advogado_id'] ?? 0) === $userId;
    }

    public static function canManageCase(string $role, int $userId, array $case): bool
    {
        if (self::roleHas($role, 'cases.manage')) {
            return true;
        }

        if (self::roleHas($role, 'cases.manage_assigned') && (int) ($case['advogado_id'] ?? 0) === $userId) {
            return true;
        }

        return self::roleHas($role, 'cases.view_own') && (int) ($case['cliente_id'] ?? 0) === $userId;
    }

    private static function overridesForRole(string $role): array
    {
        if (isset(self::$overrideCache[$role])) {
            return self::$overrideCache[$role];
        }

        self::$overrideCache[$role] = [];
        try {
            require_once dirname(__DIR__) . '/config/database.php';
            $pdo = database_connection();
            if (!self::tableExists($pdo, 'role_permission_overrides')) {
                return self::$overrideCache[$role];
            }

            $stmt = $pdo->prepare('SELECT permission, effect FROM role_permission_overrides WHERE role_name = ?');
            $stmt->execute([$role]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                self::$overrideCache[$role][(string) $row['permission']] = (string) $row['effect'];
            }
        } catch (Throwable) {
            self::$overrideCache[$role] = [];
        }

        return self::$overrideCache[$role];
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
                $stmt->execute([$table]);
                return (bool) $stmt->fetchColumn();
            }

            $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
            return (bool) ($stmt && $stmt->fetchColumn());
        } catch (Throwable) {
            return false;
        }
    }
}
