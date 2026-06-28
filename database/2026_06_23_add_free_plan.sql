USE justraduz;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO plans (slug, name, description, monthly_price_cents, yearly_price_cents, limits_json, features_json, sort_order, active) VALUES
    ('gratuito', 'Gratuito', 'Plano inicial liberado automaticamente apos a conclusao do onboarding.', 0, 0,
     JSON_OBJECT('document_upload', 5, 'document_ai', 5, 'ai_chat', 50, 'datajud_cnj', 1, 'ocr', 5),
     JSON_ARRAY('5 documentos por mes', '5 analises com IA', '50 mensagens com IA Juridica', '1 consulta CNJ por mes', 'OCR basico para ate 5 arquivos'), 1, 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    monthly_price_cents = VALUES(monthly_price_cents),
    yearly_price_cents = VALUES(yearly_price_cents),
    limits_json = VALUES(limits_json),
    features_json = VALUES(features_json),
    sort_order = VALUES(sort_order),
    active = 1;

INSERT IGNORE INTO schema_migrations (version) VALUES ('2026_06_23_add_free_plan');
