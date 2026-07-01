<?php

namespace App\Services;

use PDO;
use Throwable;

class OrganizationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function currentOrganizationId(int $userId): ?int
    {
        if (!database_table_exists($this->pdo, 'organization_members')) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT organization_id FROM organization_members
             WHERE user_id = ? AND status = 'active'
             ORDER BY CASE role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 WHEN 'member' THEN 3 ELSE 9 END, created_at ASC
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $id = (int) ($stmt->fetchColumn() ?: 0);

        return $id > 0 ? $id : null;
    }

    public function sameOrganization(int $leftUserId, int $rightUserId): bool
    {
        $left = $this->currentOrganizationId($leftUserId);
        $right = $this->currentOrganizationId($rightUserId);

        return $left !== null && $left === $right;
    }

    public static function enabled(PDO $pdo): bool
    {
        return self::tableExists($pdo, 'organizations')
            && self::tableExists($pdo, 'organization_members');
    }

    public static function tableExists(PDO $pdo, string $table): bool
    {
        return database_table_exists($pdo, $table);
    }

    public function create(string $name, int $ownerId): int
    {
        if (!database_table_exists($this->pdo, 'organizations')) {
            return 0;
        }

        $slug = $this->slug($name);
        $this->pdo->beginTransaction();
        try {
            $columns = [];
            $values = [];

            $hasNome = database_table_has_column($this->pdo, 'organizations', 'nome');
            if ($hasNome) {
                $columns[] = 'nome';
                $values[] = $name;
            }
            if (database_table_has_column($this->pdo, 'organizations', 'name')) {
                $columns[] = 'name';
                $values[] = $name;
            }
            if (database_table_has_column($this->pdo, 'organizations', 'slug')) {
                $columns[] = 'slug';
                $values[] = $slug;
            }
            if (database_table_has_column($this->pdo, 'organizations', 'owner_user_id')) {
                $columns[] = 'owner_user_id';
                $values[] = $ownerId;
            }
            if (database_table_has_column($this->pdo, 'organizations', 'tipo')) {
                $columns[] = 'tipo';
                $values[] = 'escritorio';
            }
            if (database_table_has_column($this->pdo, 'organizations', 'status')) {
                $columns[] = 'status';
                $values[] = $hasNome ? 'ativo' : 'active';
            }

            if ($columns === []) {
                $this->pdo->rollBack();
                return 0;
            }

            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $stmt = $this->pdo->prepare('INSERT INTO organizations (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')');
            $stmt->execute($values);
            $organizationId = (int) $this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare("INSERT INTO organization_members (organization_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')");
            $stmt->execute([$organizationId, $ownerId]);
            $this->pdo->commit();

            return $organizationId;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function members(int $organizationId): array
    {
        if (!database_table_exists($this->pdo, 'organization_members')) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT om.*, u.nome, u.email, u.tipo
             FROM organization_members om
             INNER JOIN users u ON u.id = om.user_id
             WHERE om.organization_id = ?
             ORDER BY CASE om.role WHEN "owner" THEN 1 WHEN "admin" THEN 2 WHEN "member" THEN 3 WHEN "viewer" THEN 4 ELSE 9 END, u.nome ASC'
        );
        $stmt->execute([$organizationId]);
        return $stmt->fetchAll();
    }

    private function slug(string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        return $slug !== '' ? $slug . '-' . bin2hex(random_bytes(3)) : 'org-' . bin2hex(random_bytes(4));
    }
}
