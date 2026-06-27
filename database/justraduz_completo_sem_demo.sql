-- JusTraduz - instalador SQL consolidado.
-- Contem schema final e migrations incorporadas.
-- ATENCAO: este arquivo recria o banco justraduz do zero.
-- Nao execute em uma base com dados reais sem backup.
-- Migrations de onboarding, SLA e produto futuro incorporadas e registradas em schema_migrations.

DROP DATABASE IF EXISTS justraduz;
CREATE DATABASE IF NOT EXISTS justraduz
CHARSET utf8mb4
COLLATE utf8mb4_general_ci;

USE justraduz;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(120) PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(180) NOT NULL,
    tipo ENUM('empresa', 'escritorio') NOT NULL DEFAULT 'empresa',
    documento VARCHAR(32) NULL,
    status ENUM('ativo', 'inativo') NOT NULL DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_organizations_status (status),
    UNIQUE KEY uq_organizations_documento (documento)
) DEFAULT CHARSET=utf8mb4;

-- usuários
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NULL,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL COLLATE utf8mb4_general_ci,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('cliente', 'advogado', 'admin') NOT NULL,
    
    oab VARCHAR(20),
    oab_uf VARCHAR(10),
    oab_status VARCHAR(50),
    oab_rejection_reason TEXT NULL,
    oab_submitted_at DATETIME NULL,
    oab_validated_at DATETIME NULL,
    oab_validated_by INT NULL,
    oab_parametro TEXT,
    oab_verificado BOOLEAN DEFAULT FALSE,

    -- Campos auxiliares legados para validacao administrativa de OAB.
    oab_tipo VARCHAR(50),
    status_cna ENUM('pendente', 'verificado', 'invalido', 'nao_encontrado') DEFAULT 'pendente',
    cna_validado_em DATETIME NULL,
    cna_origem VARCHAR(50) NULL,
    cna_payload_cache TEXT NULL,
    cna_ultimo_erro TEXT NULL,
    cna_tentativas INT DEFAULT 0,
    
    telefone VARCHAR(25),
    cpf VARCHAR(14),
    foto_perfil VARCHAR(255),
    google_sub VARCHAR(255) UNIQUE,
    google_picture VARCHAR(255),
    google_linked_at DATETIME NULL,
    provider VARCHAR(30) NULL,
    profile_completed BOOLEAN DEFAULT TRUE,
    email_verified_at DATETIME NULL,
    deletion_requested_at DATETIME NULL,
    deletion_scheduled_at DATETIME NULL,
    status ENUM('ativo','inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_tipo_status (tipo, status),
    UNIQUE KEY uniq_users_cpf (cpf),
    INDEX idx_users_oab_status (oab_status),
    INDEX idx_users_organization (organization_id),
    INDEX idx_users_provider (provider),
    INDEX idx_users_google_sub (google_sub),
    INDEX idx_users_deletion_schedule (deletion_scheduled_at),
    CONSTRAINT fk_users_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    FOREIGN KEY (oab_validated_by) REFERENCES users(id) ON DELETE SET NULL
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    organization_id INT NOT NULL,
    papel ENUM('membro', 'gestor', 'suporte') NOT NULL DEFAULT 'membro',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_organization (user_id, organization_id),
    INDEX idx_user_organizations_org (organization_id),
    CONSTRAINT fk_user_organizations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_organizations_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS user_onboarding_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tour_key VARCHAR(80) NOT NULL,
    tour_version VARCHAR(30) NOT NULL,
    dashboard_profile VARCHAR(30) NOT NULL,
    status ENUM('pending', 'completed', 'skipped', 'remind_later') NOT NULL DEFAULT 'pending',
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    skipped_at DATETIME NULL,
    reminded_at DATETIME NULL,
    last_seen_step INT DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_tour_version (user_id, tour_key, tour_version),
    INDEX idx_user_onboarding_user (user_id),
    INDEX idx_user_onboarding_status (status),
    CONSTRAINT fk_user_onboarding_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4;

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

-- documentos
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    organization_id INT NULL,
    nome_arquivo VARCHAR(255),
    tipo_arquivo VARCHAR(20),
    caminho VARCHAR(255),
    texto_extraido LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    INDEX idx_documents_organization (organization_id, created_at)
) DEFAULT CHARSET=utf8mb4;

-- resultados da ia
CREATE TABLE IF NOT EXISTS ai_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    resumo LONGTEXT,             -- Mantido LONGTEXT (Excelente escolha para IA)
    explicacao LONGTEXT,         -- Mantido LONGTEXT
    confianca DECIMAL(5,2),
    modelo VARCHAR(80),
    prompt_versao VARCHAR(80),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4;

-- casos (solicitações de ajuda)
CREATE TABLE IF NOT EXISTS cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NULL,
    cliente_id INT NOT NULL,
    advogado_id INT NULL,
    document_id INT NULL,
    titulo VARCHAR(255),
    descricao TEXT,
    status ENUM('aberto', 'em_andamento', 'finalizado') DEFAULT 'aberto',
    prioridade ENUM('baixa', 'media', 'alta') DEFAULT 'media',
    sla_due_at DATETIME NULL,
    sla_status ENUM('ok', 'em_risco', 'vencido', 'sem_sla') DEFAULT 'sem_sla',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    FOREIGN KEY (cliente_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (advogado_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
    INDEX idx_cases_cliente_status (cliente_id, status),
    INDEX idx_cases_advogado_status (advogado_id, status),
    INDEX idx_cases_organization_status (organization_id, status),
    INDEX idx_cases_sla (sla_status, sla_due_at),
    INDEX idx_cases_document (document_id)
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

-- mensagens
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    sender_id INT NOT NULL,
    mensagem TEXT NOT NULL,
    attachment_original_name VARCHAR(255) NULL,
    attachment_path VARCHAR(255) NULL,
    attachment_mime VARCHAR(120) NULL,
    attachment_size INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4;

-- tarefas
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    titulo VARCHAR(255),
    descricao TEXT,
    status ENUM('pendente', 'em_andamento', 'concluida') DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4;

-- agenda de profissionais
CREATE TABLE IF NOT EXISTS schedule_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NULL,
    professional_id INT NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    status ENUM('livre', 'ocupado', 'bloqueado') DEFAULT 'livre',
    titulo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    FOREIGN KEY (professional_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_schedule_organization (organization_id, starts_at),
    INDEX idx_schedule_professional (professional_id, starts_at),
    INDEX idx_schedule_status (status, starts_at)
) DEFAULT CHARSET=utf8mb4;

-- agendamentos feitos por clientes
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NULL,
    slot_id INT NOT NULL,
    client_id INT NOT NULL,
    case_id INT NULL,
    assunto VARCHAR(255) NOT NULL,
    observacoes TEXT,
    status ENUM('agendado', 'cancelado', 'concluido') DEFAULT 'agendado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    FOREIGN KEY (slot_id) REFERENCES schedule_slots(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL,
    INDEX idx_appointments_client (client_id, created_at),
    INDEX idx_appointments_organization (organization_id, created_at),
    INDEX idx_appointments_status (status, created_at)
) DEFAULT CHARSET=utf8mb4;

-- notificações (CORRIGIDO: Sintaxe finalizada e fechamento correto)
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    mensagem TEXT,
    lida BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4;

-- auditoria do sistema
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(80),
    entity_id INT NULL,
    details JSON NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_action (action, created_at),
    INDEX idx_audit_user (user_id, created_at),
    INDEX idx_audit_entity (entity_type, entity_id)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_reset_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(100) NOT NULL COLLATE utf8mb4_general_ci,
    code_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_password_reset_email (email, expires_at),
    INDEX idx_password_reset_user (user_id, created_at)
) DEFAULT CHARSET=utf8mb4;

-- tabela de log OAB
CREATE TABLE IF NOT EXISTS cna_validacao_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profissional_id INT NOT NULL,
    admin_id INT NULL,
    acao VARCHAR(100) NOT NULL,
    status_anterior VARCHAR(50),
    status_novo VARCHAR(50),
    origem VARCHAR(50),
    mensagem TEXT,
    erro_resumido TEXT,
    justificativa TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profissional_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS external_processes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    owner_type ENUM('cliente', 'advogado') NOT NULL,
    source VARCHAR(40) NOT NULL DEFAULT 'datajud',
    query_type ENUM('cpf', 'oab', 'cnj') NOT NULL,
    query_value VARCHAR(40) NOT NULL,
    process_number VARCHAR(40) NOT NULL,
    tribunal VARCHAR(40) NULL,
    uf VARCHAR(10) NULL,
    comarca VARCHAR(120) NULL,
    tipo_processo VARCHAR(80) NULL,
    classe_processual VARCHAR(255) NULL,
    assunto VARCHAR(255) NULL,
    status_inferido VARCHAR(120) NULL,
    status_normalizado VARCHAR(120) NULL,
    link VARCHAR(500) NULL,
    data_ultima_atualizacao DATE NULL,
    data_andamento_mais_recente DATE NULL,
    payload_json LONGTEXT NULL,
    last_synced_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_external_process_user_query (user_id, source, query_type, query_value, process_number),
    INDEX idx_external_processes_user_status (user_id, status_normalizado),
    INDEX idx_external_processes_query (source, query_type, query_value)
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS job_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(80) NOT NULL,
    status ENUM('pending', 'running', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    payload_json LONGTEXT NOT NULL,
    user_id INT NULL,
    priority INT NOT NULL DEFAULT 0,
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 3,
    last_error TEXT NULL,
    available_at DATETIME NOT NULL,
    locked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_job_queue_status (status, available_at, priority),
    INDEX idx_job_queue_user (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usage_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    feature VARCHAR(80) NOT NULL,
    units INT NOT NULL DEFAULT 1,
    entity_id INT NULL,
    metadata_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usage_user_feature (user_id, feature, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mail_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient VARCHAR(190) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    transport VARCHAR(40) NOT NULL,
    status ENUM('sent', 'failed') NOT NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mail_logs_status (status, created_at),
    INDEX idx_mail_logs_recipient (recipient, created_at)
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
-- Registro das migrations ja incorporadas neste schema consolidado.
INSERT IGNORE INTO schema_migrations (version) VALUES
    ('migration_telefone'),
    ('migration_profile_photo'),
    ('migration_password_reset_codes'),
    ('migration_oab'),
    ('migration_ai_metadata'),
    ('migration_p1_operations'),
    ('migration_message_attachments'),
    ('migration_indexes_integrity'),
    ('migration_google_oauth'),
    ('migration_datajud_processes'),
    ('migration_case_document'),
    ('2026_06_11_create_user_onboarding_progress'),
    ('2026_06_13_google_oab_profile_fields'),
    ('2026_06_15_p2_saas'),
    ('2026_06_23_add_free_plan'),
    ('2026_06_24_plan_audience_professional'),
    ('2026_06_26_max_plans'),
    ('2026_06_27_remove_intern_profile');

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO plans (slug, audience, name, description, monthly_price_cents, yearly_price_cents, limits_json, features_json, sort_order) VALUES
    ('profissional_basico', 'advogado', 'Profissional básico', 'Plano inicial para advogados com OAB validada acompanharem a mesa profissional antes de contratar mais volume.', 0, 0,
     JSON_OBJECT('document_upload', 5, 'document_ai', 5, 'ai_chat', 50, 'datajud_cnj', 1, 'ocr', 5),
     JSON_ARRAY('5 documentos por mes', '5 analises com IA documental', '50 mensagens com IA Juridica', '1 consulta CNJ por mes', 'OCR basico para ate 5 arquivos'), 5),
    ('gratuito', 'cliente', 'Gratuito', 'Plano inicial liberado automaticamente apos a conclusao do onboarding.', 0, 0,
     JSON_OBJECT('document_upload', 5, 'document_ai', 5, 'ai_chat', 50, 'datajud_cnj', 1, 'ocr', 5),
     JSON_ARRAY('5 documentos por mes', '5 analises com IA', '50 mensagens com IA Juridica', '1 consulta CNJ por mes', 'OCR basico para ate 5 arquivos'), 1),
    ('essencial', 'cliente', 'Essencial', 'Ideal para cidadãos, estudantes e usuários ocasionais.', 1490, 14300,
     JSON_OBJECT('document_upload', 30, 'document_ai', 30, 'ai_chat', 300, 'datajud_cnj', 30, 'ocr', 30),
     JSON_ARRAY('Tradução de documentos jurídicos', 'IA Jurídica (Chat)', 'Consulta CNJ', 'Resumo automático de documentos', 'Histórico de documentos', 'Upload de PDF, DOCX e imagens', 'Até 30 documentos por mês'), 10),
    ('pro', 'ambos', 'Pro', 'Ideal para advogados autônomos e profissionais jurídicos.', 4990, 47900,
     JSON_OBJECT('document_upload', 500, 'document_ai', 500, 'ai_chat', 5000, 'datajud_cnj', 500, 'ocr', 500),
     JSON_ARRAY('Até 500 documentos por mês', 'Até 500 análises com IA documental', 'Até 5.000 mensagens com IA Jurídica', 'Até 500 consultas CNJ por mês', 'Até 500 processamentos OCR por mês', 'Histórico de documentos e faturas'), 20),
    ('max_cliente', 'cliente', 'Max', 'Mais volume para clientes que analisam e acompanham uma grande quantidade de documentos e processos.', 7990, 76700,
     JSON_OBJECT('document_upload', 2000, 'document_ai', 2000, 'ai_chat', 20000, 'datajud_cnj', 2000, 'ocr', 2000),
     JSON_ARRAY('Até 2.000 documentos por mês', 'Até 2.000 análises com IA documental', 'Até 20.000 mensagens com IA Jurídica', 'Até 2.000 consultas CNJ por mês', 'Até 2.000 processamentos OCR por mês', 'Histórico de documentos e faturas'), 25),
    ('max_advogado', 'advogado', 'Max', 'Alto volume individual para advogados que operam documentos, consultas e análises jurídicas em escala.', 8990, 86300,
     JSON_OBJECT('document_upload', 3000, 'document_ai', 3000, 'ai_chat', 30000, 'datajud_cnj', 3000, 'ocr', 3000),
     JSON_ARRAY('Até 3.000 documentos por mês', 'Até 3.000 análises com IA documental', 'Até 30.000 mensagens com IA Jurídica', 'Até 3.000 consultas CNJ por mês', 'Até 3.000 processamentos OCR por mês', 'Casos, tarefas e agenda profissional', 'Histórico de documentos e faturas'), 25),
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
SELECT 'Banco JusTraduz instalado sem dados demo.' AS resultado;
