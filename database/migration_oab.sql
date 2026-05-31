USE justraduz;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'oab_status') = 0,
  'ALTER TABLE users ADD COLUMN oab_status VARCHAR(50) NULL AFTER oab_uf',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'oab_parametro') = 0,
  'ALTER TABLE users ADD COLUMN oab_parametro TEXT NULL AFTER oab_status',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'oab_verificado') = 0,
  'ALTER TABLE users ADD COLUMN oab_verificado BOOLEAN DEFAULT FALSE AFTER oab_parametro',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'oab_tipo') = 0,
  'ALTER TABLE users ADD COLUMN oab_tipo VARCHAR(50) NULL AFTER oab_verificado',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'status_cna') = 0,
  'ALTER TABLE users ADD COLUMN status_cna ENUM(''pendente'', ''verificado'', ''invalido'', ''nao_encontrado'') DEFAULT ''pendente'' AFTER oab_tipo',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'cna_validado_em') = 0,
  'ALTER TABLE users ADD COLUMN cna_validado_em DATETIME NULL AFTER status_cna',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'cna_origem') = 0,
  'ALTER TABLE users ADD COLUMN cna_origem VARCHAR(50) NULL AFTER cna_validado_em',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'cna_payload_cache') = 0,
  'ALTER TABLE users ADD COLUMN cna_payload_cache TEXT NULL AFTER cna_origem',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'cna_ultimo_erro') = 0,
  'ALTER TABLE users ADD COLUMN cna_ultimo_erro TEXT NULL AFTER cna_payload_cache',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'cna_tentativas') = 0,
  'ALTER TABLE users ADD COLUMN cna_tentativas INT DEFAULT 0 AFTER cna_ultimo_erro',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE users
SET
  oab_status = COALESCE(oab_status, status_cna),
  oab_verificado = CASE WHEN status_cna = 'verificado' THEN TRUE ELSE oab_verificado END
WHERE id > 0
  AND status_cna IS NOT NULL;

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
