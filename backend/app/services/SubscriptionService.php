<?php

class SubscriptionService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function currentForUser(int $userId): ?array
    {
        if (!database_table_exists($this->pdo, 'subscriptions')) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT s.*, p.name AS plan_name, p.slug AS plan_slug, p.limits_json, p.features_json
             FROM subscriptions s
             INNER JOIN plans p ON p.id = s.plan_id
             WHERE s.user_id = ?
               AND s.status IN ('trialing', 'active', 'past_due')
             ORDER BY CASE s.status WHEN 'active' THEN 1 WHEN 'trialing' THEN 2 WHEN 'past_due' THEN 3 ELSE 9 END, s.created_at DESC
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $subscription = $stmt->fetch();

        return $subscription ?: null;
    }

    public function ensureDefaultSubscription(int $userId): ?array
    {
        if (!$this->userCanSubscribe($userId)) {
            return null;
        }

        if (!database_table_exists($this->pdo, 'plans') || !database_table_exists($this->pdo, 'subscriptions')) {
            return null;
        }

        $current = $this->currentForUser($userId);
        if ($current) {
            return $current;
        }

        $stmt = $this->pdo->query("SELECT id FROM plans WHERE slug = 'essencial' AND active = 1 LIMIT 1");
        $planId = (int) ($stmt->fetchColumn() ?: 0);
        if ($planId <= 0) {
            return null;
        }

        $now = new DateTimeImmutable();
        $stmt = $this->pdo->prepare(
            "INSERT INTO subscriptions (user_id, plan_id, billing_cycle, status, current_period_start, current_period_end)
             VALUES (?, ?, 'monthly', 'active', ?, ?)"
        );
        $stmt->execute([$userId, $planId, $now->format('Y-m-d H:i:s'), $now->modify('+1 month')->format('Y-m-d H:i:s')]);

        return $this->currentForUser($userId);
    }

    public function isBlocked(int $userId): bool
    {
        $subscription = $this->currentForUser($userId);
        if (!$subscription) {
            return false;
        }

        return in_array((string) $subscription['status'], ['past_due', 'canceled', 'expired'], true);
    }

    public function featureLimit(int $userId, string $feature): int
    {
        $subscription = $this->currentForUser($userId);
        if (!$subscription) {
            return 0;
        }

        $limits = json_decode((string) ($subscription['limits_json'] ?? ''), true);
        if (!is_array($limits) || !array_key_exists($feature, $limits)) {
            return 0;
        }

        return max(0, (int) $limits[$feature]);
    }

    public function plans(): array
    {
        if (!database_table_exists($this->pdo, 'plans')) {
            return [];
        }

        $stmt = $this->pdo->query('SELECT * FROM plans WHERE active = 1 ORDER BY sort_order ASC, monthly_price_cents ASC');
        return $stmt->fetchAll();
    }

    public function changePlan(int $userId, int $planId, string $billingCycle = 'monthly', string $status = 'active'): bool
    {
        if (!$this->userCanSubscribe($userId)) {
            return false;
        }

        if (!database_table_exists($this->pdo, 'plans') || !database_table_exists($this->pdo, 'subscriptions')) {
            return false;
        }

        if (!in_array($billingCycle, ['monthly', 'yearly'], true)) {
            $billingCycle = 'monthly';
        }

        if (!in_array($status, ['trialing', 'active', 'past_due', 'canceled'], true)) {
            $status = 'active';
        }

        $stmt = $this->pdo->prepare('SELECT id FROM plans WHERE id = ? AND active = 1');
        $stmt->execute([$planId]);
        if (!$stmt->fetch()) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $now = new DateTimeImmutable();
            $periodEnd = $now->modify($billingCycle === 'yearly' ? '+1 year' : '+1 month');
            $this->pdo->prepare("UPDATE subscriptions SET status = 'canceled', canceled_at = ? WHERE user_id = ? AND status IN ('trialing', 'active', 'past_due')")
                ->execute([$now->format('Y-m-d H:i:s'), $userId]);

            $stmt = $this->pdo->prepare(
                "INSERT INTO subscriptions (user_id, plan_id, billing_cycle, status, current_period_start, current_period_end)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$userId, $planId, $billingCycle, $status, $now->format('Y-m-d H:i:s'), $periodEnd->format('Y-m-d H:i:s')]);
            $this->pdo->commit();
            return true;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function userCanSubscribe(int $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT tipo, status FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        return $user
            && (string) ($user['tipo'] ?? '') === 'cliente'
            && (string) ($user['status'] ?? '') === 'ativo';
    }
}
