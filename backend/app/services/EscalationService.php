<?php

require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/OrganizationService.php';
require_once __DIR__ . '/SlaService.php';

class EscalationService
{
    private const ANTI_SPAM_HOURS = 12;

    public function __construct(private PDO $pdo)
    {
    }

    public function run(int $limit = 50): int
    {
        if (!OrganizationService::tableExists($this->pdo, 'case_escalations')) {
            return 0;
        }

        $cases = $this->fetchCases($limit);
        $notifications = new NotificationService($this->pdo);
        $processed = 0;

        foreach ($cases as $case) {
            $state = $this->stateForCase($case);
            if ($state === 'none' || $this->recentlyNotified((int) $case['id'], $state)) {
                continue;
            }

            $message = $this->messageForState($case, $state);
            $recipients = array_unique(array_merge(
                $notifications->activeAdmins(),
                (int) ($case['advogado_id'] ?? 0) > 0 ? [(int) $case['advogado_id']] : []
            ));

            $notifications->notifyMany($recipients, $message);
            $this->recordEscalation((int) $case['id'], $state, $message);
            $processed++;
        }

        return $processed;
    }

    private function fetchCases(int $limit): array
    {
        $limit = max(1, min(500, $limit));
        $sql = "SELECT id, titulo, status, prioridade, created_at, advogado_id
                FROM cases
                WHERE status <> 'finalizado'
                ORDER BY created_at ASC
                LIMIT {$limit}";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function stateForCase(array $case): string
    {
        if ((int) ($case['advogado_id'] ?? 0) <= 0) {
            return 'unassigned';
        }

        $sla = SlaService::statusForCase($case);
        return in_array($sla['state'] ?? '', ['due_soon', 'overdue'], true) ? (string) $sla['state'] : 'none';
    }

    private function recentlyNotified(int $caseId, string $state): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM case_escalations
             WHERE case_id = ? AND state = ? AND notified_at >= ?'
        );
        $stmt->execute([$caseId, $state, date('Y-m-d H:i:s', time() - self::ANTI_SPAM_HOURS * 3600)]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function messageForState(array $case, string $state): string
    {
        $title = (string) ($case['titulo'] ?? ('Caso #' . (int) $case['id']));
        return match ($state) {
            'overdue' => 'SLA vencido: ' . $title,
            'due_soon' => 'SLA proximo do vencimento: ' . $title,
            'unassigned' => 'Solicitacao sem responsavel operacional: ' . $title,
            default => 'Alerta operacional: ' . $title,
        };
    }

    private function recordEscalation(int $caseId, string $state, string $message): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('INSERT INTO case_escalations (case_id, state, notified_at, message) VALUES (?, ?, ?, ?)');
        $stmt->execute([$caseId, $state, $now, mb_substr($message, 0, 255)]);

        if (database_table_has_column($this->pdo, 'cases', 'escalation_status')) {
            $this->pdo->prepare('UPDATE cases SET escalation_status = ?, last_escalated_at = ? WHERE id = ?')
                ->execute([$state, $now, $caseId]);
        }
    }
}
