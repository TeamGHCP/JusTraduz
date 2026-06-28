# Checklist pendente de release

Use este checklist somente antes de homologacao ou deploy real.

## Codigo e testes

- [ ] Branch atualizada com a base principal.
- [ ] Sem conflitos Git.
- [ ] `php -l` executado nos arquivos PHP alterados quando houver mudanca de codigo.
- [ ] `php scripts/check-references.php` passando no commit de release.
- [ ] `php backend/tests/run.php` passando no commit de release.
- [ ] `php scripts/check-production-readiness.php --env=backend/.env` passando no ambiente alvo.
- [ ] `php scripts/operational-health-report.php --output=storage-private/reports/saude-operacional.md` executado no ambiente alvo.

## Configuracao

- [ ] `backend/.env` do ambiente alvo configurado sem placeholders.
- [ ] `APP_DEBUG=false`.
- [ ] `APP_URL` e `HEALTHCHECK_URL` usando HTTPS em producao.
- [ ] Limites `USAGE_DAILY_*`, `USAGE_MONTHLY_*` e `PUBLIC_API_RATE_LIMIT_PER_MINUTE` definidos.
- [ ] Chaves Gemini/DataJud/Google/Asaas configuradas quando necessarias.
- [ ] SMTP real testado, ou `MAIL_LOG_ONLY=true` apenas em ambiente local/demo.

## Banco e storage

- [ ] SQL consolidado revisado.
- [ ] Backup de banco criado antes do deploy.
- [ ] Backup de storage criado antes do deploy.
- [ ] Restore de banco e storage testado em ambiente limpo.
- [ ] `DOCUMENT_STORAGE_PATH` e `ATTACHMENT_STORAGE_PATH` apontando para storage privado fora do webroot em producao.
- [ ] Permissoes de diretorio conferidas.
- [ ] Upload de PDF/DOCX/anexo validado apos deploy.

## PWA e cache

- [ ] `frontend/service-worker.js` com `CACHE_VERSION` atualizado quando assets mudarem.
- [ ] Instalacao PWA testada.
- [ ] Pagina offline testada.
- [ ] Cache antigo limpo apos ativacao do novo service worker.

## Seguranca e operacao

- [ ] CSP conferida sem `unsafe-eval`.
- [ ] Headers de seguranca conferidos.
- [ ] Healthcheck OK no dominio final.
- [ ] Relatorio operacional revisado sem fila, storage ou SLA inesperado.
- [ ] Logs revisados sem segredos.
- [ ] Plano de rollback validado.

## Rollback pendente de ensaio

1. Colocar aplicacao em manutencao, se necessario.
2. Voltar o codigo para a ultima tag/commit estavel.
3. Restaurar banco com `scripts/restore-database.ps1`.
4. Restaurar documentos/anexos com `scripts/restore-storage.ps1`.
5. Reiniciar Apache/PHP quando aplicavel.
6. Rodar healthcheck e fluxo minimo: login, dashboard, upload/download e LGPD.
7. Registrar incidente, causa provavel e acao corretiva.
