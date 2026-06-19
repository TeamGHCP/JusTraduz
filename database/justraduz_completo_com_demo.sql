-- JusTraduz - instalador SQL consolidado.
-- Contem schema final, migrations incorporadas e dados de apresentacao.
-- ATENCAO: este arquivo recria o banco justraduz do zero.
-- Nao execute em uma base com dados reais sem backup.
-- Migration de onboarding/tour incorporada e registrada em schema_migrations.

DROP DATABASE IF EXISTS justraduz;
CREATE DATABASE IF NOT EXISTS justraduz
CHARSET utf8mb4
COLLATE utf8mb4_general_ci;

USE justraduz;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(120) PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) DEFAULT CHARSET=utf8mb4;

-- usuários
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL COLLATE utf8mb4_general_ci,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('cliente', 'advogado', 'estagiario', 'admin') NOT NULL,
    
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
    status ENUM('ativo','inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_tipo_status (tipo, status),
    UNIQUE KEY uniq_users_cpf (cpf),
    INDEX idx_users_oab_status (oab_status),
    INDEX idx_users_provider (provider),
    INDEX idx_users_google_sub (google_sub),
    FOREIGN KEY (oab_validated_by) REFERENCES users(id) ON DELETE SET NULL
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
    owner_type ENUM('cliente', 'advogado', 'estagiario') NOT NULL,
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
-- Registro das migrations ja incorporadas neste schema consolidado.
INSERT IGNORE INTO schema_migrations (version) VALUES
    ('migration_telefone'),
    ('migration_profile_photo'),
    ('migration_password_reset_codes'),
    ('migration_oab'),
    ('migration_ai_metadata'),
    ('migration_message_attachments'),
    ('migration_indexes_integrity'),
    ('migration_google_oauth'),
    ('migration_datajud_processes'),
    ('migration_case_document'),
    ('migration_p1_operations'),
    ('2026_06_11_create_user_onboarding_progress'),
    ('2026_06_13_google_oab_profile_fields'),
    ('2026_06_15_p2_saas');

INSERT INTO plans (slug, name, description, monthly_price_cents, yearly_price_cents, limits_json, features_json, sort_order) VALUES
    ('essencial', 'Essencial', 'Para começar com documentos e IA jurídica em baixo volume.', 1500, 14400,
     JSON_OBJECT('document_upload', 10, 'document_ai', 10, 'ai_chat', 30, 'datajud_cnj', 20, 'ocr', 5),
     JSON_ARRAY('Documentos essenciais', 'IA jurídica em linguagem simples', 'Consulta CNJ'), 10),
    ('pro', 'Pro', 'Para usuários recorrentes e equipes pequenas.', 5000, 48000,
     JSON_OBJECT('document_upload', 60, 'document_ai', 60, 'ai_chat', 200, 'datajud_cnj', 100, 'ocr', 40),
     JSON_ARRAY('OCR ampliado', 'Atendimento prioritário', 'Agenda e tarefas'), 20),
    ('escritorio', 'Escritório', 'Para operação jurídica com governança, auditoria e escala.', 10000, 96000,
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

-- Dados de apresentacao.
USE justraduz;

-- Seed demo resetavel do JusTraduz.
-- Reexecutar este arquivo recria o banco justraduz e os dados de apresentacao.
-- Senha das contas @justraduz.demo: Demo@2026!
-- Tambem inclui o admin local pietro@tamanini.dev.br com hash proprio.
-- Nao use estas credenciais em producao.

SET @demo_password_hash = '$2y$10$hRRLMod1YrVw5/JlfV2Oh.FRaeW0iADWX4ioRcoRYy3OzrSvarWI.';
SET @pietro_password_hash = '$2y$10$El3sFk2bU3wRf18SlGfdBuTg/kqd6VcxuD/yiZ5hO0XvVqW9f8Bca';

DROP TEMPORARY TABLE IF EXISTS tmp_demo_users;
CREATE TEMPORARY TABLE tmp_demo_users (id INT PRIMARY KEY);
INSERT INTO tmp_demo_users
SELECT id FROM users
WHERE email IN (
    'admin@justraduz.demo',
    'cliente@justraduz.demo',
    'cliente2@justraduz.demo',
    'advogado@justraduz.demo',
    'estagiario@justraduz.demo',
    'pendente@justraduz.demo'
);

DROP TEMPORARY TABLE IF EXISTS tmp_demo_cases;
CREATE TEMPORARY TABLE tmp_demo_cases (id INT PRIMARY KEY);
INSERT INTO tmp_demo_cases
SELECT id FROM cases
WHERE EXISTS (
    SELECT 1 FROM tmp_demo_users
    WHERE tmp_demo_users.id = cases.cliente_id
       OR tmp_demo_users.id = cases.advogado_id
);

DROP TEMPORARY TABLE IF EXISTS tmp_demo_documents;
CREATE TEMPORARY TABLE tmp_demo_documents (id INT PRIMARY KEY);
INSERT INTO tmp_demo_documents
SELECT id FROM documents
WHERE user_id IN (SELECT id FROM tmp_demo_users)
   OR caminho LIKE 'backend/storage/documents/demo/%';

DROP TEMPORARY TABLE IF EXISTS tmp_demo_slots;
CREATE TEMPORARY TABLE tmp_demo_slots (id INT PRIMARY KEY);
INSERT INTO tmp_demo_slots
SELECT id FROM schedule_slots
WHERE professional_id IN (SELECT id FROM tmp_demo_users);

DROP TEMPORARY TABLE IF EXISTS tmp_demo_appointments;
CREATE TEMPORARY TABLE tmp_demo_appointments (id INT PRIMARY KEY);
INSERT INTO tmp_demo_appointments
SELECT id FROM appointments
WHERE client_id IN (SELECT id FROM tmp_demo_users)
   OR case_id IN (SELECT id FROM tmp_demo_cases)
   OR slot_id IN (SELECT id FROM tmp_demo_slots);

DROP TEMPORARY TABLE IF EXISTS tmp_demo_messages;
CREATE TEMPORARY TABLE tmp_demo_messages (id INT PRIMARY KEY);
INSERT INTO tmp_demo_messages
SELECT id FROM messages
WHERE case_id IN (SELECT id FROM tmp_demo_cases)
   OR sender_id IN (SELECT id FROM tmp_demo_users);

DROP TEMPORARY TABLE IF EXISTS tmp_demo_tasks;
CREATE TEMPORARY TABLE tmp_demo_tasks (id INT PRIMARY KEY);
INSERT INTO tmp_demo_tasks
SELECT id FROM tasks
WHERE case_id IN (SELECT id FROM tmp_demo_cases);

DROP TEMPORARY TABLE IF EXISTS tmp_demo_notifications;
CREATE TEMPORARY TABLE tmp_demo_notifications (id INT PRIMARY KEY);
INSERT INTO tmp_demo_notifications
SELECT id FROM notifications
WHERE user_id IN (SELECT id FROM tmp_demo_users);

DROP TEMPORARY TABLE IF EXISTS tmp_demo_ai_results;
CREATE TEMPORARY TABLE tmp_demo_ai_results (id INT PRIMARY KEY);
INSERT INTO tmp_demo_ai_results
SELECT id FROM ai_results
WHERE document_id IN (SELECT id FROM tmp_demo_documents);

DROP TEMPORARY TABLE IF EXISTS tmp_demo_audit;
CREATE TEMPORARY TABLE tmp_demo_audit (id INT PRIMARY KEY);
INSERT INTO tmp_demo_audit
SELECT id FROM audit_logs
WHERE EXISTS (
    SELECT 1 FROM tmp_demo_users
    WHERE tmp_demo_users.id = audit_logs.user_id
       OR (audit_logs.entity_type = 'user' AND tmp_demo_users.id = audit_logs.entity_id)
)
   OR (entity_type = 'document' AND entity_id IN (SELECT id FROM tmp_demo_documents))
   OR (entity_type = 'case' AND entity_id IN (SELECT id FROM tmp_demo_cases))
   OR (entity_type = 'schedule_slot' AND entity_id IN (SELECT id FROM tmp_demo_slots))
   OR (entity_type = 'appointment' AND entity_id IN (SELECT id FROM tmp_demo_appointments));

DROP TEMPORARY TABLE IF EXISTS tmp_demo_oab_logs;
CREATE TEMPORARY TABLE tmp_demo_oab_logs (id INT PRIMARY KEY);
INSERT INTO tmp_demo_oab_logs
SELECT id FROM cna_validacao_logs
WHERE EXISTS (
    SELECT 1 FROM tmp_demo_users
    WHERE tmp_demo_users.id = cna_validacao_logs.profissional_id
       OR tmp_demo_users.id = cna_validacao_logs.admin_id
);

DROP TEMPORARY TABLE IF EXISTS tmp_demo_external_processes;
CREATE TEMPORARY TABLE tmp_demo_external_processes (id INT PRIMARY KEY);
INSERT INTO tmp_demo_external_processes
SELECT id FROM external_processes
WHERE user_id IN (SELECT id FROM tmp_demo_users)
   OR source = 'datajud_demo'
   OR query_value IN ('52998224725', '39053344705', 'SP123456', 'RJ654321');

DELETE FROM audit_logs WHERE id IN (SELECT id FROM tmp_demo_audit);
DELETE FROM cna_validacao_logs WHERE id IN (SELECT id FROM tmp_demo_oab_logs);
DELETE FROM external_processes WHERE id IN (SELECT id FROM tmp_demo_external_processes);
DELETE FROM notifications WHERE id IN (SELECT id FROM tmp_demo_notifications);
DELETE FROM messages WHERE id IN (SELECT id FROM tmp_demo_messages);
DELETE FROM tasks WHERE id IN (SELECT id FROM tmp_demo_tasks);
DELETE FROM appointments WHERE id IN (SELECT id FROM tmp_demo_appointments);
DELETE FROM ai_results WHERE id IN (SELECT id FROM tmp_demo_ai_results);
DELETE FROM documents WHERE id IN (SELECT id FROM tmp_demo_documents);
DELETE FROM schedule_slots WHERE id IN (SELECT id FROM tmp_demo_slots);
DELETE FROM cases WHERE id IN (SELECT id FROM tmp_demo_cases);
DELETE FROM users WHERE id IN (SELECT id FROM tmp_demo_users);

INSERT INTO users
    (nome, email, senha, tipo, telefone, cpf, oab, oab_uf, oab_status, oab_parametro, oab_verificado, oab_tipo, status_cna, cna_validado_em, cna_origem, cna_tentativas, status, created_at)
VALUES
    ('Admin Demo', 'admin@justraduz.demo', @demo_password_hash, 'admin', '(11) 90000-0000', NULL, NULL, NULL, NULL, NULL, FALSE, NULL, 'pendente', NULL, NULL, 0, 'ativo', DATE_SUB(NOW(), INTERVAL 12 DAY)),
    ('Pietro Tamanini', 'pietro@tamanini.dev.br', @pietro_password_hash, 'admin', NULL, NULL, NULL, NULL, NULL, NULL, FALSE, NULL, 'pendente', NULL, NULL, 0, 'ativo', DATE_SUB(NOW(), INTERVAL 12 DAY)),
    ('Carla Cliente Demo', 'cliente@justraduz.demo', @demo_password_hash, 'cliente', '(11) 91111-1111', '52998224725', NULL, NULL, NULL, NULL, FALSE, NULL, 'pendente', NULL, NULL, 0, 'ativo', DATE_SUB(NOW(), INTERVAL 10 DAY)),
    ('Bruno Cliente Demo', 'cliente2@justraduz.demo', @demo_password_hash, 'cliente', '(21) 92222-2222', '39053344705', NULL, NULL, NULL, NULL, FALSE, NULL, 'pendente', NULL, NULL, 0, 'ativo', DATE_SUB(NOW(), INTERVAL 9 DAY)),
    ('Dra. Marina Costa', 'advogado@justraduz.demo', @demo_password_hash, 'advogado', '(31) 93333-3333', NULL, '123456', 'SP', 'Validado manualmente pela administracao.', 'demo-advogado-123456-sp', TRUE, 'advogado', 'verificado', DATE_SUB(NOW(), INTERVAL 8 DAY), 'admin_manual', 1, 'ativo', DATE_SUB(NOW(), INTERVAL 8 DAY)),
    ('Lucas Estagiario Demo', 'estagiario@justraduz.demo', @demo_password_hash, 'estagiario', '(41) 94444-4444', NULL, '654321', 'RJ', 'Validado manualmente pela administracao.', 'demo-estagiario-654321-rj', TRUE, 'estagiario', 'verificado', DATE_SUB(NOW(), INTERVAL 7 DAY), 'admin_manual', 1, 'ativo', DATE_SUB(NOW(), INTERVAL 7 DAY)),
    ('Dr. Rafael Pendente', 'pendente@justraduz.demo', @demo_password_hash, 'advogado', '(51) 95555-5555', NULL, '778899', 'MG', 'Aguardando validacao administrativa.', NULL, FALSE, 'advogado', 'pendente', NULL, 'admin_manual', 0, 'ativo', DATE_SUB(NOW(), INTERVAL 2 DAY));

SELECT id INTO @admin_id FROM users WHERE email = 'admin@justraduz.demo';
SELECT id INTO @cliente_id FROM users WHERE email = 'cliente@justraduz.demo';
SELECT id INTO @cliente2_id FROM users WHERE email = 'cliente2@justraduz.demo';
SELECT id INTO @advogado_id FROM users WHERE email = 'advogado@justraduz.demo';
SELECT id INTO @estagiario_id FROM users WHERE email = 'estagiario@justraduz.demo';
SELECT id INTO @pendente_id FROM users WHERE email = 'pendente@justraduz.demo';

INSERT INTO organizations (name, slug, owner_user_id, status)
VALUES ('Costa & Tamanini Demo', 'costa-tamanini-demo', @advogado_id, 'active')
ON DUPLICATE KEY UPDATE owner_user_id = VALUES(owner_user_id), status = 'active';
SELECT id INTO @org_demo_id FROM organizations WHERE slug = 'costa-tamanini-demo';

INSERT INTO organization_members (organization_id, user_id, role, status, invited_by) VALUES
    (@org_demo_id, @advogado_id, 'owner', 'active', @admin_id),
    (@org_demo_id, @estagiario_id, 'member', 'active', @admin_id),
    (@org_demo_id, @cliente_id, 'member', 'active', @admin_id)
ON DUPLICATE KEY UPDATE role = VALUES(role), status = 'active';

SELECT id INTO @plan_pro_id FROM plans WHERE slug = 'pro';
INSERT INTO subscriptions (user_id, organization_id, plan_id, billing_cycle, status, provider, current_period_start, current_period_end)
VALUES (@cliente_id, @org_demo_id, @plan_pro_id, 'monthly', 'active', 'demo_seed', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH));

INSERT INTO external_processes
    (user_id, owner_type, source, query_type, query_value, process_number, tribunal, uf, comarca, tipo_processo, classe_processual, assunto, status_inferido, status_normalizado, link, data_ultima_atualizacao, data_andamento_mais_recente, payload_json, last_synced_at)
VALUES
    (@cliente_id, 'cliente', 'datajud_demo', 'cnj', '10012345620248260100', '1001234-56.2024.8.26.0100', 'TJSP', 'SP', '1 Vara Civel de Sao Paulo', 'G1', 'Procedimento Comum Civel', 'Discussao de multa contratual e notificacao extrajudicial', 'Documento juntado', 'em andamento', NULL, DATE_SUB(CURDATE(), INTERVAL 9 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), JSON_OBJECT('demo', true, 'origem', 'seed modulo 8', 'justraduz', JSON_OBJECT('resumo_linguagem_simples', 'A ultima movimentacao indica que um documento foi juntado ao processo. Isso significa que alguma parte enviou uma nova informacao ou prova. Agora, o juiz ou a vara deve analisar esse documento antes do proximo andamento.', 'ultimas_movimentacoes', JSON_ARRAY(JSON_OBJECT('dataHora', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'descricao', 'Documento juntado')))), DATE_SUB(NOW(), INTERVAL 6 HOUR)),
    (@cliente_id, 'cliente', 'datajud_demo', 'cnj', '10098761220238260100', '1009876-12.2023.8.26.0100', 'TJSP', 'SP', '1 Vara Civel de Sao Paulo', 'G1', 'Cumprimento de Sentenca', 'Cobranca contratual arquivada', 'Arquivado definitivamente', 'encerrado', NULL, DATE_SUB(CURDATE(), INTERVAL 80 DAY), DATE_SUB(CURDATE(), INTERVAL 35 DAY), JSON_OBJECT('demo', true, 'origem', 'seed modulo 8', 'justraduz', JSON_OBJECT('resumo_linguagem_simples', 'O processo aparece como encerrado no cache de demonstracao. Em geral, isso indica que nao ha novos andamentos esperados, salvo recurso, reativacao ou outra medida registrada pelo tribunal.', 'ultimas_movimentacoes', JSON_ARRAY(JSON_OBJECT('dataHora', DATE_SUB(CURDATE(), INTERVAL 35 DAY), 'descricao', 'Arquivado definitivamente')))), DATE_SUB(NOW(), INTERVAL 6 HOUR)),
    (@cliente2_id, 'cliente', 'datajud_demo', 'cnj', '50123457820248190001', '5012345-78.2024.8.19.0001', 'TJRJ', 'RJ', 'Juizado Especial Civel do Rio de Janeiro', 'G1', 'Procedimento do Juizado Especial Civel', 'Revisao de clausula de locacao', 'Concluso para despacho', 'em andamento', NULL, DATE_SUB(CURDATE(), INTERVAL 15 DAY), DATE_SUB(CURDATE(), INTERVAL 4 DAY), JSON_OBJECT('demo', true, 'origem', 'seed modulo 8', 'justraduz', JSON_OBJECT('resumo_linguagem_simples', 'O processo esta aguardando analise do juiz. A ultima movimentacao indica que os autos foram encaminhados para despacho ou verificacao interna.', 'ultimas_movimentacoes', JSON_ARRAY(JSON_OBJECT('dataHora', DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'descricao', 'Concluso para despacho')))), DATE_SUB(NOW(), INTERVAL 5 HOUR)),
    (@advogado_id, 'advogado', 'datajud_demo', 'oab', 'SP123456', '1023456-44.2024.8.26.0002', 'TJSP', 'SP', 'Sao Paulo', 'civil', 'Acao de Obrigacao de Fazer', 'Direito do consumidor', 'Concluso para despacho', 'em andamento', NULL, DATE_SUB(CURDATE(), INTERVAL 6 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), JSON_OBJECT('demo', true, 'origem', 'seed modulo 8', 'advogado', 'Dra. Marina Costa'), DATE_SUB(NOW(), INTERVAL 4 HOUR)),
    (@advogado_id, 'advogado', 'datajud_demo', 'oab', 'SP123456', '1034567-21.2022.8.26.0053', 'TJSP', 'SP', 'Santos', 'trabalhista', 'Reclamacao Trabalhista', 'Verbas rescisorias', 'Baixado', 'baixado', NULL, DATE_SUB(CURDATE(), INTERVAL 120 DAY), DATE_SUB(CURDATE(), INTERVAL 60 DAY), JSON_OBJECT('demo', true, 'origem', 'seed modulo 8', 'advogado', 'Dra. Marina Costa'), DATE_SUB(NOW(), INTERVAL 4 HOUR)),
    (@estagiario_id, 'estagiario', 'datajud_demo', 'oab', 'RJ654321', '5043210-33.2024.8.19.0209', 'TJRJ', 'RJ', 'Barra da Tijuca', 'civil', 'Monitoria de Processo', 'Acompanhamento de prazo processual', 'Aguardando publicacao', 'em andamento', NULL, DATE_SUB(CURDATE(), INTERVAL 8 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), JSON_OBJECT('demo', true, 'origem', 'seed modulo 8', 'estagiario', 'Lucas Estagiario Demo'), DATE_SUB(NOW(), INTERVAL 3 HOUR));

INSERT INTO documents
    (user_id, organization_id, nome_arquivo, tipo_arquivo, caminho, texto_extraido, created_at)
VALUES
    (@cliente_id, @org_demo_id, 'notificacao-extrajudicial-demo.png', 'png', 'backend/storage/documents/demo/notificacao-extrajudicial-demo.png',
     'Notificacao extrajudicial cobrando multa contratual por atraso. O documento informa prazo de 5 dias para resposta e menciona possibilidade de medidas judiciais se nao houver contato.',
     DATE_SUB(NOW(), INTERVAL 4 DAY));
SET @doc1_id = LAST_INSERT_ID();

INSERT INTO documents
    (user_id, nome_arquivo, tipo_arquivo, caminho, texto_extraido, created_at)
VALUES
    (@cliente2_id, 'contrato-locacao-pendente-demo.png', 'png', 'backend/storage/documents/demo/contrato-locacao-pendente-demo.png',
     'Contrato de locacao residencial com clausula de multa, reajuste anual e vistoria de entrada. Documento aguardando analise automatica para a demonstracao.',
     DATE_SUB(NOW(), INTERVAL 2 DAY));
SET @doc2_id = LAST_INSERT_ID();

INSERT INTO ai_results
    (document_id, resumo, explicacao, confianca, modelo, prompt_versao, created_at)
VALUES
    (@doc1_id,
     'A notificacao extrajudicial cobra uma multa contratual e pede resposta em ate 5 dias. O texto indica que, sem retorno, a outra parte podera buscar medidas judiciais.',
     CONCAT(
       '## Explicacao em linguagem simples', CHAR(10),
       'Voce recebeu uma cobranca formal. O documento diz que existe uma multa contratual em aberto e que voce tem pouco tempo para responder. Ignorar a notificacao pode fazer a outra parte tentar resolver o problema judicialmente.', CHAR(10), CHAR(10),
       '## Pontos importantes', CHAR(10),
       '- Existe uma cobranca de multa contratual.', CHAR(10),
       '- O prazo informado para resposta e de 5 dias.', CHAR(10),
       '- O documento menciona possibilidade de medidas judiciais se nao houver contato.', CHAR(10), CHAR(10),
       '## Riscos e pontos de atencao', CHAR(10),
       '- O prazo curto exige organizacao rapida.', CHAR(10),
       '- A multa precisa ser conferida no contrato original antes de qualquer pagamento.', CHAR(10),
       '- Responder sem revisar documentos pode piorar a negociacao.', CHAR(10), CHAR(10),
       '## Proximos passos sugeridos', CHAR(10),
       '- Guarde a notificacao e todos os comprovantes relacionados.', CHAR(10),
       '- Separe o contrato citado na cobranca.', CHAR(10),
       '- Procure orientacao profissional antes de pagar, ignorar ou responder.', CHAR(10), CHAR(10),
       '## Aviso informativo', CHAR(10),
       'Esta analise e informativa e nao substitui orientacao juridica profissional.'
     ),
     88.5,
     'gemini-2.5-flash',
     '2026-06-06-document-v2',
     DATE_SUB(NOW(), INTERVAL 4 DAY));

INSERT INTO cases
    (organization_id, cliente_id, advogado_id, document_id, titulo, descricao, status, prioridade, sla_due_at, sla_status, created_at)
VALUES
    (@org_demo_id, @cliente_id, @advogado_id, @doc1_id, 'Revisar notificacao extrajudicial', 'Cliente recebeu cobranca com prazo de 5 dias e quer entender se deve responder imediatamente.', 'em_andamento', 'alta', DATE_SUB(NOW(), INTERVAL 2 DAY), 'vencido', DATE_SUB(NOW(), INTERVAL 3 DAY));
SET @case1_id = LAST_INSERT_ID();

INSERT INTO cases
    (cliente_id, advogado_id, document_id, titulo, descricao, status, prioridade, created_at)
VALUES
    (@cliente2_id, NULL, @doc2_id, 'Duvida sobre contrato de locacao', 'Contrato tem multa e reajuste anual. Cliente quer saber quais clausulas exigem atencao.', 'aberto', 'alta', DATE_SUB(NOW(), INTERVAL 2 DAY));
SET @case2_id = LAST_INSERT_ID();

INSERT INTO cases
    (cliente_id, advogado_id, document_id, titulo, descricao, status, prioridade, created_at)
VALUES
    (@cliente_id, @advogado_id, @doc1_id, 'Orientacao concluida sobre prazo de resposta', 'Atendimento usado para demonstrar historico finalizado.', 'finalizado', 'baixa', DATE_SUB(NOW(), INTERVAL 6 DAY));
SET @case3_id = LAST_INSERT_ID();

INSERT INTO messages (case_id, sender_id, mensagem, created_at)
VALUES
    (@case1_id, @cliente_id, 'Recebi essa notificacao e estou preocupada com o prazo de 5 dias.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
    (@case1_id, @advogado_id, 'Vou revisar a multa e comparar com o contrato antes de orientar a resposta.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
    (@case1_id, @cliente_id, 'Obrigada. Quero evitar responder de forma errada.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
    (@case3_id, @advogado_id, 'Atendimento encerrado. A recomendacao foi enviar resposta formal dentro do prazo.', DATE_SUB(NOW(), INTERVAL 5 DAY));

INSERT INTO tasks (case_id, titulo, descricao, status, created_at)
VALUES
    (@case1_id, 'Conferir clausula de multa', 'Comparar a notificacao com a clausula contratual antes da resposta.', 'em_andamento', DATE_SUB(NOW(), INTERVAL 2 DAY)),
    (@case1_id, 'Preparar minuta de resposta', 'Rascunhar resposta objetiva para envio dentro do prazo.', 'pendente', DATE_SUB(NOW(), INTERVAL 1 DAY)),
    (@case3_id, 'Registrar orientacao final', 'Fechar atendimento com resumo da orientacao prestada.', 'concluida', DATE_SUB(NOW(), INTERVAL 5 DAY));

INSERT INTO schedule_slots (organization_id, professional_id, starts_at, ends_at, status, titulo, created_at)
VALUES (@org_demo_id, @advogado_id, DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 1 DAY), INTERVAL 10 HOUR), DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 1 DAY), INTERVAL 11 HOUR), 'livre', 'Atendimento inicial', DATE_SUB(NOW(), INTERVAL 1 DAY));
SET @slot_free_id = LAST_INSERT_ID();

INSERT INTO schedule_slots (organization_id, professional_id, starts_at, ends_at, status, titulo, created_at)
VALUES (@org_demo_id, @advogado_id, DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 2 DAY), INTERVAL 15 HOUR), DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 2 DAY), INTERVAL 16 HOUR), 'ocupado', 'Consulta sobre notificacao', DATE_SUB(NOW(), INTERVAL 1 DAY));
SET @slot_booked_id = LAST_INSERT_ID();

INSERT INTO schedule_slots (organization_id, professional_id, starts_at, ends_at, status, titulo, created_at)
VALUES (@org_demo_id, @advogado_id, DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 2 DAY), INTERVAL 13 HOUR), DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 2 DAY), INTERVAL 14 HOUR), 'bloqueado', 'Bloqueio interno para revisao', DATE_SUB(NOW(), INTERVAL 1 DAY));
SET @slot_blocked_id = LAST_INSERT_ID();

INSERT INTO schedule_slots (organization_id, professional_id, starts_at, ends_at, status, titulo, created_at)
VALUES (@org_demo_id, @estagiario_id, DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 3 DAY), INTERVAL 9 HOUR), DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 3 DAY), INTERVAL 10 HOUR), 'livre', 'Triagem juridica', DATE_SUB(NOW(), INTERVAL 1 DAY));
SET @slot_intern_id = LAST_INSERT_ID();

INSERT INTO appointments (organization_id, slot_id, client_id, case_id, assunto, observacoes, status, created_at)
VALUES (@org_demo_id, @slot_booked_id, @cliente_id, @case1_id, 'Consulta sobre notificacao extrajudicial', 'Demo: atendimento marcado para explicar prazo e resposta.', 'agendado', DATE_SUB(NOW(), INTERVAL 12 HOUR));
SET @appointment_id = LAST_INSERT_ID();

INSERT INTO notifications (user_id, mensagem, lida, created_at)
VALUES
    (@cliente_id, 'Documento notificação-extrajudicial-demo.png analisado com IA.', FALSE, DATE_SUB(NOW(), INTERVAL 4 DAY)),
    (@cliente_id, 'Atendimento agendado com Dra. Marina Costa.', FALSE, DATE_SUB(NOW(), INTERVAL 12 HOUR)),
    (@advogado_id, 'Novo caso de prioridade alta atribuido.', FALSE, DATE_SUB(NOW(), INTERVAL 3 DAY)),
    (@admin_id, 'Profissional pendente aguardando validacao OAB.', FALSE, DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO cna_validacao_logs
    (profissional_id, admin_id, acao, status_anterior, status_novo, origem, mensagem, justificativa, created_at)
VALUES
    (@advogado_id, @admin_id, 'admin_approve', 'pendente', 'verificado', 'admin_manual', 'Validado manualmente pela administracao.', 'Seed demo para apresentacao.', DATE_SUB(NOW(), INTERVAL 8 DAY)),
    (@estagiario_id, @admin_id, 'admin_approve', 'pendente', 'verificado', 'admin_manual', 'Validado manualmente pela administracao.', 'Seed demo para apresentacao.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
    (@pendente_id, NULL, 'cadastro', NULL, 'pendente', 'admin_manual', 'Aguardando validacao administrativa.', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO audit_logs
    (user_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
VALUES
    (@cliente_id, 'document.upload', 'document', @doc1_id, JSON_OBJECT('nome_arquivo', 'notificacao-extrajudicial-demo.png', 'analysis_generated', true), '127.0.0.1', 'JusTraduz Demo', DATE_SUB(NOW(), INTERVAL 4 DAY)),
    (@cliente_id, 'document.analyze', 'document', @doc1_id, JSON_OBJECT('analysis_generated', true, 'model', 'gemini-2.5-flash'), '127.0.0.1', 'JusTraduz Demo', DATE_SUB(NOW(), INTERVAL 4 DAY)),
    (@cliente_id, 'case.create', 'case', @case1_id, JSON_OBJECT('prioridade', 'alta', 'advogado_id', @advogado_id), '127.0.0.1', 'JusTraduz Demo', DATE_SUB(NOW(), INTERVAL 3 DAY)),
    (@advogado_id, 'message.send', 'case', @case1_id, JSON_OBJECT('sender_id', @advogado_id), '127.0.0.1', 'JusTraduz Demo', DATE_SUB(NOW(), INTERVAL 2 DAY)),
    (@admin_id, 'admin.professional_oab_approve', 'user', @advogado_id, JSON_OBJECT('status_anterior', 'pendente', 'status_novo', 'verificado'), '127.0.0.1', 'JusTraduz Demo', DATE_SUB(NOW(), INTERVAL 8 DAY)),
    (@admin_id, 'admin.case_update', 'case', @case1_id, JSON_OBJECT('status', 'em_andamento', 'prioridade', 'alta', 'advogado_id', @advogado_id), '127.0.0.1', 'JusTraduz Demo', DATE_SUB(NOW(), INTERVAL 3 DAY)),
    (@cliente_id, 'schedule.appointment_booked', 'appointment', @appointment_id, JSON_OBJECT('case_id', @case1_id, 'professional_id', @advogado_id), '127.0.0.1', 'JusTraduz Demo', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
    (NULL, 'auth.login_failed', 'user', NULL, JSON_OBJECT('email', 'tentativa@demo.local', 'reason', 'wrong_password'), '127.0.0.1', 'JusTraduz Demo', DATE_SUB(NOW(), INTERVAL 2 HOUR));

SELECT 'Seed demo aplicado. Senha das contas: Demo@2026!' AS resultado;
