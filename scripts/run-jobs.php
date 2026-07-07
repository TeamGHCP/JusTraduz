<?php

require_once dirname(__DIR__) . '/backend/app/config/app.php';
require_once dirname(__DIR__) . '/backend/app/support/security.php';
require_once dirname(__DIR__) . '/backend/app/support/session.php';
require_once dirname(__DIR__) . '/backend/app/support/autoload.php';
require_once dirname(__DIR__) . '/backend/app/config/database.php';
require_once dirname(__DIR__) . '/backend/app/services/JobQueueService.php';
require_once dirname(__DIR__) . '/backend/app/controllers/DocumentController.php';
require_once dirname(__DIR__) . '/backend/app/controllers/PrivacyController.php';
require_once dirname(__DIR__) . '/backend/app/services/DataJudService.php';
require_once dirname(__DIR__) . '/backend/app/services/EscalationService.php';

$limit = 10;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--limit=')) {
        $limit = max(1, (int) substr($argument, 8));
    }
}

$pdo = database_connection();
$queue = new JobQueueService($pdo);
$processed = 0;

while ($processed < $limit) {
    $job = $queue->reserveNext();
    if (!$job) {
        break;
    }

    try {
        $payload = json_decode((string) $job['payload_json'], true);
        if (!is_array($payload)) {
            throw new RuntimeException('Payload inválido.');
        }

        $ok = match ((string) $job['type']) {
            'document_analysis' => (new DocumentController())->processQueuedAnalysis((int) ($payload['document_id'] ?? 0)),
            'datajud_cnj_sync' => process_datajud_job($pdo, $payload),
            'operational_escalation' => (new EscalationService($pdo))->run((int) ($payload['limit'] ?? 50)) >= 0,
            default => throw new RuntimeException('Tipo de job desconhecido: ' . (string) $job['type']),
        };

        if (!$ok) {
            throw new RuntimeException('Job não concluiu com sucesso.');
        }

        $queue->complete((int) $job['id']);
        $processed++;
    } catch (Throwable $exception) {
        $queue->fail($job, $exception->getMessage());
        $processed++;
    }
}

echo "Jobs processados: {$processed}\n";

if (in_array('--escalate', $argv, true)) {
    $escalated = (new EscalationService($pdo))->run($limit);
    echo "Escalonamentos gerados: {$escalated}\n";
}

if (in_array('--finalize-deletions', $argv, true)) {
    $finalized = (new PrivacyController())->finalizeExpiredDeletions($limit);
    echo "Exclusoes definitivas processadas: {$finalized}\n";
}

function process_datajud_job(PDO $pdo, array $payload): bool
{
    $userId = (int) ($payload['user_id'] ?? 0);
    $cpf = (string) ($payload['cpf'] ?? '');
    $processNumber = (string) ($payload['process_number'] ?? '');
    if ($userId <= 0 || $cpf === '' || $processNumber === '') {
        return false;
    }

    $result = (new DataJudService($pdo))->syncProcessByCnj($userId, $cpf, $processNumber, true);
    return (bool) ($result['success'] ?? false);
}
