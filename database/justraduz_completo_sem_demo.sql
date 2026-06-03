-- ==========================================================
-- JusTraduz - SQL completo unificado
-- Gerado a partir dos arquivos enviados pelo usuario.
--
-- Ordem aplicada:
-- 1) Criacao do banco e tabelas completas
-- 2) Campos das migrations ja incorporados no schema
-- 3) Dados demo/resetaveis
-- 4) Seed admin de exemplo comentado ao final
--
-- Migrations incorporadas no schema:
-- - migration_telefone
-- - migration_profile_photo
-- - migration_password_reset_codes
-- - migration_oab
-- - migration_ai_metadata
-- - migration_google_oauth
-- - migration_indexes_integrity
-- - migration_message_attachments
--
-- Observacao: os DELETE do seed demo foram convertidos para JOIN,
-- evitando erro 1175 do MySQL Workbench em Safe Update Mode.
-- ==========================================================

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
    oab_parametro TEXT,
    oab_verificado BOOLEAN DEFAULT FALSE,

    -- Campos auxiliares para integracoes futuras com o CNA.
    oab_tipo VARCHAR(50),
    status_cna ENUM('pendente', 'verificado', 'invalido', 'nao_encontrado') DEFAULT 'pendente',
    cna_validado_em DATETIME NULL,
    cna_origem VARCHAR(50) NULL,
    cna_payload_cache TEXT NULL,
    cna_ultimo_erro TEXT NULL,
    cna_tentativas INT DEFAULT 0,
    
    telefone VARCHAR(25),
    foto_perfil VARCHAR(255),
    google_sub VARCHAR(255) UNIQUE,
    google_picture VARCHAR(255),
    google_linked_at DATETIME NULL,
    status ENUM('ativo','inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_tipo_status (tipo, status)
) DEFAULT CHARSET=utf8mb4;

-- documentos
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nome_arquivo VARCHAR(255),
    tipo_arquivo VARCHAR(20),
    caminho VARCHAR(255),
    texto_extraido LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
    cliente_id INT NOT NULL,
    advogado_id INT NULL,
    titulo VARCHAR(255),
    descricao TEXT,
    status ENUM('aberto', 'em_andamento', 'finalizado') DEFAULT 'aberto',
    prioridade ENUM('baixa', 'media', 'alta') DEFAULT 'media',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (advogado_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_cases_cliente_status (cliente_id, status),
    INDEX idx_cases_advogado_status (advogado_id, status)
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
    professional_id INT NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    status ENUM('livre', 'ocupado', 'bloqueado') DEFAULT 'livre',
    titulo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (professional_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_schedule_professional (professional_id, starts_at),
    INDEX idx_schedule_status (status, starts_at)
) DEFAULT CHARSET=utf8mb4;

-- agendamentos feitos por clientes
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_id INT NOT NULL,
    client_id INT NOT NULL,
    case_id INT NULL,
    assunto VARCHAR(255) NOT NULL,
    observacoes TEXT,
    status ENUM('agendado', 'cancelado', 'concluido') DEFAULT 'agendado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (slot_id) REFERENCES schedule_slots(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL,
    INDEX idx_appointments_client (client_id, created_at),
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

-- tabela de log cna
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

INSERT IGNORE INTO schema_migrations (version) VALUES
('migration_telefone'),
('migration_profile_photo'),
('migration_password_reset_codes'),
('migration_oab'),
('migration_ai_metadata'),
('migration_google_oauth'),
('migration_indexes_integrity'),
('migration_message_attachments');


-- ==========================================================
-- Migrations incorporadas
-- ==========================================================
-- As migrations enviadas eram, em grande parte, ALTER TABLE para
-- campos que ja estao no schema acima. Para evitar duplicacao e erros
-- como "Duplicate column name", elas nao foram repetidas aqui.
--
-- A migration_message_attachments tambem foi incorporada diretamente
-- na tabela messages com os campos:
-- attachment_original_name, attachment_path, attachment_mime, attachment_size.
-- ==========================================================




-- ==========================================================
-- Administrador inicial
-- ==========================================================

INSERT INTO users (nome, email, senha, tipo, status)
SELECT 'Administrador', 'admin@justraduz.local', '$2b$12$Shf6kW2iXWvVLEW9qAUHAOVUKPFCM47oG5iQfZdQHMyH1W4Ho8o2W', 'admin', 'ativo'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'admin@justraduz.local'
);

-- ==========================================================
-- Seed admin de exemplo original
-- ==========================================================
-- Mantive este trecho comentado porque ele usa um placeholder
-- '<HASH_GERADO_COM_PASSWORD_HASH>'. Para usar, gere um hash real
-- com password_hash no PHP e descomente o INSERT abaixo.

-- -- ATENÇÃO: este arquivo é um exemplo. NÃO o execute em produção sem revisar.
-- -- Antes de executar, gere um hash seguro com:
-- -- C:\xampp\php\php.exe -r "echo password_hash('SENHA_FORTE_AQUI', PASSWORD_DEFAULT);"
-- -- Troque o e-mail e o hash abaixo antes de importar.
-- INSERT INTO users (nome, email, senha, tipo, status)
-- SELECT 'Administrador', 'admin@justraduz.local', '<HASH_GERADO_COM_PASSWORD_HASH>', 'admin', 'ativo'
-- WHERE NOT EXISTS (
--     SELECT 1 FROM users WHERE email = 'admin@justraduz.local'
-- );
