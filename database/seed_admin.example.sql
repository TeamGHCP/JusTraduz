USE justraduz;

-- Admin local para desenvolvimento/apresentacao.
-- Login: admin@justraduz.local
-- Senha: Admin@2026!
-- Nao use estas credenciais em producao.

INSERT INTO users (nome, email, senha, tipo, status)
VALUES (
    'NOME_ADMIN',
    'EMAIL_ADMIN_LOCAL',
    'HASH_SENHA_ADMIN',
    'admin',
    'ativo'
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    senha = VALUES(senha),
    tipo = 'admin',
    status = 'ativo';
