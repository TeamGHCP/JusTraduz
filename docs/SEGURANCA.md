# Segurança

## Objetivo

Este documento descreve os controles de segurança já aplicados no JusTraduz, os riscos residuais e os cuidados antes de qualquer uso fora de ambiente local/acadêmico.

## Controles implementados

### Autenticação

- Senhas são verificadas com `password_verify`.
- Login comum usa mensagem genérica de credenciais inválidas.
- Login regenera o ID de sessão para reduzir risco de session fixation.
- Admin tem entrada separada em `frontend/admin/login-admin.html`.
- Tentativas falhas recentes são limitadas por auditoria.

### Sessão e CSRF

- Sessão é inicializada por helper próprio.
- Token CSRF é gerado por sessão.
- Formulários sensíveis enviam `_csrf`.
- Controllers críticos chamam validação CSRF em operações POST.

### Autorização por perfil

- Páginas frontend usam `require_role`.
- Controllers conferem sessão e perfil antes de executar ações.
- Cliente só acessa seus próprios documentos/casos.
- Advogado acessa casos/documentos vinculados.
- Estagiário não herda permissões de admin.
- Admin mantém visão operacional, com auditoria.

### Documentos e uploads

- Upload permitido apenas para cliente autenticado.
- Extensões aceitas: PDF, PNG, JPG, JPEG e WEBP.
- MIME type é validado.
- Limite atual: 50 MB.
- Acesso direto a `backend/storage/documents/` é bloqueado por `public-router.php`.
- Download deve passar pelo controller, que aplica regra de autorização.

### IA e consentimento

- Documento só é enviado à IA quando o usuário autoriza.
- Resultado da IA é registrado com modelo e versão do prompt.
- A interface informa que a análise é informativa e não substitui orientação jurídica.
- O prompt orienta a IA a não inventar dados ausentes.

### OAB/CNA

- Profissionais informam OAB/UF no cadastro.
- O sistema tenta validação automática no CNA.
- Admin pode aprovar, reprovar ou devolver para pendência.
- Revisões manuais são registradas em `cna_validacao_logs` e `audit_logs`.

### Auditoria

São auditados eventos como:

- cadastro, login, logout e falhas;
- envio, análise e exclusão de documentos;
- criação e atualização de casos;
- envio de mensagens;
- tarefas;
- agenda;
- notificações;
- alterações administrativas;
- validação manual de OAB/CNA.

## Configuração sensível

- `backend/.env` não deve ser versionado.
- `backend/app/config/gemini.php` não deve guardar chave real.
- A chave da Gemini deve ficar em `backend/.env` como `GEMINI_API_KEY`.
- `database/seed_admin.example.sql` é apenas exemplo; a senha deve ser trocada e o hash gerado localmente.

## Auditoria técnica

Data da auditoria: 31/05/2026  
Objetivo: identificar riscos reais no JusTraduz e definir correções práticas antes da banca.

### Diagnóstico direto

O JusTraduz já tem uma base melhor que muitos projetos acadêmicos: usa PDO/prepared statements nas rotas principais, possui CSRF, sessão configurada com cuidado, validação de upload, auditoria e controle por perfil.

O ponto crítico encontrado foi o `backend/.env` rastreado pelo Git. Arquivo sensível versionado é falha grave de processo. A correção aplicada foi remover o arquivo do índice, manter apenas o arquivo local, reforçar o `.gitignore` e criar `backend/.env.example` sem segredos.

### Verificação ponto a ponto

| Item auditado | Status encontrado | Risco | Correção objetiva |
|---|---|---:|---|
| `.env` exposto | `backend/.env` estava rastreado pelo Git | Crítico | Remover do índice, manter no `.gitignore` e criar `backend/.env.example`. |
| Credenciais hardcoded | Seed demo contém hash conhecido; configuração lê ambiente | Médio | Usar apenas em demo, nunca em produção; documentar credenciais fake. |
| SQL Injection | Uso predominante de `prepare()` e parâmetros | Baixo/médio | Manter prepared statements e revisar SQL dinâmico com allowlist. |
| XSS | Frontend PHP usa helper `e()` em várias telas | Médio | Auditar `echo`, `nl2br`, atributos HTML e JSON embutido. |
| CSRF | Middleware existe e formulários usam `_csrf` | Médio | Garantir token em todo POST, inclusive formulários HTML estáticos via JS. |
| Sessão | `secure_session_start()` aplica hardening básico | Médio | Em HTTPS, habilitar cookie `Secure`; regenerar ID após login/reset. |
| Uploads | Documentos validam extensão/MIME/tamanho; fotos também | Alto | Adicionar antivírus em produção e manter storage fora do webroot quando possível. |
| Arquivos privados | Storage tem `.htaccess` e download passa por controller | Alto | Testar acesso direto e manter regra no servidor real, não só no Apache local. |
| Permissões por usuário | Cliente/advogado/admin estão cobertos; estagiário é perfil sensível | Alto | Manter estagiário limitado a agenda/casos atribuídos ou cortar da demo. |
| Acesso direto sem login | Páginas PHP usam helpers de sessão; wrappers redirecionam | Médio | Smoke test de todas as páginas internas anônimas. |
| Validação de entrada | Existe em controllers principais | Médio | Centralizar validadores para email, telefone, status, IDs e datas. |
| Sanitização de saída | Parcialmente padronizada por `e()` | Médio | Tratar todo dado vindo do banco como não confiável. |
| Rotas admin | Rotas admin existem e devem exigir admin | Alto | Manter checagem de admin nos controllers e telas. |
| Logs sensíveis | `AuditService` mascara senha/token/secret | Médio | Não registrar texto integral de documentos jurídicos. |
| Erros internos | `ErrorHandler` considera `APP_DEBUG` | Médio | `APP_DEBUG=false` na demo e produção. |
| Ambiente local | XAMPP facilita acesso indevido se mal configurado | Médio | Evitar listar diretórios, proteger storage e usar banco com senha fora da demo. |

### Correções aplicadas

1. `.gitignore` passou a ignorar `.env` na raiz e em `backend/`, mantendo exceção para `.env.example`.
2. Criado `backend/.env.example` com variáveis usadas pelo projeto, sem segredos.
3. `backend/.env` foi removido do índice do Git sem apagar o arquivo local.

## Checklist antes de produção

- [ ] Usar HTTPS.
- [ ] Definir cookies com `Secure`, `HttpOnly` e `SameSite`.
- [ ] Remover `APP_DEBUG=true` em produção.
- [ ] Rotacionar qualquer chave que já tenha sido exposta.
- [ ] Revisar permissões de diretórios de upload.
- [ ] Colocar storage fora da raiz pública quando possível.
- [ ] Implementar política de retenção e exclusão de documentos.
- [ ] Configurar backup criptografado.
- [ ] Configurar logs de erro sem vazar dados pessoais.
- [ ] Validar SMTP e política de reset de senha.
- [ ] Criar testes automatizados para autorização.
- [ ] Revisar base legal e consentimento LGPD.

## Riscos residuais conhecidos

- Não há OCR para imagem/PDF escaneado.
- Não há antivírus/antimalware no upload.
- Não há testes automatizados cobrindo todos os perfis.
- A retenção de documentos ainda depende de regra operacional.
- O sistema é monolítico local; produção exigiria hardening de servidor.
- A validação CNA pode depender de disponibilidade externa e reCAPTCHA.

## Como explicar na banca

O JusTraduz já aplica controles básicos de segurança para dados sensíveis: login por perfil, CSRF, autorização nos controllers, bloqueio de storage, auditoria, consentimento para IA e validação profissional. Para produção, o plano é reforçar infraestrutura, retenção, testes automatizados e governança LGPD.

