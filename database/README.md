# Banco de dados

## Instalação limpa

1. Importe `database/schema.sql`.
2. Crie um administrador local a partir de uma cópia revisada de `database/seed_admin.example.sql`.
3. Nunca use senha padrão em ambiente compartilhado ou produção.

## Atualização de banco existente

Execute as migrations em ordem cronológica/funcional:

1. `migration_telefone.sql`
2. `migration_profile_photo.sql`
3. `migration_password_reset_codes.sql`
4. `migration_oab.sql`
5. `migration_ai_metadata.sql`
6. `migration_indexes_integrity.sql`

Depois de aplicar uma migration, registre a versão em `schema_migrations` se estiver fazendo controle manual.
