# Roteiro de QA manual local

Use este roteiro antes de apresentacao ou depois de qualquer mudanca grande.

## Preparacao

- [ ] XAMPP com Apache e MySQL ativos.
- [ ] `php scripts\check-local-readiness.php` passando.
- [ ] `php backend\tests\run.php` passando.
- [ ] `php scripts\check-references.php` passando.
- [ ] `php scripts\check-orphan-storage.php` sem orfaos inesperados.
- [ ] Navegador em janela anonima ou cache limpo.

## Fluxo cliente

- [ ] Entrar com usuario cliente demo.
- [ ] Abrir dashboard do cliente.
- [ ] Enviar documento valido.
- [ ] Confirmar que upload grande, vazio ou formato proibido mostra erro claro.
- [ ] Abrir documento enviado.
- [ ] Gerar ou explicar analise por IA quando configurada.
- [ ] Criar solicitacao de ajuda vinculada ao documento.
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
- [ ] Confirmar que profissional pendente nao acessa area liberada.

## Fluxo admin

- [ ] Entrar pelo login administrativo.
- [ ] Abrir dashboard admin.
- [ ] Revisar usuarios.
- [ ] Aprovar/rejeitar profissional OAB.
- [ ] Consultar documentos.
- [ ] Exportar auditoria CSV.
- [ ] Confirmar que telas admin exigem perfil admin.

## Responsivo e acessibilidade

- [ ] Desktop.
- [ ] Notebook.
- [ ] Celular pelo DevTools.
- [ ] Menu lateral sem sobrepor conteudo.
- [ ] Chat e agenda usaveis em tela estreita.
- [ ] Foco visivel ao navegar por teclado.
- [ ] Inputs com labels.
- [ ] Mensagens de erro legiveis.

## Plano B para apresentacao

- [ ] Se Gemini/DataJud/SMTP falharem, demonstrar dados demo e explicar fallback.
- [ ] Manter arquivo de exemplo local para upload.
- [ ] Ter uma conta cliente, profissional e admin ja testadas.
- [ ] Nao depender de API externa para o roteiro principal.
