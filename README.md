# JusTraduz

**JusTraduz** é uma plataforma SaaS legaltech desenvolvida para simplificar a comunicação jurídica entre clientes e advogados. O sistema utiliza inteligência artificial para transformar documentos, contratos, processos e termos jurídicos em explicações mais claras, organizando também atendimento, casos, agenda, mensagens, pagamentos, assinaturas, auditoria e administração.

A plataforma foi criada para reduzir a distância entre a linguagem jurídica técnica e o entendimento do usuário final, oferecendo uma experiência digital mais acessível, segura e organizada para operações jurídicas.

> O JusTraduz é uma ferramenta de apoio, organização e simplificação de informações jurídicas. Ele não substitui a análise de um advogado habilitado.

---

## Visão geral

O JusTraduz centraliza em uma única plataforma:

- Análise e simplificação de documentos jurídicos com IA.
- Comunicação entre clientes e advogados.
- Organização de solicitações, casos, tarefas e agenda.
- Consulta processual integrada.
- Gestão de planos, assinaturas e pagamentos.
- Administração de usuários, permissões, OAB, documentos, relatórios e auditoria.
- Controles de segurança, LGPD, rate limit e rastreabilidade.

O sistema possui três áreas principais:

| Perfil | Recursos principais |
|---|---|
| Cliente | Upload de documentos, análise com IA, solicitação jurídica, chat, consulta CNJ, agenda, notificações e assinatura |
| Advogado | Recebimento e aceite de casos, comunicação com clientes, tarefas, agenda e acompanhamento jurídico |
| Administrador | Gestão de usuários, OAB, documentos, solicitações, assinaturas, permissões, relatórios e auditoria |

---

## Principais funcionalidades

### Inteligência artificial jurídica

- Análise automática de documentos jurídicos.
- Simplificação de juridiquês.
- Explicação de cláusulas, riscos e pontos relevantes.
- Chat de apoio jurídico com controle de uso.
- Processamento de PDF, DOCX e imagens.
- Extração de texto com suporte a OCR.
- Integração com Cloudflare Workers AI.

### Gestão de documentos

- Upload seguro de documentos.
- Validação de extensão e MIME type.
- Limite de tamanho de arquivo.
- Armazenamento privado.
- Sanitização de imagens.
- Scanner antimalware com ClamAV quando configurado.
- Organização de documentos por usuário e contexto.

### Clientes, advogados e casos

- Cadastro e autenticação de usuários.
- Login tradicional e login com Google OAuth.
- Perfis de cliente, advogado e administrador.
- Solicitação de ajuda jurídica.
- Aceite de casos por advogados.
- Chat entre cliente e advogado.
- Envio de anexos nas mensagens.
- Tarefas vinculadas aos casos.
- Notificações internas.
- Histórico de atendimento e acompanhamento.

### Consulta processual

- Consulta de processos por número CNJ.
- Integração com DataJud/CNJ.
- Validação de número CNJ.
- Controle de consentimento LGPD.
- Cache auditável de consultas.
- Limites de uso por usuário.

### Agenda e atendimento

- Criação e gerenciamento de horários.
- Agendamento de atendimentos.
- Visualização de compromissos.
- Integração com fluxos de casos e solicitações.

### Assinaturas e pagamentos

- Estrutura de planos.
- Controle de assinaturas.
- Checkout de pagamento.
- Integração com Asaas.
- Suporte a PIX quando configurado.
- Webhook de cobrança.
- Faturas, recibos e status de pagamento.
- Cancelamento de assinatura.

### Administração

- Dashboard administrativo.
- Gestão de usuários.
- Validação de advogados por OAB.
- Gestão de documentos.
- Gestão de solicitações.
- Gestão de organizações.
- Gestão de permissões.
- Gestão de planos e assinaturas.
- Relatórios administrativos.
- Exportação de auditoria.
- Monitoramento de integrações.

### Segurança e conformidade

- Controle de acesso por perfil.
- RBAC.
- CSRF.
- Rate limit.
- Auditoria de ações sensíveis.
- Logs operacionais.
- Consentimentos LGPD.
- Exportação de dados do usuário.
- Solicitação de exclusão de conta.
- Storage privado para documentos.
- Healthcheck operacional.
- Jobs assíncronos.
- Rotinas de backup e restore.

---

## Fluxo principal

1. O cliente cria uma conta ou entra com Google OAuth.
2. O cliente envia um documento jurídico.
3. O sistema valida o arquivo, salva em ambiente privado e extrai o texto.
4. A IA gera uma explicação mais simples e estruturada.
5. O cliente pode solicitar atendimento jurídico.
6. Um advogado validado pode aceitar o caso.
7. Cliente e advogado conversam pelo chat, compartilham anexos e acompanham tarefas.
8. O cliente pode consultar processos pelo número CNJ, mediante consentimento.
9. A agenda organiza atendimentos e compromissos.
10. O administrador acompanha operação, usuários, documentos, assinaturas, relatórios e auditoria.

---

## Tecnologias utilizadas

- PHP 8+
- MariaDB / MySQL
- Apache
- Docker
- Docker Compose
- Composer
- HTML
- CSS
- JavaScript
- PDO MySQL
- cURL
- Cloudflare Workers AI
- DataJud/CNJ
- Google OAuth
- Asaas
- PIX
- Tesseract OCR
- ClamAV
- Bacon QR Code
- REST API
- JSON
- PWA / Service Worker
- GitHub Actions

---

## Estrutura do repositório

```text
JusTraduz/
├── .github/workflows/        # automações e deploy
├── backend/
│   ├── app/
│   │   ├── config/           # configuração do backend
│   │   ├── controllers/      # controladores das rotas
│   │   ├── core/             # núcleo da aplicação
│   │   ├── dtos/             # objetos de transporte
│   │   ├── exceptions/       # exceções
│   │   ├── helpers/          # funções auxiliares
│   │   ├── middlewares/      # autenticação, CSRF e proteções
│   │   ├── policies/         # políticas de acesso
│   │   ├── repositories/     # acesso a dados
│   │   ├── resources/        # recursos da aplicação
│   │   ├── services/         # regras de negócio e integrações
│   │   ├── support/          # suporte interno
│   │   ├── transformers/     # transformação de respostas
│   │   └── validators/       # validações
│   ├── public/               # entrada pública do backend
│   ├── routes/               # rotas HTTP
│   ├── storage/              # armazenamento interno do backend
│   └── tests/                # testes automatizados
├── database/
│   ├── justraduz_completo_com_demo.sql
│   ├── justraduz_completo_sem_demo.sql
│   └── README.md
├── docs/                     # documentação técnica e operacional
├── frontend/
│   ├── admin/                # telas administrativas
│   ├── app/                  # recursos do frontend
│   ├── assets/               # CSS, JS e imagens
│   ├── blog/                 # páginas de conteúdo
│   ├── includes/             # componentes compartilhados
│   └── pages/                # páginas internas
├── scripts/                  # scripts de validação, jobs e operação
├── storage-private/          # documentos e anexos privados
├── Dockerfile
├── docker-compose.yml
├── composer.json
├── public-router.php
└── README.md
```

---

## API

O backend possui rotas internas e aliases versionados em `/api/v1`.

Principais rotas versionadas:

- `GET /api/v1/health`
- `GET /api/v1/openapi.json`
- `GET /api/v1/integrations/health`
- `GET /api/v1/integrations/reports/summary`
- `GET /api/v1/me`
- `GET /api/v1/cases`
- `GET /api/v1/reports`
- `POST /api/v1/auth/registrar`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `POST /api/v1/documents/upload`
- `POST /api/v1/documents/analyze`
- `POST /api/v1/cases/create`
- `POST /api/v1/cases/accept`
- `POST /api/v1/messages/send`
- `POST /api/v1/schedule/book`
- `GET /api/v1/admin/reports/summary`
- `GET /api/v1/admin/reports/export`
- `GET /api/v1/admin/audit/export`

A API pública utiliza Bearer Token para integrações controladas e registra auditoria nas chamadas autenticadas.

---

## Requisitos

### Ambiente local

- PHP 8+
- Apache
- MySQL ou MariaDB
- Composer
- Extensões PHP para PDO, cURL, JSON, sessão, arquivos e ZIP
- Opcional: Docker e Docker Compose
- Opcional: Tesseract OCR
- Opcional: ClamAV

### Ambiente de produção

- HTTPS ativo.
- `.env` configurado com variáveis reais e sem placeholders.
- `APP_DEBUG=false`.
- Storage privado fora do webroot.
- SMTP configurado.
- Backups criptografados e testados.
- Jobs assíncronos habilitados quando necessário.
- ClamAV configurado para validação de uploads.
- Tesseract configurado quando `OCR_ENABLED=true`.
- Revisão jurídica e LGPD antes da operação comercial.

---

## Instalação local

1. Clone o repositório:

```bash
git clone https://github.com/TeamGHCP/JusTraduz.git
cd JusTraduz
```

2. Instale as dependências PHP:

```bash
composer install
```

3. Copie o arquivo de ambiente:

```bash
cp backend/.env.example backend/.env
```

No Windows PowerShell:

```powershell
Copy-Item backend\.env.example backend\.env
```

4. Configure as variáveis do arquivo:

```text
backend/.env
```

5. Importe o banco de dados.

Com dados de demonstração:

```bash
mysql -h localhost -u root < database/justraduz_completo_com_demo.sql
```

Sem dados de demonstração:

```bash
mysql -h localhost -u root < database/justraduz_completo_sem_demo.sql
```

6. Acesse pelo navegador conforme a configuração do servidor local.

---

## Instalação com Docker

1. Copie o arquivo de ambiente:

```bash
cp backend/.env.example backend/.env
```

2. Suba os containers:

```bash
docker compose up -d --build
```

3. Verifique os containers:

```bash
docker compose ps
```

O ambiente Docker utiliza:

- Aplicação PHP/Apache
- Worker de jobs
- Banco MariaDB

---

## Banco de dados

O projeto possui dois instaladores consolidados:

- `database/justraduz_completo_com_demo.sql`
- `database/justraduz_completo_sem_demo.sql`

Esses arquivos recriam o banco `justraduz`. Em ambientes com dados reais, não execute scripts destrutivos sem backup, validação prévia e plano de rollback.

Para produção, recomenda-se utilizar migrations incrementais, backup antes da alteração e testes em ambiente de homologação.

---

## Variáveis de ambiente

O arquivo `backend/.env.example` centraliza as configurações do sistema.

Principais grupos:

- Aplicação: `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_PUBLIC_URL`, `APP_BASE_PATH`
- Banco: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- IA: `AI_PROVIDER`, `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_AI_MODEL`
- Google OAuth: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
- E-mail: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`
- DataJud: `DATAJUD_API_BASE_URL`, `DATAJUD_API_KEY`, `DATAJUD_CACHE_TTL_HOURS`
- Pagamentos: `PAYMENT_PROVIDER`, `ASAAS_API_URL`, `ASAAS_API_KEY`, `ASAAS_WEBHOOK_TOKEN`
- PIX: `PIX_CHAVE`, `PIX_NOME`, `PIX_CIDADE`
- Limites: `USAGE_DAILY_DOCUMENT_UPLOADS`, `USAGE_DAILY_DOCUMENT_AI`, `USAGE_DAILY_AI_CHAT`
- Segurança: `CSRF_SECRET`, `CLAMAV_BINARY`, `RATE_LIMIT_STORAGE_PATH`
- OCR: `OCR_ENABLED`, `OCR_TESSERACT_BINARY`, `OCR_LANGUAGE`
- Jobs e backup: `ASYNC_JOBS_ENABLED`, `BACKUP_PATH`, `BACKUP_RETENTION_DAYS`, `BACKUP_ENCRYPTION_PASSWORD`

---

## Integrações externas

| Integração | Uso |
|---|---|
| Cloudflare Workers AI | Análise e simplificação de documentos |
| DataJud/CNJ | Consulta processual por número CNJ |
| Google OAuth | Login e cadastro com conta Google |
| Asaas | Assinaturas, checkout, cobrança e webhook |
| PIX | Pagamentos configuráveis |
| SMTP | Envio de e-mails |
| Tesseract OCR | Extração de texto em imagens e documentos escaneados |
| ClamAV | Scanner de segurança em uploads |

---

## Comandos úteis

Executar testes:

```bash
php backend/tests/run.php
```

Validar ambiente local:

```bash
php scripts/check-local-readiness.php
```

Validar referências internas:

```bash
php scripts/check-references.php
```

Verificar storage órfão:

```bash
php scripts/check-orphan-storage.php
```

Gerar relatório operacional:

```bash
php scripts/operational-health-report.php --output=storage-private/reports/saude-operacional.md
```

Validar ambiente de produção:

```bash
php scripts/check-production-readiness.php --env=backend/.env
```

Executar jobs manualmente:

```bash
php scripts/run-jobs.php --escalate --finalize-deletions
```

---

## Testes

O projeto possui suíte de testes automatizados em PHP.

Comando principal:

```bash
php backend/tests/run.php
```

Áreas cobertas:

- Guardrails de IA.
- Permissões e fluxos críticos.
- Operações prioritárias.
- Fluxos SaaS.
- Rate limiter.
- Referências internas.
- Saúde operacional.

---

## Documentação

A pasta `docs/` reúne documentação técnica, operacional e de conformidade.

Conteúdos principais:

- API pública.
- Checklist de release.
- Configuração de ClamAV.
- Manual operacional interno.
- Matriz de acessibilidade.
- Operação de backup e restore.
- Registro de revisão jurídica.
- Roteiro de QA manual.
- Configuração Apache para produção.

---

## Status do projeto

O JusTraduz possui uma base funcional para evolução comercial, testes de operação e implantação controlada. Antes de operar com clientes reais, recomenda-se validar:

- Domínio e HTTPS.
- Variáveis reais de produção.
- Rotação de segredos.
- SMTP.
- Cloudflare Workers AI.
- DataJud/CNJ.
- Asaas e PIX.
- Webhooks de pagamento.
- Backups e restore.
- ClamAV.
- OCR.
- Logs e auditoria.
- QA manual completo.
- Revisão LGPD e jurídica.
- Contratos, termos de uso, política de privacidade e suporte.

---

## Licença

Este projeto está licenciado sob a licença MIT.

---

## Criadores

O JusTraduz é desenvolvido pela **TeamGHCP**, organização formada pelos criadores do projeto:

- [Pietro Tamanini](https://github.com/PietroTamanini)
- [Guilherme Arthur](https://github.com/guiarthur09)
- [Caetano Petry](https://github.com/caetanopetry)
- [Henrique Nau](https://github.com/henriquenau13)

Organização oficial:

- [TeamGHCP](https://github.com/TeamGHCP)
