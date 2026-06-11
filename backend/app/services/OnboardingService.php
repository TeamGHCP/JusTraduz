<?php

class OnboardingService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function state(int $userId, string $tourKey, string $tourVersion): array
    {
        $row = $this->find($userId, $tourKey, $tourVersion);
        $status = (string) ($row['status'] ?? 'pending');

        return [
            'should_start' => $row === null || in_array($status, ['pending', 'remind_later'], true),
            'status' => $status,
            'last_seen_step' => (int) ($row['last_seen_step'] ?? 0),
            'storage_available' => $this->tableExists(),
        ];
    }

    public function start(
        int $userId,
        string $tourKey,
        string $tourVersion,
        string $profile,
        int $lastSeenStep,
        bool $manual
    ): void {
        if (!$this->tableExists()) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_onboarding_progress
                (user_id, tour_key, tour_version, dashboard_profile, status, started_at, last_seen_step)
             VALUES (?, ?, ?, ?, "pending", NOW(), ?)
             ON DUPLICATE KEY UPDATE
                dashboard_profile = VALUES(dashboard_profile),
                status = IF(status IN ("completed", "skipped", "remind_later"), status, VALUES(status)),
                started_at = NOW(),
                last_seen_step = GREATEST(last_seen_step, VALUES(last_seen_step))'
        );
        $stmt->execute([$userId, $tourKey, $tourVersion, $profile, $lastSeenStep]);
    }

    public function complete(
        int $userId,
        string $tourKey,
        string $tourVersion,
        string $profile,
        int $lastSeenStep
    ): void {
        if (!$this->tableExists()) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_onboarding_progress
                (user_id, tour_key, tour_version, dashboard_profile, status, started_at, completed_at, last_seen_step)
             VALUES (?, ?, ?, ?, "completed", NOW(), NOW(), ?)
             ON DUPLICATE KEY UPDATE
                dashboard_profile = VALUES(dashboard_profile),
                status = "completed",
                completed_at = NOW(),
                skipped_at = NULL,
                reminded_at = NULL,
                last_seen_step = VALUES(last_seen_step)'
        );
        $stmt->execute([$userId, $tourKey, $tourVersion, $profile, $lastSeenStep]);
    }

    public function skip(
        int $userId,
        string $tourKey,
        string $tourVersion,
        string $profile,
        string $mode,
        int $lastSeenStep,
        bool $manual
    ): void {
        if (!$this->tableExists()) {
            return;
        }

        $existing = $this->find($userId, $tourKey, $tourVersion);
        if ($manual && in_array((string) ($existing['status'] ?? ''), ['completed', 'skipped'], true)) {
            return;
        }

        $status = $mode === 'remind_later' ? 'remind_later' : 'skipped';
        $timeColumn = $mode === 'remind_later' ? 'reminded_at' : 'skipped_at';
        $sql = 'INSERT INTO user_onboarding_progress
                    (user_id, tour_key, tour_version, dashboard_profile, status, started_at, ' . $timeColumn . ', last_seen_step)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)
                ON DUPLICATE KEY UPDATE
                    dashboard_profile = VALUES(dashboard_profile),
                    status = VALUES(status),
                    ' . $timeColumn . ' = NOW(),
                    last_seen_step = VALUES(last_seen_step)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $tourKey, $tourVersion, $profile, $status, $lastSeenStep]);
    }

    public function reset(int $userId, string $tourKey, string $tourVersion): void
    {
        if (!$this->tableExists()) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'DELETE FROM user_onboarding_progress
             WHERE user_id = ? AND tour_key = ? AND tour_version = ?'
        );
        $stmt->execute([$userId, $tourKey, $tourVersion]);
    }

    private function find(int $userId, string $tourKey, string $tourVersion): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT status, last_seen_step
             FROM user_onboarding_progress
             WHERE user_id = ? AND tour_key = ? AND tour_version = ?
             LIMIT 1'
        );
        $stmt->execute([$userId, $tourKey, $tourVersion]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function tableExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'user_onboarding_progress'");
            $exists = (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('Onboarding storage check failed: ' . $e->getMessage());
            $exists = false;
        }

        return $exists;
    }
}
