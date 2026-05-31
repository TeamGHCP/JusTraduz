# Roteiro de demo

## Objetivo

Demonstrar o JusTraduz como produto completo: uma pessoa envia um documento jurídico, recebe uma explicação simples com IA, solicita ajuda profissional, conversa com advogado, agenda atendimento e o admin acompanha tudo.

## Preparação

1. Iniciar MySQL pelo XAMPP.
2. Subir o servidor PHP:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8080 public-router.php
```

3. Abrir:

```text
http://127.0.0.1:8080/frontend/index.html
```

4. Ter contas prontas:

- Cliente: para enviar documento e abrir solicitação.
- Advogado: para aceitar/acompanhar caso.
- Admin: para mostrar painel operacional.

5. Ter um PDF curto de exemplo.

Para usar dados prontos, execute `database/seed_demo.sql` e consulte `docs/CREDENCIAIS_DEMO.md`.

## Sequência recomendada

### 1. Landing page

Abrir `frontend/index.html`.

Mostrar:

- proposta do produto;
- fluxo: enviar, entender, pedir ajuda, agendar;
- diferenciais: IA, profissionais, auditoria, admin.

Frase sugerida:

> O JusTraduz transforma documentos jurídicos difíceis em uma explicação simples e conecta o usuário a atendimento profissional quando necessário.

### 2. Cadastro ou login do cliente

Entrar como cliente.

Mostrar:

- dashboard cliente;
- jornada guiada;
- upload de documento.

### 3. Upload e análise por IA

No dashboard cliente:

1. selecionar documento;
2. marcar autorização de IA;
3. enviar;
4. abrir documento;
5. mostrar resumo, explicação simples e confiança.

Ponto importante:

> A análise é informativa e não substitui advogado. O sistema deixa isso claro na interface.

### 4. Solicitação de ajuda

Como cliente:

1. abrir `Solicitar ajuda`;
2. criar solicitação;
3. escolher prioridade;
4. acompanhar status.

Mostrar que o caso vira um atendimento organizado.

### 5. Painel do advogado

Entrar como advogado.

Mostrar:

- mesa do advogado;
- fila priorizada;
- casos ativos;
- documentos recentes;
- tarefas;
- chat.

Se houver caso aberto, aceitar ou acompanhar.

### 6. Chat e tarefas

Abrir chat do caso.

Mostrar:

- conversa por solicitação;
- histórico organizado;
- tarefa associada ao caso.

### 7. Agenda

Como advogado ou admin:

1. criar horário livre.

Como cliente:

1. abrir agenda;
2. escolher profissional;
3. reservar atendimento.

Mostrar que a plataforma vai além da IA: ela organiza o atendimento humano.

### 8. Admin

Entrar em:

```text
http://127.0.0.1:8080/frontend/admin/login-admin.html
```

Mostrar:

- dashboard com métricas;
- gráficos;
- profissionais OAB pendentes;
- documentos com IA pendente;
- solicitações críticas;
- auditoria recente;
- saúde das integrações.

### 9. Gestão OAB/CNA

Em `Usuários`:

- filtrar profissionais pendentes;
- aprovar, revisar ou reprovar OAB;
- explicar que ações são auditadas.

### 10. Auditoria

Em `Auditoria`:

- filtrar severidade;
- mostrar JSON formatado;
- explicar rastreabilidade.

## Ordem curta para apresentação de 5 minutos

1. Landing.
2. Cliente envia documento.
3. IA explica em linguagem simples.
4. Cliente cria solicitação.
5. Advogado acompanha caso.
6. Admin mostra operação e auditoria.

## Ordem completa para apresentação de 10 a 15 minutos

1. Problema.
2. Landing.
3. Cadastro/login.
4. Upload.
5. IA.
6. Solicitação.
7. Chat.
8. Agenda.
9. Advogado.
10. Admin.
11. Segurança/LGPD.
12. Comparação com SmartCart/e-commerce.
13. Próximos passos.

## Frases-chave

- "O produto não tenta substituir o advogado; ele melhora o entendimento inicial."
- "A IA é usada com consentimento e com aviso claro de limitação."
- "O admin mostra maturidade operacional: OAB, IA, documentos, casos e auditoria."
- "O diferencial frente a um e-commerce comum é o problema real, sensível e socialmente relevante."
