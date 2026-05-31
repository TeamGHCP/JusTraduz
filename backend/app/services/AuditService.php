<?php

require_once dirname(__DIR__) . '/support/session.php';

class AuditService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function log(string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void
    {
        $safeDetails = $this->redact($details);
        $json = $safeDetails ? json_encode($safeDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $this->currentUserId(),
            mb_substr($action, 0, 100),
            $entityType ? mb_substr($entityType, 0, 80) : null,
            $entityId,
            $json,
            mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
        ]);
    }

    private function currentUserId(): ?int
    {
        secure_session_start();

        $id = (int) ($_SESSION['id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    private function redact(array $details): array
    {
        $blocked = ['senha', 'password', 'token', 'secret', 'api_key', 'gemini_api_key', 'nova_senha', 'senha_atual'];
        $safe = [];

        foreach ($details as $key => $value) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, $blocked, true) || str_contains($normalized, 'senha') || str_contains($normalized, 'password')) {
                $safe[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = $this->redact($value);
            } elseif (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            } else {
                $safe[$key] = (string) $value;
            }
        }

        return $safe;
    }
}
