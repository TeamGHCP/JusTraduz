# Banco de dados

Este arquivo lista apenas acoes pendentes ou perigosas relacionadas ao banco.

Os instaladores consolidados `justraduz_completo_sem_demo.sql` e `justraduz_completo_com_demo.sql` ja incluem o schema final versionado no proprio arquivo.

## Reset local quando necessario

Os instaladores consolidados recriam o banco `justraduz`. Eles executam `DROP DATABASE IF EXISTS justraduz`, entao nao rode em base com dados reais sem backup.

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

## Pendencias de banco real

- Use os instaladores consolidados apenas para reset local ou ambiente limpo.
- Para base real existente, gerar script incremental especifico a partir da diferenca entre o schema atual e os instaladores consolidados.
- Criar backup antes de qualquer migration.
- Testar migration e rollback em copia do banco.
- Nao rodar os instaladores consolidados em producao.
