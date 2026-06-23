# API v1

A API versionada `/api/v1` foi adicionada como alias das rotas existentes. As rotas antigas continuam funcionando.

Exemplos:

```text
/backend/public/index.php?rota=/auth/login
/backend/public/index.php?rota=/api/v1/auth/login

/backend/public/index.php?rota=/documents/upload
/backend/public/index.php?rota=/api/v1/documents/upload

/backend/public/index.php?rota=/admin/reports/summary
/backend/public/index.php?rota=/api/v1/admin/reports/summary
```

## Autenticacao e permissoes

Os aliases `/api/v1` usam os mesmos controllers, sessao, CSRF e permissoes das rotas antigas.

## Endpoint gerencial

`GET /api/v1/admin/reports/summary`

Permissao exigida: `reports.view`.

Retorna resumo de:

- usuarios por perfil;
- documentos totais e analisados;
- documentos dos ultimos 7 dias;
- casos por status e prioridade;
- SLA vencido/proximo;
- OAB pendente/validada;
- uso e erros de IA.
