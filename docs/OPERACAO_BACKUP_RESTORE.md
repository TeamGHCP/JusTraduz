# Operacao: Backup, Restore e Healthcheck

## Backup

Execute antes de deploys e periodicamente em producao:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\backup-database.ps1 -EnvFile backend\.env -OutputDir backups
```

O script usa `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` e `BACKUP_ENCRYPTION_PASSWORD`.

Tambem copie os diretorios configurados em:

- `DOCUMENT_STORAGE_PATH`
- `ATTACHMENT_STORAGE_PATH`
- logs operacionais relevantes

## Restore

Restaure primeiro em ambiente limpo ou homologacao:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\restore-database.ps1 -EnvFile backend\.env -BackupFile backups\arquivo.sql
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
