USE justraduz;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO plans (slug, name, description, monthly_price_cents, yearly_price_cents, limits_json, features_json, sort_order) VALUES
    ('essencial', 'Essencial', 'Ideal para cidadãos, estudantes e usuários ocasionais.', 1490, 14300,
     JSON_OBJECT('document_upload', 30, 'document_ai', 30, 'ai_chat', 300, 'datajud_cnj', 30, 'ocr', 30),
     JSON_ARRAY('Tradução de documentos jurídicos', 'IA Jurídica (Chat)', 'Consulta CNJ', 'Resumo automático de documentos', 'Histórico de documentos', 'Upload de PDF, DOCX e imagens', 'Até 30 documentos por mês'), 10),
    ('pro', 'Pro', 'Ideal para advogados autônomos e profissionais jurídicos.', 4990, 47900,
     JSON_OBJECT('document_upload', 500, 'document_ai', 500, 'ai_chat', 5000, 'datajud_cnj', 500, 'ocr', 500),
     JSON_ARRAY('Até 500 documentos por mês', 'Até 500 análises com IA documental', 'Até 5.000 mensagens com IA Jurídica', 'Até 500 consultas CNJ por mês', 'Até 500 processamentos OCR por mês', 'Histórico de documentos e faturas'), 20),
    ('escritorio', 'Escritório', 'Ideal para escritórios e equipes jurídicas.', 9990, 95900,
     JSON_OBJECT('document_upload', 0, 'document_ai', 0, 'ai_chat', 10000, 'datajud_cnj', 1000, 'ocr', 0),
     JSON_ARRAY('Documentos, OCR e IA documental ilimitados', 'Até 10.000 mensagens com IA Jurídica', 'Até 1.000 consultas CNJ por mês', 'Compartilhamento por organização', 'Agenda, casos e tarefas por equipe', 'Histórico de documentos e faturas'), 30)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    monthly_price_cents = VALUES(monthly_price_cents),
    yearly_price_cents = VALUES(yearly_price_cents),
    limits_json = VALUES(limits_json),
    features_json = VALUES(features_json),
    sort_order = VALUES(sort_order),
    active = 1;

INSERT IGNORE INTO schema_migrations (version) VALUES ('2026_06_22_update_paid_plans');
