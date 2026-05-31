ALTER TABLE users
  CHANGE oab_status status_cna ENUM('pendente', 'verificado', 'invalido', 'nao_encontrado') DEFAULT 'pendente',
  CHANGE oab_verificado oab_verificado BOOLEAN DEFAULT FALSE, -- we will just drop it
  ADD COLUMN oab_tipo VARCHAR(50) NULL AFTER oab_uf,
  ADD COLUMN cna_validado_em DATETIME NULL,
  ADD COLUMN cna_origem VARCHAR(50) NULL,
  ADD COLUMN cna_payload_cache TEXT NULL,
  ADD COLUMN cna_ultimo_erro TEXT NULL,
  ADD COLUMN cna_tentativas INT DEFAULT 0;

ALTER TABLE users DROP COLUMN oab_verificado;
ALTER TABLE users DROP COLUMN oab_parametro;

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
