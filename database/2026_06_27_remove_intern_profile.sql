SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

UPDATE organization_members om
INNER JOIN users u ON u.id = om.user_id
SET om.status = 'suspended'
WHERE u.tipo = 'estagiario' AND om.status <> 'suspended';

UPDATE organization_invites oi
INNER JOIN users u ON LOWER(u.email) = LOWER(oi.email)
SET oi.status = 'revoked'
WHERE u.tipo = 'estagiario' AND oi.status = 'pending';

UPDATE schedule_slots s
INNER JOIN users u ON u.id = s.professional_id
SET s.status = 'bloqueado'
WHERE u.tipo = 'estagiario' AND s.status = 'livre';

UPDATE subscriptions s
INNER JOIN users u ON u.id = s.user_id
SET s.status = 'canceled', s.canceled_at = COALESCE(s.canceled_at, NOW())
WHERE u.tipo = 'estagiario' AND s.status IN ('trialing', 'active', 'past_due');

UPDATE users
SET status = 'inativo', updated_at = NOW()
WHERE tipo = 'estagiario' AND status <> 'inativo';

INSERT IGNORE INTO schema_migrations (version) VALUES ('2026_06_27_remove_intern_profile');
