# Operacao de backup, restore e healthcheck

Execute estes passos primeiro em uma base temporaria e registre a evidencia antes de usar em producao.

## Backup do banco

```powershell
powershell -ExecutionPolicy Bypass -File scripts\backup-database.ps1 -EnvFile backend\.env -OutputDir backups
```

Validar o arquivo gerado:

```powershell
php scripts\check-backup-file.php backups\arquivo.sql
```

Para backup criptografado (`.enc`), configure `BACKUP_ENCRYPTION_PASSWORD` e valide apos descriptografar em ambiente controlado.

## Backup do storage

O projeto ja possui rotina para copiar documentos e anexos configurados em:

- `DOCUMENT_STORAGE_PATH`
- `ATTACHMENT_STORAGE_PATH`

Comando:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\backup-storage.ps1 -EnvFile backend\.env -OutputDir backups
```

Em producao, esses caminhos devem ficar fora do webroot sempre que possivel.

## Restore do banco

Testar primeiro em ambiente limpo:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\restore-database.ps1 -EnvFile backend\.env -BackupPath backups\arquivo.sql.enc -TargetDatabase justraduz_restore_test
```

Nunca use o nome da base principal no primeiro teste de restore.

## Restore do storage

Por padrao o restore mescla arquivos no destino. Use `-ClearTarget` somente em ambiente controlado quando quiser substituir o conteudo anterior.

```powershell
powershell -ExecutionPolicy Bypass -File scripts\restore-storage.ps1 -EnvFile backend\.env -BackupPath backups\justraduz-storage-arquivo.zip
```

## Validacao apos restore

- [ ] Login funciona.
- [ ] Usuarios e perfis existem.
- [ ] Documentos aparecem.
- [ ] Download exige autenticacao.
- [ ] Solicitacoes e mensagens aparecem.
- [ ] Auditoria esta preservada.
- [ ] Healthcheck responde.

## Healthcheck em ambiente real

Rota:

```text
/backend/public/index.php?rota=/health
```

Comandos:

```powershell
php scripts\check-local-readiness.php
php scripts\check-orphan-storage.php
php scripts\check-production-readiness.php --env=backend/.env
php scripts\operational-health-report.php --output=storage-private\reports\saude-operacional.md
```
