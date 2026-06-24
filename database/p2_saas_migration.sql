-- JusTraduz P2 SaaS - migration incremental.
-- Rode em uma base existente `justraduz` depois de backup.

USE justraduz;

CREATE TABLE IF NOT EXISTS organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    owner_user_id INT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_organizations_status (status, created_at)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS organization_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'admin', 'member', 'viewer') NOT NULL DEFAULT 'member',
    status ENUM('invited', 'active', 'suspended') NOT NULL DEFAULT 'active',
    invited_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_organization_user (organization_id, user_id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_organization_members_user (user_id, status)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS organization_invites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    email VARCHAR(190) NOT NULL,
    role ENUM('admin', 'member', 'viewer') NOT NULL DEFAULT 'member',
    token_hash VARCHAR(255) NOT NULL,
    status ENUM('pending', 'accepted', 'revoked', 'expired') NOT NULL DEFAULT 'pending',
    invited_by INT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    accepted_at DATETIME NULL,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_organization_invites_email (email, status)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    audience ENUM('cliente', 'advogado', 'ambos') NOT NULL DEFAULT 'cliente',
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    monthly_price_cents INT NOT NULL DEFAULT 0,
    yearly_price_cents INT NOT NULL DEFAULT 0,
    limits_json JSON NOT NULL,
    features_json JSON NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4;

ALTER TABLE plans
    ADD COLUMN IF NOT EXISTS audience ENUM('cliente', 'advogado', 'ambos') NOT NULL DEFAULT 'cliente' AFTER slug;

CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    organization_id INT NULL,
    plan_id INT NOT NULL,
    billing_cycle ENUM('monthly', 'yearly') NOT NULL DEFAULT 'monthly',
    status ENUM('trialing', 'active', 'past_due', 'canceled', 'expired') NOT NULL DEFAULT 'active',
    provider VARCHAR(60) NOT NULL DEFAULT 'manual',
    provider_subscription_id VARCHAR(190) NULL,
    current_period_start DATETIME NULL,
    current_period_end DATETIME NULL,
    canceled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    FOREIGN KEY (plan_id) REFERENCES plans(id),
    INDEX idx_subscriptions_user_status (user_id, status),
    INDEX idx_subscriptions_org_status (organization_id, status),
    INDEX idx_subscriptions_provider (provider, provider_subscription_id)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscription_id INT NULL,
    user_id INT NULL,
    provider VARCHAR(60) NOT NULL DEFAULT 'manual',
    provider_event_id VARCHAR(190) NULL,
    event_type VARCHAR(120) NOT NULL,
    amount_cents INT NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'BRL',
    status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    payload_json JSON NULL,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_payment_events_status (status, created_at),
    INDEX idx_payment_events_provider (provider, provider_event_id)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission_key VARCHAR(120) NOT NULL,
    allowed BOOLEAN NOT NULL DEFAULT TRUE,
    granted_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_permission (user_id, permission_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
) DEFAULT CHARSET=utf8mb4;

ALTER TABLE documents ADD COLUMN IF NOT EXISTS organization_id INT NULL AFTER user_id;
ALTER TABLE cases ADD COLUMN IF NOT EXISTS organization_id INT NULL AFTER id;
ALTER TABLE cases ADD COLUMN IF NOT EXISTS sla_due_at DATETIME NULL AFTER prioridade;
ALTER TABLE cases ADD COLUMN IF NOT EXISTS sla_status ENUM('ok', 'em_risco', 'vencido', 'sem_sla') DEFAULT 'sem_sla' AFTER sla_due_at;
ALTER TABLE schedule_slots ADD COLUMN IF NOT EXISTS organization_id INT NULL AFTER id;
ALTER TABLE appointments ADD COLUMN IF NOT EXISTS organization_id INT NULL AFTER id;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO plans (slug, audience, name, description, monthly_price_cents, yearly_price_cents, limits_json, features_json, sort_order) VALUES
    ('profissional_basico', 'advogado', 'Profissional básico', 'Plano inicial para advogados com OAB validada acompanharem a mesa profissional antes de contratar mais volume.', 0, 0,
     JSON_OBJECT('document_upload', 5, 'document_ai', 5, 'ai_chat', 50, 'datajud_cnj', 1, 'ocr', 5),
     JSON_ARRAY('5 documentos por mes', '5 analises com IA documental', '50 mensagens com IA Juridica', '1 consulta CNJ por mes', 'OCR basico para ate 5 arquivos'), 5),
    ('essencial', 'cliente', 'Essencial', 'Ideal para cidadãos, estudantes e usuários ocasionais.', 1490, 14300,
     JSON_OBJECT('document_upload', 30, 'document_ai', 30, 'ai_chat', 300, 'datajud_cnj', 30, 'ocr', 30),
     JSON_ARRAY('Tradução de documentos jurídicos', 'IA Jurídica (Chat)', 'Consulta CNJ', 'Resumo automático de documentos', 'Histórico de documentos', 'Upload de PDF, DOCX e imagens', 'Até 30 documentos por mês'), 10),
    ('pro', 'ambos', 'Pro', 'Ideal para advogados autônomos e profissionais jurídicos.', 4990, 47900,
     JSON_OBJECT('document_upload', 500, 'document_ai', 500, 'ai_chat', 5000, 'datajud_cnj', 500, 'ocr', 500),
     JSON_ARRAY('Até 500 documentos por mês', 'Até 500 análises com IA documental', 'Até 5.000 mensagens com IA Jurídica', 'Até 500 consultas CNJ por mês', 'Até 500 processamentos OCR por mês', 'Histórico de documentos e faturas'), 20),
    ('escritorio', 'advogado', 'Escritório', 'Ideal para escritórios e equipes jurídicas.', 9990, 95900,
     JSON_OBJECT('document_upload', 0, 'document_ai', 0, 'ai_chat', 10000, 'datajud_cnj', 1000, 'ocr', 0),
     JSON_ARRAY('Documentos, OCR e IA documental ilimitados', 'Até 10.000 mensagens com IA Jurídica', 'Até 1.000 consultas CNJ por mês', 'Compartilhamento por organização', 'Agenda, casos e tarefas por equipe', 'Histórico de documentos e faturas'), 30)
ON DUPLICATE KEY UPDATE
    audience = VALUES(audience),
    name = VALUES(name),
    description = VALUES(description),
    monthly_price_cents = VALUES(monthly_price_cents),
    yearly_price_cents = VALUES(yearly_price_cents),
    limits_json = VALUES(limits_json),
    features_json = VALUES(features_json),
    sort_order = VALUES(sort_order);

INSERT IGNORE INTO schema_migrations (version) VALUES ('2026_06_15_p2_saas');
INSERT IGNORE INTO schema_migrations (version) VALUES ('2026_06_24_plan_audience_professional');
