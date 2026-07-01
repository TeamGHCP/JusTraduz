<?php

namespace App\Services {
    use PDO;
    use PDOException;
    use Exception;
    use RuntimeException;
    use stdClass;
    use Throwable;

class JobQueueService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function enqueue(string $type, array $payload, int $userId, int $maxAttempts = 3): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO job_queue (type, status, payload_json, user_id, attempts, max_attempts, available_at, created_at, updated_at)
             VALUES (?, "pending", ?, ?, 0, ?, ?, ?, ?)'
        );
        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            $type,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $userId,
            $maxAttempts,
            $now,
            $now,
            $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function reserveNext(): ?array
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $now = date('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM job_queue
                 WHERE status = "pending" AND available_at <= ?
                 ORDER BY priority DESC, available_at ASC, id ASC
                 LIMIT 1'
            );
            $stmt->execute([$now]);
            $job = $stmt->fetch();

            if (!$job) {
                $this->pdo->commit();
                return null;
            }

            $this->pdo->prepare('UPDATE job_queue SET status = "running", locked_at = ?, attempts = attempts + 1, updated_at = ? WHERE id = ?')
                ->execute([$now, $now, (int) $job['id']]);
            $this->pdo->commit();

            $job['attempts'] = (int) $job['attempts'] + 1;
            return $job;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function complete(int $jobId): void
    {
        $this->pdo->prepare('UPDATE job_queue SET status = "completed", last_error = NULL, updated_at = ? WHERE id = ?')
            ->execute([date('Y-m-d H:i:s'), $jobId]);
    }

    public function fail(array $job, string $error): void
    {
        $attempts = (int) ($job['attempts'] ?? 1);
        $maxAttempts = (int) ($job['max_attempts'] ?? 3);
        $status = $attempts >= $maxAttempts ? 'failed' : 'pending';
        $delay = min(3600, 60 * (2 ** max(0, $attempts - 1)));
        $availableAt = date('Y-m-d H:i:s', time() + $delay);

        $this->pdo->prepare('UPDATE job_queue SET status = ?, last_error = ?, available_at = ?, updated_at = ? WHERE id = ?')
            ->execute([$status, mb_substr($error, 0, 1000), $availableAt, date('Y-m-d H:i:s'), (int) $job['id']]);
    }

    public function pendingCountForEntity(string $type, string $entityKey, int $entityId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM job_queue
             WHERE type = ? AND status IN ("pending", "running")
             AND payload_json LIKE ?'
        );
        $stmt->execute([$type, '%"' . $entityKey . '":' . $entityId . '%']);
        return (int) $stmt->fetchColumn();
    }
}
}

namespace {
    if (!class_exists('JobQueueService')) {
        class_alias('App\Services\JobQueueService', 'JobQueueService');
    }
}
