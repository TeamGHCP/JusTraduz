-- Células de índices para as chaves estrangeiras de mensagens e tarefas
CREATE INDEX idx_messages_case_id ON messages(case_id);
CREATE INDEX idx_messages_sender_id ON messages(sender_id);
CREATE INDEX idx_tasks_case_id ON tasks(case_id);
