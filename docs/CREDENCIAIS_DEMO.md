# Credenciais demo

Use somente em ambiente local ou de apresentação.

Senha de todas as contas:

```text
Demo@2026!
```

| Perfil | E-mail | Uso na demo |
|---|---|---|
| Admin | `admin@justraduz.demo` | Painel operacional, usuários, OAB, documentos, solicitações e auditoria. |
| Cliente principal | `cliente@justraduz.demo` | Upload/análise, solicitação, chat e agenda. |
| Cliente secundário | `cliente2@justraduz.demo` | Caso aberto sem responsável e documento pendente de IA. |
| Advogada | `advogado@justraduz.demo` | Dashboard advogado, caso em andamento, tarefas e chat. |
| Estagiário | `estagiario@justraduz.demo` | Agenda restrita e perfil limitado. |
| Profissional pendente | `pendente@justraduz.demo` | Fila de validação OAB/CNA no admin. |

## Como carregar

1. Importe `database/schema.sql`.
2. Aplique as migrations conforme `database/README.md`.
3. Execute:

```powershell
Get-Content database\seed_demo.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -h localhost -u root justraduz
```

O seed é resetável: ele remove e recria apenas dados dos e-mails `@justraduz.demo`.
