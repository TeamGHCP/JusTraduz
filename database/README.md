# Banco de dados

Este arquivo lista apenas ações pendentes ou perigosas relacionadas ao banco.

Os instaladores consolidados `justraduz_completo_sem_demo.sql` e `justraduz_completo_com_demo.sql` já incluem o schema final versionado no próprio arquivo.

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

- Use os instaladores consolidados apenas para reset local ou ambiente limpo.
- Para base real existente, gerar script incremental específico a partir da diferença entre o schema atual e os instaladores consolidados.
- Criar backup antes de qualquer migration.
- Testar migration e rollback em cópia do banco.
- Gerar script incremental específico para bases reais existentes.
- Não rodar os instaladores consolidados em produção.
