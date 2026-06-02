USE justraduz;

ALTER TABLE messages
    ADD COLUMN IF NOT EXISTS attachment_original_name VARCHAR(255) NULL AFTER mensagem,
    ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(255) NULL AFTER attachment_original_name,
    ADD COLUMN IF NOT EXISTS attachment_mime VARCHAR(120) NULL AFTER attachment_path,
    ADD COLUMN IF NOT EXISTS attachment_size INT NULL AFTER attachment_mime;
