-- Migration revisavel para SLA, prioridade operacional e escalonamento.
-- Nao execute em producao sem backup e janela de rollback.

ALTER TABLE cases
    MODIFY prioridade ENUM('baixa', 'media', 'normal', 'alta', 'urgente') DEFAULT 'media',
    ADD COLUMN sla_deadline_at DATETIME NULL AFTER prioridade,
    ADD COLUMN escalated_at DATETIME NULL AFTER sla_deadline_at,
    ADD COLUMN assigned_to INT NULL AFTER escalated_at,
    ADD INDEX idx_cases_sla_status_deadline (status, sla_deadline_at),
    ADD INDEX idx_cases_assigned_to (assigned_to),
    ADD CONSTRAINT fk_cases_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL;

UPDATE cases
SET sla_deadline_at = CASE prioridade
    WHEN 'baixa' THEN DATE_ADD(created_at, INTERVAL 72 HOUR)
    WHEN 'alta' THEN DATE_ADD(created_at, INTERVAL 24 HOUR)
    WHEN 'urgente' THEN DATE_ADD(created_at, INTERVAL 4 HOUR)
    ELSE DATE_ADD(created_at, INTERVAL 48 HOUR)
END
WHERE sla_deadline_at IS NULL;

-- Rollback manual sugerido:
-- ALTER TABLE cases
--     DROP FOREIGN KEY fk_cases_assigned_to,
--     DROP INDEX idx_cases_sla_status_deadline,
--     DROP INDEX idx_cases_assigned_to,
--     DROP COLUMN assigned_to,
--     DROP COLUMN escalated_at,
--     DROP COLUMN sla_deadline_at,
--     MODIFY prioridade ENUM('baixa', 'media', 'alta') DEFAULT 'media';
