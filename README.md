# JusTraduz

Sistema PHP/MySQL para envio de documentos jurídicos, análise em linguagem simples, solicitação de ajuda jurídica, agenda, chat, validação OAB manual e consulta processual por número CNJ via DataJud.

## Documentação

A documentação ativa fica em `docs/`:

- `docs/README.md`
- `docs/O_QUE_FALTA_AGORA.md`
- `docs/REGISTRO_REVISAO_JURIDICA.md`
- `docs/apache-justraduz-production.conf`

Os documentos antigos e os guias de entregas já implementadas foram removidos para evitar várias versões da verdade. A documentação ativa mostra apenas o que ainda falta para o sistema ficar pronto para uso real/comercial.

## Como rodar localmente

1. Inicie Apache e MySQL pelo XAMPP.
2. Copie o arquivo de ambiente:

```powershell
Copy-Item backend\.env.example backend\.env
```

3. Ajuste `backend/.env` com banco, SMTP e chaves externas. Não versionar esse arquivo.
4. Importe o banco sem demo:

```powershell
Get-Content database\justraduz_completo_sem_demo.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -h localhost -u root
```

5. Para demo local, use o instalador completo com dados:

```powershell
Get-Content database\justraduz_completo_com_demo.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -h localhost -u root
```

As contas `@justraduz.demo` usam a senha:

```text
Demo@2026!
```

6. Acesse pelo Apache/XAMPP:

```text
http://localhost/JusTraduz/frontend/index.html
```

Ou, se quiser usar o servidor PHP embutido:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8080 public-router.php
```

```text
http://127.0.0.1:8080/frontend/index.html
```

## Variáveis de ambiente

Use `backend/.env.example` como modelo. O arquivo `backend/.env` local fica ignorado pelo Git.

Principais grupos:

- Banco: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
- E-mail: `MAIL_*`.
- IA: `GEMINI_API_KEY`, `GEMINI_MODEL`.
- Google OAuth: `GOOGLE_*`.
- DataJud/CNJ: `DATAJUD_*`.

## Limpeza aplicada

- Páginas HTML antigas da área logada foram removidas.
- Scripts SQL incrementais antigos foram removidos; ficaram apenas os dois instaladores consolidados.
- Documentos antigos e entregas já implementadas foram consolidados/removidos; as pendências ficam em `docs/O_QUE_FALTA_AGORA.md`.
- Uploads locais órfãos fora do seed demo foram removidos.

## Qualidade e produção

Suite P0 local:

```powershell
C:\xampp\php\php.exe backend\tests\run.php
```

Checagem de referências:

```powershell
C:\xampp\php\php.exe scripts\check-references.php
```

Prontidão P0 de produção:

```powershell
C:\xampp\php\php.exe scripts\check-production-readiness.php --env=backend/.env
```

Health check:

```text
/backend/public/index.php?rota=/health
```
