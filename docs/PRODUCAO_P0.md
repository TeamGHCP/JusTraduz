# Producao P0 - JusTraduz

Este checklist cobre o minimo operacional antes de expor o JusTraduz a usuarios reais.

## Servidor e HTTPS

- Usar HTTPS real com certificado valido.
- Redirecionar HTTP para HTTPS no proxy/servidor.
- Usar `docs/apache-justraduz-production.conf` como modelo de VirtualHost Apache.
- Manter `APP_DEBUG=false` em `backend/.env`.
- Configurar `APP_ENV=production` e `APP_URL` com a URL publica.
- Confirmar HSTS pelo header `Strict-Transport-Security` acessando via HTTPS.
- Revisar CSP em `backend/app/support/security.php` sempre que adicionar CDN, iframe ou script externo.
- Manter `backend/storage/*` fora de listagem publica; os arquivos ja possuem `.htaccess`, e downloads devem passar pelos controladores.

## CI/CD

O workflow em `.github/workflows/ci.yml` executa em cada push e pull request:

- lint de PHP;
- import dos SQLs consolidados em MySQL;
- testes automatizados em SQLite;
- checagem de referencias locais.

Comando local equivalente:

```powershell
C:\xampp\php\php.exe backend\tests\run.php
C:\xampp\php\php.exe scripts\check-references.php
C:\xampp\php\php.exe scripts\check-production-readiness.php --env=backend/.env
```

## Observabilidade

Health check:

```text
/backend/public/index.php?rota=/health
```

O retorno `status=ok` exige:

- banco respondendo;
- `APP_DEBUG` desligado;
- pastas de storage esperadas presentes.

Monitoramento recomendado:

- consultar `HEALTHCHECK_URL` a cada 1 minuto;
- alertar se o endpoint retornar HTTP 5xx ou `status=degraded` por 2 ciclos;
- centralizar logs do PHP/Apache e filtrar por `Uncaught exception`, `MailerService`, `DataJud`, `Gemini` e `Health database check failed`;
- revisar diariamente `audit_logs` para falhas de login, OAB, IA, DataJud, upload e admin.

## Verificacao P0 antes do deploy

Rodar:

```powershell
C:\xampp\php\php.exe scripts\check-production-readiness.php --env=backend/.env
```

O comando falha quando `APP_DEBUG` esta ligado, URLs publicas nao usam HTTPS, arquivos obrigatorios P0 estao ausentes ou protecoes basicas do Apache/storage nao existem.

## Backup

Criar backup:

```powershell
.\scripts\backup-database.ps1 -OutputDir backups -RetentionDays 14
```

Se `BACKUP_ENCRYPTION_PASSWORD` estiver definido em `backend/.env`, o arquivo sera criptografado como `.sql.enc`.

Regras minimas:

- executar ao menos 1 vez por dia;
- reter 14 dias, ou prazo definido em contrato;
- guardar copia fora do servidor principal;
- restringir acesso ao diretorio de backup;
- nunca versionar backups.

## Restore testado

Teste mensal obrigatorio em ambiente separado:

```powershell
.\scripts\restore-database.ps1 -BackupPath backups\justraduz-YYYYMMDD-HHMMSS.sql.enc
```

Checklist do teste:

- restauracao concluiu sem erro;
- login admin funciona;
- dashboard carrega;
- documentos, solicitacoes, chat, agenda e processos aparecem;
- data, arquivo restaurado, responsavel e resultado foram registrados no controle operacional.
