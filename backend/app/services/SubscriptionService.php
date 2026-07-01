<?php

namespace App\Services {
    use PDO;
    use DateTimeImmutable;
    use Throwable;

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

            $planAudienceSelect = $this->plansHaveAudience() ? ', p.audience AS plan_audience' : ", NULL AS plan_audience";
            $stmt = $this->pdo->prepare(
                "SELECT s.*, p.name AS plan_name, p.slug AS plan_slug, p.monthly_price_cents, p.yearly_price_cents, p.limits_json, p.features_json{$planAudienceSelect}
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
            $stmt = $this->pdo->prepare("SELECT tipo FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            if ((string) ($stmt->fetchColumn() ?: '') !== 'cliente') {
                return null;
            }

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

            $stmt = $this->pdo->query(
                "SELECT id
                 FROM plans
                 WHERE slug IN ('gratuito', 'free')
                   AND active = 1
                 ORDER BY CASE slug
                     WHEN 'gratuito' THEN 1
                     WHEN 'free' THEN 2
                     ELSE 9
                 END
                 LIMIT 1"
            );
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

        public function ensureDefaultProfessionalSubscription(int $userId): ?array
        {
            if (!$this->userCanSubscribe($userId)) {
                return null;
            }

            if (!database_table_exists($this->pdo, 'plans') || !database_table_exists($this->pdo, 'subscriptions')) {
                return null;
            }

            $stmt = $this->pdo->prepare("SELECT tipo FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            if ((string) ($stmt->fetchColumn() ?: '') !== 'advogado') {
                return null;
            }

            $current = $this->currentForUser($userId);
            if ($current) {
                return $current;
            }

            $stmt = $this->pdo->query(
                "SELECT id
                 FROM plans
                 WHERE slug IN ('profissional_basico', 'advogado_basico')
                   AND active = 1
                 ORDER BY CASE slug
                     WHEN 'profissional_basico' THEN 1
                     WHEN 'advogado_basico' THEN 2
                     ELSE 9
                 END
                 LIMIT 1"
            );
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

        public function ensureDefaultForUser(int $userId): ?array
        {
            $stmt = $this->pdo->prepare("SELECT tipo FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $type = (string) ($stmt->fetchColumn() ?: '');

            return $type === 'advogado'
                ? $this->ensureDefaultProfessionalSubscription($userId)
                : $this->ensureDefaultSubscription($userId);
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
            $limit = $this->featureLimitValue($userId, $feature);
            return $limit === null ? 0 : $limit;
        }

        public function featureLimitValue(int $userId, string $feature): ?int
        {
            $subscription = $this->currentForUser($userId);
            if (!$subscription) {
                return null;
            }

            $limits = json_decode((string) ($subscription['limits_json'] ?? ''), true);
            if (!is_array($limits) || !array_key_exists($feature, $limits)) {
                return null;
            }

            return max(0, (int) $limits[$feature]);
        }

        public function currentUsageWindow(int $userId): array
        {
            $subscription = $this->currentForUser($userId);
            if ($subscription && !empty($subscription['current_period_start'])) {
                return [
                    'start' => (string) $subscription['current_period_start'],
                    'end' => (string) ($subscription['current_period_end'] ?? ''),
                ];
            }

            return [
                'start' => date('Y-m-01 00:00:00'),
                'end' => date('Y-m-t 23:59:59'),
            ];
        }

        public function renewCurrentPeriod(int $subscriptionId, ?DateTimeImmutable $paidAt = null): ?array
        {
            if ($subscriptionId <= 0 || !database_table_exists($this->pdo, 'subscriptions')) {
                return null;
            }

            $stmt = $this->pdo->prepare('SELECT * FROM subscriptions WHERE id = ? LIMIT 1');
            $stmt->execute([$subscriptionId]);
            $subscription = $stmt->fetch();
            if (!$subscription) {
                return null;
            }

            $billingCycle = (string) ($subscription['billing_cycle'] ?? 'monthly');
            if (!in_array($billingCycle, ['monthly', 'yearly'], true)) {
                $billingCycle = 'monthly';
            }

            $paidAt ??= new DateTimeImmutable();
            $currentEndValue = trim((string) ($subscription['current_period_end'] ?? ''));
            try {
                $currentEnd = $currentEndValue !== '' ? new DateTimeImmutable($currentEndValue) : $paidAt;
            } catch (Throwable) {
                $currentEnd = $paidAt;
            }

            $periodStart = $currentEnd > $paidAt ? $currentEnd : $paidAt;
            $periodEnd = $periodStart->modify($billingCycle === 'yearly' ? '+1 year' : '+1 month');

            $stmt = $this->pdo->prepare(
                "UPDATE subscriptions
                 SET status = 'active',
                     current_period_start = ?,
                     current_period_end = ?,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );
            $stmt->execute([
                $periodStart->format('Y-m-d H:i:s'),
                $periodEnd->format('Y-m-d H:i:s'),
                $subscriptionId,
            ]);

            $stmt = $this->pdo->prepare('SELECT * FROM subscriptions WHERE id = ? LIMIT 1');
            $stmt->execute([$subscriptionId]);
            $renewed = $stmt->fetch();

            return $renewed ?: null;
        }

        public function plans(?string $profile = null): array
        {
            if (!database_table_exists($this->pdo, 'plans')) {
                return [];
            }

            $sql = 'SELECT * FROM plans WHERE active = 1 AND (monthly_price_cents > 0 OR yearly_price_cents > 0)';
            $params = [];

            if ($profile !== null && $this->plansHaveAudience()) {
                $sql .= " AND audience IN (?, 'ambos')";
                $params[] = $this->audienceForProfile($profile);
            } elseif ($profile === 'advogado') {
                $sql .= " AND slug IN ('pro', 'escritorio')";
            } elseif ($profile === 'cliente') {
                $sql .= " AND slug NOT IN ('profissional_basico', 'advogado_basico')";
            }

            $sql .= ' ORDER BY sort_order ASC, monthly_price_cents ASC';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        }

        public function planAvailableForUser(int $userId, int $planId): bool
        {
            if ($planId <= 0 || !database_table_exists($this->pdo, 'plans')) {
                return false;
            }

            $stmt = $this->pdo->prepare('SELECT tipo FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $profile = (string) ($stmt->fetchColumn() ?: '');
            if (!in_array($profile, ['cliente', 'advogado'], true)) {
                return false;
            }

            foreach ($this->plans($profile) as $plan) {
                if ((int) ($plan['id'] ?? 0) === $planId) {
                    return true;
                }
            }

            return false;
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

            if (!$this->planAvailableForUser($userId, $planId)) {
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

        public function cancelCurrentForUser(int $userId): bool
        {
            if (!$this->userCanSubscribe($userId)) {
                return false;
            }

            if (!database_table_exists($this->pdo, 'subscriptions')) {
                return false;
            }

            $now = date('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare(
                "UPDATE subscriptions
                 SET status = 'canceled', canceled_at = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE user_id = ?
                   AND status IN ('trialing', 'active', 'past_due')"
            );
            $stmt->execute([$now, $userId]);

            return $stmt->rowCount() > 0;
        }

        public function userCanSubscribe(int $userId): bool
        {
            $stmt = $this->pdo->prepare("SELECT tipo, status, oab_verificado, oab_status, status_cna FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || (string) ($user['status'] ?? '') !== 'ativo') {
                return false;
            }

            $type = (string) ($user['tipo'] ?? '');
            if ($type === 'cliente') {
                return true;
            }

            if ($type !== 'advogado') {
                return false;
            }

            return (int) ($user['oab_verificado'] ?? 0) === 1
                || in_array((string) (($user['oab_status'] ?? '') ?: ($user['status_cna'] ?? '')), ['approved', 'verificado'], true);
        }

        private function plansHaveAudience(): bool
        {
            return database_table_exists($this->pdo, 'plans')
                && database_table_has_column($this->pdo, 'plans', 'audience');
        }

        private function audienceForProfile(string $profile): string
        {
            return $profile === 'advogado' ? 'advogado' : 'cliente';
        }
    }
}

namespace {
    if (!class_exists('SubscriptionService')) {
        class_alias('App\Services\SubscriptionService', 'SubscriptionService');
    }
}
