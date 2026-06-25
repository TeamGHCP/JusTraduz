<?php

require_once dirname(__DIR__) . '/config/database.php';

class OrganizationService
{
    public static function enabled(PDO $pdo): bool
    {
        return self::tableExists($pdo, 'organizations') && database_table_has_column($pdo, 'users', 'organization_id');
    }

    public static function userOrganizationId(PDO $pdo, int $userId): ?int
    {
        if ($userId <= 0 || !self::enabled($pdo)) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT organization_id FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();

        return $value ? (int) $value : null;
    }

    public static function tableExists(PDO $pdo, string $table): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }

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
