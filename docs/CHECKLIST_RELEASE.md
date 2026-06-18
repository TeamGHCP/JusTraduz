# Checklist de Release

Use este checklist antes de qualquer deploy ou entrega homologada.

## Codigo e testes

- [ ] Branch atualizada com a base principal.
- [ ] Sem conflitos Git.
- [ ] `php -l` executado em todos os arquivos PHP.
- [ ] `php scripts/check-references.php` passando.
- [ ] `php backend/tests/run.php` passando.
- [ ] `php scripts/check-production-readiness.php --env=backend/.env` passando no ambiente alvo.

## Configuracao

- [ ] `backend/.env.example` revisado e compativel com o codigo.
- [ ] `backend/.env` do ambiente alvo configurado sem placeholders.
- [ ] `APP_DEBUG=false`.
- [ ] `APP_URL` e `HEALTHCHECK_URL` usando HTTPS em producao.
- [ ] Limites `USAGE_DAILY_*` definidos.
- [ ] Chaves Gemini/DataJud/Google configuradas apenas quando necessarias.
- [ ] SMTP testado com credenciais reais ou `MAIL_LOG_ONLY=true` apenas em ambiente local/demo.

## Banco e storage

- [ ] SQL/migrations revisados.
- [ ] Backup do banco criado antes do deploy.
- [ ] Restore testado em ambiente limpo ou de homologacao.
- [ ] `DOCUMENT_STORAGE_PATH` e `ATTACHMENT_STORAGE_PATH` fora do webroot em producao.
- [ ] Permissoes de diretorio conferidas.
- [ ] Upload de PDF/DOCX/anexo validado apos deploy.

## PWA e cache

- [ ] `frontend/service-worker.js` teve `CACHE_VERSION` atualizado quando assets mudaram.
- [ ] Instalacao PWA testada.
- [ ] Pagina offline testada.
- [ ] Cache antigo limpo apos ativacao do novo service worker.

## Seguranca e operacao

- [ ] CSP conferida sem `unsafe-eval`.
- [ ] Headers de seguranca conferidos.
- [ ] Healthcheck OK.
- [ ] Logs revisados sem segredos.
- [ ] Plano de rollback abaixo validado.

## Rollback

1. Colocar aplicacao em manutencao, se necessario.
2. Voltar o codigo para a ultima tag/commit estavel.
3. Restaurar banco com `scripts/restore-database.ps1` usando o backup pre-deploy.
4. Restaurar documentos/anexos a partir do backup de storage.
5. Reiniciar Apache/PHP quando aplicavel.
6. Rodar healthcheck e fluxo minimo: login, dashboard, upload/download, LGPD.
7. Registrar incidente, causa provavel e acao corretiva.
