# Plano de ação até 07/07/2026

Data: 31/05/2026  
Meta: tornar o JusTraduz superior a um e-commerce comum na banca e mais proximo de um produto comercial real.

## Diagnostico geral

O JusTraduz tem mais potencial que um e-commerce comum, mas tambem tem mais lugares para quebrar. A estrategia correta nao e adicionar mais funcionalidades. E estabilizar o fluxo principal, polir as telas de valor, blindar seguranca basica, preparar banco demo e ensaiar a apresentacao.

Se a equipe tentar melhorar tudo ao mesmo tempo, vai chegar em 07/07 com muita tela incompleta. O foco precisa ser brutal: documento, analise simples, atendimento, agenda, admin, OAB, auditoria e pitch.

## Ranking de prioridade

| Ranking | Prioridade | Tarefa | Motivo |
|---:|---:|---|---|
| 1 | P0 | Garantir que `.env` nao esta versionado e rotacionar segredos | Falha de seguranca derruba credibilidade. |
| 2 | P1 | Rodar demo completa com seed | Sem dados, o produto parece vazio. |
| 3 | P1 | Polir tela de analise de documento | E o diferencial que vence e-commerce. |
| 4 | P1 | Garantir login por perfil | Demo depende disso. |
| 5 | P1 | Testar upload/download protegido | Documento juridico e dado sensivel. |
| 6 | P1 | Fortalecer dashboard admin | Mostra maturidade de SaaS. |
| 7 | P1 | Mostrar validacao OAB/CNA manual | Prova confianca juridica. |
| 8 | P1 | Ensaiar roteiro sem integracoes ao vivo | Reduz chance de erro. |
| 9 | P2 | Criar testes automatizados minimos | Reduz regressao. |
| 10 | P2 | Melhorar docs e pitch final | Ajuda defesa tecnica/comercial. |
| 11 | P3 | Tema escuro, detalhes secundarios e perfis extras | So depois do essencial. |

## Fase 1 - Correcoes criticas

Periodo sugerido: 31/05 a 05/06.

| Tarefa | Prioridade | Responsavel ideal | Dificuldade | Tempo estimado | Impacto | Risco se nao fizer |
|---|---:|---|---:|---:|---:|---|
| Remover `.env` do Git e manter `.env.example` | P0 | Backend/DevOps | Baixa | 1h | Muito alto | Banca pode apontar falha grave de seguranca. |
| Rotacionar qualquer chave real exposta | P0 | Backend/DevOps | Baixa/media | 1h | Muito alto | Chave vazada continua valida. |
| Confirmar `APP_DEBUG=false` na demo | P1 | Backend | Baixa | 20min | Alto | Erro pode mostrar caminho interno. |
| Testar paginas internas anonimas | P1 | QA/Backend | Media | 3h | Alto | Acesso sem login mata a defesa de seguranca. |
| Testar upload/download de documento por perfil | P1 | QA/Backend | Media | 3h | Muito alto | Documento sensivel pode vazar. |
| Validar agenda por cliente/advogado/admin | P1 | Backend | Media | 3h | Alto | Agenda errada enfraquece a demo. |
| Definir se estagiario entra na demo | P1 | Produto | Baixa | 30min | Medio/alto | Perfil confuso gera perguntas ruins. |
| Congelar fluxo principal cliente -> advogado -> admin | P1 | Produto/Fullstack | Media | 2h | Muito alto | Demo fica dispersa. |

## Fase 2 - Frontend e experiencia visual

Periodo sugerido: 06/06 a 16/06.

| Tarefa | Prioridade | Responsavel ideal | Dificuldade | Tempo estimado | Impacto | Risco se nao fizer |
|---|---:|---|---:|---:|---:|---|
| Polir landing com produto real | P1 | Frontend/UI | Media | 6h | Alto | Um e-commerce comum pode parecer mais comercial. |
| Transformar analise em tela estrela | P1 | Frontend/UI | Media | 8h | Muito alto | Diferencial fica escondido. |
| Melhorar dashboard cliente como jornada | P1 | Frontend/UI | Media | 5h | Alto | Usuario nao entende proximo passo. |
| Melhorar dashboard advogado com fila | P1 | Frontend/UI | Media | 5h | Alto | Atendimento parece generico. |
| Revisar estados de loading/erro/sucesso | P1 | Frontend | Media | 4h | Alto | Sistema parece travado. |
| Padronizar badges/status | P2 | Frontend | Baixa | 3h | Medio | Visual inconsistente. |
| Ajustar mobile/projetor | P2 | Frontend/QA | Baixa | 2h | Medio | Layout pode quebrar na banca. |

## Fase 3 - Dashboard/admin

Periodo sugerido: 17/06 a 23/06.

| Tarefa | Prioridade | Responsavel ideal | Dificuldade | Tempo estimado | Impacto | Risco se nao fizer |
|---|---:|---|---:|---:|---:|---|
| Garantir metricas principais no admin | P1 | Fullstack | Baixa/media | 3h | Muito alto | Admin parece raso. |
| Mostrar fila OAB/CNA com acoes | P1 | Fullstack | Media | 5h | Muito alto | Diferencial juridico some. |
| Criar/validar graficos simples | P1 | Frontend | Media | 4h | Alto | Dashboard perde impacto visual. |
| Melhorar filtros de documentos e solicitacoes | P2 | Fullstack | Media | 5h | Medio/alto | Operacao parece limitada. |
| Melhorar auditoria com severidade e detalhes legiveis | P2 | Fullstack | Media | 4h | Alto | Logs parecem crus. |
| Criar health check visual de integracoes | P2 | Backend/Frontend | Media | 4h | Medio | IA/CNA/SMTP parecem caixas pretas. |

## Fase 4 - Seguranca e documentacao

Periodo sugerido: 24/06 a 28/06.

| Tarefa | Prioridade | Responsavel ideal | Dificuldade | Tempo estimado | Impacto | Risco se nao fizer |
|---|---:|---|---:|---:|---:|---|
| Revisar `docs/SEGURANCA.md` com evidencias reais | P1 | Backend/docs | Baixa | 2h | Alto | Segurança fica discurso. |
| Atualizar mapa de rotas | P1 | Backend/docs | Baixa | 2h | Medio | Banca pode pedir endpoints. |
| Finalizar checklist LGPD | P1 | Produto/juridico | Media | 3h | Alto | Produto comercial fica vulneravel. |
| Criar smoke test final | P1 | QA | Baixa | 3h | Muito alto | Erro ao vivo aumenta. |
| Criar respostas tecnicas provaveis | P1 | Fullstack | Media | 3h | Alto | Equipe trava na banca. |
| Revisar README principal | P2 | Docs | Baixa | 2h | Medio | Primeira leitura fica confusa. |
| Criar testes automatizados minimos | P2 | Backend | Media/alta | 8h | Alto | Regressao passa despercebida. |

## Fase 5 - Demo e apresentacao

Periodo sugerido: 29/06 a 03/07.

| Tarefa | Prioridade | Responsavel ideal | Dificuldade | Tempo estimado | Impacto | Risco se nao fizer |
|---|---:|---|---:|---:|---:|---|
| Resetar banco com seed demo | P1 | Backend | Baixa/media | 2h | Muito alto | Demo vazia ou inconsistente. |
| Validar credenciais de teste | P1 | QA | Baixa | 30min | Alto | Login falha ao vivo. |
| Ensaiar roteiro em 8 a 10 minutos | P1 | Apresentador/equipe | Baixa | 3h | Muito alto | Apresentacao estoura ou se perde. |
| Gravar video backup | P1 | Equipe | Baixa | 2h | Alto | Sem plano B. |
| Preparar pitch de 30s e 2min | P1 | Produto | Baixa | 2h | Alto | Ideia nao vende. |
| Preparar perguntas e respostas | P1 | Equipe | Media | 3h | Alto | Defesa fica insegura. |
| Testar sem internet | P1 | QA | Baixa | 1h | Alto | Integracao externa derruba demo. |

## Fase 6 - Polimento final

Periodo sugerido: 04/07 a 07/07.

| Tarefa | Prioridade | Responsavel ideal | Dificuldade | Tempo estimado | Impacto | Risco se nao fizer |
|---|---:|---|---:|---:|---:|---|
| Congelar codigo da demo | P1 | Equipe | Baixa | 30min | Muito alto | Ultima alteracao quebra tudo. |
| Revisar textos, acentos e labels | P1 | Frontend/docs | Baixa | 3h | Alto | Cara de amador. |
| Testar no notebook/projetor | P1 | QA | Baixa | 2h | Alto | Layout quebra na apresentacao. |
| Fazer backup de banco e storage | P1 | Backend | Baixa | 1h | Alto | Sem restauracao se algo falhar. |
| Deixar abas abertas na ordem da demo | P1 | Apresentador | Baixa | 20min | Medio | Perde tempo procurando tela. |
| Preparar credenciais em local visivel | P1 | Apresentador | Baixa | 10min | Medio | Login esquecido. |
| Cortar telas que nao agregam | P1 | Produto | Baixa | 1h | Alto | Demo dispersa. |

## Checklist tecnico

- [ ] Banco importa do zero.
- [ ] Migrations aplicam sem erro.
- [ ] Seed demo cria usuarios e dados.
- [ ] Login cliente funciona.
- [ ] Login advogado funciona.
- [ ] Login admin funciona.
- [ ] Upload funciona.
- [ ] Documento abre por endpoint autorizado.
- [ ] Analise IA aparece na tela.
- [ ] Solicitacao cria e muda status.
- [ ] Advogado aceita/acompanha caso.
- [ ] Chat envia e lista mensagens.
- [ ] Agenda lista horarios e agenda atendimento.
- [ ] Admin mostra metricas.
- [ ] Admin revisa OAB/CNA.
- [ ] Auditoria registra acoes.
- [ ] PHP lint sem erros.

## Checklist visual

- [ ] Landing mostra claramente o produto.
- [ ] Dashboard cliente guia a jornada.
- [ ] Tela de analise parece premium.
- [ ] Dashboard advogado mostra fila e prioridade.
- [ ] Admin tem metricas, graficos e alertas.
- [ ] Tabelas importantes tem filtros.
- [ ] Empty states nao parecem tela quebrada.
- [ ] Loading/erro/sucesso sao claros.
- [ ] Layout funciona no projetor.
- [ ] Textos foram revisados.

## Checklist de seguranca

- [ ] `.env` fora do Git.
- [ ] `.env.example` sem segredos.
- [ ] Chaves reais rotacionadas se necessario.
- [ ] `APP_DEBUG=false` na demo.
- [ ] Uploads protegidos.
- [ ] Download exige autorizacao.
- [ ] Admin exige perfil admin.
- [ ] CSRF em POST.
- [ ] Prepared statements nas queries com entrada do usuario.
- [ ] Logs nao registram senha/token/documento integral.
- [ ] LGPD explicada em checklist.

## Checklist de apresentacao

- [ ] Roteiro definido.
- [ ] Credenciais prontas.
- [ ] Banco resetado.
- [ ] Dados fake realistas.
- [ ] Analises pre-geradas.
- [ ] Gemini/CNA/SMTP nao sao dependencias ao vivo.
- [ ] Video backup gravado.
- [ ] Prints ou PDF reserva prontos.
- [ ] Ensaio cronometrado.
- [ ] Plano B combinado.

## O que esta fraco, amador, arriscado ou deve ser cortado

| Categoria | Item | Decisao |
|---|---|---|
| Fraco | Analise juridica escondida entre telas | Dar destaque maximo. |
| Fraco | Admin sem dados ou graficos | Usar seed e metricas visuais. |
| Amador | Mostrar telas vazias | Nunca demonstrar sem seed. |
| Amador | Textos genericos | Revisar linguagem juridica/comercial. |
| Arriscado | `.env` versionado | Corrigir e mencionar como auditoria resolveu. |
| Arriscado | Integracoes ao vivo | Simular com dados pre-carregados. |
| Arriscado | Perfil estagiario amplo/confuso | Cortar da demo ou justificar muito bem. |
| Desperdicio | Tema escuro como destaque | Deixar como detalhe, nao como venda. |
| Desperdicio | Recuperacao de senha ao vivo | Nao mostrar. |
| Cortar | Fluxos secundarios que nao provam valor | Focar documento, ajuda, agenda e admin. |

## Plano final

Para superar um e-commerce comum ate 07/07, o JusTraduz precisa vencer em tres frentes:

1. Valor: mostrar documento juridico virando linguagem simples.
2. Confianca: mostrar advogado validado, seguranca, consentimento e auditoria.
3. Produto: mostrar admin, dados reais de demo, pitch comercial e roadmap SaaS.

O que nao servir a essas tres frentes deve ser adiado.
