<?php

namespace App\Services {
    use PDO;
    use PDOException;
    use Exception;
    use RuntimeException;
    use stdClass;
    use Throwable;

require_once __DIR__ . '/OrganizationService.php';
require_once __DIR__ . '/AuditService.php';

class PublicApiClientService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function authenticate(string $scope): ?array
    {
        if (!OrganizationService::tableExists($this->pdo, 'public_api_clients')) {
            return null;
        }

        $token = $this->bearerToken();
        if ($token === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM public_api_clients WHERE status = 'ativo'");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $client) {
            if (!password_verify($token, (string) $client['token_hash'])) {
                continue;
            }

            $scopes = array_filter(array_map('trim', explode(',', (string) ($client['scopes'] ?? ''))));
            if (!in_array($scope, $scopes, true)) {
                return null;
            }

            $this->pdo->prepare('UPDATE public_api_clients SET last_used_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([(int) $client['id']]);
            return $client;
        }

        return null;
    }

    public function create(string $name, array $scopes): array
    {
        if (!OrganizationService::tableExists($this->pdo, 'public_api_clients')) {
            throw new RuntimeException('Tabela public_api_clients ausente.');
        }

        $token = 'jt_' . bin2hex(random_bytes(24));
        $hash = password_hash($token, PASSWORD_DEFAULT);
        $scopeList = implode(',', array_values(array_unique(array_filter($scopes))));

        $stmt = $this->pdo->prepare('INSERT INTO public_api_clients (nome, token_hash, scopes, status) VALUES (?, ?, ?, "ativo")');
        $stmt->execute([$name, $hash, $scopeList !== '' ? $scopeList : 'health:read']);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'name' => $name,
            'token' => $token,
            'scopes' => $scopeList,
        ];
    }

    public function consumeRateLimit(array $client, string $scope): array
    {
        $limit = max(1, (int) (getenv('PUBLIC_API_RATE_LIMIT_PER_MINUTE') ?: 60));
        $window = time() - (time() % 60);
        $key = hash('sha256', implode('|', [
            (string) ($client['id'] ?? 'unknown'),
            $scope,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            (string) $window,
        ]));
        $path = $this->rateLimitDirectory() . DIRECTORY_SEPARATOR . 'public-api-' . $key . '.json';
        $data = ['window' => $window, 'count' => 0];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded) && (int) ($decoded['window'] ?? 0) === $window) {
                $data = $decoded;
            }
        }

        $data['count'] = (int) ($data['count'] ?? 0) + 1;
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);

        return [
            'allowed' => (int) $data['count'] <= $limit,
            'limit' => $limit,
            'remaining' => max(0, $limit - (int) $data['count']),
            'retry_after' => max(1, 60 - (time() - $window)),
        ];
    }

    public function auditRequest(array $client, string $scope, string $endpoint, int $statusCode): void
    {
        if (!OrganizationService::tableExists($this->pdo, 'audit_logs')) {
            return;
        }

        try {
            (new AuditService($this->pdo))->log('public_api.request', 'public_api_client', (int) ($client['id'] ?? 0), [
                'client_name' => (string) ($client['nome'] ?? ''),
                'scope' => $scope,
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
            ]);
        } catch (Throwable $exception) {
            error_log('Public API audit failed: ' . $exception->getMessage());
        }
    }

    private function bearerToken(): string
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function rateLimitDirectory(): string
    {
        $directory = (string) (getenv('RATE_LIMIT_STORAGE_PATH') ?: dirname(__DIR__, 2) . '/storage/rate-limits');
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory;
    }
}
}

namespace {
    if (!class_exists('PublicApiClientService')) {
        class_alias('App\Services\PublicApiClientService', 'PublicApiClientService');
    }
}
