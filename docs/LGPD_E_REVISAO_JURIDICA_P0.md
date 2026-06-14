# LGPD e Revisao Juridica P0

Este documento define o processo minimo antes de producao. A implementacao tecnica existe em controles de acesso, auditoria, consentimento DataJud por CNJ e logs; a liberacao comercial ainda exige validacao formal por profissional juridico e responsavel LGPD.

## Bases e consentimentos

- Cadastro de cliente: CPF usado para vincular consulta processual por CNJ quando informado pelo proprio usuario.
- Consulta DataJud: exigir consentimento explicito na tela antes da sincronizacao.
- IA: manter autorizacao separada antes de analisar documento.
- Profissionais: validar OAB/registro antes de liberar acesso de advogado ou estagiario.

## Retencao

Politica sugerida para aprovacao:

- documentos enviados: reter enquanto a conta estiver ativa ou enquanto houver solicitacao vinculada;
- mensagens e agenda: reter enquanto necessario para atendimento e auditoria;
- logs de auditoria: reter por periodo definido pelo responsavel juridico/compliance;
- backups: reter pelo prazo operacional definido em `docs/PRODUCAO_P0.md`.

## Exclusao

Fluxo operacional minimo:

1. Receber pedido pelo canal de suporte oficial.
2. Confirmar identidade do solicitante.
3. Exportar dados do usuario quando solicitado.
4. Verificar impedimentos legais/contratuais de exclusao imediata.
5. Excluir ou anonimizar dados aplicaveis.
6. Registrar data, responsavel, base da decisao e evidencias no controle interno.

Implementacao disponivel:

- usuario logado pode encerrar a propria conta em `Perfil > Privacidade e LGPD`;
- a acao exige CSRF, sessao ativa e confirmacao textual `EXCLUIR`;
- documentos do titular sao removidos, dados cadastrais sao anonimizados, conta e inativada e a acao fica registrada em auditoria;
- o ultimo administrador ativo nao pode se autoencerrar para evitar perda de governanca operacional.

## Exportacao

Exportacao minima por usuario:

- dados cadastrais;
- documentos;
- solicitacoes/casos;
- mensagens;
- agenda;
- processos sincronizados;
- logs de auditoria associados ao titular, quando cabivel.

Implementacao disponivel:

- usuario logado pode baixar um JSON em `Perfil > Privacidade e LGPD`;
- a exportacao exige CSRF e sessao ativa;
- o arquivo inclui cadastro, documentos, solicitacoes, mensagens, agenda, processos, notificacoes e auditoria associada ao titular.

## Incidentes

Fluxo minimo:

1. Classificar severidade e dados afetados.
2. Conter acesso ou chave comprometida.
3. Preservar logs.
4. Identificar titulares afetados.
5. Avaliar comunicacao a titulares e ANPD com responsavel juridico.
6. Registrar causa, impacto, acao corretiva e prazo.

## Operadores

Antes de producao, registrar operadores/suboperadores:

- hospedagem;
- e-mail transacional;
- IA/Gemini;
- Google OAuth;
- DataJud ou API juridica;
- backup/storage externo, se usado.

Para cada operador, manter:

- finalidade;
- dados tratados;
- base contratual;
- localidade de tratamento;
- responsavel interno;
- contato de seguranca/privacidade.

## Revisao juridica de termos

Arquivos a revisar:

- `frontend/termos.html`;
- `frontend/privacidade.html`;
- textos de consentimento de IA e DataJud;
- disclaimers de que a IA e apoio informativo e nao parecer juridico final;
- mensagens de validacao OAB e responsabilidades do profissional.

Saida esperada da revisao:

- versao aprovada;
- data;
- nome/OAB ou identificacao profissional;
- observacoes obrigatorias;
- plano de atualizacao quando houver mudanca de produto, operador ou base legal.
