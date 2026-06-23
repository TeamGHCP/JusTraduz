<?php

class PermissionService
{
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
            'audit.view',
            'schedule.manage_all',
            'profile.manage_own',
        ],
    ];

    public static function permissionsForRole(string $role): array
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
}
