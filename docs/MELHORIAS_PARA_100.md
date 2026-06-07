# Melhorias para o JusTraduz ficar 100% pronto

Data da revisao: 07/06/2026

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

## P1 - Necessario para produto comercial serio

| Melhoria | Por que falta | Criterio de pronto |
|---|---|---|
| Validar Jusbrasil com token real | A estrutura existe, mas chamada real nao foi comprovada no ambiente atual. | Sincronizacao real por CPF/OAB com paginacao, erro amigavel, retry e auditoria. |
| OCR para documentos escaneados | IA sem texto extraido fica fraca para PDF/imagem escaneada. | OCR integrado ao upload com fallback, custo controlado e aviso de qualidade. |
| Antimalware no upload | Validar MIME/extensao nao basta para producao. | Arquivos passam por scanner antes de visualizacao/processamento. |
| Storage externo ou fora do webroot | Storage local e aceitavel no MVP, mas fraco para escala e operacao. | S3/MinIO ou pasta privada fora da raiz publica, com download autorizado. |
| Fila assicrona para IA/OCR/processos | Chamadas longas travam UX e aumentam risco de timeout. | Jobs com status, retry, backoff e tela de acompanhamento. |
| Controle de custos e limites | IA, OCR e APIs externas custam dinheiro. | Limites por usuario/plano, quota, rate limit e painel de consumo. |
| E-mail transacional confiavel | Recuperacao de senha e avisos precisam entregabilidade real. | Provedor configurado, templates revisados, logs e fallback de falha. |
| Exportacao de auditoria | Auditoria so em tela nao basta para operacao/compliance. | Filtros, exportacao CSV/PDF e trilha protegida contra edicao. |

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
5. Validacao real Jusbrasil.
6. Filas assicronas e limites de custo.
7. Planos, cobranca e multiempresa.
8. Relatorios, RBAC granular e API versionada.
9. Acessibilidade, copy final e manual operacional.

## O que nao deve ser vendido como 100%

- Consulta Jusbrasil real enquanto nao houver token validado em ambiente de producao.
- LGPD completa enquanto nao houver processo de retencao, exclusao, exportacao e incidentes.
- SaaS comercial enquanto nao houver billing, multiempresa, suporte, backup e observabilidade.
- Seguranca de producao enquanto nao houver testes automatizados, HTTPS real, antimalware e monitoramento.
- IA juridica conclusiva. A IA deve continuar sendo apoio informativo, nunca parecer final.
