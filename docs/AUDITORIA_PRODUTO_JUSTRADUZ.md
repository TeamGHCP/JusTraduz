# Auditoria de Produto e Plano de Evolução do JusTraduz

Data da auditoria: 31/05/2026  
Meta de apresentação: 07/07/2026  
Escopo analisado: PHP, MySQL, frontend HTML/CSS/JS/PHP, rotas, banco, uploads, IA, OAB/CNA, agenda, chat, admin, documentação e potencial comercial.

Observação sobre SmartCart: não encontrei o projeto SmartCart no workspace `c:\xampp\htdocs`. A comparação abaixo considera o SmartCart como referência de projeto acadêmico/comercial de e-commerce, isto é, um sistema mais comum, fácil de explicar e geralmente mais visual, mas menos diferenciado como produto.

## Correções aplicadas - Parte 1 Segurança

Aplicado em 31/05/2026:

- `GET /schedule/calendar` passou a exigir usuário logado.
- Calendário agora filtra dados por perfil: cliente vê somente horários livres; advogado/estagiário veem somente a própria agenda; admin pode consultar a visão geral.
- Documentos não ficam mais liberados para qualquer perfil não cliente/não advogado; admin mantém visão global e estagiário deixa de herdar acesso amplo.
- Chat/casos/tarefas deixaram de tratar estagiário como admin.
- Dashboard do estagiário passou a mostrar apenas agenda própria e aviso de permissão limitada.
- Login comum passou a usar mensagem genérica de credenciais inválidas, reduzindo enumeração de e-mail.
- Lint PHP dos arquivos alterados: sem erros de sintaxe.

## Correções aplicadas - Fase 2 Frontend e experiência visual

Aplicado em 31/05/2026:

- Landing page redesenhada para apresentar o JusTraduz como produto, com prévia visual do sistema e pitch mais comercial.
- Dashboard do cliente ganhou uma área de comando com jornada clara: enviar documento, consultar análise, pedir ajuda e acompanhar atendimento.
- Upload de documento ganhou estado visual de envio para evitar clique duplo e deixar o processamento mais claro.
- Dashboard do advogado ganhou mesa de trabalho com fila priorizada, contadores de alta prioridade, casos ativos e documentos recentes.
- Tela de documentos/análise passou a destacar status da análise, confiança, arquivo original, linguagem simples, resumo e aviso de uso informativo.
- Botões de copiar resumo/explicação foram ativados na tela de análise.
- CSS responsivo atualizado para os novos blocos.
- Lint PHP completo: sem erros de sintaxe.

## Correções aplicadas - Fase 3 Dashboard/admin

Aplicado em 31/05/2026:

- Dashboard admin redesenhado como central operacional com métricas de usuários, profissionais, OAB, documentos, IA, solicitações, agenda e falhas de login.
- Gráficos simples foram adicionados com HTML/CSS: documentos por dia, uso de IA, composição de usuários, solicitações por status e advogados com mais casos.
- Fila OAB/CNA criada no painel, com ações rápidas de aprovar ou reprovar profissionais pendentes.
- Backend ganhou rota administrativa para revisão manual de OAB/CNA, com auditoria, notificação e registro em `cna_validacao_logs`.
- Tela de usuários ganhou busca por nome/e-mail/telefone/OAB, filtros por perfil/status/OAB e ações manuais de validação profissional.
- Tela de solicitações ganhou busca, filtro por responsável, visão crítica, contadores e ordenação por prioridade.
- Tela de documentos ganhou filtros por busca, status de análise, tipo e período, além de confiança/modelo da última análise.
- Auditoria ganhou severidade, filtro por risco e detalhes JSON formatados para leitura.
- CSS responsivo atualizado para os novos blocos administrativos.

## Correções aplicadas - Fase 4 Segurança e documentação

Aplicado em 31/05/2026:

- Criados documentos de arquitetura, segurança, rotas, requisitos, demo, banca, LGPD e smoke test manual.
- Criado `docs/README.md` como índice da documentação.
- `README.md` passou a apontar para a documentação principal.
- `backend/app/config/gemini.php` deixou de armazenar chave fixa e passou a ler `GEMINI_API_KEY`/`GEMINI_MODEL` do ambiente.
- Documentado o uso responsável de IA: consentimento explícito, limitação informativa e riscos residuais.
- Documentado checklist LGPD com dados tratados, minimização, direitos do titular, operadores e incidentes.
- Criado roteiro de smoke test para reduzir risco de erro ao vivo na banca.

## Correções aplicadas - Fase 5 Demo e apresentação

Aplicado em 31/05/2026:

- Criado `database/seed_demo.sql`, resetável e limitado a contas `@justraduz.demo`.
- Seed demo inclui admin, clientes, advogado, estagiário, profissional OAB pendente, documentos, análise IA, solicitações, chat, tarefas, agenda, notificações e auditoria.
- Criados documentos visuais fictícios para pré-visualização da demo no storage protegido.
- Criado `docs/CREDENCIAIS_DEMO.md` com contas e senha de apresentação.
- Criados `docs/ENSAIO_DEMO.md`, `docs/PITCH_COMERCIAL.md`, `docs/RESPOSTAS_TECNICAS.md` e `docs/VIDEO_BACKUP.md`.
- `README.md`, `database/README.md`, `docs/README.md`, `docs/DEMO.md` e `docs/SMOKE_TEST.md` passaram a apontar para o seed e material de apresentação.

## 1. Diagnóstico geral

O JusTraduz não é só mais uma aplicação CRUD. Ele já tem base real de produto: autenticação por perfil, upload de documento, análise por IA, validação OAB/CNA, solicitações, chat, tarefas, agenda, notificações, admin e auditoria. Isso dá mais substância do que um e-commerce comum.

O problema é que a maturidade está irregular. O backend tem decisões boas, mas ainda mistura regra de negócio nas páginas e controllers. O frontend tem uma identidade consistente, porém ainda parece "sistema funcional bonito o bastante", não SaaS jurídico premium. O admin existe, mas ainda não transmite operação de plataforma em tempo real. A documentação principal ajuda a rodar, mas `docs/` está praticamente vazio.

Veredito brutal: o JusTraduz tem mais potencial que o SmartCart, mas não pode ir para banca com fluxo quebrado, dados vazios, admin raso e risco de agenda/documentos expostos. Se o objetivo é superar, parem de adicionar enfeite e foquem em: fluxo de demo impecável, admin convincente, segurança básica comprovável e visual de produto.

## 1.1. O que está fraco, amador ou arriscado

O que está fraco:
- A tela de maior valor, a análise jurídica em linguagem simples, ainda não parece o centro do produto.
- O admin existe, mas ainda não passa sensação de operação SaaS madura.
- A documentação em `docs/` praticamente não existe.
- Não há testes automatizados, então a estabilidade depende de teste manual.
- A gestão de profissionais/OAB está incompleta para o tamanho da promessa.

O que está com cara de amador:
- Landing page vendendo a ideia sem mostrar claramente o produto real.
- Arquivos duplicados/wrappers/HTMLs antigos sem uma explicação forte no README.
- Tabelas demais com pouca hierarquia visual.
- Textos legais ainda genéricos para um produto que lida com documento jurídico.
- Ausência de dados demo robustos, caso o banco esteja vazio.

O que está arriscado:
- `GET /schedule/calendar` pode expor agenda/agendamentos sem login.
- Estagiário tem acesso amplo demais a documentos, casos e chat.
- `.env` existe localmente; se já foi versionado antes, precisa limpeza e rotação.
- Dependência de Gemini/CNA/SMTP ao vivo pode quebrar a apresentação.
- Permissões estão espalhadas entre páginas, controllers e helpers.

O que está desperdiçando tempo:
- Melhorar tema escuro antes de polir fluxo principal.
- Investir em perfil de estagiário se ele não for essencial para a banca.
- Criar novas telas antes de deixar análise, admin e demo impecáveis.
- Tentar mostrar recuperação de senha por e-mail ao vivo.
- Ajustar detalhes pequenos de tabela antes de corrigir segurança e seed.

O que deve ser cortado ou escondido na demo:
- Perfil de estagiário, se as regras de acesso não forem revisadas.
- Fluxo de CNA ao vivo, se não estiver 100% estável.
- Recuperação de senha por SMTP.
- Scripts de reprocessamento em lote.
- Qualquer tela vazia ou com dados pobres.

## 2. Evidências verificadas

- Estrutura principal: `backend/`, `frontend/`, `database/`, `docs/`.
- Rotas em `backend/routes/api.php`.
- Controllers em `backend/app/controllers/`.
- Serviços existentes: Gemini, OAB, auditoria, notificações, e-mail e extração de PDF.
- Páginas reais em `frontend/pages/app/` e `frontend/pages/admin/`; raiz de `frontend/` contém wrappers e páginas públicas.
- `docs/requisitos.md` está vazio.
- `backend/.env` existe localmente; `.gitignore` ignora `backend/.env`.
- `backend/storage/documents/` está vazio, exceto `.htaccess`.
- `php -l` passou sem erro de sintaxe em todos os arquivos PHP.
- Não há `composer.json`, `phpunit.xml`, `package.json` ou testes automatizados visíveis.
- `git` não está disponível no terminal, então não confirmei rastreamento real de arquivos sensíveis pelo histórico.

## 3. Frontend

### Avaliação direta

O frontend está acima de protótipo cru: possui sidebar, topbar, cards, tabelas, badges, tema claro/escuro, estados vazios e responsividade básica. Para banca, isso já é apresentável. Para produto comercial, ainda falta acabamento de SaaS: hierarquia visual mais forte, gráficos reais, telas de configuração, fluxo de análise mais guiado e menos textos genéricos.

Pontos bons:
- Design system simples com `btn`, `card`, `badge`, `table`, `stat-card`, `input`, `select`.
- Layout de dashboard com sidebar fixa e responsivo.
- Estado vazio existe em várias telas.
- Upload com consentimento explícito de IA.
- Visual sóbrio combina com jurídico.

Pontos fracos:
- Landing page não mostra o produto funcionando; usa logo em um card, o que parece menos comercial.
- Admin tem métricas, mas sem gráficos, tendências, filtros avançados ou fila operacional forte.
- Muitas telas são tabelas parecidas, com pouca diferenciação de fluxo.
- Chat é básico, sem status de leitura, anexos, tempo real ou indicador de participante.
- Agenda tem boa intenção, mas o calendário ainda precisa polimento visual e regra de segurança.
- Páginas antigas/duplicadas criam sensação de projeto em transição.
- Não há tela clara de "resultado da análise jurídica" como experiência hero; ela aparece dentro de `visualizar-documento.php`.

### Telas que devem ser redesenhadas primeiro

1. `frontend/index.html`: transformar de landing genérica em vitrine comercial com print real do dashboard/análise.
2. `frontend/pages/app/visualizar-documento.php`: virar a principal tela de valor, com documento + resumo + explicação + próximos passos.
3. `frontend/pages/admin/dashboard-admin.php`: precisa parecer central de operação SaaS, não só resumo.
4. `frontend/pages/app/dashboard-cliente.php`: precisa guiar o fluxo "enviar documento -> entender -> pedir ajuda -> agendar".
5. `frontend/pages/app/dashboard-advogado.php`: precisa parecer mesa de trabalho profissional, com fila, SLA e casos.
6. `frontend/pages/app/agenda.php`: melhorar calendário, estados e segurança.

### Componentes a criar ou padronizar

- `PageHeader`: título, subtítulo, ações primárias e breadcrumbs.
- `MetricCard`: valor, variação, período, ícone e cor semântica.
- `StatusBadge`: status padronizado para casos, documentos, OAB, agenda e usuários.
- `DataTable`: busca, filtros, paginação, ações e estado vazio.
- `Timeline`: eventos de documento/caso/análise/chat.
- `DocumentAnalysisPanel`: resumo, explicação, confiança, aviso legal e ações.
- `CaseStatusStepper`: aberto, em atendimento, finalizado, cancelado.
- `ConfirmDialog`: exclusão, bloqueio, aprovação/reprovação.
- `Toast`: sucesso/erro sem depender só de query string.
- `Skeleton/LoadingState`: uploads, IA e tabelas.
- `ErrorState`: sessão expirada, IA indisponível, CNA indisponível.

### Como deixar com cara de SaaS jurídico moderno

- Usar dashboard denso e escaneável, com menos hero interno e mais operação.
- Exibir produto real na landing: print do painel, análise de documento e agenda.
- Trocar textos genéricos por linguagem de negócio: "documentos analisados", "profissionais pendentes", "casos sem responsável", "risco alto", "aguardando cliente".
- Criar telas de configuração, planos/status de conta e auditoria com filtros.
- Adicionar microcopy jurídico: "IA informativa", "não substitui advogado", "profissional verificado".
- Mostrar trilha de confiança: OAB verificada, consentimento de IA, auditoria, controle de acesso.
- Reduzir aparência de "tabela atrás de tabela" com timeline, cards de fila e visual de kanban simples para solicitações.

## 4. Telas existentes

Legenda: "crítica" significa importante para apresentação. "Duplicada" inclui wrappers ou redirecionamentos mantidos por compatibilidade.

| Tela/arquivo | Classificação | Comentário |
|---|---|---|
| `frontend/index.html` | funcional mas genérica; crítica | Landing existe, mas precisa mostrar o produto real. |
| `frontend/login.html` | funcional; crítica | Boa para demo, depende de JS para CSRF. |
| `frontend/cadastro.html` | funcional; crítica | Tem OAB, telefone, termos; melhorar validação e feedback. |
| `frontend/recuperar-senha.html` | funcional | Boa base, mas demo não deve depender de SMTP ao vivo. |
| `frontend/termos.html` | incompleta | Texto simples; falta base LGPD/jurídica mais robusta. |
| `frontend/privacidade.html` | incompleta | Texto inicial; falta retenção, exclusão, IA, operadores. |
| `frontend/admin/login-admin.html` | funcional; crítica | Entrada separada ajuda a parecer produto real. |
| `frontend/dashboard-cliente.php` | duplicada/wrapper | Encaminha para página real. |
| `frontend/dashboard-advogado.php` | duplicada/wrapper | Encaminha para página real. |
| `frontend/dashboard-estagiario.php` | duplicada/wrapper | Encaminha para página real. |
| `frontend/visualizar-documento.php` | duplicada/wrapper | Encaminha para página real. |
| `frontend/solicitar-ajuda.php` | duplicada/wrapper | Encaminha para página real. |
| `frontend/acompanhar-solicitacoes.php` | duplicada/wrapper | Encaminha para página real. |
| `frontend/chat.php` | duplicada/wrapper | Encaminha para página real. |
| `frontend/agenda.php` | duplicada/wrapper | Encaminha para página real. |
| `frontend/tarefas.php` | duplicada/wrapper | Encaminha para página real. |
| `frontend/notificacoes.php` | duplicada/wrapper | Encaminha para página real. |
| `frontend/perfil.php` | duplicada/wrapper | Encaminha para página real. |
| `frontend/lista-advogados.php` | duplicada/wrapper | Encaminha para página real. |
| HTMLs antigos de área logada | duplicada/desnecessária | Redirecionam para PHP; manter só se explicar no README. |
| `pages/app/dashboard-cliente.php` | funcional; crítica | Fluxo principal existe, mas precisa ficar mais guiado e bonito. |
| `pages/app/visualizar-documento.php` | funcional; crítica | É o coração do produto; precisa maior polimento. |
| `pages/app/solicitar-ajuda.php` | funcional; crítica | Simples e suficiente para demo. |
| `pages/app/acompanhar-solicitacoes.php` | funcional; crítica | Tabela útil, mas fluxo visual pode melhorar. |
| `pages/app/chat.php` | funcional mas básico; crítica | Sem tempo real/leitura; suficiente se dados fake forem bons. |
| `pages/app/agenda.php` | funcional mas arriscada; crítica | Calendário útil, mas endpoint precisa proteção. |
| `pages/app/dashboard-advogado.php` | funcional; crítica | Boa densidade; falta priorização/SLA. |
| `pages/app/dashboard-estagiario.php` | funcional mas questionável | Perfil amplo demais; pode confundir a banca. |
| `pages/app/tarefas.php` | funcional | Útil para maturidade, não deve roubar tempo da demo. |
| `pages/app/lista-advogados.php` | funcional | Mostra profissionais, mas expõe contato amplo. |
| `pages/app/notificacoes.php` | funcional | Complementar, não central. |
| `pages/app/perfil.php` | funcional | Bom suporte; não destacar na demo. |
| `pages/admin/dashboard-admin.php` | funcional; crítica | Precisa de gráficos e métricas de operação. |
| `pages/admin/usuarios.php` | funcional; crítica | Bloqueia/desbloqueia, mas não aprova/reprova OAB formalmente. |
| `pages/admin/solicitacoes.php` | funcional; crítica | Boa tela operacional, faltam filtros/contadores melhores. |
| `pages/admin/documentos.php` | funcional; crítica | Boa para auditoria, faltam filtros por usuário/status/período. |
| `pages/admin/auditoria.php` | funcional; crítica | Diferencial forte, mas detalhes JSON precisam ser mais legíveis. |
| Configurações | inexistente; crítica comercial | Falta tela para IA, SMTP, segurança, planos e sistema. |
| Gestão de planos/status de conta | incompleta | Existe status ativo/inativo, mas não plano comercial. |
| Gestão de profissionais | incompleta; crítica | Está diluída em usuários; falta aprovação/reprovação/revalidação. |

## 5. Telas mínimas para demo/comercial

| Tela obrigatória | Objetivo | Prioridade | Status atual | Melhorias necessárias | Impacto |
|---|---|---:|---|---|---|
| Landing page | Vender a proposta | P1 | funcional | Mostrar produto real, pitch forte, diferenciais e CTA | Alto |
| Login | Entrada segura | P1 | funcional | Mensagens genéricas, CSRF sem depender só de JS | Médio |
| Cadastro | Captar cliente/profissional | P1 | funcional | Fluxo OAB mais claro e fallback CNA visível | Alto |
| Dashboard cliente | Começar jornada | P1 | funcional | Transformar em fluxo guiado, não só métricas | Alto |
| Envio de documento | Capturar valor principal | P1 | funcional | Loading/processando/erro de IA, progresso e status | Alto |
| Resultado da análise jurídica | Mostrar diferencial | P1 | parcial | Tela/experiência dedicada com resumo, linguagem simples, confiança e CTA | Altíssimo |
| Solicitações | Abrir atendimento | P1 | funcional | Timeline de status e próximos passos | Alto |
| Chat | Atendimento humano | P1 | básico | Estado online/leitura, anexos ou ao menos timestamps melhores | Médio |
| Agenda | Agendar atendimento | P1 | funcional com risco | Proteger endpoint e melhorar calendário | Alto |
| Dashboard advogado | Operação profissional | P1 | funcional | Fila priorizada, SLA, casos recentes, documentos pendentes | Alto |
| Dashboard admin | Operação SaaS | P1 | funcional | Gráficos, pendências, OAB, documentos e alertas | Altíssimo |
| Gestão de usuários | Controle de contas | P1 | funcional | Busca, paginação, criação/edição e histórico | Alto |
| Gestão de profissionais | Validar OAB/CNA | P1 | incompleta | Tela própria para aprovar/reprovar/revalidar | Altíssimo |
| Auditoria/logs | Segurança e maturidade | P1 | funcional | Layout legível, filtros salvos, severidade | Alto |
| Configurações | Produto comercial | P2 | inexistente | IA, e-mail, limites de upload, termos, planos | Médio/alto |

## 6. Backend

### Avaliação

Pontos fortes:
- Rotas centralizadas em `backend/routes/api.php`.
- Prepared statements predominam.
- CSRF global para POST.
- Sessão com cookie `HttpOnly`, `SameSite=Lax`, strict mode e timeout.
- Upload valida extensão, MIME e tamanho.
- Download de documento passa por endpoint autenticado.
- Auditoria existe e redige campos sensíveis.
- Gemini e OAB estão isolados em services.
- Password reset usa hash de código e expiração.

Pontos fracos:
- Não há camada de Models/Repositories/Policies; SQL e autorização aparecem em controllers e páginas.
- `RoleMiddleware.php` está vazio.
- `AuthMiddleware.php` existe, mas o frontend usa helpers próprios e controllers têm validações manuais.
- `GET /schedule/calendar` não exige login e pode expor agenda/agendamentos para usuário anônimo.
- Estagiário tem acesso amplo demais a casos, documentos, chat e tarefas.
- Admin de profissionais/OAB não está completo.
- Não há testes automatizados.
- Não há Composer/autoload, migrations versionadas por ferramenta ou pipeline.
- Erros e redirects misturam HTML/JSON; APIs AJAX ainda têm comportamento inconsistente.

### O que refatorar

1. Criar `AuthPolicy`, `DocumentPolicy`, `CasePolicy`, `SchedulePolicy`.
2. Extrair SQL de páginas para services/repositories.
3. Implementar `RoleMiddleware` e usar em rotas.
4. Separar rotas públicas, autenticadas e admin.
5. Transformar status de documentos em entidade explícita: pendente, processando, analisado, erro.
6. Criar serviço de `ProfessionalValidationService` para OAB/CNA e aprovação manual.
7. Criar `CaseTimelineService` para eventos de solicitação.
8. Padronizar JSON de erro: `{ success, error, code }`.
9. Criar migrations controladas por `schema_migrations` de verdade.
10. Adicionar testes de integração dos fluxos centrais.

### O que manter

- `secure_session_start`.
- `CsrfMiddleware`.
- `AuditService`.
- `GeminiService`, com consentimento explícito.
- `OabService`, com fallback pendente.
- Endpoint de download autenticado.
- `frontend/app/bootstrap.php` como camada inicial de componentes.
- `.env.example` e seed de admin sem senha padrão.

### O que remover ou reduzir

- Páginas HTML antigas de área logada, depois de estabilizar wrappers.
- Duplicação de cálculo de `appBasePath` nos JS.
- Regras de autorização repetidas em páginas e controllers.
- Perfil de estagiário se ele não for essencial para a banca; hoje ele aumenta escopo e risco.
- Textos administrativos genéricos que não ajudam o fluxo de demo.

### Endpoints que faltam

- `POST /admin/professionals/approve`
- `POST /admin/professionals/reject`
- `POST /admin/professionals/revalidate-oab`
- `POST /admin/users/create`
- `POST /admin/users/update`
- `POST /admin/plans/update`
- `GET /admin/metrics`
- `GET /admin/security/health`
- `GET /cases/timeline`
- `POST /cases/cancel`
- `POST /documents/attach-to-case`
- `GET /documents/status`
- `GET /notifications/list`
- `POST /messages/read`
- `POST /settings/update`

### Endpoints que precisam de proteção/revisão imediata

- `GET /schedule/calendar`: exigir login e filtrar por perfil.
- `GET /documents/download`: já valida permissão; manter teste automatizado.
- `POST /documents/analyze`: manter consentimento; registrar base legal.
- `POST /admin/users/status`: admin only, ok, mas registrar IP/justificativa.
- `POST /admin/cases/update`: admin only, ok, mas precisa validação de transição.
- `POST /messages/send`: ok com permissão, mas falta rate limit.
- `POST /auth/login`: trocar mensagem específica por genérica para evitar enumeração.

## 7. Admin/Dashboard

O admin atual é funcional, mas ainda não parece uma central de SaaS. Ele mostra usuários, documentos e solicitações, mas não transmite "operação, risco e crescimento".

### Cards de métricas obrigatórios

- Usuários totais.
- Clientes ativos.
- Advogados ativos.
- Profissionais pendentes de validação.
- Solicitações abertas.
- Solicitações em andamento.
- Solicitações concluídas.
- Documentos enviados.
- Documentos analisados por IA.
- Documentos com erro/pendentes.
- Agendamentos próximos.
- Logins/tentativas falhas nas últimas 24h.

### Gráficos

- Linha: documentos enviados por dia.
- Barras: solicitações por status.
- Donut: composição de usuários por perfil.
- Barras horizontais: advogados com mais casos.
- Pequena série: uso de IA por período.

### Tabelas/filtros

- Fila de profissionais pendentes: nome, OAB, UF, status CNA, ação aprovar/reprovar/revalidar.
- Solicitações críticas: prioridade alta, sem advogado, abertas há mais de X dias.
- Documentos pendentes: sem análise, erro de IA, tipo de arquivo.
- Auditoria recente: severidade, ação, usuário, IP, data.
- Usuários: busca por nome/e-mail, perfil, status, data, plano.

### Ações rápidas

- Aprovar/reprovar advogado.
- Bloquear/desbloquear usuário.
- Atribuir advogado a solicitação.
- Reprocessar análise IA.
- Exportar logs.
- Ver saúde das integrações: banco, Gemini, SMTP, CNA.

### Layout ideal

1. Topo com métricas essenciais e alertas críticos.
2. Coluna principal com gráficos e fila de solicitações.
3. Coluna lateral com pendências: OAB, IA, agendamentos, erros.
4. Tabelas com filtros e ações rápidas abaixo.
5. Rodapé interno com health checks e versão do sistema.

Para superar SmartCart, o admin precisa vender maturidade: auditoria, validação profissional, IA com consentimento e gestão de documentos sensíveis. Um e-commerce comum dificilmente mostra isso.

## 8. Documentação

Status atual:
- `README.md` tem instalação local, banco, IA e funcionalidades.
- `database/README.md` tem ordem de migrations.
- `frontend/README.md` explica organização.
- `vistoria.md` tem uma auditoria técnica anterior útil.
- `docs/requisitos.md` está vazio.
- Faltam documentos de arquitetura, segurança, rotas, roadmap, demo e banca.

### Estrutura ideal

```text
README.md
docs/
  ARQUITETURA.md
  SEGURANCA.md
  ROTAS.md
  ROADMAP.md
  DEMO.md
  BANCA.md
  PRODUTO.md
  LGPD.md
  PRINTS.md
database/
  README.md
  schema.sql
  migrations...
```

### README ideal

1. Nome e slogan.
2. Descrição comercial.
3. Problema resolvido.
4. Público-alvo.
5. Funcionalidades principais.
6. Diferenciais competitivos.
7. Stack utilizada.
8. Arquitetura de pastas.
9. Pré-requisitos.
10. Instalação local.
11. Configuração do banco.
12. Variáveis de ambiente.
13. IA/Gemini.
14. OAB/CNA.
15. Credenciais de teste.
16. Fluxos principais.
17. Prints.
18. Segurança e LGPD.
19. Roadmap.
20. Limitações conhecidas.
21. Guia rápido de apresentação.

## 9. Segurança

### Achados

| Tema | Status | Risco | Correção prática |
|---|---|---:|---|
| `.env` local | Existe e está no `.gitignore` | Alto se já foi commitado | Confirmar histórico, remover do Git e rotacionar credenciais se necessário. |
| Credenciais hardcoded | Não vi senha real em código; seed é exemplo | Médio | Manter sem senha padrão e documentar criação segura. |
| SQL Injection | Baixo nas rotas principais | Médio | Manter prepared statements e revisar SQL montado com filtros. |
| XSS | Saída usa `e()` em muitas páginas | Médio | Auditar todos os pontos com `nl2br`, JSON e atributos. |
| CSRF | Global em POST | Médio | Não depender só de JS em páginas estáticas; padronizar token server-side. |
| Sessão | Boa base | Médio | Invalidar sessões após reset e trocar mensagens de login. |
| Uploads | Valida tamanho/MIME/extensão | Alto | Mover storage para fora do webroot em produção e testar negação direta. |
| Agenda pública | `GET /schedule/calendar` não exige login | Crítico | Exigir login e aplicar filtro por perfil. |
| Estagiário | Permissão ampla | Alto | Definir escopo: só casos atribuídos ou remover da demo. |
| Documentos | Admin/estagiário podem ver tudo | Alto | Formalizar política; limitar estagiário. |
| Logs | Auditoria existe e redige segredos | Médio | Classificar severidade e evitar detalhes sensíveis de documentos. |
| Erros | Handler genérico existe | Médio | Garantir `APP_DEBUG=false` em demo/produção. |
| OAB/CNA | Fallback pendente existe | Médio | Tela admin para revisão manual e histórico. |
| LGPD | Parcial | Alto | Consentimento, retenção, exclusão/exportação, base legal e revisão jurídica. |

### Checklist LGPD básico

- Consentimento explícito para IA.
- Aviso de que IA não substitui advogado.
- Política de retenção de documentos.
- Exclusão de documento pelo cliente.
- Exportação de dados do usuário.
- Registro de auditoria para acesso a documento.
- Minimização de dados no admin.
- Controle de acesso por perfil.
- Contrato/termo para uso de APIs externas.
- Plano de resposta a incidente.

## 10. Diferencial de negócio

JusTraduz resolve uma dor real: pessoas recebem documentos jurídicos e não entendem o que estão lendo. SmartCart/e-commerce resolve uma dor comum, mas muito repetida em trabalhos acadêmicos: comprar, vender e administrar produtos. O JusTraduz é mais nichado, mais memorável e mais fácil de defender como inovação.

Posicionamento recomendado:

> JusTraduz é uma plataforma que transforma documentos jurídicos complexos em linguagem simples e conecta o cidadão a profissionais qualificados.

Slogans:
- "Direito em linguagem simples."
- "Entenda antes de decidir."
- "Do juridiquês à ação."

Pitch de 30 segundos:

O JusTraduz ajuda pessoas a entender documentos jurídicos sem se perder no juridiquês. O usuário envia um PDF ou imagem, recebe uma explicação em linguagem simples com apoio de IA e, se precisar, abre uma solicitação, conversa com um advogado verificado e agenda atendimento. Para a administração, a plataforma controla usuários, documentos, solicitações, validação OAB e auditoria.

Pitch de 2 minutos:

Muita gente recebe contrato, intimação, notificação ou documento jurídico e não sabe o que aquilo significa, quais são os riscos e qual próximo passo tomar. O JusTraduz ataca essa dor combinando três camadas: compreensão inicial por IA, conexão com profissionais validados e gestão segura do atendimento. O cliente envia o documento, autoriza a análise automática e vê resumo, explicação simples e confiança. Depois pode solicitar ajuda, conversar pelo chat e agendar um atendimento. O advogado recebe uma fila de casos, documentos dos clientes, tarefas e agenda. O administrador acompanha usuários, profissionais, documentos, solicitações e auditoria. Diferente de um e-commerce comum, o JusTraduz vende confiança, acessibilidade jurídica e organização de atendimento. É um produto com potencial SaaS para escritórios, núcleos de prática jurídica, faculdades, defensorias, legaltechs e canais de atendimento jurídico.

Modelo de monetização:
- Freemium para clientes: X análises grátis.
- Créditos por documento analisado.
- Assinatura mensal para escritórios.
- Plano institucional para faculdades/Núcleos de Prática Jurídica.
- White-label para escritórios.
- Taxa por lead/agendamento, com cuidado jurídico/regulatório.
- Plano premium com auditoria, relatórios e múltiplos profissionais.

Funcionalidades de maior valor:
- Análise de documento em linguagem simples.
- Validação OAB/CNA.
- Atendimento com advogado.
- Agenda integrada.
- Auditoria e segurança.
- Admin com operação real.

Funcionalidades que podem ser cortadas se faltar tempo:
- Perfil de estagiário como jornada separada.
- Tema escuro como destaque de apresentação.
- Tarefas muito detalhadas.
- Notificações avançadas.
- Recuperação de senha ao vivo.
- Reprocessamento em lote via script.

## 11. Pronto para apresentar

### O que precisa funcionar obrigatoriamente

- Login cliente, advogado e admin.
- Upload de documento.
- Visualização do documento.
- Resultado de análise por IA, mesmo que pré-carregado.
- Criação de solicitação.
- Aceite/atribuição por advogado.
- Chat com mensagens.
- Agenda com horário livre e agendamento.
- Admin com métricas, usuários, solicitações, documentos e auditoria.
- Bloqueio/desbloqueio de usuário.
- Profissional com OAB verificada ou pendente explicada.

### O que pode ser simulado

- Consulta OAB/CNA ao vivo.
- Envio de e-mail de recuperação.
- Processamento Gemini em tempo real.
- Notificações em tempo real.
- Gráficos com dados de seed.
- Planos comerciais.

### O que não deve ser mostrado

- `.env`.
- Logs técnicos crus.
- Tela vazia de admin.
- Erro de Gemini/CNA ao vivo.
- Código fonte durante apresentação, exceto se pedirem.
- Fluxo de recuperação por e-mail se SMTP não estiver 100%.
- Perfil de estagiário se as permissões não forem justificadas.

### Bugs que matariam a apresentação

- Login falhar por CSRF.
- Upload salvar mas não abrir documento.
- IA travar a requisição.
- Admin vazio.
- Agenda vazar ou listar dados errados.
- Advogado não conseguir aceitar solicitação.
- Chat sem mensagens ou com permissão errada.
- Caminho de arquivo/documento aparecer com erro.
- Banco sem seed de dados.

### Dados fake obrigatórios

- 1 admin: `admin@justraduz.local`.
- 2 clientes: Maria com contrato de aluguel; João com notificação extrajudicial.
- 2 advogados: um validado OAB/SP, outro pendente.
- 1 estagiário apenas se for demonstrar.
- 4 documentos com nomes realistas.
- 2 análises IA já geradas.
- 3 solicitações: aberta, em andamento, finalizada.
- 8 mensagens de chat.
- 5 tarefas.
- 5 horários de agenda, sendo 2 livres, 2 ocupados e 1 bloqueado.
- 20 logs de auditoria.

### Fluxo de demo recomendado

1. Abrir landing e explicar problema.
2. Entrar como cliente.
3. Mostrar dashboard cliente.
4. Enviar ou abrir documento já enviado.
5. Mostrar análise em linguagem simples.
6. Clicar em "Pedir ajuda".
7. Criar solicitação.
8. Entrar como advogado.
9. Aceitar caso, abrir documento e responder chat.
10. Agendar atendimento.
11. Entrar como admin.
12. Mostrar métricas, usuários, profissionais, solicitações, documentos e auditoria.
13. Fechar com diferenciais comerciais e próximos passos.

## 12. Potencial comercial

Pode virar SaaS, mas ainda não está vendável. Para vender de verdade, faltam multiempresa, cobrança, políticas LGPD, revisão jurídica, termos robustos, monitoramento, suporte, testes, storage fora do webroot e governança de IA.

Versão MVP vendável:
- Cliente envia documento.
- IA explica em linguagem simples com aviso legal.
- Cliente solicita atendimento.
- Advogado validado atende via chat/agenda.
- Admin controla usuários, profissionais, documentos e auditoria.

Versão premium:
- Escritórios com múltiplos advogados.
- Planos e cobrança.
- SLA e fila inteligente.
- OCR avançado.
- Modelos de documentos.
- Relatórios e exportação.
- White-label.
- Integração calendário/e-mail.
- Auditoria avançada e LGPD.

### Matriz comercial

| Funcionalidade | Valor comercial | Dificuldade | Prioridade | Impacto na apresentação |
|---|---:|---:|---:|---:|
| Análise IA em linguagem simples | Muito alto | Média | P1 | Muito alto |
| Upload seguro de documentos | Muito alto | Média | P1 | Alto |
| Validação OAB/CNA | Alto | Alta | P1 | Muito alto |
| Solicitações/casos | Alto | Média | P1 | Alto |
| Chat | Alto | Média | P1 | Alto |
| Agenda | Alto | Alta | P1 | Alto |
| Admin com métricas | Muito alto | Média | P1 | Muito alto |
| Auditoria | Alto | Média | P1 | Alto |
| Gestão de profissionais | Muito alto | Média | P1 | Muito alto |
| Planos/cobrança | Alto | Alta | P2 | Médio |
| OCR avançado | Alto | Alta | P2 | Médio |
| Tema escuro | Baixo | Baixa | P3 | Baixo |
| Estagiário separado | Médio | Média | P3 | Baixo/médio |

## 13. Comparação JusTraduz vs SmartCart

| Critério | Quem vence hoje | Por quê | O que o JusTraduz precisa melhorar | Prioridade | Esforço |
|---|---|---|---|---:|---:|
| Frontend | SmartCart, se estiver mais polido | E-commerce costuma ser visualmente simples de vender; JusTraduz ainda parece operacional | Landing com prints reais, análise como tela hero, admin mais bonito | P1 | Médio |
| Quantidade de telas | JusTraduz | Tem cliente, advogado, admin, agenda, chat, docs, auditoria | Remover duplicadas e deixar fluxo claro | P1 | Baixo |
| Backend | JusTraduz | Tem IA, OAB, auditoria, permissões, uploads | Policies, testes, proteger agenda, models/services | P1 | Médio/alto |
| Admin/dashboard | Empate/SmartCart | JusTraduz tem admin, mas ainda raso visualmente | Métricas, gráficos, profissionais pendentes, alertas | P1 | Médio |
| Documentação | SmartCart, se tiver docs completos | `docs/` do JusTraduz está vazio | Criar docs formais e roteiro de banca | P1 | Baixo/médio |
| Segurança | JusTraduz em intenção; risco em execução | Tem CSRF, sessão, audit, upload; mas há endpoint e permissões amplas | Corrigir agenda, estagiário, `.env`, LGPD | P1 | Médio |
| Diferencial de negócio | JusTraduz | Problema mais nichado e memorável | Posicionamento comercial forte e demo consistente | P1 | Baixo |
| Pronto para apresentar | SmartCart se estiver estável; JusTraduz se corrigir fluxo | JusTraduz tem mais pontos de falha | Seed, roteiro, evitar integrações ao vivo | P1 | Médio |
| Potencial comercial | JusTraduz | Legaltech + IA + atendimento é mais vendável que e-commerce genérico | Compliance, cobrança, multiempresa, suporte | P2 | Alto |

Conclusão: JusTraduz vence em ideia, escopo e diferenciação. SmartCart vence se estiver mais simples, estável e bonito. Para superar, o JusTraduz precisa parecer menos "trabalho escolar ambicioso" e mais "produto jurídico com fluxo confiável".

## 14. Plano de ação até 07/07/2026

### Fase 1 - Correções críticas (31/05 a 05/06)

| Tarefa | Prioridade | Responsável ideal | Dificuldade | Tempo | Impacto | Risco se não fizer |
|---|---:|---|---:|---:|---:|---|
| Proteger `GET /schedule/calendar` com login e filtro por perfil | P1 | Backend | Média | 3h | Alto | Vazamento em banca técnica |
| Revisar permissão de estagiário em documentos/casos/chat | P1 | Backend | Média | 4h | Alto | Exposição indevida |
| Confirmar `.env` fora do Git e rotacionar credenciais se necessário | P1 | DevOps/backend | Baixa | 1h | Alto | Falha grave de segurança |
| Criar seed de demo completo | P1 | Backend | Média | 5h | Muito alto | Demo vazia |
| Garantir fluxo cliente -> documento -> análise -> solicitação | P1 | Fullstack | Média | 6h | Muito alto | Apresentação quebra |
| Mensagem de login genérica | P2 | Backend | Baixa | 30min | Médio | Enumeração de usuário |
| Definir se estagiário entra na demo | P1 | Produto | Baixa | 30min | Alto | Escopo confuso |

### Fase 2 - Frontend e experiência visual (06/06 a 16/06)

| Tarefa | Prioridade | Responsável ideal | Dificuldade | Tempo | Impacto | Risco se não fizer |
|---|---:|---|---:|---:|---:|---|
| Redesenhar landing com print real do produto | P1 | Frontend/UI | Média | 8h | Muito alto | SmartCart parece mais comercial |
| Polir tela de análise de documento | P1 | Frontend/UI | Média | 8h | Muito alto | Diferencial fica escondido |
| Melhorar dashboard cliente como jornada guiada | P1 | Frontend/UI | Média | 6h | Alto | Usuário não entende fluxo |
| Melhorar dashboard advogado com fila e prioridades | P1 | Frontend/UI | Média | 6h | Alto | Profissional parece genérico |
| Padronizar badges/status | P2 | Frontend | Baixa | 3h | Médio | Visual inconsistente |
| Criar loading/erro/sucesso para IA/upload | P1 | Frontend | Média | 5h | Alto | Demo parece travada |

### Fase 3 - Dashboard/admin (17/06 a 23/06)

| Tarefa | Prioridade | Responsável ideal | Dificuldade | Tempo | Impacto | Risco se não fizer |
|---|---:|---|---:|---:|---:|---|
| Adicionar cards: clientes, advogados, pendentes, docs analisados | P1 | Fullstack | Baixa | 3h | Alto | Admin raso |
| Criar gráficos simples com CSS/JS ou dados renderizados | P1 | Frontend | Média | 6h | Alto | Visual perde para SmartCart |
| Criar gestão de profissionais/OAB | P1 | Fullstack | Média | 8h | Muito alto | Diferencial não aparece |
| Adicionar filtros em documentos/admin | P2 | Fullstack | Média | 4h | Médio | Operação parece limitada |
| Melhorar auditoria com severidade e JSON legível | P2 | Fullstack | Média | 4h | Alto | Logs parecem crus |

### Fase 4 - Segurança e documentação (24/06 a 28/06)

| Tarefa | Prioridade | Responsável ideal | Dificuldade | Tempo | Impacto | Risco se não fizer |
|---|---:|---|---:|---:|---:|---|
| Criar `docs/ARQUITETURA.md` | P1 | Backend/docs | Baixa | 3h | Alto | Banca não vê arquitetura |
| Criar `docs/SEGURANCA.md` | P1 | Backend/docs | Baixa | 3h | Alto | Segurança fica só falada |
| Criar `docs/ROTAS.md` | P1 | Backend/docs | Baixa | 2h | Médio | API parece improvisada |
| Criar `docs/DEMO.md` e `docs/BANCA.md` | P1 | Produto/docs | Baixa | 4h | Muito alto | Apresentação sem roteiro |
| Criar checklist LGPD | P1 | Produto/jurídico | Média | 3h | Alto | Risco jurídico ignorado |
| Smoke test manual documentado | P1 | QA | Baixa | 3h | Muito alto | Erro ao vivo |

### Fase 5 - Demo e apresentação (29/06 a 03/07)

| Tarefa | Prioridade | Responsável ideal | Dificuldade | Tempo | Impacto | Risco se não fizer |
|---|---:|---|---:|---:|---:|---|
| Criar banco demo resetável | P1 | Backend | Média | 5h | Muito alto | Dados inconsistentes |
| Ensaiar fluxo completo com cronômetro | P1 | Apresentador | Baixa | 3h | Muito alto | Fala estoura ou quebra |
| Gravar vídeo backup da demo | P1 | Equipe | Baixa | 2h | Alto | Sem plano B |
| Preparar pitch comercial | P1 | Produto | Baixa | 2h | Alto | Ideia não vende |
| Preparar respostas técnicas | P1 | Backend/frontend | Média | 4h | Alto | Banca pega insegurança |

### Fase 6 - Polimento final (04/07 a 07/07)

| Tarefa | Prioridade | Responsável ideal | Dificuldade | Tempo | Impacto | Risco se não fizer |
|---|---:|---|---:|---:|---:|---|
| Corrigir textos, acentos, labels e estados vazios | P1 | Frontend/docs | Baixa | 4h | Alto | Cara de amador |
| Testar em notebook/projetor/resolução da banca | P1 | QA | Baixa | 2h | Alto | Layout quebra |
| Congelar código da demo | P1 | Equipe | Baixa | 30min | Alto | Última hora quebra tudo |
| Preparar credenciais impressas/local | P1 | Apresentador | Baixa | 30min | Alto | Login esquecido |
| Backup do banco e arquivos | P1 | Backend | Baixa | 1h | Alto | Sem restauração |

## 15. Ranking de prioridade

1. Corrigir segurança de agenda.
2. Definir e restringir estagiário.
3. Criar seed demo completo.
4. Polir tela de análise de documento.
5. Redesenhar landing com produto real.
6. Turbinar admin com métricas, gráficos e profissionais pendentes.
7. Criar gestão de profissionais/OAB.
8. Padronizar status, badges e estados de loading/erro.
9. Criar docs de arquitetura, segurança, rotas e banca.
10. Ensaiar fluxo completo com plano B.
11. Confirmar `.env` e credenciais fora do Git.
12. Cortar o que não ajuda a demo.

## 16. Checklists finais

### Checklist técnico

- [ ] Banco importa do zero.
- [ ] Seed cria usuários e dados demo.
- [ ] Login por perfil funciona.
- [ ] Upload funciona.
- [ ] Documento abre via endpoint autenticado.
- [ ] Análise IA existe para demo.
- [ ] Solicitação cria e muda status.
- [ ] Advogado aceita caso.
- [ ] Chat envia mensagem.
- [ ] Agenda agenda horário.
- [ ] Admin bloqueia usuário.
- [ ] Auditoria registra ações.
- [ ] PHP lint sem erros.

### Checklist visual

- [ ] Landing com produto real.
- [ ] Dashboard cliente bonito.
- [ ] Tela de análise impecável.
- [ ] Dashboard advogado com fila clara.
- [ ] Admin com métricas e gráficos.
- [ ] Tabelas com filtros.
- [ ] Estados vazios bonitos.
- [ ] Loading/erro/sucesso claros.
- [ ] Mobile aceitável.
- [ ] Textos revisados.

### Checklist de segurança

- [ ] `.env` ignorado e não versionado.
- [ ] `.env.example` sem segredos.
- [ ] Uploads protegidos.
- [ ] Agenda protegida.
- [ ] Estagiário restrito.
- [ ] Admin protegido.
- [ ] CSRF em todos os POST.
- [ ] Prepared statements.
- [ ] Mensagens de login genéricas.
- [ ] Logs sem segredos.
- [ ] LGPD documentada.

### Checklist de apresentação

- [ ] Roteiro impresso.
- [ ] Credenciais de teste prontas.
- [ ] Banco resetado.
- [ ] Documentos fake cadastrados.
- [ ] Análises IA pré-geradas.
- [ ] Vídeo backup gravado.
- [ ] Internet opcional, não obrigatória.
- [ ] CNA/Gemini não dependem de chamada ao vivo.
- [ ] Fluxo ensaiado em até 8-10 minutos.
- [ ] Plano B para erro.
