# Banco de dados

## Instalacao limpa

Use um dos instaladores consolidados:

- `database/justraduz_completo_sem_demo.sql`: recria o banco `justraduz` vazio, somente com estrutura.
- `database/justraduz_completo_com_demo.sql`: recria o banco `justraduz` com estrutura e dados de apresentacao.

Atencao: os dois arquivos executam `DROP DATABASE IF EXISTS justraduz`. Faca backup antes de rodar em uma base com dados reais.

Sem demo:

```powershell
Get-Content database\justraduz_completo_sem_demo.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -h localhost -u root
```

Com demo:

```powershell
Get-Content database\justraduz_completo_com_demo.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -h localhost -u root
```

As contas `@justraduz.demo` usam `Demo@2026!`.

## Atualizacao de banco existente

Os scripts incrementais antigos foram removidos para nao existir duas formas conflitantes de instalar o banco. Para atualizar uma base real existente, faca backup e gere um script incremental especifico a partir da diferenca necessaria.

## Pendencias para producao

As melhorias restantes de banco, backup, LGPD, Jusbrasil real e observabilidade ficam em `../docs/MELHORIAS_PARA_100.md`.
