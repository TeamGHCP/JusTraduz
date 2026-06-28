<?php

require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/services/PublicApiClientService.php';
require_once dirname(__DIR__) . '/services/SlaService.php';

class IntegrationController
{
    private Response $response;
    private PDO $pdo;
    private ?array $authorizedClient = null;
    private string $authorizationError = 'invalid_token';
    private int $retryAfter = 60;

    public function __construct()
    {
        $this->response = new Response();
        $this->pdo = database_connection();
    }

    public function health(): void
    {
        if (!$this->authorized('health:read')) {
            $this->unauthorizedResponse();
            return;
        }

        $this->auditAuthorized('health:read', '/api/v1/integrations/health', 200);
        $this->response->json([
            'status' => 'ok',
            'timestamp' => date(DATE_ATOM),
        ]);
    }

    public function reportsSummary(): void
    {
        if (!$this->authorized('reports:read')) {
            $this->unauthorizedResponse();
            return;
        }

        $cases = $this->fetchAll("SELECT id, titulo, status, prioridade, created_at, advogado_id FROM cases WHERE status <> 'finalizado'");
        $sla = ['overdue' => 0, 'due_soon' => 0, 'on_track' => 0];
        foreach ($cases as $case) {
            $state = SlaService::statusForCase($case)['state'] ?? 'on_track';
            if (isset($sla[$state])) {
                $sla[$state]++;
            }
        }

        $this->auditAuthorized('reports:read', '/api/v1/integrations/reports/summary', 200);
        $this->response->json([
            'generated_at' => date(DATE_ATOM),
            'users' => $this->count('SELECT COUNT(*) FROM users'),
            'documents' => $this->count('SELECT COUNT(*) FROM documents'),
            'cases_open' => count($cases),
            'sla' => $sla,
        ]);
    }

    private function authorized(string $scope): bool
    {
        $service = new PublicApiClientService($this->pdo);
        $client = $service->authenticate($scope);
        if ($client === null) {
            $this->authorizationError = 'invalid_token';
            return false;
        }

        $quota = $service->consumeRateLimit($client, $scope);
        if (!$quota['allowed']) {
            $this->authorizedClient = $client;
            $this->authorizationError = 'rate_limited';
            $this->retryAfter = (int) $quota['retry_after'];
            $this->auditAuthorized($scope, (string) ($_GET['rota'] ?? ''), 429);
            return false;
        }

        $this->authorizedClient = $client;
        $this->authorizationError = '';
        return true;
    }

    private function unauthorizedResponse(): void
    {
        if ($this->authorizationError === 'rate_limited') {
            header('Retry-After: ' . (string) $this->retryAfter);
            $this->response->json(['error' => 'rate_limited'], 429);
            return;
        }

        $this->response->json(['error' => 'invalid_token'], 401);
    }

    private function auditAuthorized(string $scope, string $endpoint, int $statusCode): void
    {
        if ($this->authorizedClient === null) {
            return;
        }

        (new PublicApiClientService($this->pdo))->auditRequest($this->authorizedClient, $scope, $endpoint, $statusCode);
    }

    private function count(string $sql): int
    {
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    private function fetchAll(string $sql): array
    {
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
