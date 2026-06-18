# JusTraduz — Correções por Módulos na Ordem Correta

Revisão base: 17/06/2026  
Objetivo: organizar as correções do sistema JusTraduz por módulos, na ordem certa de execução, para evitar perda de tempo com polimento antes de resolver bloqueadores reais.

> Regra principal: primeiro corrigir o que quebra o sistema, depois o que aumenta segurança e confiabilidade, e só depois mexer em polimento visual, produto e melhorias futuras.

---

# Ordem correta de execução

1. **Módulo 1 — Configuração e Ambiente**
2. **Módulo 2 — Autenticação e Perfil**
3. **Módulo 3 — Testes Automatizados**
4. **Módulo 4 — LGPD, Permissões e Auditoria**
5. **Módulo 5 — Uploads, Documentos e Storage**
6. **Módulo 6 — Segurança HTTP / CSP / XSS**
7. **Módulo 7 — PWA, Cache e Offline**
8. **Módulo 8 — E-mails e Integrações Externas**
9. **Módulo 9 — Backup, Restore e Operação**
10. **Módulo 10 — QA Manual, Responsivo e Acessibilidade**
11. **Módulo 11 — CI/CD e Checklist de Release**
12. **Módulo 12 — Melhorias Futuras de Produto**

---

# Visão rápida de prioridade

| Ordem | Módulo | Prioridade | Motivo |
| --- | --- | --- | --- |
| 1 | Configuração e Ambiente | P0 | Sem `.env` e `.env.example`, o sistema não tem base confiável para rodar e validar produção. |
| 2 | Autenticação e Perfil | P0 | Reset de senha quebrado afeta usuário real. |
| 3 | Testes Automatizados | P0 | O projeto não está verde. Não existe sinal confiável de release. |
| 4 | LGPD, Permissões e Auditoria | P0/P1 | Fluxo legal/sensível falhando é risco alto. |
| 5 | Uploads, Documentos e Storage | P0/P1 | Documento jurídico mal protegido vira risco sério. |
| 6 | Segurança HTTP / CSP / XSS | P0/P1 | CSP fraca e `innerHTML` sem auditoria aumentam risco de XSS. |
| 7 | PWA, Cache e Offline | P1 | Pode entregar versão antiga ao usuário. |
| 8 | E-mails e Integrações Externas | P1 | Fluxos de aprovação, senha e IA dependem disso. |
| 9 | Backup, Restore e Operação | P1 | Backup sem restore testado é teatro. |
| 10 | QA Manual, Responsivo e Acessibilidade | P1 | Precisa validar o sistema como usuário real. |
| 11 | CI/CD e Checklist de Release | P2 | Evita regressão e deploy quebrado. |
| 12 | Melhorias Futuras de Produto | P2/Futuro | Só depois de estabilizar o essencial. |

---

# Módulo 1 — Configuração e Ambiente

## Prioridade

**P0 — Bloqueador.**

## Por que vem primeiro

Sem configuração de ambiente, o resto vira chute. Se o `.env` não existe e o `.env.example` também não, ninguém consegue instalar, validar ou reproduzir o sistema com segurança.

## Arquivos principais

```txt
backend/.env.example
backend/.env
README.md
scripts/check-production-readiness.php
```

## O que corrigir

### 1. Criar `backend/.env.example`

O README cita `backend/.env.example`, mas esse arquivo não existe no repositório.

Criar um arquivo completo, sem segredos reais, contendo todas as variáveis obrigatórias.

Modelo sugerido:

```env
# Aplicação
APP_ENV=local
APP_URL=http://localhost:9999
APP_DEBUG=true
APP_KEY=

# Banco de dados
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=justraduz
DB_USERNAME=root
DB_PASSWORD=

# Sessão e segurança
SESSION_SECURE=false
CSRF_SECRET=
HEALTHCHECK_TOKEN=

# SMTP / E-mail
SMTP_HOST=
SMTP_PORT=587
SMTP_USERNAME=
SMTP_PASSWORD=
SMTP_FROM_EMAIL=
SMTP_FROM_NAME=JusTraduz

# IA / Integrações externas
GEMINI_API_KEY=
DATAJUD_API_KEY=
OAB_API_URL=

# Storage
DOCUMENT_STORAGE_PATH=
ATTACHMENT_STORAGE_PATH=
PROFILE_PHOTO_STORAGE_PATH=

# Backup
BACKUP_PATH=
BACKUP_RETENTION_DAYS=7

# Ambiente
TIMEZONE=America/Sao_Paulo
```

### 2. Criar/configurar `backend/.env`

Criar o `.env` local baseado no `.env.example`.

No ambiente local com XAMPP, conferir:

```env
APP_URL=http://localhost:9999
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=justraduz
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Atualizar o README

O README precisa explicar:

- Como copiar `.env.example` para `.env`.
- Como configurar banco.
- Como rodar migrations/schema.
- Como iniciar no XAMPP.
- Como validar prontidão.

Exemplo:

```powershell
copy backend\.env.example backend\.env
php scripts\check-production-readiness.php --env=backend/.env
```

### 4. Rodar check de produção

Depois do `.env` criado:

```powershell
php scripts\check-production-readiness.php --env=backend/.env
```

## Critério de pronto

- `backend/.env.example` existe e está versionado.
- `backend/.env` local existe, mas não deve ser versionado.
- README ensina o setup corretamente.
- O script de prontidão não falha por ausência de `.env`.
- Nenhum segredo real foi commitado.

---

# Módulo 2 — Autenticação e Perfil

## Prioridade

**P0 — Bloqueador.**

## Por que vem agora

Reset de senha quebrado afeta diretamente usuário real. Isso não é detalhe. Sistema que não recupera senha passa sensação de amadorismo e trava acesso.

## Arquivos principais

```txt
frontend/pages/app/perfil.php
backend/app/controllers/AuthController.php
```

## O que corrigir

### 1. Corrigir campo do código no reset de senha

Problema:

No arquivo:

```txt
frontend/pages/app/perfil.php
```

O campo do código está usando nome errado ou com acento:

```html
name="código"
```

Mas o backend lê:

```php
codigo
```

No método:

```txt
AuthController::profilePasswordReset()
```

Corrigir o input para:

```html
<input type="text" name="codigo" id="codigo">
```

O nome precisa ser exatamente:

```txt
codigo
```

Sem acento.

### 2. Validar fluxo completo no navegador

Testar:

1. Usuário logado abre perfil.
2. Solicita reset de senha.
3. Recebe ou informa código.
4. Digita nova senha.
5. Backend recebe `codigo`.
6. Senha é alterada.
7. Login com nova senha funciona.

### 3. Criar teste específico

Adicionar teste para garantir que o reset pelo perfil não quebre de novo.

Fluxo mínimo do teste:

- Simular usuário autenticado.
- Enviar `codigo`.
- Enviar nova senha.
- Validar resposta de sucesso.
- Validar que senha foi alterada.

## Critério de pronto

- Campo envia `codigo`.
- Backend recebe código corretamente.
- Reset de senha pelo perfil funciona.
- Existe teste cobrindo o fluxo.
- Login com nova senha funciona.

---

# Módulo 3 — Testes Automatizados

## Prioridade

**P0 — Bloqueador.**

## Por que vem antes de mexer no resto

Se os testes estão falhando, o projeto não está verde. Continuar adicionando coisa por cima é empilhar sujeira. Primeiro deixar a base confiável.

## Arquivos principais

```txt
backend/tests/run.php
backend/tests/AiGuardrailsTest.php
backend/tests/PermissionAndCriticalFlowsTest.php
backend/tests/P1OperationsTest.php
backend/app/services/GeminiService.php
```

## O que corrigir

### 1. Corrigir `AiGuardrailsTest.php`

Falha encontrada:

```txt
Prompt deve proibir calculo de prazos.
```

O teste espera:

```txt
Nao calcule prazos processuais
```

Mas o prompt atual contém:

```txt
Não calcule prazos processuais
```

O problema é desalinhamento por acentuação.

## Solução recomendada

Normalizar acentos no teste antes da comparação.

Exemplo conceitual:

```php
$normalizedPrompt = removerAcentos($prompt);

$this->assertStringContainsString(
    'Nao calcule prazos processuais',
    $normalizedPrompt
);
```

## Solução rápida

Alterar o prompt em `GeminiService.php` para usar texto sem acento:

```txt
Nao calcule prazos processuais
```

Mas a melhor solução é o teste ser tolerante a acentuação, porque isso evita falha boba de encoding.

---

### 2. Corrigir `PermissionAndCriticalFlowsTest.php`

Problemas encontrados:

- Warnings de sessão:
  ```txt
  headers already sent
  ```

- Falha na exportação LGPD:
  ```txt
  Exportacao LGPD deve incluir cadastro do titular
  ```

Esperado:

```txt
cliente1@teste.local
```

Obtido:

```txt
vazio
```

Investigar:

- Como o usuário titular é criado no teste.
- Como a sessão é simulada.
- Se o controller está lendo o usuário certo.
- Se a exportação está buscando os dados da tabela correta.
- Se o e-mail está sendo omitido por erro de consulta ou por falta de autenticação.

Arquivos prováveis:

```txt
backend/tests/PermissionAndCriticalFlowsTest.php
backend/app/controllers/PrivacyController.php
backend/app/controllers/AuditExportController.php
```

---

### 3. Rodar os testes individualmente

```powershell
php backend\tests\AiGuardrailsTest.php
php backend\tests\PermissionAndCriticalFlowsTest.php
php backend\tests\P1OperationsTest.php
```

### 4. Rodar a suíte completa

```powershell
php backend\tests\run.php
```

## Critério de pronto

- `AiGuardrailsTest.php` passa.
- `PermissionAndCriticalFlowsTest.php` passa.
- `P1OperationsTest.php` continua passando.
- `backend/tests/run.php` passa completo.
- Sem warnings de sessão.
- Sem falha silenciosa em LGPD.

---

# Módulo 4 — LGPD, Permissões e Auditoria

## Prioridade

**P0/P1.**

## Por que vem agora

LGPD e permissão não são enfeite. Se o usuário consegue exportar dado errado, dado vazio ou dado de outra pessoa, o sistema perde credibilidade e segurança.

## Arquivos principais

```txt
backend/app/controllers/PrivacyController.php
backend/app/controllers/AuditExportController.php
backend/app/middlewares/AuthMiddleware.php
backend/tests/PermissionAndCriticalFlowsTest.php
```

## O que corrigir

### 1. Corrigir exportação LGPD

A exportação precisa incluir corretamente os dados do titular.

Verificar:

- ID do usuário autenticado.
- Consulta de cadastro do titular.
- Inclusão de e-mail.
- Inclusão de nome.
- Inclusão de perfil.
- Inclusão de histórico relevante.
- Permissão para exportar apenas os próprios dados, exceto admin quando aplicável.

### 2. Corrigir warnings de sessão

O warning:

```txt
headers already sent
```

indica que alguma saída está acontecendo antes de iniciar/modificar sessão.

Verificar:

- Espaços antes de `<?php`.
- Echo/var_dump perdido.
- Arquivo incluído imprimindo conteúdo.
- Teste iniciando sessão depois de já ter gerado saída.
- Ordem dos includes.

### 3. Validar auditoria de ações sensíveis

Ações que precisam gerar log:

- Login.
- Logout.
- Cadastro.
- Alteração de senha.
- Upload.
- Download.
- Exclusão de documento.
- Criação de solicitação.
- Aceite de solicitação.
- Exportação LGPD.
- Encerramento de conta.
- Aprovação de advogado.
- Rejeição de advogado.
- Aprovação de estagiário.
- Rejeição de estagiário.
- Alterações administrativas.

### 4. Validar separação de perfis

Perfis existentes:

- Cliente.
- Advogado.
- Estagiário.
- Admin.

Validar:

- Cliente não acessa painel de advogado.
- Cliente não acessa painel admin.
- Advogado não acessa painel admin.
- Estagiário não executa ação de advogado se não tiver permissão.
- Profissional pendente não acessa dashboard liberado.
- Admin consegue aprovar/rejeitar profissional.

## Critério de pronto

- Exportação LGPD traz dados corretos.
- Exportação não vaza dados de outro usuário.
- Warnings de sessão foram removidos.
- Auditoria registra ações sensíveis.
- Testes de permissão passam.

---

# Módulo 5 — Uploads, Documentos e Storage

## Prioridade

**P0/P1.**

## Por que vem agora

O sistema lida com documento jurídico. Upload mal validado e storage mal posicionado são riscos reais. Não trate isso como detalhe técnico.

## Arquivos principais

```txt
backend/app/controllers/CaseController.php
backend/app/services/StorageService.php
backend/app/services/UploadScannerService.php
backend/storage/documents/.htaccess
backend/storage/message-attachments/.htaccess
```

## O que corrigir

### 1. Revisar validação de `.docx`

Problema:

O sistema aceita MIME:

```txt
application/zip
```

Isso provavelmente foi feito porque `.docx` é um ZIP internamente, mas isso abre margem para ZIP genérico passar como documento.

## Correção esperada

Para `.docx`, validar:

- Extensão `.docx`.
- MIME compatível.
- Estrutura real interna do arquivo.
- Arquivos obrigatórios do DOCX.

Estrutura mínima esperada:

```txt
[Content_Types].xml
word/document.xml
_rels/.rels
```

Se o arquivo não tiver essa estrutura, rejeitar.

### 2. Testar uploads bons e ruins

Testar:

- PDF válido.
- DOCX válido.
- TXT válido, se permitido.
- Imagem válida, se permitida.
- Arquivo vazio.
- Arquivo grande.
- Arquivo corrompido.
- ZIP renomeado para `.docx`.
- `.exe` renomeado para `.pdf`.
- MIME falso.
- Nome com caracteres especiais.
- Nome com tentativa de path traversal:
  ```txt
  ../../arquivo.pdf
  ```

### 3. Confirmar storage sensível fora do webroot

Variáveis relacionadas:

```txt
DOCUMENT_STORAGE_PATH
ATTACHMENT_STORAGE_PATH
PROFILE_PHOTO_STORAGE_PATH
```

Documentos e anexos não devem ficar diretamente acessíveis por URL pública.

O acesso correto deve ser:

```txt
Usuário autenticado -> Controller -> Verificação de permissão -> Download
```

Nunca:

```txt
URL direta -> arquivo sensível
```

### 4. Decidir regra para foto de perfil

Hoje as fotos de perfil ficam em:

```txt
backend/storage/profile_photos
```

Decidir:

- Avatar é público?
- Avatar é privado?
- Avatar precisa de controller?
- Avatar precisa de `.htaccess`?

Se for público, documentar.

Se for privado, bloquear acesso direto.

## Critério de pronto

- DOCX é validado pela estrutura real.
- ZIP genérico não passa.
- Upload malicioso é bloqueado.
- Documentos e anexos ficam fora do webroot.
- Download exige permissão.
- Foto de perfil tem regra explícita.

---

# Módulo 6 — Segurança HTTP / CSP / XSS

## Prioridade

**P0/P1.**

## Por que vem agora

CSP fraca e renderização insegura de HTML são portas clássicas para XSS. Em sistema com login, documentos e dados jurídicos, isso precisa ser tratado antes de polimento.

## Arquivos principais

```txt
backend/app/support/security.php
frontend/**/*.js
frontend/**/*.php
```

## O que corrigir

### 1. Remover ou justificar `unsafe-eval`

Arquivo:

```txt
backend/app/support/security.php
```

Problema:

A CSP contém:

```txt
'unsafe-eval'
```

Isso reduz a proteção contra XSS.

Ação correta:

1. Remover `unsafe-eval`.
2. Testar telas principais.
3. Se algo quebrar, identificar qual dependência exige.
4. Se for realmente necessário, documentar o motivo.

### 2. Auditar usos de `innerHTML`

Buscar:

```powershell
findstr /S /I "innerHTML" frontend\*.js frontend\*.php
```

Para cada ocorrência:

- Se renderiza texto do usuário, trocar por `textContent`.
- Se precisa renderizar HTML, sanitizar.
- Se usa resposta da API, escapar antes.
- Se é HTML fixo interno, documentar como baixo risco.

### 3. Validar headers de segurança

Confirmar existência e funcionamento:

- `Content-Security-Policy`
- `X-Frame-Options`
- `X-Content-Type-Options`
- `Referrer-Policy`
- `Permissions-Policy`

## Critério de pronto

- `unsafe-eval` removido ou justificado.
- `innerHTML` revisado.
- Dados de usuário não entram crus no HTML.
- Headers de segurança continuam ativos.
- Nenhuma tela principal quebra depois da CSP ajustada.

---

# Módulo 7 — PWA, Cache e Offline

## Prioridade

**P1.**

## Por que vem depois da segurança

PWA é importante para apresentação, mas cache errado pode esconder correções. Primeiro estabiliza o sistema, depois garante que o navegador entregue a versão certa.

## Arquivos principais

```txt
frontend/service-worker.js
frontend/site.webmanifest
frontend/offline.html
```

## O que corrigir

### 1. Controlar versão do cache

Problema:

O service worker usa:

```js
CACHE_VERSION = "justraduz-pwa-v4"
```

Se mudar CSS/JS/HTML e não mudar a versão, o navegador pode continuar servindo asset antigo.

## Solução simples

Atualizar manualmente a cada deploy:

```js
const CACHE_VERSION = "justraduz-pwa-v5";
```

## Solução melhor

Gerar versão por data/build:

```js
const CACHE_VERSION = "justraduz-pwa-2026-06-18-01";
```

### 2. Testar PWA no navegador

Testar:

- Instalação no Chrome.
- Ícone.
- Nome do app.
- Splash/open.
- Página offline.
- Cache dos assets.
- Atualização depois de novo deploy.
- Limpeza de cache antigo.
- Funcionamento em celular.

### 3. Validar assets cacheados

A revisão indicou que os assets declarados existem. Manter essa checagem.

## Critério de pronto

- PWA instala.
- Offline funciona.
- Cache atualiza depois de deploy.
- Usuário não fica preso em versão antiga.
- Manifest continua válido.

---

# Módulo 8 — E-mails e Integrações Externas

## Prioridade

**P1.**

## Por que vem agora

Fluxos de cadastro, senha, aprovação e IA dependem de serviços externos. Serviço externo falha. O sistema precisa falhar de modo controlado.

## Arquivos principais

```txt
Serviço de e-mail/SMTP
Serviço Gemini/IA
Serviço OAB/DataJud
Controllers de autenticação
Controllers de aprovação profissional
```

## O que testar/corrigir

### 1. E-mail transacional

Testar fluxos:

- Cadastro.
- Recuperação de senha.
- Código de reset.
- Aprovação de advogado.
- Rejeição de advogado.
- Aprovação de estagiário.
- Rejeição de estagiário.
- Notificações importantes.

Validar:

- SMTP real.
- SPF.
- DKIM.
- DMARC.
- Se cai em spam.
- Mensagem amigável se o envio falhar.
- Log sem vazar senha SMTP.

### 2. IA / Gemini

Testar:

- Chave inválida.
- Sem internet.
- Timeout.
- Resposta vazia.
- Erro 500.
- Limite diário.
- Prompt jurídico sensível.
- Guardrails.
- Rate limit.

### 3. OAB / DataJud

Testar:

- Sucesso.
- Nenhum resultado.
- Serviço fora.
- Timeout.
- reCAPTCHA bloqueando.
- Resposta inesperada.
- Chave inválida.
- Fallback para pendente quando necessário.

## Critério de pronto

- E-mails principais funcionam.
- Falha de SMTP não quebra tela.
- IA respeita limites e guardrails.
- Consulta OAB/DataJud tem fallback.
- Erros externos são logados sem vazar segredo.

---

# Módulo 9 — Backup, Restore e Operação

## Prioridade

**P1.**

## Por que vem agora

Backup que nunca foi restaurado é só uma falsa sensação de segurança. Para apresentação até pode parecer exagero, mas para sistema sério isso separa projeto escolar de sistema confiável.

## Arquivos principais

```txt
scripts de backup
storage de documentos
banco MySQL
rota de healthcheck
logs
```

## O que corrigir/testar

### 1. Testar backup

Validar:

- Backup do banco.
- Backup de documentos.
- Backup de anexos.
- Backup de logs importantes.
- Nomeação dos arquivos.
- Retenção.
- Permissão de acesso.
- Local seguro.

### 2. Testar restore

Criar ambiente limpo e restaurar.

Depois do restore, validar:

- Login.
- Usuários.
- Perfis.
- Solicitações.
- Documentos.
- Anexos.
- Permissões.
- Auditoria.

### 3. Validar healthcheck

Rota citada:

```txt
/backend/public/index.php?rota=/health
```

Validar se checa:

- Aplicação respondendo.
- Banco conectado.
- Storage acessível.
- Configuração essencial presente.
- Ambiente correto.

### 4. Criar plano de rollback

Documentar:

- Como voltar versão.
- Como restaurar banco.
- Como restaurar arquivos.
- Quem executa.
- Quanto tempo demora.
- O que verificar depois.

## Critério de pronto

- Backup gera arquivo válido.
- Restore funciona em ambiente limpo.
- Healthcheck responde corretamente.
- Existe plano de rollback.
- Logs não vazam dados sensíveis.

---

# Módulo 10 — QA Manual, Responsivo e Acessibilidade

## Prioridade

**P1.**

## Por que vem depois dos bloqueadores

QA manual antes dos bloqueadores é perder tempo. Depois que o sistema fica tecnicamente estável, aí sim simula usuário real.

## O que testar

### 1. Fluxos principais

Testar no navegador:

- Cadastro.
- Login.
- Logout.
- Recuperar senha.
- Reset por código.
- Dashboard cliente.
- Dashboard advogado.
- Dashboard estagiário.
- Dashboard admin.
- Upload de documento.
- Download de documento.
- Exclusão de documento.
- Criar solicitação.
- Pedir ajuda.
- Aceitar solicitação.
- Chat/mensagens.
- Anexos.
- Agenda.
- Aprovação OAB.
- Rejeição OAB.
- Exportação LGPD.
- Encerramento de conta.
- PWA offline.

### 2. Responsivo

Testar em:

- Desktop.
- Notebook.
- Tablet.
- Celular.
- PWA instalado.

### 3. Acessibilidade

Verificar:

- Contraste.
- Foco visível.
- Navegação por teclado.
- Labels nos inputs.
- Mensagens de erro claras.
- Botões compreensíveis.
- Estados vazios.
- Estados de loading.
- Estados de erro.
- Leitura básica por leitor de tela, se possível.

### 4. Formulários com erro

Regra importante:

Quando ocorrer erro, o sistema não deve apagar tudo.

Verificar:

- Cadastro.
- Login.
- Perfil.
- Solicitação.
- Upload.
- Chat.
- Agenda.

## Critério de pronto

- Fluxos principais funcionam.
- Sistema não quebra no celular.
- Erros são claros.
- Formulários preservam dados.
- Interface não depende do console para entender problema.

---

# Módulo 11 — CI/CD e Checklist de Release

## Prioridade

**P2, mas importante antes de qualquer deploy sério.**

## Por que vem aqui

Depois que os testes passam, coloque eles para rodar automaticamente. Senão o projeto volta a quebrar em dois commits.

## O que criar

### 1. CI básico

A cada push ou PR, rodar:

```powershell
php -l em todos os arquivos PHP
php scripts\check-references.php
php backend\tests\run.php
php scripts\check-production-readiness.php --env=backend/.env.example
```

### 2. Checklist de release

Criar documento com:

```txt
docs/CHECKLIST_RELEASE.md
```

Conteúdo mínimo:

- Branch atualizada.
- Sem conflito Git.
- Testes passando.
- `.env.example` atualizado.
- Migrations revisadas.
- Backup feito antes do deploy.
- Cache PWA atualizado.
- Permissões de storage conferidas.
- SMTP testado.
- Integrações testadas.
- Healthcheck OK.
- Plano de rollback pronto.

### 3. Checklist de deploy local para apresentação da SA

Como o objetivo atual é apresentação da SA, criar também:

```txt
docs/CHECKLIST_APRESENTACAO_SA.md
```

Incluir:

- XAMPP ligado.
- Apache na porta correta.
- MySQL ligado.
- Banco importado.
- Usuários demo funcionando.
- Internet disponível, se IA/OAB forem usadas.
- Plano B se API externa falhar.
- Navegador limpo/cache limpo.
- PWA testado.
- Fluxo de demonstração ensaiado.

## Critério de pronto

- CI roda automaticamente.
- Release tem checklist.
- Apresentação tem checklist separado.
- Deploy não depende só de memória.

---

# Módulo 12 — Melhorias Futuras de Produto

## Prioridade

**P2/Futuro.**

## Regra

Não começar este módulo antes de concluir P0 e P1.

Mexer nisso antes é jogar pequeno: parece produtividade, mas só mascara instabilidade.

## Ideias futuras

- Planos e pagamentos.
- Multiempresa.
- Permissões granulares.
- Relatórios avançados.
- SLA e escalonamento.
- API pública versionada.
- Rotina de limpeza de arquivos órfãos.
- Limpeza de jobs antigos.
- Relatório periódico de saúde.
- Dashboard de operação.
- Melhorias visuais premium.
- Polimento da landing page.
- Melhorias de onboarding.
- Tela pública de status.
- Monitoramento real.
- Métricas de uso.
- Melhorias de performance.

## Critério para iniciar

Só iniciar depois que:

- Todos os P0 estiverem concluídos.
- Testes estiverem verdes.
- QA manual principal estiver aprovado.
- PWA estiver validado.
- Upload/storage estiver seguro.
- Backup/restore tiver sido testado.

---

# Checklist final de pronto

O sistema só deve ser considerado pronto para uma entrega forte quando:

- [ ] `backend/.env.example` existir e estiver completo.
- [ ] `backend/.env` local estiver configurado.
- [ ] Check de produção rodar sem falhar por configuração ausente.
- [ ] Reset de senha do perfil funcionar.
- [ ] `AiGuardrailsTest.php` passar.
- [ ] `PermissionAndCriticalFlowsTest.php` passar.
- [ ] `P1OperationsTest.php` passar.
- [ ] `backend/tests/run.php` passar completo.
- [ ] Exportação LGPD incluir dados corretos do titular.
- [ ] Auditoria registrar ações sensíveis.
- [ ] Upload de DOCX validar estrutura real.
- [ ] ZIP genérico não passar como DOCX.
- [ ] Storage sensível estar fora do webroot.
- [ ] `unsafe-eval` ser removido ou justificado.
- [ ] Usos de `innerHTML` serem auditados.
- [ ] PWA instalar e atualizar cache corretamente.
- [ ] E-mails principais serem testados.
- [ ] IA/Gemini ter fallback para falhas.
- [ ] OAB/DataJud ter fallback para falhas.
- [ ] Backup ser gerado.
- [ ] Restore ser testado em ambiente limpo.
- [ ] Healthcheck responder corretamente.
- [ ] QA manual dos fluxos principais ser aprovado.
- [ ] Responsivo ser testado.
- [ ] Acessibilidade básica ser revisada.
- [ ] Checklist de release existir.
- [ ] Checklist da apresentação da SA existir.

---

# Resumo brutal

A ordem correta não é começar por tela bonita.

A ordem correta é:

```txt
.env e ambiente
→ reset de senha
→ testes verdes
→ LGPD/permissões
→ upload/storage
→ segurança
→ PWA
→ integrações
→ backup/restore
→ QA manual
→ CI/release
→ melhorias futuras
```

Enquanto os P0 estiverem abertos, qualquer polimento visual é secundário.
