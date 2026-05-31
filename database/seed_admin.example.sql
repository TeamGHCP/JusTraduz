USE justraduz;

-- ATENÇÃO: este arquivo é um exemplo. NÃO o execute em produção sem revisar.
-- Antes de executar, gere um hash seguro com:
-- C:\xampp\php\php.exe -r "echo password_hash('SENHA_FORTE_AQUI', PASSWORD_DEFAULT);"
-- Troque o e-mail e o hash abaixo antes de importar.
INSERT INTO users (nome, email, senha, tipo, status)
SELECT 'Administrador', 'admin@justraduz.local', '<HASH_GERADO_COM_PASSWORD_HASH>', 'admin', 'ativo'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'admin@justraduz.local'
);
