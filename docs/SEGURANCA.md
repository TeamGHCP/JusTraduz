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

