# JusTraduz

Sistema PHP/MySQL para envio de documentos juridicos, analise em linguagem simples, solicitacao de ajuda juridica, agenda, chat, validacao OAB manual e consulta processual por numero CNJ via DataJud.

## Documentacao

A documentacao ativa fica em `docs/`:

- `docs/README.md`
- `docs/O_QUE_FALTA_AGORA.md`
- `docs/REGISTRO_REVISAO_JURIDICA.md`
- `docs/apache-justraduz-production.conf`
- `docs/CONFIGURAR_CLAMAV.md`
- `docs/CHECKLIST_RELEASE.md`
- `docs/CHECKLIST_APRESENTACAO_SA.md`
- `docs/OPERACAO_BACKUP_RESTORE.md`
- `docs/ROTEIRO_QA_MANUAL.md`
- `docs/STATUS_MODULOS_2026-06-22.md`

Os documentos antigos e os guias de entregas ja implementadas foram removidos para evitar varias versoes da verdade. A documentacao ativa mostra apenas o que ainda falta para o sistema ficar pronto para uso real/comercial.

## Como rodar localmente

1. Inicie Apache e MySQL pelo XAMPP.
2. Copie o arquivo de ambiente:

```powershell
Copy-Item backend\.env.example backend\.env
```

3. Ajuste `backend/.env` com banco, SMTP e chaves externas. Nao versionar esse arquivo.
   Para XAMPP local, confira principalmente:

```env
APP_ENV=local
APP_URL=http://localhost/JusTraduz
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=justraduz
DB_USER=root
DB_PASS=
MAIL_LOG_ONLY=true
HEALTHCHECK_URL=http://localhost/JusTraduz/backend/public/index.php?rota=/health
```

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
- Operacao: `HEALTHCHECK_TOKEN`, `BACKUP_*`, `CLAMAV_BINARY`.
- Storage: `DOCUMENT_STORAGE_PATH`, `ATTACHMENT_STORAGE_PATH`, `PROFILE_PHOTO_STORAGE_PATH`. Para documentos e anexos, use `storage-private/...` no ambiente local e, em producao, prefira um caminho absoluto fora do webroot.
- Upload seguro: `CLAMAV_BINARY` e `CLAMAV_TIMEOUT_SECONDS`. O sistema funciona sem ClamAV usando heuristica interna, mas para producao real siga `docs/CONFIGURAR_CLAMAV.md`.

## Limpeza aplicada

- Paginas HTML antigas da area logada foram removidas.
- Scripts SQL incrementais antigos foram removidos; ficaram apenas os dois instaladores consolidados.
- Documentos antigos e entregas ja implementadas foram consolidados/removidos; as pendencias ficam em `docs/O_QUE_FALTA_AGORA.md`.
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

Prontidao local:

```powershell
C:\xampp\php\php.exe scripts\check-local-readiness.php
```

Storage orfao:

```powershell
C:\xampp\php\php.exe scripts\check-orphan-storage.php
```

Prontidao P0 de producao:

```powershell
C:\xampp\php\php.exe scripts\check-production-readiness.php --env=backend/.env
```

Para validar apenas o template versionado no CI ou em revisao local:

```powershell
C:\xampp\php\php.exe scripts\check-production-readiness.php --env=backend/.env.example --allow-placeholders
```

Health check:

```text
/backend/public/index.php?rota=/health
```

Se `HEALTHCHECK_TOKEN` estiver configurado, envie o token por `?token=...` ou pelo header `X-Healthcheck-Token`.

## Revisao Atual

Ultima revisao local: 18/06/2026.

Status automatizado esperado:

- Sintaxe PHP: OK.
- Testes backend: OK.
- Referencias internas: OK.
- Template `backend/.env.example`: OK com placeholders permitidos.
- PWA estrutural: OK, com `CACHE_VERSION` atualizado quando assets mudam.

Pendencias que ainda dependem de ambiente real ou validacao manual:

1. Configurar `backend/.env` de producao com `APP_DEBUG=false`, URLs HTTPS reais, SMTP, Google OAuth, Gemini/DataJud, backup e ClamAV.
2. Validar `/backend/public/index.php?rota=/health` com MySQL ativo e, se exposto, com `HEALTHCHECK_TOKEN`.
3. Executar restore real de backup em ambiente limpo.
4. Fazer QA manual dos fluxos principais com `docs/ROTEIRO_QA_MANUAL.md`.
5. Testar SMTP real, entregabilidade e logs de erro.
6. Validar integracoes externas em sucesso, falha, timeout e limite de uso.
7. Fazer matriz visual mobile/tablet/desktop e instalacao PWA em navegador real.
8. Ativar monitoramento, alertas e plano de rollback antes de producao.

O sistema nao deve ser considerado pronto para producao enquanto essas validacoes externas e operacionais nao estiverem concluidas.
