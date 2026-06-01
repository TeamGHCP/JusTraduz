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
7. `migration_google_oauth.sql`

Depois de aplicar uma migration, registre a versão em `schema_migrations` se estiver fazendo controle manual.

## Login com Google OAuth

Para habilitar o login com Google em ambiente local:

1. Aplique `database/migration_google_oauth.sql`.
2. No Google Cloud, crie um cliente OAuth do tipo `Aplicativo da Web`.
3. Em `Origens JavaScript autorizadas`, adicione:

```text
http://localhost:9999
```

4. Em `URIs de redirecionamento autorizados`, adicione exatamente:

```text
http://localhost:9999/JusTraduz/backend/public/index.php?rota=/auth/google/callback
```

5. No arquivo `backend/.env`, configure:

```env
GOOGLE_CLIENT_ID=seu_client_id
GOOGLE_CLIENT_SECRET=seu_client_secret
GOOGLE_REDIRECT_URI=http://localhost:9999/JusTraduz/backend/public/index.php?rota=/auth/google/callback
```

6. Rode o sistema pelo mesmo host e porta configurados no Google Cloud:

```text
http://localhost:9999/JusTraduz/frontend/index.html
```

O `GOOGLE_REDIRECT_URI` precisa ser idêntico à URI cadastrada no Google Cloud. Se a porta, protocolo (`http`/`https`) ou caminho mudar, atualize os dois lugares.

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
