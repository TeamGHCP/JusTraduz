# Arquitetura do JusTraduz

## Visão geral

O JusTraduz é uma aplicação PHP/MySQL para tradução de documentos jurídicos em linguagem simples, com apoio de IA, atendimento por profissionais, agenda, chat, tarefas, notificações e administração.

A arquitetura é monolítica, organizada em backend PHP, frontend PHP/HTML/CSS/JS e banco MySQL. Essa escolha reduz complexidade operacional para ambiente acadêmico e facilita demonstrar o fluxo completo em XAMPP.

## Camadas

```text
Navegador
  |
  | HTML/PHP + CSS + JS
  v
frontend/
  | páginas públicas, dashboards e telas admin
  | formulários com CSRF
  v
backend/public/index.php
  | roteador HTTP
  v
controllers/
  | regras de entrada, autorização, redirects e JSON
  v
services/
  | IA, auditoria, notificações, OAB/CNA, e-mail
  v
MySQL
```

## Diretórios principais

- `frontend/`: entradas públicas, wrappers de compatibilidade, assets e páginas autenticadas.
- `frontend/pages/app/`: telas logadas para cliente, advogado e estagiário.
- `frontend/pages/admin/`: central administrativa.
- `frontend/app/`: bootstrap, helpers, sessão, CSRF, navegação e componentes.
- `backend/routes/api.php`: registro das rotas HTTP.
- `backend/app/controllers/`: controllers de autenticação, documentos, casos, agenda, notificações, OAB e admin.
- `backend/app/services/`: serviços de auditoria, Gemini, OAB, e-mail, PDF e notificações.
- `backend/storage/documents/`: documentos enviados, bloqueados para acesso direto pelo roteador local.
- `database/`: schema, migrations e seed admin exemplo.
- `docs/`: documentação técnica e de apresentação.

## Perfis de usuário

- Cliente: envia documentos, autoriza IA, cria solicitações, acompanha atendimento, usa chat e agenda.
- Advogado: atende casos, cria tarefas, conversa no chat, visualiza documentos permitidos e gerencia agenda.
- Estagiário: perfil restrito, com agenda própria e sem herdar poderes administrativos.
- Admin: opera usuários, documentos, solicitações, validação OAB/CNA e auditoria.

## Fluxos principais

### Cadastro e login

1. Usuário preenche cadastro.
2. Se for advogado ou estagiário, informa OAB/UF.
3. O sistema tenta consultar CNA/OAB.
4. Quando a consulta automática não resolve, o cadastro fica pendente para revisão administrativa.
5. Login cria sessão, regenera ID e gera token CSRF.

### Documento e IA

1. Cliente envia PDF ou imagem.
2. Backend valida extensão, MIME e tamanho.
3. Arquivo é salvo em `backend/storage/documents/{user_id}`.
4. PDF pode ter texto extraído.
5. IA só é chamada quando há autorização explícita.
6. Resultado é salvo em `ai_results` com resumo, explicação, confiança, modelo e versão do prompt.
7. A tela exibe aviso de que a análise é informativa e não substitui advogado.

### Solicitação jurídica

1. Cliente cria solicitação com título, descrição, prioridade e opcionalmente advogado.
2. Advogado pode aceitar caso aberto.
3. Admin pode atribuir responsável, prioridade e status.
4. Participantes conversam pelo chat e podem acompanhar tarefas.

### Agenda

1. Advogado ou estagiário cria horários.
2. Cliente visualiza apenas horários livres de profissionais ativos e elegíveis.
3. Cliente agenda atendimento.
4. Profissional e cliente recebem notificações.
5. Admin tem visão geral.

### Administração

O admin acompanha:

- usuários por perfil/status;
- profissionais pendentes de OAB/CNA;
- documentos enviados/analisados;
- solicitações abertas, críticas e sem responsável;
- auditoria, falhas de login e ações sensíveis;
- saúde básica de integrações.

## Modelo de dados essencial

- `users`: contas, perfis, status, OAB/CNA e contato.
- `documents`: metadados e caminho dos arquivos enviados.
- `ai_results`: resumo, explicação, confiança, modelo e prompt.
- `cases`: solicitações de ajuda jurídica.
- `messages`: chat por solicitação.
- `tasks`: tarefas vinculadas a casos.
- `schedule_slots`: disponibilidade de profissionais.
- `appointments`: agendamentos de clientes.
- `notifications`: avisos internos.
- `audit_logs`: trilha de auditoria.
- `cna_validacao_logs`: histórico de validação OAB/CNA.
- `password_reset_codes`: recuperação de senha.

## Decisões técnicas

- Monólito PHP: adequado para XAMPP e entrega acadêmica.
- MySQL: simples de instalar, consultar e demonstrar.
- Páginas PHP renderizadas no servidor: reduzem dependência de build frontend.
- CSS/JS sem pipeline: facilita execução local.
- Controllers com validação por perfil: protege as rotas mesmo quando chamadas diretamente.
- Auditoria centralizada: registra ações sensíveis para defesa de segurança.

## Pontos de evolução

- Criar testes automatizados.
- Mover configuração sensível totalmente para variáveis de ambiente em produção.
- Adicionar política formal de retenção/exclusão de documentos.
- Implementar OCR para PDFs escaneados.
- Separar API e frontend caso o produto cresça.

