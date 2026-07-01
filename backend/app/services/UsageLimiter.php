<?php

namespace App\Services {
    use PDO;
    use PDOException;
    use Exception;
    use RuntimeException;
    use stdClass;
    use Throwable;

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

        $window = $this->usageWindow($userId);
        $used = $this->usedInWindow($userId, $feature, $window['start'], $window['end']);
        $allowed = ($used + $units) <= $limit;

        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'window_start' => $window['start'],
            'window_end' => $window['end'],
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
            substr($feature, 0, 80),
            max(1, $units),
            $entityId,
            $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            date('Y-m-d H:i:s'),
        ]);
    }

    public function limitMessage(string $feature, array $quota): string
    {
        $label = $this->featureLabel($feature);
        $limit = (int) ($quota['limit'] ?? 0);
        $used = (int) ($quota['used'] ?? 0);
        $windowEnd = trim((string) ($quota['window_end'] ?? ''));
        $renewal = $windowEnd !== '' ? ' A cota renova em ' . date('d/m/Y', strtotime($windowEnd)) . '.' : '';

        if ($limit <= 0) {
            return 'Limite do plano atingido para ' . $label . '.' . $renewal;
        }

        return 'Você atingiu o limite mensal do seu plano para ' . $label . ' (' . $used . '/' . $limit . ').' . $renewal . ' Para continuar agora, suba de plano.';
    }

    private function usedInWindow(int $userId, string $feature, string $start, string $end): int
    {
        $sql = 'SELECT COALESCE(SUM(units), 0) FROM usage_events WHERE user_id = ? AND feature = ? AND created_at >= ?';
        $params = [$userId, $feature, $start];
        if ($end !== '') {
            $sql .= ' AND created_at < ?';
            $params[] = $end;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function usageWindow(int $userId): array
    {
        if ($this->subscriptions !== null) {
            return $this->subscriptions->currentUsageWindow($userId);
        }

        return [
            'start' => date('Y-m-01 00:00:00'),
            'end' => date('Y-m-t 23:59:59'),
        ];
    }

    private function featureLabel(string $feature): string
    {
        return match ($feature) {
            'document_upload' => 'envio de documentos',
            'document_ai' => 'análise de documentos com IA',
            'ai_chat' => 'mensagens com IA Jurídica',
            'datajud_cnj' => 'consulta CNJ',
            'ocr' => 'OCR',
            'contract_analysis' => 'análise de contratos',
            'draft_generation' => 'geração de minutas',
            'info_extraction' => 'extração automática de informações',
            default => 'este recurso',
        };
    }

    private function limitFor(string $feature, int $userId): int
    {
        $map = [
            'document_upload' => 'USAGE_DAILY_DOCUMENT_UPLOADS',
            'document_ai' => 'USAGE_DAILY_DOCUMENT_AI',
            'ai_chat' => 'USAGE_DAILY_AI_CHAT',
            'datajud_cnj' => 'USAGE_DAILY_DATAJUD_CNJ',
            'ocr' => 'USAGE_DAILY_OCR',
            'contract_analysis' => 'USAGE_MONTHLY_CONTRACT_ANALYSIS',
            'draft_generation' => 'USAGE_MONTHLY_DRAFT_GENERATION',
            'info_extraction' => 'USAGE_MONTHLY_INFO_EXTRACTION',
        ];

        $key = $map[$feature] ?? '';
        if ($key === '') {
            return 0;
        }

        if ($this->subscriptions !== null) {
            if ($this->subscriptions->currentForUser($userId) === null) {
                $this->subscriptions->ensureDefaultProfessionalSubscription($userId);
            }

            $planLimit = method_exists($this->subscriptions, 'featureLimitValue')
                ? $this->subscriptions->featureLimitValue($userId, $feature)
                : $this->subscriptions->featureLimit($userId, $feature);
            if ($planLimit !== null) {
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
}

namespace {
    if (!class_exists('UsageLimiter')) {
        class_alias('App\Services\UsageLimiter', 'UsageLimiter');
    }
}
