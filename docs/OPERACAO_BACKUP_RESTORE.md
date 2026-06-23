# Operacao: Backup, Restore e Healthcheck

## Backup

Execute antes de deploys e periodicamente em producao:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\backup-database.ps1 -EnvFile backend\.env -OutputDir backups
```

O script usa `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` e `BACKUP_ENCRYPTION_PASSWORD`.

Depois de gerar, valide o arquivo:

```powershell
php scripts\check-backup-file.php backups\arquivo.sql
```

Tambem copie os diretorios configurados em:

- `DOCUMENT_STORAGE_PATH`
- `ATTACHMENT_STORAGE_PATH`
- logs operacionais relevantes

## Restore

Restaure primeiro em ambiente limpo ou homologacao:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\restore-database.ps1 -EnvFile backend\.env -BackupPath backups\arquivo.sql
```

Depois restaure documentos e anexos para os caminhos configurados no `.env`.

## Validacao apos restore

- [ ] Login funciona.
- [ ] Usuarios e perfis existem.
- [ ] Documentos aparecem.
- [ ] Download exige autenticacao.
- [ ] Solicitacoes e mensagens aparecem.
- [ ] Auditoria esta preservada.
- [ ] Healthcheck responde.

## Healthcheck

Rota:

```text
/backend/public/index.php?rota=/health
```

Ela verifica aplicacao sem debug, banco, storage, fila, logs de e-mail e eventos de uso.

Para validacao local completa, rode:

```powershell
php scripts\check-local-readiness.php
php scripts\check-orphan-storage.php
```

O check de producao continua separado porque exige HTTPS e dominio real:

```powershell
php scripts\check-production-readiness.php --env=backend/.env
```
