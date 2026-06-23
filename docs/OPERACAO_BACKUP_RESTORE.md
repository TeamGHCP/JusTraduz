# Operação pendente: backup, restore e healthcheck

Estes passos ainda precisam ser executados e evidenciados em ambiente de homologação ou produção.

## Backup pendente

```powershell
powershell -ExecutionPolicy Bypass -File scripts\backup-database.ps1 -EnvFile backend\.env -OutputDir backups
```

Validar o arquivo gerado:

```powershell
php scripts\check-backup-file.php backups\arquivo.sql
```

Também falta definir rotina de cópia para:

- `DOCUMENT_STORAGE_PATH`
- `ATTACHMENT_STORAGE_PATH`
- logs operacionais relevantes

Em produção, esses caminhos devem ficar fora do webroot sempre que possível.

## Restore pendente

Testar primeiro em ambiente limpo:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\restore-database.ps1 -EnvFile backend\.env -BackupPath backups\arquivo.sql
```

Depois restaurar documentos e anexos para os caminhos configurados no `.env`.

## Validação pendente após restore

- [ ] Login funciona.
- [ ] Usuários e perfis existem.
- [ ] Documentos aparecem.
- [ ] Download exige autenticação.
- [ ] Solicitações e mensagens aparecem.
- [ ] Auditoria está preservada.
- [ ] Healthcheck responde.

## Healthcheck pendente em ambiente real

Rota:

```text
/backend/public/index.php?rota=/health
```

Comandos:

```powershell
php scripts\check-local-readiness.php
php scripts\check-orphan-storage.php
php scripts\check-production-readiness.php --env=backend/.env
```
