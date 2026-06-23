# Checklist pendente de release

Use este checklist somente antes de homologação ou deploy real.

## Código e testes

- [ ] Branch atualizada com a base principal.
- [ ] Sem conflitos Git.
- [ ] `php -l` executado nos arquivos PHP alterados.
- [ ] `php scripts/check-references.php` passando.
- [ ] `php backend/tests/run.php` passando.
- [ ] `php scripts/check-production-readiness.php --env=backend/.env` passando no ambiente alvo.

## Configuração

- [ ] `backend/.env.example` compatível com o código.
- [ ] `backend/.env` do ambiente alvo configurado sem placeholders.
- [ ] `APP_DEBUG=false`.
- [ ] `APP_URL` e `HEALTHCHECK_URL` usando HTTPS em produção.
- [ ] Limites `USAGE_DAILY_*` definidos.
- [ ] Chaves Gemini/DataJud/Google configuradas quando necessárias.
- [ ] SMTP real testado, ou `MAIL_LOG_ONLY=true` apenas em ambiente local/demo.

## Banco e storage

- [ ] SQL/migrations revisados.
- [ ] Backup criado antes do deploy.
- [ ] Restore testado em ambiente limpo.
- [ ] `DOCUMENT_STORAGE_PATH` e `ATTACHMENT_STORAGE_PATH` apontando para storage privado fora do webroot em produção.
- [ ] Permissões de diretório conferidas.
- [ ] Upload de PDF/DOCX/anexo validado após deploy.

## PWA e cache

- [ ] `frontend/service-worker.js` com `CACHE_VERSION` atualizado quando assets mudarem.
- [ ] Instalação PWA testada.
- [ ] Página offline testada.
- [ ] Cache antigo limpo após ativação do novo service worker.

## Segurança e operação

- [ ] CSP conferida sem `unsafe-eval`.
- [ ] Headers de segurança conferidos.
- [ ] Healthcheck OK no domínio final.
- [ ] Logs revisados sem segredos.
- [ ] Plano de rollback validado.

## Rollback pendente de ensaio

1. Colocar aplicação em manutenção, se necessário.
2. Voltar o código para a última tag/commit estável.
3. Restaurar banco com `scripts/restore-database.ps1`.
4. Restaurar documentos/anexos a partir do backup de storage.
5. Reiniciar Apache/PHP quando aplicável.
6. Rodar healthcheck e fluxo mínimo: login, dashboard, upload/download e LGPD.
7. Registrar incidente, causa provável e ação corretiva.
