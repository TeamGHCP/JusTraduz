# Checklist LGPD

Este checklist não substitui parecer jurídico. Ele organiza os cuidados mínimos para defender o projeto e evoluir para uso real.

## Dados tratados

- Nome.
- E-mail.
- Telefone.
- Senha com hash.
- Perfil de usuário.
- OAB e UF.
- Documentos enviados.
- Texto extraído de documentos.
- Resultado de IA.
- Mensagens de chat.
- Agendamentos.
- Logs de auditoria, IP e user-agent.

## Bases e transparência

- [x] Informar finalidade do sistema.
- [x] Informar que a IA é opcional no upload.
- [x] Exigir autorização explícita antes de enviar documento para IA.
- [x] Avisar que a análise é informativa e não substitui advogado.
- [ ] Revisar termos de uso com linguagem jurídica final.
- [ ] Revisar política de privacidade com responsável jurídico.

## Minimização

- [x] Cadastro coleta dados básicos.
- [x] OAB/UF só é exigida para profissional.
- [x] Admin visualiza dados para operação e auditoria.
- [ ] Definir prazo de retenção de documentos.
- [ ] Permitir anonimização ou exclusão assistida de dados antigos.

## Controle de acesso

- [x] Cliente acessa seus próprios documentos/casos.
- [x] Advogado acessa casos e documentos vinculados.
- [x] Estagiário possui permissão limitada.
- [x] Admin possui área separada.
- [x] Rotas sensíveis validam sessão/perfil.
- [ ] Criar testes automatizados de autorização.

## Segurança dos dados

- [x] Senhas com hash.
- [x] CSRF em ações sensíveis.
- [x] Storage de documentos bloqueado para acesso direto.
- [x] Auditoria de ações sensíveis.
- [x] Chaves sensíveis fora do código versionado.
- [ ] HTTPS em produção.
- [ ] Cookies `Secure`, `HttpOnly` e `SameSite`.
- [ ] Backup criptografado.
- [ ] Antivírus/antimalware para upload.
- [ ] Monitoramento de erros e tentativas suspeitas.

## Operadores e terceiros

- [x] Gemini é usado apenas quando há autorização.
- [ ] Documentar fornecedor de IA na política de privacidade.
- [ ] Documentar SMTP/e-mail como operador, quando configurado.
- [ ] Avaliar termos dos serviços externos.

## Direitos do titular

- [ ] Canal para solicitar acesso aos dados.
- [ ] Canal para solicitar correção.
- [ ] Canal para solicitar exclusão.
- [ ] Registro interno de solicitações LGPD.
- [ ] Procedimento para exportar dados do titular.

## Incidentes

- [ ] Definir responsável por incidentes.
- [ ] Definir fluxo de resposta.
- [ ] Definir critérios de comunicação.
- [ ] Manter histórico de incidentes e ações.

## Estado para apresentação

Para a banca, o projeto já demonstra preocupação real com LGPD: consentimento de IA, controle por perfil, auditoria e bloqueio de storage. Para produção, o principal avanço necessário é formalizar política de retenção, direitos do titular, contratos com operadores e hardening de infraestrutura.

