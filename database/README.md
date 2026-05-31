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

## Observações sobre reexecução

- Avisos `1050 Table ... already exists` em comandos com `CREATE TABLE IF NOT EXISTS` não são falha; indicam apenas que a tabela já estava criada.
- No MySQL Workbench com safe update mode ativo, updates de migração precisam usar uma coluna chave no `WHERE`. As migrations devem manter filtros como `id > 0` quando atualizarem linhas existentes.

## Banco demo

Para popular uma apresentação local, execute `database/seed_demo.sql` depois do schema e das migrations:

```powershell
Get-Content database\seed_demo.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -h localhost -u root justraduz
```

O seed é resetável e recria apenas contas com e-mail `@justraduz.demo`.

Credenciais e roteiro ficam em `docs/CREDENCIAIS_DEMO.md` e `docs/DEMO.md`.
