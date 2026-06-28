# API publica

O projeto registra aliases versionados em `/api/v1` e endpoints de integracao com Bearer token.

## Endpoints versionados existentes

- `GET /api/v1/health`
- `POST /api/v1/auth/registrar`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `POST /api/v1/documents/upload`
- `POST /api/v1/documents/analyze`
- `POST /api/v1/cases/create`
- `POST /api/v1/cases/accept`
- `POST /api/v1/messages/send`
- `POST /api/v1/schedule/book`
- `GET /api/v1/admin/reports/summary`
- `GET /api/v1/admin/reports/export?type=cases`
- `GET /api/v1/admin/audit/export`
- `GET /api/v1/openapi.json`
- `GET /api/v1/integrations/health`
- `GET /api/v1/integrations/reports/summary`

## Credenciais de integracao

Depois de importar um instalador consolidado atualizado, crie um cliente externo com:

```powershell
php scripts\create-api-client.php --name=IntegracaoExterna --scopes=health:read,reports:read
```

Use o token retornado no header:

```text
Authorization: Bearer jt_xxx
```

As chamadas autenticadas registram auditoria em `audit_logs` e usam rate limit por cliente/IP. O limite padrao e controlado por `PUBLIC_API_RATE_LIMIT_PER_MINUTE`.

## Regras antes de abrir para integracoes externas

- Revisar contrato, suporte e LGPD com cliente externo antes de liberar token real.
- Manter payloads, erros e codigos HTTP no contrato OpenAPI exposto por `/api/v1/openapi.json`.
- Versionar mudancas quebraveis.
- Revisar LGPD e termos com profissional habilitado.

Enquanto esses pontos nao existirem, libere tokens somente para homologacao controlada.
