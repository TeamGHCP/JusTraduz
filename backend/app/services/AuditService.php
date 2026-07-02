<?php

namespace App\Services;

use PDO;
use Throwable;

class AuditService
{
    private const MAX_DETAIL_STRING_LENGTH = 800;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function log(string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void
    {
        if (function_exists('database_table_exists') && !database_table_exists($this->pdo, 'audit_logs')) {
            return;
        }

        $safeDetails = $this->redact($details);
        $json = $safeDetails ? json_encode($safeDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        $userId = $this->currentUserId();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bindValue(1, $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(2, mb_substr($action, 0, 100), PDO::PARAM_STR);
            $stmt->bindValue(3, $entityType ? mb_substr($entityType, 0, 80) : null, $entityType ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(4, $entityId, $entityId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(5, $json, $json === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(6, mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null, PDO::PARAM_STR);
            $stmt->bindValue(7, mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null, PDO::PARAM_STR);
            $stmt->execute();
        } catch (Throwable $exception) {
            error_log('Audit log failed: ' . $exception->getMessage());
        }
    }

    private function currentUserId(): ?int
    {
        secure_session_start();

        $id = (int) ($_SESSION['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        try {
            if (function_exists('database_table_exists') && database_table_exists($this->pdo, 'users')) {
                $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$id]);
                return $stmt->fetch() ? $id : null;
            }
        } catch (Throwable) {
            return null;
        }

        return $id;
    }

    private function redact(array $details): array
    {
        $safe = [];

        foreach ($details as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $safe[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = $this->redact($value);
            } elseif (is_scalar($value) || $value === null) {
                $safe[$key] = $this->sanitizeScalar($value);
            } else {
                $safe[$key] = $this->truncateString((string) $value);
            }
        }

        return $safe;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);
        $normalized = str_replace(['-', ' '], '_', $normalized);
        $exact = [
            'senha',
            'password',
            'pass',
            'token',
            'secret',
            'api_key',
            'apikey',
            'authorization',
            'bearer',
            'cpf',
            'cnpj',
            'document_number',
            'documentnumber',
            'process_number',
            'numero_processo',
            'nova_senha',
            'senha_atual',
            'access_token',
            'refresh_token',
            'id_token',
            'client_secret',
            'gemini_api_key',
            'datajud_api_key',
        ];

        if (in_array($normalized, $exact, true)) {
            return true;
        }

        foreach (['senha', 'password', 'secret', 'token', 'api_key', 'apikey', 'authorization'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function sanitizeScalar($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = preg_replace('/Bearer\s+[A-Za-z0-9._~+\-\/]+=*/i', 'Bearer [redacted]', $value) ?? $value;
        $value = preg_replace('/AIza[0-9A-Za-z_\-]{20,}/', '[redacted-google-key]', $value) ?? $value;

        return $this->truncateString($value);
    }

    private function truncateString(string $value): string
    {
        if (mb_strlen($value) <= self::MAX_DETAIL_STRING_LENGTH) {
            return $value;
        }

        return mb_substr($value, 0, self::MAX_DETAIL_STRING_LENGTH) . '...[truncated]';
    }
}
