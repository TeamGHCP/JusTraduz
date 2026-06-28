# Roteiro pendente de QA manual

Execute este roteiro antes da apresentação ou depois de mudança grande.

## Preparação

- [ ] XAMPP com Apache e MySQL ativos.
- [ ] `php scripts\check-local-readiness.php` passando.
- [ ] `php backend\tests\run.php` passando.
- [ ] `php scripts\check-references.php` passando.
- [ ] `php scripts\check-orphan-storage.php` sem órfãos inesperados.
- [ ] Navegador em janela anônima ou cache limpo.

## Fluxo Cliente

- [ ] Entrar com usuário cliente demo.
- [ ] Abrir dashboard do cliente.
- [ ] Enviar documento válido.
- [ ] Confirmar erro claro para upload grande, vazio ou formato proibido.
- [ ] Abrir documento enviado.
- [ ] Gerar ou explicar análise por IA quando configurada.
- [ ] Criar solicitação de ajuda vinculada ao documento.
- [ ] Abrir chat do caso.
- [ ] Enviar mensagem com e sem anexo.
- [ ] Agendar atendimento.
- [ ] Exportar dados LGPD pelo perfil.
- [ ] Testar reset de senha pelo perfil.

## Fluxo profissional

- [ ] Entrar como advogado demo.
- [ ] Ver fila de casos.
- [ ] Aceitar caso aberto.
- [ ] Ver documentos vinculados permitidos.
- [ ] Usar chat, tarefas e agenda.
- [ ] Confirmar que profissional pendente não acessa área liberada.
- [ ] Entrar com o segundo advogado demo e confirmar o Max Advogado.
- [ ] Confirmar que o Escritório lista somente os dois advogados ativos.
- [ ] Confirmar que cliente não pode enviar nem aceitar convite do Escritório.

## Planos e pagamento

- [ ] Comprar Max Cliente no sandbox e confirmar ativação por webhook.
- [ ] Comprar Max Advogado no sandbox e confirmar ativação por webhook.
- [ ] Comprar Escritório no sandbox com a quantidade permitida de participantes.
- [ ] Confirmar envio do convite somente depois do pagamento.
- [ ] Aceitar convite com conta existente de advogado.
- [ ] Abrir convite sem conta e confirmar redirecionamento para cadastro de advogado.
- [ ] Confirmar limites e benefícios funcionais de cada plano.

## Fluxo Admin

- [ ] Entrar pelo login administrativo.
- [ ] Abrir dashboard admin.
- [ ] Revisar usuários.
- [ ] Aprovar/rejeitar profissional OAB.
- [ ] Consultar documentos.
- [ ] Abrir relatórios admin.
- [ ] Exportar auditoria CSV.
- [ ] Confirmar que telas admin exigem perfil admin.

## Responsivo e acessibilidade

- [ ] Desktop.
- [ ] Notebook.
- [ ] Celular pelo DevTools.
- [ ] Menu lateral sem sobrepor conteúdo.
- [ ] Chat e agenda usáveis em tela estreita.
- [ ] Foco visível ao navegar por teclado.
- [ ] Inputs com labels.
- [ ] Mensagens de erro legíveis.

## Plano B para apresentação

- [ ] Se Gemini/DataJud/SMTP falharem, demonstrar dados demo e explicar fallback.
- [ ] Manter arquivo de exemplo local para upload.
- [ ] Ter conta Cliente, Advogado e Admin já testadas.
- [ ] Não depender de API externa para o roteiro principal.
