<?php

require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/services/PublicApiClientService.php';
require_once dirname(__DIR__) . '/services/SlaService.php';

class IntegrationController
{
    private Response $response;
    private PDO $pdo;

    public function __construct()
    {
        $this->response = new Response();
        $this->pdo = database_connection();
    }

    public function health(): void
    {
        if (!$this->authorized('health:read')) {
            $this->response->json(['error' => 'invalid_token'], 401);
            return;
        }

        $this->response->json([
            'status' => 'ok',
            'timestamp' => date(DATE_ATOM),
        ]);
    }

    public function reportsSummary(): void
    {
        if (!$this->authorized('reports:read')) {
            $this->response->json(['error' => 'invalid_token'], 401);
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
        return (new PublicApiClientService($this->pdo))->authenticate($scope) !== null;
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
