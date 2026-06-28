CREATE TABLE IF NOT EXISTS role_permission_overrides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(40) NOT NULL,
    permission VARCHAR(100) NOT NULL,
    effect ENUM('allow', 'deny') NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_role_permission_override (role_name, permission),
    INDEX idx_role_permission_role (role_name),
    CONSTRAINT fk_role_permission_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) DEFAULT CHARSET=utf8mb4;

ALTER TABLE organizations
    ADD COLUMN IF NOT EXISTS nome VARCHAR(180) NULL AFTER id,
    ADD COLUMN IF NOT EXISTS tipo ENUM('empresa', 'escritorio') NOT NULL DEFAULT 'empresa' AFTER nome,
    ADD COLUMN IF NOT EXISTS documento VARCHAR(32) NULL AFTER tipo;

UPDATE organizations
SET nome = COALESCE(NULLIF(nome, ''), name)
WHERE nome IS NULL OR nome = '';

ALTER TABLE organizations
    MODIFY status VARCHAR(20) NOT NULL DEFAULT 'ativo';

UPDATE organizations
SET status = CASE status
    WHEN 'active' THEN 'ativo'
    WHEN 'inactive' THEN 'inativo'
    ELSE status
END;

ALTER TABLE organizations
    MODIFY nome VARCHAR(180) NOT NULL,
    MODIFY status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo';

CREATE TABLE IF NOT EXISTS user_organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    organization_id INT NOT NULL,
    papel ENUM('dono', 'admin', 'membro') NOT NULL DEFAULT 'membro',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_organization (user_id, organization_id),
    INDEX idx_user_organizations_org (organization_id),
    CONSTRAINT fk_user_organizations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_organizations_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS case_escalations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    state ENUM('due_soon', 'overdue', 'unassigned') NOT NULL,
    notified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    message VARCHAR(255) NOT NULL,
    UNIQUE KEY uq_case_escalation_window (case_id, state, notified_at),
    INDEX idx_case_escalations_case (case_id),
    CONSTRAINT fk_case_escalations_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS public_api_clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(180) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    scopes VARCHAR(255) NOT NULL DEFAULT 'health:read,reports:read',
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_public_api_client_name (nome),
    INDEX idx_public_api_clients_status (status)
) DEFAULT CHARSET=utf8mb4;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS deletion_requested_at DATETIME NULL AFTER email_verified_at,
    ADD COLUMN IF NOT EXISTS deletion_scheduled_at DATETIME NULL AFTER deletion_requested_at;

CREATE INDEX IF NOT EXISTS idx_users_deletion_schedule ON users (deletion_scheduled_at);

INSERT IGNORE INTO schema_migrations (version) VALUES ('2026_06_26_permissions_privacy_schema');
