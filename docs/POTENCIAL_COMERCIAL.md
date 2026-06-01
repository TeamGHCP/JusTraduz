# Potencial comercial

Data: 31/05/2026  
Objetivo: avaliar se o JusTraduz pode virar SaaS e qual caminho realista existe entre projeto academico e produto vendavel.

## Diagnostico brutal

Sim, o JusTraduz pode virar SaaS. Mas ainda nao e vendavel como produto real. Hoje ele esta no ponto de "MVP demonstravel forte": tem fluxo, narrativa, admin, IA, OAB, documentos, chat e agenda. Para cobrar de clientes reais, faltam infraestrutura, compliance, multiempresa, testes, suporte, billing e validacao juridica formal.

O maior perigo e vender cedo demais uma promessa juridica. A primeira versao comercial deve vender organizacao, triagem e linguagem simples, sempre com aviso de que nao substitui advogado.

## Pode virar SaaS?

Pode, desde que evolua nestas frentes:

| Frente | Status atual | Necessario para SaaS |
|---|---|---|
| Produto | MVP demonstravel | Onboarding, planos, limites e suporte. |
| Tecnologia | Monolito PHP funcional | Deploy confiavel, logs, backups, testes e storage externo. |
| Segurança | Base boa | Hardening de producao, LGPD, incidentes e auditoria ampliada. |
| Comercial | Narrativa forte | Precificacao, publico-alvo validado e contratos. |
| Juridico | Avisos basicos | Revisao por advogado, termos robustos e limites da IA. |
| Operacao | Admin existe | Multiempresa, permissoes finas e relatorios. |

## Modulos que faltam para vender

| Modulo | Por que falta | Prioridade |
|---|---|---:|
| Multiempresa/escritorios | SaaS precisa separar dados por organizacao | P1 comercial |
| Planos e cobranca | Produto pago precisa controlar assinatura | P1 comercial |
| Limites de uso de IA | Controla custo por documento | P1 comercial |
| Storage externo privado | Documentos juridicos nao devem depender de pasta local | P1 tecnico |
| Politica de retencao | LGPD exige regra clara | P1 juridico |
| Exportacao/exclusao de dados | Direito do titular | P1 juridico |
| Testes automatizados | Evita quebrar permissao e fluxos criticos | P1 tecnico |
| Observabilidade | Saber quando IA, e-mail ou banco falham | P2 tecnico |
| Suporte/admin de conta | Cliente pagante precisa atendimento | P2 comercial |
| OCR avancado | Melhora analise de PDFs/imagens escaneadas | P2 produto |

## Riscos existentes

| Risco | Gravidade | Mitigacao |
|---|---:|---|
| IA dar interpretacao errada | Alta | Aviso informativo, revisao humana e logs de prompt/modelo. |
| Vazamento de documento | Critica | Storage privado, controle de acesso, auditoria e criptografia. |
| Prometer consultoria juridica automatica | Alta | Posicionamento como explicacao/triagem, nao parecer juridico. |
| Profissional nao validado atender | Alta | OAB/CNA, revisao manual e bloqueio de pendentes. |
| Custo de IA crescer | Media/alta | Limites, creditos, cache e planos. |
| Dependencia externa cair | Media | Fallback, fila e pre-processamento. |
| Falta de testes | Alta | Testes de permissao, upload, login e fluxos principais. |
| LGPD incompleta | Alta | Politica, base legal, retencao, exclusao e encarregado. |

## Partes que precisam de validacao juridica

- Termos de uso.
- Politica de privacidade.
- Aviso de IA informativa.
- Limites entre explicacao juridica e consultoria juridica.
- Uso de dados em APIs externas.
- Modelo de cobranca por lead/agendamento.
- Regras de exibicao de advogados/OAB.
- Retencao e exclusao de documentos.
- Responsabilidade por erro de interpretacao.

## MVP vendavel inicial

| Funcionalidade | Entra no MVP? | Motivo |
|---|---|---|
| Cadastro/login por perfil | Sim | Base de acesso. |
| Upload seguro | Sim | Coracao do produto. |
| Analise IA em linguagem simples | Sim | Diferencial principal. |
| Aviso legal e consentimento | Sim | Reduz risco. |
| Solicitacao de ajuda | Sim | Converte analise em atendimento. |
| Chat cliente-advogado | Sim | Atendimento minimo. |
| Agenda | Sim | Continuidade e valor comercial. |
| Admin usuarios/documentos/casos | Sim | Operacao. |
| Validacao OAB manual | Sim | Confianca. |
| Auditoria | Sim | Segurança e governanca. |
| Multiempresa | Nao no MVP academico; sim para SaaS pago | Necessario para vender a escritorios. |
| Billing | Pos-MVP | Pode validar demanda antes. |
| OCR avancado | Pos-MVP | Aumenta qualidade, mas nao bloqueia demo. |

## Pos-MVP

- Multiempresa com isolamento por escritorio.
- Billing/assinaturas.
- Painel de planos, uso e limites.
- OCR avancado.
- Exportacao PDF da analise.
- Templates de documentos.
- Relatorios para gestores.
- Integracao Google Calendar/Outlook.
- Webhooks/API para parceiros.
- Painel de suporte.
- Testes automatizados e CI.

## Versao vendavel inicial

Nome sugerido: JusTraduz Starter.

Oferta:

- Ate X usuarios internos.
- Ate X documentos analisados por mes.
- Upload PDF/imagem.
- Analise em linguagem simples.
- Solicitacoes, chat e agenda.
- Validacao manual de profissionais.
- Auditoria basica.
- Suporte por e-mail.

Cliente ideal: nucleo de pratica juridica, pequeno escritorio ou projeto social juridico.

## Versao premium

Nome sugerido: JusTraduz Pro.

Oferta:

- Multiempresa/white-label.
- Limites maiores de IA.
- OCR avancado.
- Relatorios gerenciais.
- Auditoria avancada.
- SLA e filas de prioridade.
- Integracoes de calendario/e-mail.
- Exportacao de analises.
- Controle de permissao granular.
- Suporte prioritario.

## Esforco faltante para produto real

| Area | Esforco estimado | Comentario |
|---|---:|---|
| Hardening seguranca | 2 a 4 semanas | Upload, storage, HTTPS, logs, permissao e testes. |
| Multiempresa | 3 a 6 semanas | Impacta banco, queries, admin e autorizacao. |
| Billing/planos | 2 a 4 semanas | Depende de gateway e regras comerciais. |
| UX/onboarding | 2 a 3 semanas | Produto pago precisa ser autoexplicativo. |
| LGPD/juridico | 2 a 4 semanas | Precisa revisao profissional. |
| Testes/CI | 2 a 3 semanas | Login, permissao, upload, admin e casos. |
| Deploy/observabilidade | 1 a 3 semanas | Ambiente real, backups e monitoramento. |

## Matriz de funcionalidades

| Funcionalidade | Valor comercial | Dificuldade tecnica | Prioridade | Impacto na apresentacao |
|---|---:|---:|---:|---:|
| Analise IA em linguagem simples | Muito alto | Media | P1 | Muito alto |
| Upload seguro de documentos | Muito alto | Media | P1 | Alto |
| Resultado com aviso legal | Alto | Baixa | P1 | Muito alto |
| Validacao OAB/CNA | Alto | Alta | P1 | Muito alto |
| Revisao manual OAB admin | Alto | Media | P1 | Alto |
| Solicitacoes/casos | Alto | Media | P1 | Alto |
| Chat | Alto | Media | P1 | Alto |
| Agenda | Alto | Alta | P1 | Alto |
| Dashboard admin | Muito alto | Media | P1 | Muito alto |
| Auditoria | Alto | Media | P1 | Alto |
| Seed demo completo | Medio | Baixa/media | P1 | Muito alto |
| Multiempresa | Muito alto | Alta | P2 | Medio |
| Billing/assinatura | Alto | Alta | P2 | Medio |
| OCR avancado | Alto | Alta | P2 | Medio |
| Exportar analise em PDF | Medio/alto | Media | P2 | Medio |
| Tema escuro | Baixo | Baixa | P3 | Baixo |
| Perfil estagiario completo | Medio | Media | P3 | Baixo/medio |

## Conclusao

O JusTraduz tem potencial comercial maior que um e-commerce comum porque vende clareza juridica, confianca e organizacao. A versao de banca deve provar o MVP. A versao vendavel exige menos telas novas e mais maturidade: isolamento de dados, seguranca, LGPD, testes, planos e limites de IA.
