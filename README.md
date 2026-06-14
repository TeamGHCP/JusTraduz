# JusTraduz

Sistema PHP/MySQL para envio de documentos juridicos, analise em linguagem simples, solicitacao de ajuda juridica, agenda, chat, validacao OAB manual e consulta processual por numero CNJ via DataJud.

## Documentacao

A documentacao ativa fica em `docs/`:

- `docs/README.md`
- `docs/MELHORIAS_PARA_100.md`
- `docs/PRODUCAO_P0.md`
- `docs/LGPD_E_REVISAO_JURIDICA_P0.md`
- `docs/P1_OPERACIONAL.md`
- `docs/O_QUE_FALTA_AGORA.md`

Os documentos antigos de banca, pitch, plano modular e status foram removidos para evitar varias versoes da verdade. O documento ativo mostra apenas o que ainda falta para o sistema ficar pronto para uso real/comercial.

## Como rodar localmente

1. Inicie Apache e MySQL pelo XAMPP.
2. Copie o arquivo de ambiente:

```powershell
Copy-Item backend\.env.example backend\.env
```

3. Ajuste `backend/.env` com banco, SMTP e chaves externas. Nao versionar esse arquivo.
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

## Variaveis de ambiente

Use `backend/.env.example` como modelo. O arquivo `backend/.env` local fica ignorado pelo Git.

Principais grupos:

- Banco: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
- E-mail: `MAIL_*`.
- IA: `GEMINI_API_KEY`, `GEMINI_MODEL`.
- Google OAuth: `GOOGLE_*`.
- DataJud/CNJ: `DATAJUD_*`.

## Limpeza aplicada

- Paginas HTML antigas da area logada foram removidas.
- Scripts SQL incrementais antigos foram removidos; ficaram apenas os dois instaladores consolidados.
- Documentos antigos foram consolidados em `docs/MELHORIAS_PARA_100.md`.
- Uploads locais orfaos fora do seed demo foram removidos.

## Qualidade e producao

Suite P0 local:

```powershell
C:\xampp\php\php.exe backend\tests\run.php
```

Checagem de referencias:

```powershell
C:\xampp\php\php.exe scripts\check-references.php
```

Prontidao P0 de producao:

```powershell
C:\xampp\php\php.exe scripts\check-production-readiness.php --env=backend/.env
```

Health check:

```text
/backend/public/index.php?rota=/health
```
