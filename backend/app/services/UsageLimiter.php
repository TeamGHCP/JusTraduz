<?php

class UsageLimiter
{
    private PDO $pdo;
    private ?SubscriptionService $subscriptions = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $subscriptionFile = __DIR__ . '/SubscriptionService.php';
        if (is_file($subscriptionFile)) {
            require_once $subscriptionFile;
            $this->subscriptions = new SubscriptionService($pdo);
        }
    }

    public function allow(int $userId, string $feature, int $units = 1): array
    {
        $limit = $this->limitFor($feature, $userId);
        if ($limit <= 0) {
            return ['allowed' => true, 'limit' => 0, 'used' => 0, 'remaining' => null];
        }

        $used = $this->usedToday($userId, $feature);
        $allowed = ($used + $units) <= $limit;

        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
        ];
    }

    public function record(int $userId, string $feature, int $units = 1, ?int $entityId = null, array $metadata = []): void
    {
        if (!database_table_has_column($this->pdo, 'usage_events', 'feature')) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO usage_events (user_id, feature, units, entity_id, metadata_json, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            mb_substr($feature, 0, 80),
            max(1, $units),
            $entityId,
            $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            date('Y-m-d H:i:s'),
        ]);
    }

    private function usedToday(int $userId, string $feature): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(units), 0) FROM usage_events WHERE user_id = ? AND feature = ? AND created_at >= ?'
        );
        $stmt->execute([$userId, $feature, date('Y-m-d 00:00:00')]);
        return (int) $stmt->fetchColumn();
    }

    private function limitFor(string $feature, int $userId): int
    {
        $map = [
            'document_upload' => 'USAGE_DAILY_DOCUMENT_UPLOADS',
            'document_ai' => 'USAGE_DAILY_DOCUMENT_AI',
            'ai_chat' => 'USAGE_DAILY_AI_CHAT',
            'datajud_cnj' => 'USAGE_DAILY_DATAJUD_CNJ',
            'ocr' => 'USAGE_DAILY_OCR',
        ];

        $key = $map[$feature] ?? '';
        if ($key === '') {
            return 0;
        }

        if ($this->subscriptions !== null) {
            $planLimit = $this->subscriptions->featureLimit($userId, $feature);
            if ($planLimit > 0) {
                return $planLimit;
            }
        }

        $value = getenv($key);
        if ($value === false) {
            $env = function_exists('database_env_values') ? database_env_values(dirname(__DIR__, 2) . '/.env') : [];
            $value = $env[$key] ?? '';
        }

        return max(0, (int) $value);
    }
}
