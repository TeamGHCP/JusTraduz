# Pronto para apresentar

Data: 31/05/2026  
Objetivo: avaliar se o JusTraduz aguenta uma apresentacao de banca e definir uma demo segura ate 07/07/2026.

## Diagnostico brutal

O projeto tem material suficiente para impressionar, mas tambem tem muitos pontos de falha. A demo nao pode tentar mostrar tudo. Se mostrar tela vazia, erro de integracao externa, `.env`, SMTP quebrado ou fluxo de estagiario confuso, o JusTraduz perde para um sistema mais simples e estavel.

A regra da banca deve ser: mostrar o fluxo que prova valor, maturidade e seguranca. O resto fica como roadmap.

## Precisa estar funcionando obrigatoriamente

| Fluxo | Por que e obrigatorio | Risco se falhar |
|---|---|---|
| Login cliente | Porta de entrada da jornada | Demo nem comeca. |
| Upload/visualizacao de documento | E o coracao do produto | Diferencial desaparece. |
| Analise IA pre-carregada | Mostra linguagem simples | Produto vira CRUD. |
| Criar solicitacao | Conecta IA a atendimento humano | Jornada fica incompleta. |
| Login advogado | Mostra segundo lado da plataforma | Parece sistema de cliente apenas. |
| Aceitar/acompanhar caso | Prova operacao real | Atendimento parece falso. |
| Chat | Mostra comunicacao cliente-advogado | Plataforma perde utilidade. |
| Agenda | Mostra continuidade do atendimento | Fluxo comercial enfraquece. |
| Login admin | Prova maturidade SaaS | Banca ve so telas de usuario. |
| Admin com metricas | Mostra operacao e controle | Fica com cara amadora. |
| Validacao OAB/CNA manual | Diferencial juridico | Perde confianca. |
| Auditoria | Diferencial tecnico/seguranca | Segurança fica so discurso. |

## Pode ser simulado

| Item | Como simular bem | Frase honesta |
|---|---|---|
| Chamada Gemini ao vivo | Usar analise ja salva no seed | "Para evitar depender da internet na banca, deixamos uma analise pre-processada." |
| Consulta CNA/OAB | Usar profissional pendente e aprovacao admin | "A integracao existe, mas a revisao manual cobre indisponibilidade externa." |
| E-mail de recuperacao | Nao demonstrar envio real | "O fluxo usa codigo, mas SMTP e configuracao de ambiente." |
| Notificacao em tempo real | Mostrar notificacoes registradas | "A versao atual registra notificacoes; realtime fica para evolucao." |
| Planos pagos | Mostrar como roadmap comercial | "A monetizacao entra apos MVP validado." |

## Nao deve ser mostrado

- `backend/.env`, logs crus ou qualquer segredo.
- Tela de recuperacao de senha se SMTP nao estiver 100%.
- Erro de Gemini/CNA ao vivo.
- Perfil de estagiario como fluxo principal.
- Banco vazio.
- Codigo fonte durante a demo, exceto se a banca pedir.
- Qualquer pagina com warning, notice ou caminho interno.
- Telas duplicadas/HTML antigas que nao fazem parte da jornada principal.

## Bugs que matariam a apresentacao

| Bug | Gravidade | Prevencao |
|---|---:|---|
| Login falhar por CSRF | Critico | Testar login antes da banca e manter sessao limpa. |
| Upload salvar mas documento nao abrir | Critico | Usar documento demo pre-carregado. |
| Analise IA travar | Critico | Nao depender de chamada ao vivo. |
| Admin vazio | Alto | Importar `database/seed_demo.sql`. |
| Agenda mostrar dados errados | Alto | Testar com cliente e advogado antes. |
| Advogado nao aceitar caso | Alto | Ter caso ja em andamento no seed. |
| Chat sem mensagens | Medio/alto | Seed com conversa pronta. |
| Erro exibindo caminho interno | Alto | `APP_DEBUG=false`. |
| Layout quebrado no projetor | Alto | Testar resolucao do notebook/projetor. |

## Telas que precisam estar bonitas

| Tela | Nivel de exigencia | Motivo |
|---|---:|---|
| Landing | Muito alto | Primeira impressao. |
| Dashboard cliente | Alto | Mostra jornada. |
| Visualizar documento/analise | Maximo | E o diferencial principal. |
| Solicitar ajuda | Alto | Converte analise em atendimento. |
| Chat | Medio/alto | Prova comunicacao. |
| Agenda | Alto | Mostra continuidade. |
| Dashboard advogado | Alto | Prova operacao profissional. |
| Dashboard admin | Maximo | Mostra produto real/SaaS. |
| Usuarios/OAB admin | Alto | Prova validacao profissional. |
| Auditoria | Alto | Prova segurança. |

## Dados fake obrigatorios

| Dado | Quantidade minima | Exemplo |
|---|---:|---|
| Admin | 1 | `admin@justraduz.demo` |
| Clientes | 2 | Carla e Bruno |
| Advogado validado | 1 | Dra. Marina Costa |
| Advogado pendente | 1 | Dr. Rafael Pendente |
| Estagiario | 1 opcional | Lucas Estagiario Demo |
| Documentos | 4 | Contrato, notificacao, termo e acordo |
| Analises IA | 2 | Resumo + explicacao simples |
| Solicitacoes | 3 | Aberta, em andamento, finalizada |
| Mensagens | 8+ | Conversa cliente/advogado |
| Tarefas | 5 | Revisar documento, responder cliente |
| Agenda | 5 horarios | Livres, ocupados e bloqueado |
| Auditoria | 20 logs | Login, documento, admin e OAB |

## Usuarios de teste

| Perfil | E-mail | Uso na demo |
|---|---|---|
| Admin | `admin@justraduz.demo` | Dashboard, usuarios, OAB, documentos, auditoria. |
| Cliente | `cliente@justraduz.demo` | Upload, analise, solicitacao, chat e agenda. |
| Cliente 2 | `cliente2@justraduz.demo` | Mostrar dados sem tela vazia. |
| Advogado | `advogado@justraduz.demo` | Aceitar caso, chat, agenda e documentos. |
| Pendente | `pendente@justraduz.demo` | Validacao OAB/CNA no admin. |
| Estagiario | `estagiario@justraduz.demo` | Somente se for justificar o perfil. |

## Preparar o banco antes da banca

1. Fazer backup do banco atual.
2. Importar `database/schema.sql`.
3. Aplicar migrations listadas em `database/README.md`.
4. Importar `database/seed_demo.sql`.
5. Conferir login com admin, cliente e advogado.
6. Abrir documento demo e confirmar analise existente.
7. Conferir que dashboard admin tem dados.
8. Desativar dependencia de chamada ao vivo na fala/demo.

## Como evitar erro ao vivo

- Ensaiar a demo completa no mesmo notebook.
- Abrir o sistema 15 minutos antes e testar login.
- Manter credenciais em `docs/CREDENCIAIS_DEMO.md`.
- Nao improvisar tela fora do roteiro.
- Usar dados pre-carregados.
- Ter video backup.
- Ter print/PDF das telas principais.
- Nao atualizar codigo no dia da banca.
- Fechar apps pesados antes de apresentar.

## Roteiro de apresentacao

### 1. Abertura

"O JusTraduz e uma plataforma para transformar documentos juridicos complexos em linguagem simples e conectar o cidadao a profissionais qualificados."

### 2. Problema

Mostrar que pessoas comuns recebem contratos, notificacoes e termos juridicos sem entender riscos, prazos ou proximos passos.

### 3. Solucao

Explicar as tres camadas: IA para compreensao inicial, atendimento humano qualificado e administracao segura.

### 4. Demonstracao

Fluxo recomendado:

1. Landing.
2. Login cliente.
3. Dashboard cliente.
4. Documento analisado.
5. Solicitar ajuda.
6. Chat/agendamento.
7. Login advogado.
8. Caso e atendimento.
9. Login admin.
10. Metricas, OAB, documentos e auditoria.

### 5. Diferencial tecnico

Falar de PHP/PDO, CSRF, sessoes, uploads, regras de perfil, auditoria, IA e validacao OAB/CNA.

### 6. Diferencial comercial

Comparar com e-commerce comum: JusTraduz e nichado, vende confianca e ataca dor real de compreensao juridica.

### 7. Seguranca

Mostrar consentimento de IA, controle por perfil, validacao de upload, auditoria e `.env.example` sem segredos.

### 8. Proximos passos

Multiempresa, cobranca, OCR avancado, storage externo, testes automatizados, termos juridicos revisados e LGPD completa.

### 9. Fechamento

"O JusTraduz nao tenta substituir o advogado. Ele reduz a barreira de entendimento, organiza o atendimento e aumenta a confianca entre cliente, profissional e plataforma."
