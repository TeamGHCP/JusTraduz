USE justraduz;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE plans
    ADD COLUMN IF NOT EXISTS audience ENUM('cliente', 'advogado', 'ambos') NOT NULL DEFAULT 'cliente' AFTER slug;

UPDATE plans
SET audience = CASE
    WHEN slug IN ('profissional_basico', 'advogado_basico', 'escritorio') THEN 'advogado'
    WHEN slug = 'pro' THEN 'ambos'
    ELSE 'cliente'
END;

INSERT INTO plans (slug, audience, name, description, monthly_price_cents, yearly_price_cents, limits_json, features_json, sort_order, active) VALUES
    ('profissional_basico', 'advogado', 'Profissional básico', 'Plano inicial para advogados com OAB validada acompanharem a mesa profissional antes de contratar mais volume.', 0, 0,
     JSON_OBJECT('document_upload', 5, 'document_ai', 5, 'ai_chat', 50, 'datajud_cnj', 1, 'ocr', 5),
     JSON_ARRAY('5 documentos por mes', '5 analises com IA documental', '50 mensagens com IA Juridica', '1 consulta CNJ por mes', 'OCR basico para ate 5 arquivos'), 5, 1)
ON DUPLICATE KEY UPDATE
    audience = VALUES(audience),
    name = VALUES(name),
    description = VALUES(description),
    monthly_price_cents = VALUES(monthly_price_cents),
    yearly_price_cents = VALUES(yearly_price_cents),
    limits_json = VALUES(limits_json),
    features_json = VALUES(features_json),
    sort_order = VALUES(sort_order),
    active = 1;

INSERT IGNORE INTO schema_migrations (version) VALUES ('2026_06_24_plan_audience_professional');
