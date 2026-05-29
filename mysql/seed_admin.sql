USE justraduz;

-- Garante o admin padrão se ele ainda não existir.
-- E-mail: admin@justraduz.com
-- Senha: admin
INSERT INTO users (nome, email, senha, tipo, status)
SELECT 'Administrador', 'admin@justraduz.com', '$2y$10$gFuTy/IWe/Z/o/fcrZ6y1eYq4MrDaQh//Gs0voZMK7Fp0Aintw4OK', 'admin', 'ativo'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'admin@justraduz.com'
);
