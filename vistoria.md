# Vistoria tecnica do JusTraduz

Data da vistoria: 31/05/2026  
Escopo analisado: backend PHP, frontend PHP/HTML/JS/CSS, schema/migracoes SQL, configuracao local, rotas, seguranca, banco, uploads, IA, agenda, chat, auditoria e fluxo administrativo.

## Resumo executivo

O sistema ja tem uma base funcional: autenticacao por sessao, perfis separados, upload de documentos, analise por Gemini, solicitacoes/casos, chat, tarefas, agenda, notificacoes e painel administrativo. A organizacao atual tambem mostra uma migracao em andamento, saindo de arquivos soltos para `frontend/pages/*`, `frontend/app/*`, `backend/app/*` e `database/*`.

O ponto mais importante agora e estabilizar seguranca, deploy e consistencia do codigo. Existem riscos criticos envolvendo credenciais/arquivos rastreados, admin padrao, documentos enviados no Git, logs sensiveis, rotas GET que alteram estado, OAB lookup possivelmente quebrado por CSRF, e autorizacoes amplas para alguns perfis.

Resultado de lint: todos os arquivos `.php` passaram em `php -l`, sem erros de sintaxe.

## Status da rodada de correções

Correções aplicadas em 31/05/2026:

- `.env` e PDFs de upload removidos do rastreamento do Git, mantendo os arquivos locais.
- Criado `backend/.env.example`.
- Seed de admin removido do `schema.sql`; `seed_admin.example.sql` passou a exigir hash próprio.
- Logs sensíveis de login e recuperação de senha removidos.
- Aceite de caso alterado de GET para POST com CSRF.
- Lookup OAB agora envia CSRF no `fetch`.
- Download/preview de documentos passou a usar endpoint autenticado.
- Advogado só visualiza documentos de casos atribuídos.
- Exclusão controlada de documento adicionada para cliente dono e admin.
- Upload/análise de IA passou a exigir autorização explícita do usuário.
- Sessão centralizada, com cookie host-only e timeout de inatividade.
- CSP do backend restringida.
- Criados migrations/documentação de banco para metadados de IA e índices.
- README atualizado com `.env`, seed admin seguro, migrations e roteador local.

Itens que ainda exigem decisão operacional fora do código:

- Rotacionar credenciais que já possam ter sido expostas.
- Limpar histórico Git remoto, se `.env` ou PDFs já tiverem sido publicados.
- Definir política LGPD final e revisão jurídica dos textos.

## Achados criticos

### 1. `backend/.env` esta rastreado no Git

`git ls-files` mostra `backend/.env` versionado. Mesmo sem abrir ou expor valores, isso e um risco alto porque `.env` normalmente contem credenciais de banco, SMTP e chaves de API.

O que fazer:

- Remover `backend/.env` do historico/rastreamento do Git.
- Criar `backend/.env.example` sem segredos reais.
- Rotacionar qualquer senha ou chave que ja tenha ficado nesse arquivo.
- Adicionar explicitamente `backend/.env` no `.gitignore`.

### 2. Documentos enviados por usuarios estao rastreados

`git ls-files` mostra PDFs em `backend/storage/documents/...`. Esses arquivos podem conter dados juridicos sensiveis de clientes.

O que fazer:

- Remover `backend/storage/documents/*` do Git.
- Garantir que `backend/storage/documents/` fique ignorado.
- Considerar limpeza do historico se os PDFs ja foram commitados.
- Separar storage de uploads fora da raiz publica quando for para producao.

### 3. Admin padrao com senha conhecida

O `README.md` e o `database/schema.sql` documentam o acesso `admin@justraduz.com` / `admin`. Isso e aceitavel apenas em ambiente local descartavel, mas perigoso em qualquer ambiente compartilhado.

O que fazer:

- Remover seed de admin padrao do `schema.sql`.
- Manter apenas `database/seed_admin.example.sql` com aviso forte.
- Exigir criacao de admin por comando/script local.
- Trocar senha inicial obrigatoriamente no primeiro login.

### 4. Codigo de recuperacao de senha e logado em texto claro

`AuthController::issuePasswordResetCode()` registra o codigo de recuperacao com `error_log('[PASSWORD RESET] Codigo para ...')`. Isso invalida parte da seguranca do fluxo se logs forem acessiveis.

O que fazer:

- Remover esse `error_log` antes de uso real.
- Registrar apenas evento sem codigo, por exemplo `password_reset_code_issued`.
- Adicionar rate limit por IP/e-mail para solicitacao e validacao de codigo.

### 5. Login tem log de debug em producao

`AuthController::login()` registra tentativa de login e nomes de cookies. Nao registra senha, mas ainda e telemetria sensivel e ruidosa.

O que fazer:

- Remover o bloco `[AUTH DEBUG]`.
- Se precisar diagnostico, usar flag `APP_DEBUG=true` e logs sanitizados.

### 6. Rota GET altera estado

`GET /cases/accept` aceita um caso e altera banco. Mudanca de estado por GET e vulneravel a acionamento acidental, prefetch, historico do navegador e CSRF.

O que fazer:

- Trocar para `POST /cases/accept`.
- Exigir CSRF.
- Atualizar links/botoes no frontend para formulario POST.

### 7. Consulta OAB no cadastro provavelmente falha por CSRF

O roteador valida CSRF em todo POST. `frontend/assets/js/auth.js` chama `POST /oab/lookup`, mas nao envia `_csrf` nem `X-CSRF-Token`. Como a injecao de CSRF adiciona token apenas aos formularios, a busca CNA tende a retornar erro 403.

O que fazer:

- Buscar o token em `/auth/csrf` e enviar `X-CSRF-Token` na chamada `fetch`.
- Ou incluir `_csrf` no `URLSearchParams`.
- Tratar erro 403 no frontend com mensagem clara.

## Seguranca e privacidade

### Endurecer sessoes

O projeto ja usa `httponly`, `samesite=Lax`, `secure` quando HTTPS e `session_regenerate_id`. Isso e bom.

O que fazer:

- Evitar definir `domain` para IP/localhost quando nao necessario; cookie de host costuma ser mais simples e menos sujeito a erro.
- Centralizar configuracao de sessao em um unico arquivo usado por backend e frontend.
- Adicionar timeout de inatividade.
- Invalidar sessoes apos troca de senha.

### Revisar CSP

A CSP atual permite `style-src 'unsafe-inline' https:` e `connect-src https: wss:`. E ampla.

O que fazer:

- Restringir `connect-src` aos dominios realmente usados.
- Reduzir `style-src` quando o CSS inline for eliminado.
- Definir ambientes diferentes para desenvolvimento e producao.

### Melhorar autorizacao de documentos

Advogados conseguem ver documentos de clientes se existir caso sem advogado (`advogado_id IS NULL`). Isso pode expor documentos de casos ainda nao aceitos para qualquer advogado.

O que fazer:

- Definir regra de negocio: advogado ve documentos somente de casos atribuidos ou somente apos aceitar o caso.
- Se documentos de casos abertos forem visiveis, registrar consentimento e escopo.
- Ajustar `DocumentController::findDocumentForCurrentUser()` e `frontend/pages/app/visualizar-documento.php`.

### Revisar permissao de estagiario

Em varios pontos, `estagiario` tem acesso amplo parecido com admin, por exemplo gerenciar/ver casos, tarefas, documentos e chat.

O que fazer:

- Definir se estagiario deve ver todos os casos ou apenas casos atribuidos.
- Criar tabela de atribuicao/participantes de caso se necessario.
- Aplicar essa regra em chat, tarefas, documentos e dashboards.

### Proteger arquivos de upload

Os documentos ficam em caminho acessivel por URL relativa (`../backend/storage/documents/...`). Mesmo com nomes aleatorios, isso nao e ideal para documentos juridicos.

O que fazer:

- Mover uploads para fora da raiz servida pelo PHP embutido/servidor web.
- Criar endpoint autenticado para download/preview que valide permissao por documento.
- Adicionar headers `Content-Disposition`, `X-Content-Type-Options` e cache privado.
- Considerar criptografia em repouso se houver dados sensiveis reais.

## Banco de dados e migracoes

### Consolidar schema e migracoes

Ha `database/schema.sql` e migracoes separadas, enquanto mensagens antigas mencionam `mysql/migrations/...`, pasta que aparece como deletada no Git.

O que fazer:

- Escolher uma estrategia: schema completo + migrations versionadas, ou apenas migrations.
- Corrigir mensagem em `AuthController` que aponta para `mysql/migrations/2026_05_29_add_oab_columns.sql`.
- Criar tabela `schema_migrations` para controlar o que foi aplicado.
- Documentar ordem de execucao em `database/README.md`.

### Adicionar constraints e indices uteis

O schema cobre relacionamentos principais, mas faltam algumas protecoes de integridade.

O que fazer:

- `schedule_slots`: impedir sobreposicao por regra transacional/aplicacao e indice auxiliar.
- `appointments`: impedir mais de um agendamento ativo por `slot_id`.
- `messages`: considerar `mensagem NOT NULL`.
- `tasks`: considerar responsavel, prazo e timestamps de atualizacao.
- `users`: avaliar indice para `tipo/status`, e normalizar `email`.

### Revisar dados LGPD

O sistema armazena documentos, conversas, telefone, OAB, e possivelmente dados juridicos sensiveis.

O que fazer:

- Definir politica de retencao de documentos.
- Criar exclusao/exportacao de dados do usuario.
- Registrar consentimento para analise por IA.
- Evitar envio automatico de documentos sensiveis para IA sem aceite claro.

## Backend

### Centralizar middleware

Ha middlewares `AuthMiddleware` e `RoleMiddleware`, mas os controllers fazem muitas validacoes manualmente.

O que fazer:

- Padronizar `requireLogin`, `requireRole` e `requireAdmin`.
- Evitar repeticao entre controllers e frontend.
- Criar respostas consistentes para HTML redirect e JSON.

### Melhorar roteamento

O roteador usa `?rota=/...`, funcionando localmente, mas menos limpo para producao.

O que fazer:

- Manter compatibilidade com `?rota=`.
- Adicionar suporte opcional a path real via rewrite.
- Criar pagina/JSON 404 padronizado.
- Separar rotas publicas, autenticadas e administrativas.

### Padronizar tratamento de erros

Existe `ErrorHandler`, mas fluxos misturam redirect, JSON e exception.

O que fazer:

- Diferenciar erro de validacao, permissao, nao encontrado e erro interno.
- Para chamadas AJAX, sempre retornar JSON.
- Em producao, nunca exibir detalhes de exception ao usuario.

### IA/Gemini

O servico esta bem isolado e limita arquivo inline a 19 MB.

O que fazer:

- Salvar `lastError` em auditoria tecnica sem expor ao cliente.
- Adicionar consentimento antes de enviar documento para IA.
- Permitir reprocessamento em fila, para upload nao travar em chamada externa.
- Registrar modelo usado e versao do prompt em `ai_results`.

### E-mail

`MailerService` implementa SMTP manualmente e fallback para `mail()`.

O que fazer:

- Validar configuracao no startup/admin.
- Considerar biblioteca madura para SMTP em producao.
- Adicionar template e remetente configuravel.
- Adicionar fila/retry para e-mails de recuperacao.

## Frontend

### Remover duplicacao e arquivos antigos

Ha arquivos HTML antigos e wrappers PHP na raiz de `frontend`, alem das paginas reais em `frontend/pages/app` e `frontend/pages/admin`.

O que fazer:

- Decidir quais HTML antigos permanecem como paginas publicas.
- Remover ou redirecionar explicitamente paginas antigas de area logada.
- Documentar que as paginas reais ficam em `frontend/pages/*`.
- Evitar duplicar logica entre raiz e `pages`.

### Corrigir caminhos absolutos/relativos

Alguns JS usam `/backend/public/index.php`, outros usam `../backend/public/index.php`. Isso pode quebrar se o app rodar em subpasta.

O que fazer:

- Expor `APP_BASE_PATH`/`app_url()` para JS.
- Centralizar rotas em `frontend/assets/js/api.js`.
- Usar `JusApi.route('/rota')` em todos os fetches.

### Melhorar UX dos erros de CSRF/sessao

Quando CSRF falha, o usuario provavelmente ve erro generico ou redirect inesperado.

O que fazer:

- Exibir mensagem amigavel: "Sua sessao expirou, recarregue a pagina".
- Renovar token automaticamente quando possivel.
- Evitar `force-logout` automatico em paginas que nao precisam limpar sessao.

### Acessibilidade e consistencia visual

O sistema tem componentes reaproveitaveis, mas precisa de uma passada de acessibilidade.

O que fazer:

- Revisar foco de teclado em modais, menu mobile e calendario.
- Garantir contraste em badges e botoes.
- Conferir labels/aria em botoes de icone.
- Testar layout mobile nos fluxos principais.

## Funcionalidades a completar

### Documentos

- Criar endpoint autenticado de download/preview.
- Adicionar exclusao de documento pelo cliente/admin.
- Adicionar status de analise: pendente, processando, concluida, erro.
- Adicionar OCR para PDFs escaneados/imagens, se isso for requisito real.
- Exibir historico de analises ou versao atual com data/modelo.

### Casos/solicitacoes

- Trocar aceite de caso para POST.
- Definir fluxo completo: aberto, aceito, em atendimento, finalizado, cancelado.
- Registrar eventos de status em tabela propria ou audit log consultavel.
- Permitir anexar documentos especificos a um caso, nao apenas ao cliente inteiro.
- Definir se advogado pode ver documentos antes de aceitar o caso.

### Chat

- Adicionar leitura/nao lida por usuario.
- Atualizacao em tempo real ou polling controlado.
- Anexos no chat, se fizer sentido.
- Moderacao/admin: deixar claro quando admin/estagiario acessou conversa.

### Agenda

- Impedir duplo agendamento tambem por constraint/indice no banco.
- Definir timezone oficial do sistema.
- Enviar notificacao/e-mail de confirmacao/cancelamento.
- Criar bloqueios recorrentes e horarios recorrentes.
- Exportacao `.ics` ou integracao calendario, se virar necessidade.

### Admin

- Remover senha padrao.
- Criar criacao/edicao de usuarios por admin com trilha de auditoria.
- Criar tela de configuracao de SMTP/Gemini/OAB.
- Criar dashboard de erros tecnicos recentes.
- Criar filtros e paginacao robusta nas tabelas grandes.

### OAB/CNA

- Corrigir CSRF do lookup.
- Guardar historico de validacoes e status.
- Permitir revalidacao manual por admin.
- Tratar indisponibilidade do CNA com politica clara: pendente, bloqueado ou permissivo.

## Qualidade, testes e operacao

### Testes minimos recomendados

Hoje nao ha estrutura de testes automatizados visivel.

O que fazer:

- Criar testes de integracao para autenticacao, CSRF, upload, permissao de documentos, casos, agenda e admin.
- Criar fixtures SQL para ambiente de teste.
- Criar smoke test de rotas principais.
- Manter lint PHP no fluxo antes de deploy.

### Observabilidade

Existe auditoria de negocio, mas falta observabilidade tecnica estruturada.

O que fazer:

- Separar audit log de usuario de log tecnico de erro.
- Definir `APP_ENV` e `APP_DEBUG`.
- Registrar erros externos de Gemini, SMTP e CNA sem dados sensiveis.
- Criar rotina de limpeza de logs antigos.

### Deploy

O README esta focado em XAMPP local.

O que fazer:

- Criar instrucoes de producao: servidor web, PHP extensions, permissao de storage, banco, HTTPS.
- Documentar variaveis de ambiente.
- Criar checklist de deploy.
- Garantir que storage e `.env` nao sejam publicados.

## Prioridade sugerida

### Fazer imediatamente

1. Remover `backend/.env` e PDFs de upload do Git.
2. Rotacionar credenciais possivelmente expostas.
3. Remover logs de codigo de recuperacao de senha e debug de login.
4. Remover admin padrao do schema principal ou exigir troca obrigatoria.
5. Corrigir `POST /oab/lookup` com CSRF.
6. Trocar `GET /cases/accept` por POST com CSRF.

### Fazer em seguida

1. Mover uploads para acesso via endpoint autenticado.
2. Revisar autorizacao de documentos para advogados e estagiarios.
3. Consolidar schema/migrations e corrigir referencias antigas a `mysql/`.
4. Centralizar `app_url`/rotas no JS.
5. Criar testes de integracao dos fluxos principais.
6. Adicionar rate limit em login e recuperacao de senha.

### Fazer antes de producao

1. Politica LGPD: consentimento, retencao, exclusao e exportacao.
2. Hardening de CSP, cookies e sessoes.
3. Observabilidade tecnica e logs sanitizados.
4. Pipeline de lint/testes.
5. Documentacao completa de deploy.
6. Revisao juridica do aviso de IA: analise automatica nao substitui advogado.

## Verificacoes realizadas

- Mapeamento de arquivos com `rg --files`.
- Leitura dos controllers principais: autenticacao, documentos, casos, agenda, admin e OAB.
- Leitura de servicos: Gemini, OAB, e-mail e auditoria.
- Leitura do bootstrap frontend, helpers de sessao/CSRF, paginas principais e JS de autenticacao.
- Leitura do schema SQL e `.gitignore`.
- Verificacao de arquivos rastreados sensiveis com `git ls-files`.
- Lint PHP completo com `C:\xampp\php\php.exe -l`: sem erros de sintaxe.

## Observacao sobre o estado atual do Git

O worktree ja estava modificado antes desta vistoria, com muitos arquivos alterados, alguns deletados e novos arquivos em `database/` e `frontend/assets/js/`. Esta vistoria nao reverteu nada disso e adicionou apenas este arquivo `vistoria.md`.
