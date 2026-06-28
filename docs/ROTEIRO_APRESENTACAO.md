# Roteiro de apresentação do JusTraduz

## Contas demo

Todas usam a senha `Demo@2026!`.

| Papel | E-mail | Cenário |
| --- | --- | --- |
| Cliente | `cliente@justraduz.demo` | Max Cliente |
| Advogado principal | `advogado@justraduz.demo` | Dono do Escritório |
| Segundo advogado | `advogado2@justraduz.demo` | Max Advogado e membro do Escritório |
| Advogado pendente | `pendente@justraduz.demo` | Validação OAB pelo Admin |
| Admin | `admin@justraduz.demo` | Administração e relatórios |

## Sequência principal

1. Entrar como Cliente e mostrar dashboard, Max Cliente, documento demo e explicação em linguagem simples.
2. Abrir a solicitação em andamento, mensagens, agenda e o indicador intencional de SLA vencido.
3. Entrar como Advogado principal e mostrar casos, tarefas e o Escritório com dois advogados.
4. Explicar o fluxo pago do Escritório: pagamento, convite, e-mail, cadastro ou login e aceite.
5. Entrar como segundo Advogado e mostrar o Max Advogado e sua participação no Escritório.
6. Entrar como Admin, validar a OAB pendente e mostrar relatórios, auditoria e controles LGPD.
7. Encerrar com exportação de dados e exclusão imediata ou agendada da conta.

## Plano B

- Use o documento e as análises já gravadas no banco demo se Gemini ou internet falharem.
- Use os processos `datajud_demo` se o DataJud estiver indisponível.
- Mostre o registro em `mail_logs` se o provedor SMTP atrasar o convite.
- Mantenha os dashboards já autenticados em abas separadas antes de iniciar.
- Restaure `database/justraduz_completo_com_demo.sql` caso os dados da demonstração sejam alterados.
