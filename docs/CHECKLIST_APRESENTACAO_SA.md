# Checklist pendente para apresentação da SA

Use este checklist no dia da apresentação local.

## Ambiente

- [ ] XAMPP aberto.
- [ ] Apache ligado.
- [ ] MySQL ligado.
- [ ] Porta planejada funcionando.
- [ ] `backend/.env` existe.
- [ ] Banco importado com `database/justraduz_completo_com_demo.sql`.
- [ ] `php scripts\check-local-readiness.php` passando.
- [ ] `php scripts\check-orphan-storage.php` sem órfãos inesperados.
- [ ] Navegador com cache limpo ou janela anônima preparada.

## Contas demo

- [ ] Cliente demo entra.
- [ ] Advogado demo entra.
- [ ] Estagiário demo entra.
- [ ] Admin demo entra pelo login administrativo.
- [ ] Senha demo conferida: `Demo@2026!`.

## Fluxo a ensaiar

- [ ] Login.
- [ ] Dashboard cliente.
- [ ] Upload de documento.
- [ ] Visualização/explicação de documento.
- [ ] Solicitação de ajuda.
- [ ] Chat/mensagens.
- [ ] Agenda.
- [ ] Painel admin.
- [ ] Validação OAB.
- [ ] Relatórios admin.
- [ ] Exportação LGPD.
- [ ] PWA offline, se for demonstrado.

## Plano B

- [ ] Internet disponível se IA/DataJud/OAB forem usados.
- [ ] Fluxo alternativo preparado caso API externa falhe.
- [ ] `MAIL_LOG_ONLY=true` para demonstração sem SMTP real.
- [ ] Arquivos de exemplo disponíveis localmente.
- [ ] Prints ou dados demo prontos para explicar partes que dependam de serviço externo.
