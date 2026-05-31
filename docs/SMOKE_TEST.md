# Smoke test manual

Execute este roteiro antes da apresentação. Marque o resultado na coluna "OK".

## Ambiente

- Data do teste: ____/____/____
- Responsável: __________________
- URL local: `http://127.0.0.1:8080/frontend/index.html`

## Pré-requisitos

- [ ] MySQL iniciado.
- [ ] Banco importado.
- [ ] Migrations aplicadas.
- [ ] `database/seed_demo.sql` executado quando a apresentação usar dados prontos.
- [ ] Conta cliente disponível.
- [ ] Conta advogado disponível.
- [ ] Conta admin disponível.
- [ ] Documento PDF/imagem de teste disponível.
- [ ] Servidor PHP iniciado com `public-router.php`.

## Testes

| OK | Área | Passo | Resultado esperado |
|---|---|---|---|
| [ ] | Landing | Abrir `frontend/index.html`. | Página abre com favicon correto, logo e CTAs. |
| [ ] | Cadastro | Abrir cadastro e alternar perfil advogado. | Campos OAB/UF aparecem. |
| [ ] | Login cliente | Entrar como cliente. | Redireciona para dashboard cliente. |
| [ ] | Upload | Enviar documento com autorização IA. | Documento é salvo e aparece mensagem de sucesso. |
| [ ] | IA | Abrir documento analisado. | Resumo, explicação e confiança aparecem. |
| [ ] | Documento | Tentar abrir documento permitido. | Arquivo ou imagem aparece sem erro. |
| [ ] | Solicitação | Criar solicitação como cliente. | Caso aparece em acompanhamento. |
| [ ] | Advogado | Entrar como advogado. | Dashboard mostra fila/casos. |
| [ ] | Chat | Enviar mensagem em caso permitido. | Mensagem aparece no histórico. |
| [ ] | Tarefas | Criar/atualizar tarefa como advogado/admin. | Status atualiza sem erro. |
| [ ] | Agenda profissional | Criar horário livre. | Slot aparece no calendário. |
| [ ] | Agenda cliente | Agendar horário livre como cliente. | Atendimento fica confirmado. |
| [ ] | Admin login | Entrar pelo login admin. | Abre dashboard admin. |
| [ ] | Admin dashboard | Conferir métricas e gráficos. | Cards e filas carregam sem erro. |
| [ ] | OAB admin | Filtrar OAB pendente. | Profissionais aparecem ou empty state correto. |
| [ ] | Documentos admin | Filtrar documentos pendentes/analisados. | Tabela responde aos filtros. |
| [ ] | Solicitações admin | Filtrar casos críticos. | Fila responde aos filtros. |
| [ ] | Auditoria | Abrir auditoria. | Logs aparecem com severidade e JSON formatado. |
| [ ] | Segurança storage | Abrir URL direta de `backend/storage/documents/...`. | Retorna bloqueio/403. |
| [ ] | Logout | Sair do sistema. | Sessão encerra. |

## Critérios de parada

Não apresentar ao vivo sem corrigir:

- erro fatal PHP;
- login quebrado;
- upload quebrado;
- admin inacessível;
- documento de outro usuário visível indevidamente;
- rota de storage acessível diretamente;
- chave sensível aparecendo no código exibido.

## Observações

Registre aqui qualquer problema encontrado:

```text

```
