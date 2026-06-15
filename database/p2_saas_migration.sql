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

INSERT INTO plans (slug, name, description, monthly_price_cents, yearly_price_cents, limits_json, features_json, sort_order) VALUES
    ('essencial', 'Essencial', 'Para começar com documentos e IA jurídica em baixo volume.', 2900, 27900,
     JSON_OBJECT('document_upload', 10, 'document_ai', 10, 'ai_chat', 30, 'datajud_cnj', 20, 'ocr', 5),
     JSON_ARRAY('Documentos essenciais', 'IA jurídica em linguagem simples', 'Consulta CNJ'), 10),
    ('pro', 'Pro', 'Para usuários recorrentes e equipes pequenas.', 7900, 75900,
     JSON_OBJECT('document_upload', 60, 'document_ai', 60, 'ai_chat', 200, 'datajud_cnj', 100, 'ocr', 40),
     JSON_ARRAY('OCR ampliado', 'Atendimento prioritário', 'Agenda e tarefas'), 20),
    ('escritorio', 'Escritório', 'Para operação jurídica com governança, auditoria e escala.', 19900, 191000,
     JSON_OBJECT('document_upload', 300, 'document_ai', 300, 'ai_chat', 1000, 'datajud_cnj', 500, 'ocr', 200),
     JSON_ARRAY('Multiempresa', 'Relatórios gerenciais', 'SLA e auditoria'), 30)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    monthly_price_cents = VALUES(monthly_price_cents),
    yearly_price_cents = VALUES(yearly_price_cents),
    limits_json = VALUES(limits_json),
    features_json = VALUES(features_json),
    sort_order = VALUES(sort_order);

INSERT IGNORE INTO schema_migrations (version) VALUES ('2026_06_15_p2_saas');
