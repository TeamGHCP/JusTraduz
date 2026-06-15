# Melhorias para o JusTraduz ficar 100% pronto

Data da revisao: 07/06/2026

Nota atual: depois da entrega P0/P1, use `docs/O_QUE_FALTA_AGORA.md` como lista operacional das pendencias reais restantes.

Este documento lista apenas o que ainda falta para o JusTraduz sair de MVP demonstravel e virar um sistema pronto para uso real/comercial. A leitura honesta e simples: para banca, o produto ja tem corpo; para producao, ainda faltam controles que protegem usuario, operacao e negocio.

## P0 - Obrigatorio antes de producao

| Melhoria | Por que falta | Criterio de pronto |
|---|---|---|
| Testes automatizados de permissao | Hoje a seguranca depende de smoke manual. Isso nao escala e deixa regressao passar. | Cobertura de login, CSRF, perfil, OAB pendente, documento de outro usuario, admin, chat, agenda e processos. |
| Testes automatizados de fluxos criticos | Documento, solicitacao, chat e agenda sao o coracao do produto. | Suite rodando em CI com banco de teste e fixtures resetaveis. |
| Pipeline de CI/CD | Sem CI, qualquer alteracao pode quebrar PHP, SQL ou rotas sem aviso. | Lint PHP, import SQL, testes e checagem de referencias em cada push. |
| HTTPS real e configuracao de servidor | Ambiente local tem bloqueios, mas producao precisa TLS, headers e servidor revisado. | Deploy com HTTPS, HSTS, CSP revisada, logs seguros e `APP_DEBUG=false`. |
| Backup e restore testado | Backup sem restore testado e ilusao. | Rotina automatica, criptografia, retencao e teste de restauracao documentado. |
| Observabilidade | Hoje falhas de IA, e-mail, banco e API externa podem passar escondidas. | Health check, logs centralizados, alertas e dashboard de erros. |
| Politica LGPD operacional | Existem controles tecnicos, mas ainda falta processo formal. | Retencao, exclusao, exportacao, consentimento, incidentes e operadores documentados e implementados. |
| Revisao juridica dos termos | Produto juridico nao pode prometer demais nem tratar dado sensivel sem base clara. | Termos, privacidade e disclaimers revisados por profissional. |

### Entregaveis P0 no repositorio

- Testes automatizados: `backend/tests/run.php`, `backend/tests/PermissionAndCriticalFlowsTest.php` e `backend/tests/AiGuardrailsTest.php`.
- CI: `.github/workflows/ci.yml` com lint PHP, import dos SQLs, testes e checagem de referencias.
- Observabilidade: rota `/backend/public/index.php?rota=/health` e checklist em `docs/PRODUCAO_P0.md`.
- Backup/restore: `scripts/backup-database.ps1`, `scripts/restore-database.ps1` e rotina documentada em `docs/PRODUCAO_P0.md`.
- LGPD operacional: exportacao e encerramento de conta em `backend/app/controllers/PrivacyController.php`, controles no perfil e processo em `docs/LGPD_E_REVISAO_JURIDICA_P0.md`.
- HTTPS/configuracao de servidor: headers ja aplicados em codigo, modelo Apache em `docs/apache-justraduz-production.conf` e verificacao em `scripts/check-production-readiness.php`; a ativacao do certificado e HSTS real deve ser conferida no ambiente de hospedagem.
- Revisao juridica dos termos: termos, privacidade e disclaimers estao preparados; a aprovacao profissional deve ser registrada antes da venda comercial.

## P1 - Necessario para produto comercial serio

| Melhoria | Por que falta | Criterio de pronto |
|---|---|---|
| Evoluir consulta processual | A versao inicial usa DataJud por numero CNJ. Consulta por CPF fica fora do MVP por depender de API juridica paga, contrato, consentimento e auditoria reforcada. | DataJud validado em producao, cache auditavel por CNJ e decisao formal para futura API paga por CPF. |
| OCR para documentos escaneados | IA sem texto extraido fica fraca para PDF/imagem escaneada. | OCR integrado ao upload com fallback, custo controlado e aviso de qualidade. |
| Antimalware no upload | Validar MIME/extensao nao basta para producao. | Arquivos passam por scanner antes de visualizacao/processamento. |
| Storage externo ou fora do webroot | Storage local e aceitavel no MVP, mas fraco para escala e operacao. | S3/MinIO ou pasta privada fora da raiz publica, com download autorizado. |
| Fila assicrona para IA/OCR/processos | Chamadas longas travam UX e aumentam risco de timeout. | Jobs com status, retry, backoff e tela de acompanhamento. |
| Controle de custos e limites | IA, OCR e APIs externas custam dinheiro. | Limites por usuario/plano, quota, rate limit e painel de consumo. |
| E-mail transacional confiavel | Recuperacao de senha e avisos precisam entregabilidade real. | Provedor configurado, templates revisados, logs e fallback de falha. |
| Exportacao de auditoria | Auditoria so em tela nao basta para operacao/compliance. | Filtros, exportacao CSV/PDF e trilha protegida contra edicao. |

### Entregaveis P1 no repositorio

- DataJud/CNJ: cache auditavel por CNJ em `external_processes`, TTL por `DATAJUD_CACHE_TTL_HOURS` e decisao formal de manter CPF fora do escopo em `docs/P1_OPERACIONAL.md`.
- OCR: `backend/app/services/OcrService.php`, variaveis `OCR_*` e fallback de qualidade quando OCR nao esta configurado ou nao extrai texto.
- Antimalware: `backend/app/services/UploadScannerService.php`, aplicado a documentos e anexos, com suporte opcional a `CLAMAV_BINARY`.
- Storage privado: `backend/app/services/StorageService.php`, suporte a `DOCUMENT_STORAGE_PATH`, `ATTACHMENT_STORAGE_PATH` e referencias `private://...` fora do webroot.
- Fila assincrona: tabela `job_queue`, `backend/app/services/JobQueueService.php` e worker `scripts/run-jobs.php`.
- Controle de custos e limites: tabela `usage_events`, `backend/app/services/UsageLimiter.php` e limites diarios por variaveis `USAGE_DAILY_*`.
- E-mail confiavel: `mail_logs`, `MAIL_LOG_ONLY` para homologacao e registro de sucesso/falha no `MailerService`.
- Exportacao de auditoria: rota `/backend/public/index.php?rota=/admin/audit/export` e botao CSV em `frontend/pages/admin/auditoria.php`.
- Operacao P1: guia em `docs/P1_OPERACIONAL.md` e checagem ampliada em `scripts/check-production-readiness.php`.

## P2 - Para escalar como SaaS

| Melhoria | Por que falta | Criterio de pronto |
|---|---|---|
| Planos e cobranca | Sem monetizacao, nao e SaaS comercial completo. | Planos, assinatura, pagamento, limite de uso e bloqueio por inadimplencia. |
| Multiempresa/escritorios | Um unico espaco global limita uso por escritorios. | Tabela de organizacoes, membros, papeis, convites e isolamento de dados. |
| RBAC granular | Perfis atuais sao suficientes para MVP, mas rigidos para equipe real. | Permissoes por recurso: documentos, casos, agenda, auditoria e admin. |
| Relatorios gerenciais | Escritorios precisam enxergar produtividade e risco. | Relatorios por periodo, profissional, status, SLA e origem de demanda. |
| SLA e prioridade operacional | Casos criticos precisam regra clara de atendimento. | Prazos, alertas, escalonamento e painel de vencimentos. |
| API versionada | Rotas atuais funcionam, mas nao tem contrato versionado. | `/api/v1`, padrao de resposta, erros, docs e compatibilidade. |
| Internacionalizacao/acessibilidade | Para uso publico, acessibilidade vira requisito serio. | WCAG AA nas telas principais, navegacao por teclado e contraste validado. |

### Entregaveis P2 no repositorio

- Planos e cobranca: `plans`, `subscriptions`, `payment_events`, tela `frontend/subir-plano.php` exclusiva para clientes, admin `frontend/admin/assinaturas.php` e checkout manual em `/backend/public/index.php?rota=/billing/subscribe`.
- Multiempresa/escritorios: `organizations`, `organization_members`, `organization_invites`, admin `frontend/admin/organizacoes.php` e preenchimento de `organization_id` em documentos, casos, agenda e agendamentos.
- RBAC granular: `user_permissions`, `backend/app/services/RbacService.php` e admin `frontend/admin/permissoes.php`.
- Relatorios gerenciais: `frontend/admin/relatorios.php` e endpoint `/backend/public/index.php?rota=/api/v1/reports`.
- SLA e prioridade operacional: campos `sla_due_at` e `sla_status` em `cases`, calculo em `SlaService` e atualizacao por status/prioridade.
- API versionada: endpoints `/api/v1/me`, `/api/v1/cases` e `/api/v1/reports` com envelope `api_version`.
- Acessibilidade/internacionalizacao: novas telas reaproveitam componentes acessiveis existentes, labels de formulario, badges sem depender apenas de cor e navegacao via sidebar.

## P3 - Polimento final

| Melhoria | Por que falta | Criterio de pronto |
|---|---|---|
| Teste visual em mobile/tablet/projetor | Responsividade foi tratada, mas precisa matriz real de dispositivos. | Checklist visual aprovado em resolucoes comuns. |
| Estados vazios e de erro em todas as telas | Telas sem dados podem parecer quebradas. | Empty/loading/error states padronizados em app e admin. |
| Revisao de copy juridica | Texto juridico sensivel precisa ser preciso. | Linguagem sem promessa de parecer, sem falsa automacao e com orientacao clara. |
| Manual operacional interno | Admin precisa saber operar validacao, auditoria, bloqueios e incidentes. | Guia curto para rotina diaria, incidentes e manutencao. |
| Scripts de manutencao | Operacao real precisa tarefas repetiveis. | Comandos para backup, restore, limpeza de arquivos orfaos e verificacao de saude. |

## Ordem recomendada

1. Testes automatizados e CI.
2. LGPD operacional, termos e revisao juridica.
3. Backup/restore, observabilidade e HTTPS real.
4. OCR, antimalware e storage externo.
5. Validacao real DataJud em producao.
6. Filas assicronas e limites de custo.
7. Planos, cobranca e multiempresa.
8. Relatorios, RBAC granular e API versionada.
9. Acessibilidade, copy final e manual operacional.

## O que nao deve ser vendido como 100%

- Consulta por CPF. Ela depende de API juridica paga, CNPJ/contrato, consentimento LGPD e logs de auditoria.
- LGPD completa sem execucao operacional real, registro de incidentes quando ocorrerem e revisao do responsavel juridico/LGPD.
- SaaS comercial enquanto nao houver billing, multiempresa, suporte, backup e observabilidade.
- Seguranca de producao sem HTTPS ativo no servidor real, monitoramento ligado, backups restaurados em teste e antimalware no upload.
- IA juridica conclusiva. A IA deve continuar sendo apoio informativo, nunca parecer final.
