# Requisitos

## Objetivo do produto

Ajudar pessoas a entender documentos jurídicos em linguagem simples, com apoio de IA e possibilidade de atendimento por profissionais validados.

## Requisitos funcionais

| Código | Requisito | Prioridade | Status |
|---|---|---:|---|
| RF01 | Permitir cadastro de cliente. | Alta | Implementado |
| RF02 | Permitir cadastro de advogado/estagiário com OAB/UF. | Alta | Implementado |
| RF03 | Validar OAB/CNA automaticamente quando possível. | Alta | Implementado |
| RF04 | Permitir revisão manual de OAB pelo admin. | Alta | Implementado |
| RF05 | Permitir login e logout por sessão. | Alta | Implementado |
| RF06 | Permitir recuperação de senha. | Média | Implementado |
| RF07 | Permitir envio de PDF/imagem por cliente. | Alta | Implementado |
| RF08 | Exigir autorização para análise por IA. | Alta | Implementado |
| RF09 | Gerar resumo e explicação em linguagem simples. | Alta | Implementado |
| RF10 | Permitir criação de solicitação de ajuda jurídica. | Alta | Implementado |
| RF11 | Permitir atribuição/aceite de advogado. | Alta | Implementado |
| RF12 | Permitir chat por solicitação. | Alta | Implementado |
| RF13 | Permitir criação e atualização de tarefas. | Média | Implementado |
| RF14 | Permitir criação de horários por profissionais. | Média | Implementado |
| RF15 | Permitir agendamento de atendimento por cliente. | Média | Implementado |
| RF16 | Exibir notificações internas. | Média | Implementado |
| RF17 | Exibir dashboard admin com métricas operacionais. | Alta | Implementado |
| RF18 | Registrar auditoria de ações sensíveis. | Alta | Implementado |

## Requisitos não funcionais

| Código | Requisito | Prioridade | Status |
|---|---|---:|---|
| RNF01 | Rodar localmente com XAMPP, PHP e MySQL. | Alta | Implementado |
| RNF02 | Usar MySQL com charset `utf8mb4`. | Alta | Implementado |
| RNF03 | Proteger ações POST com CSRF. | Alta | Implementado |
| RNF04 | Separar permissões por perfil. | Alta | Implementado |
| RNF05 | Bloquear acesso direto ao storage de documentos. | Alta | Implementado |
| RNF06 | Registrar logs de segurança e operação. | Alta | Implementado |
| RNF07 | Não versionar `.env` nem chaves reais. | Alta | Implementado |
| RNF08 | Interface responsiva. | Média | Implementado |
| RNF09 | Documentação técnica para banca. | Alta | Implementado |
| RNF10 | Testes automatizados. | Média | Pendente |

## Regras de negócio

- Somente cliente envia documento.
- IA só analisa documento mediante autorização.
- A análise por IA é informativa.
- Cliente visualiza apenas seus documentos e casos.
- Advogado visualiza casos atribuídos ou abertos conforme fluxo.
- Estagiário possui permissão limitada e não atua como admin.
- Admin pode revisar OAB/CNA manualmente.
- Caso em andamento precisa ter responsável.
- Agenda de cliente exibe apenas horários livres e profissionais elegíveis.
- Documentos sensíveis não devem ser acessados diretamente por URL de storage.

