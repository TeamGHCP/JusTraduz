SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO plans (
    slug, audience, name, description, monthly_price_cents, yearly_price_cents,
    limits_json, features_json, active, sort_order
) VALUES
    (
        'max_cliente', 'cliente', 'Max',
        'Mais volume para clientes que analisam e acompanham uma grande quantidade de documentos e processos.',
        7990, 76700,
        JSON_OBJECT('document_upload', 2000, 'document_ai', 2000, 'ai_chat', 20000, 'datajud_cnj', 2000, 'ocr', 2000),
        JSON_ARRAY(
            'Até 2.000 documentos por mês',
            'Até 2.000 análises com IA documental',
            'Até 20.000 mensagens com IA Jurídica',
            'Até 2.000 consultas CNJ por mês',
            'Até 2.000 processamentos OCR por mês',
            'Histórico de documentos e faturas'
        ),
        1, 25
    ),
    (
        'max_advogado', 'advogado', 'Max',
        'Alto volume individual para advogados que operam documentos, consultas e análises jurídicas em escala.',
        8990, 86300,
        JSON_OBJECT('document_upload', 3000, 'document_ai', 3000, 'ai_chat', 30000, 'datajud_cnj', 3000, 'ocr', 3000),
        JSON_ARRAY(
            'Até 3.000 documentos por mês',
            'Até 3.000 análises com IA documental',
            'Até 30.000 mensagens com IA Jurídica',
            'Até 3.000 consultas CNJ por mês',
            'Até 3.000 processamentos OCR por mês',
            'Casos, tarefas e agenda profissional',
            'Histórico de documentos e faturas'
        ),
        1, 25
    )
ON DUPLICATE KEY UPDATE
    audience = VALUES(audience),
    name = VALUES(name),
    description = VALUES(description),
    monthly_price_cents = VALUES(monthly_price_cents),
    yearly_price_cents = VALUES(yearly_price_cents),
    limits_json = VALUES(limits_json),
    features_json = VALUES(features_json),
    active = VALUES(active),
    sort_order = VALUES(sort_order);

INSERT IGNORE INTO schema_migrations (version) VALUES ('2026_06_26_max_plans');
