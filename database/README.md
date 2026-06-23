# Banco de dados

Este arquivo lista apenas ações pendentes ou perigosas relacionadas ao banco.

## Reset local quando necessário

Os instaladores consolidados recriam o banco `justraduz`. Eles executam `DROP DATABASE IF EXISTS justraduz`, então não rode em base com dados reais sem backup.

Sem demo:

```powershell
Get-Content database\justraduz_completo_sem_demo.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -h localhost -u root
```

Com demo:

```powershell
Get-Content database\justraduz_completo_com_demo.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -h localhost -u root
```

Senha demo:

```text
Demo@2026!
```

## Pendências de banco real

- Revisar `database/migrations/2026_06_23_cases_sla.sql` antes de aplicar.
- Criar backup antes de qualquer migration.
- Testar migration e rollback em cópia do banco.
- Gerar script incremental específico para bases reais existentes.
- Não rodar os instaladores consolidados em produção.
