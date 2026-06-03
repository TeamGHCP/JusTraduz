USE justraduz;

-- Seed demo resetavel do JusTraduz.
-- Reexecutar este arquivo remove e recria apenas dados dos e-mails @justraduz.demo.
-- Senha de todas as contas demo: Demo@2026!
-- Nao use estas credenciais em producao.

SET @demo_password_hash = '$2y$10$hRRLMod1YrVw5/JlfV2Oh.FRaeW0iADWX4ioRcoRYy3OzrSvarWI.';

DROP TEMPORARY TABLE IF EXISTS tmp_demo_users;
CREATE TEMPORARY TABLE tmp_demo_users (id INT PRIMARY KEY);
INSERT INTO tmp_demo_users
SELECT id FROM users
WHERE email IN (
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
WHERE cliente_id IN (SELECT id FROM tmp_demo_users)
   OR advogado_id IN (SELECT id FROM tmp_demo_users);

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
WHERE user_id IN (SELECT id FROM tmp_demo_users)
   OR (entity_type = 'user' AND entity_id IN (SELECT id FROM tmp_demo_users))
   OR (entity_type = 'document' AND entity_id IN (SELECT id FROM tmp_demo_documents))
   OR (entity_type = 'case' AND entity_id IN (SELECT id FROM tmp_demo_cases))
   OR (entity_type = 'schedule_slot' AND entity_id IN (SELECT id FROM tmp_demo_slots))
   OR (entity_type = 'appointment' AND entity_id IN (SELECT id FROM tmp_demo_appointments));

DROP TEMPORARY TABLE IF EXISTS tmp_demo_cna_logs;
CREATE TEMPORARY TABLE tmp_demo_cna_logs (id INT PRIMARY KEY);
INSERT INTO tmp_demo_cna_logs
SELECT id FROM cna_validacao_logs
WHERE profissional_id IN (SELECT id FROM tmp_demo_users)
   OR admin_id IN (SELECT id FROM tmp_demo_users);

DELETE target FROM audit_logs AS target INNER JOIN tmp_demo_audit AS tmp ON target.id = tmp.id;
DELETE target FROM cna_validacao_logs AS target INNER JOIN tmp_demo_cna_logs AS tmp ON target.id = tmp.id;
DELETE target FROM notifications AS target INNER JOIN tmp_demo_notifications AS tmp ON target.id = tmp.id;
DELETE target FROM messages AS target INNER JOIN tmp_demo_messages AS tmp ON target.id = tmp.id;
DELETE target FROM tasks AS target INNER JOIN tmp_demo_tasks AS tmp ON target.id = tmp.id;
DELETE target FROM appointments AS target INNER JOIN tmp_demo_appointments AS tmp ON target.id = tmp.id;
DELETE target FROM ai_results AS target INNER JOIN tmp_demo_ai_results AS tmp ON target.id = tmp.id;
DELETE target FROM documents AS target INNER JOIN tmp_demo_documents AS tmp ON target.id = tmp.id;
DELETE target FROM schedule_slots AS target INNER JOIN tmp_demo_slots AS tmp ON target.id = tmp.id;
DELETE target FROM cases AS target INNER JOIN tmp_demo_cases AS tmp ON target.id = tmp.id;
DELETE target FROM users AS target INNER JOIN tmp_demo_users AS tmp ON target.id = tmp.id;

INSERT INTO users
    (nome, email, senha, tipo, telefone, oab, oab_uf, oab_status, oab_parametro, oab_verificado, oab_tipo, status_cna, cna_validado_em, cna_origem, cna_tentativas, status, created_at)
VALUES    ('Carla Cliente Demo', 'cliente@justraduz.demo', @demo_password_hash, 'cliente', '(11) 91111-1111', NULL, NULL, NULL, NULL, FALSE, NULL, 'pendente', NULL, NULL, 0, 'ativo', DATE_SUB(NOW(), INTERVAL 10 DAY)),
    ('Bruno Cliente Demo', 'cliente2@justraduz.demo', @demo_password_hash, 'cliente', '(21) 92222-2222', NULL, NULL, NULL, NULL, FALSE, NULL, 'pendente', NULL, NULL, 0, 'ativo', DATE_SUB(NOW(), INTERVAL 9 DAY)),
    ('Dra. Marina Costa', 'advogado@justraduz.demo', @demo_password_hash, 'advogado', '(31) 93333-3333', '123456', 'SP', 'Validado manualmente pela administracao.', 'demo-advogado-123456-sp', TRUE, 'advogado', 'verificado', DATE_SUB(NOW(), INTERVAL 8 DAY), 'admin_manual', 1, 'ativo', DATE_SUB(NOW(), INTERVAL 8 DAY)),
    ('Lucas Estagiario Demo', 'estagiario@justraduz.demo', @demo_password_hash, 'estagiario', '(41) 94444-4444', '654321', 'RJ', 'Validado manualmente pela administracao.', 'demo-estagiario-654321-rj', TRUE, 'estagiario', 'verificado', DATE_SUB(NOW(), INTERVAL 7 DAY), 'admin_manual', 1, 'ativo', DATE_SUB(NOW(), INTERVAL 7 DAY)),
    ('Dr. Rafael Pendente', 'pendente@justraduz.demo', @demo_password_hash, 'advogado', '(51) 95555-5555', '778899', 'MG', 'Pendente: validacao automatica do CNA indisponivel', NULL, FALSE, 'advogado', 'pendente', NULL, 'fallback', 1, 'ativo', DATE_SUB(NOW(), INTERVAL 2 DAY));

SELECT id INTO @cliente_id FROM users WHERE email = 'cliente@justraduz.demo';
SELECT id INTO @cliente2_id FROM users WHERE email = 'cliente2@justraduz.demo';
SELECT id INTO @advogado_id FROM users WHERE email = 'advogado@justraduz.demo';
SELECT id INTO @estagiario_id FROM users WHERE email = 'estagiario@justraduz.demo';
SELECT id INTO @pendente_id FROM users WHERE email = 'pendente@justraduz.demo';

INSERT INTO documents
    (user_id, nome_arquivo, tipo_arquivo, caminho, texto_extraido, created_at)
VALUES
    (@cliente_id, 'notificacao-extrajudicial-demo.png', 'png', 'backend/storage/documents/demo/notificacao-extrajudicial-demo.png',
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
     'Em linguagem simples: voce recebeu uma cobranca formal. O ponto mais importante e o prazo curto para responder. Antes de pagar ou ignorar, vale conferir se a multa esta prevista no contrato e conversar com um profissional.',
     88.5,
     'gemini-2.5-flash',
     '2026-05-31-document-v1',
     DATE_SUB(NOW(), INTERVAL 4 DAY));

INSERT INTO cases
    (cliente_id, advogado_id, titulo, descricao, status, prioridade, created_at)
VALUES
    (@cliente_id, @advogado_id, 'Revisar notificacao extrajudicial', 'Cliente recebeu cobranca com prazo de 5 dias e quer entender se deve responder imediatamente.', 'em_andamento', 'alta', DATE_SUB(NOW(), INTERVAL 3 DAY));
SET @case1_id = LAST_INSERT_ID();

INSERT INTO cases
    (cliente_id, advogado_id, titulo, descricao, status, prioridade, created_at)
VALUES
    (@cliente2_id, NULL, 'Duvida sobre contrato de locacao', 'Contrato tem multa e reajuste anual. Cliente quer saber quais clausulas exigem atencao.', 'aberto', 'alta', DATE_SUB(NOW(), INTERVAL 2 DAY));
SET @case2_id = LAST_INSERT_ID();

INSERT INTO cases
    (cliente_id, advogado_id, titulo, descricao, status, prioridade, created_at)
VALUES
    (@cliente_id, @advogado_id, 'Orientacao concluida sobre prazo de resposta', 'Atendimento usado para demonstrar historico finalizado.', 'finalizado', 'baixa', DATE_SUB(NOW(), INTERVAL 6 DAY));
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

INSERT INTO schedule_slots (professional_id, starts_at, ends_at, status, titulo, created_at)
VALUES (@advogado_id, DATE_ADD(CURDATE(), INTERVAL 10 HOUR), DATE_ADD(CURDATE(), INTERVAL 11 HOUR), 'livre', 'Atendimento inicial', DATE_SUB(NOW(), INTERVAL 1 DAY));
SET @slot_free_id = LAST_INSERT_ID();

INSERT INTO schedule_slots (professional_id, starts_at, ends_at, status, titulo, created_at)
VALUES (@advogado_id, DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 1 DAY), INTERVAL 15 HOUR), DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 1 DAY), INTERVAL 16 HOUR), 'ocupado', 'Consulta sobre notificacao', DATE_SUB(NOW(), INTERVAL 1 DAY));
SET @slot_booked_id = LAST_INSERT_ID();

INSERT INTO schedule_slots (professional_id, starts_at, ends_at, status, titulo, created_at)
VALUES (@estagiario_id, DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 2 DAY), INTERVAL 9 HOUR), DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 2 DAY), INTERVAL 10 HOUR), 'livre', 'Triagem juridica', DATE_SUB(NOW(), INTERVAL 1 DAY));
SET @slot_intern_id = LAST_INSERT_ID();

INSERT INTO appointments (slot_id, client_id, case_id, assunto, observacoes, status, created_at)
VALUES (@slot_booked_id, @cliente_id, @case1_id, 'Consulta sobre notificacao extrajudicial', 'Demo: atendimento marcado para explicar prazo e resposta.', 'agendado', DATE_SUB(NOW(), INTERVAL 12 HOUR));
SET @appointment_id = LAST_INSERT_ID();

INSERT INTO notifications (user_id, mensagem, lida, created_at)
VALUES
    (@cliente_id, 'Documento notificação-extrajudicial-demo.png analisado com IA.', FALSE, DATE_SUB(NOW(), INTERVAL 4 DAY)),
    (@cliente_id, 'Atendimento agendado com Dra. Marina Costa.', FALSE, DATE_SUB(NOW(), INTERVAL 12 HOUR)),
    (@advogado_id, 'Novo caso de prioridade alta atribuido.', FALSE, DATE_SUB(NOW(), INTERVAL 3 DAY));

INSERT INTO cna_validacao_logs
    (profissional_id, admin_id, acao, status_anterior, status_novo, origem, mensagem, justificativa, created_at)
VALUES
    (@advogado_id, NULL, 'validacao_demo', 'pendente', 'verificado', 'demo_seed', 'Validado automaticamente pelo seed demo.', 'Seed demo para apresentacao.', DATE_SUB(NOW(), INTERVAL 8 DAY)),
    (@estagiario_id, NULL, 'validacao_demo', 'pendente', 'verificado', 'demo_seed', 'Validado automaticamente pelo seed demo.', 'Seed demo para apresentacao.', DATE_SUB(NOW(), INTERVAL 7 DAY)),
    (@pendente_id, NULL, 'cadastro', NULL, 'pendente', 'fallback', 'Pendente: validacao automatica do CNA indisponivel', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO audit_logs
    (user_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
VALUES
    (@cliente_id, 'document.upload', 'document', @doc1_id, JSON_OBJECT('nome_arquivo', 'notificacao-extrajudicial-demo.png', 'analysis_generated', true), '127.0.0.1', 'JusTraduz Demo', DATE_SUB(NOW(), INTERVAL 4 DAY)),
    (@cliente_id, 'document.analyze', 'document', @doc1_id, JSON_OBJECT('analysis_generated', true, 'model', 'gemini-2.5-flash'), '127.0.0.1', 'JusTraduz Demo', DATE_SUB(NOW(), INTERVAL 4 DAY)),
    (@cliente_id, 'case.create', 'case', @case1_id, JSON_OBJECT('prioridade', 'alta', 'advogado_id', @advogado_id), '127.0.0.1', 'JusTraduz Demo', DATE_SUB(NOW(), INTERVAL 3 DAY)),
    (@advogado_id, 'message.send', 'case', @case1_id, JSON_OBJECT('sender_id', @advogado_id), '127.0.0.1', 'JusTraduz Demo', DATE_SUB(NOW(), INTERVAL 2 DAY)),    (@cliente_id, 'schedule.appointment_booked', 'appointment', @appointment_id, JSON_OBJECT('case_id', @case1_id, 'professional_id', @advogado_id), '127.0.0.1', 'JusTraduz Demo', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
    (NULL, 'auth.login_failed', 'user', NULL, JSON_OBJECT('email', 'tentativa@demo.local', 'reason', 'wrong_password'), '127.0.0.1', 'JusTraduz Demo', DATE_SUB(NOW(), INTERVAL 2 HOUR));

SELECT 'Seed demo aplicado. Senha das contas: Demo@2026!' AS resultado;
