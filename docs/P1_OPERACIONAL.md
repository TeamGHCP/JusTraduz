# P1 Operacional - JusTraduz

Este documento cobre os controles implementados para produto comercial serio.

## DataJud por CNJ

- Consulta continua limitada a numero CNJ informado pelo cliente.
- Cache auditavel fica em `external_processes`, com `query_type='cnj'`, `query_value` normalizado e `payload_json`.
- `DATAJUD_CACHE_TTL_HOURS` define por quanto tempo o cache evita nova chamada externa.
- Consulta por CPF segue fora do escopo por depender de contrato/API paga, consentimento reforcado e decisao formal.

## OCR

Variaveis:

```env
OCR_ENABLED=true
OCR_TESSERACT_BINARY=C:\Program Files\Tesseract-OCR\tesseract.exe
OCR_LANGUAGE=por+eng
```

Quando OCR nao esta configurado ou nao extrai texto, o documento recebe fallback claro de qualidade, sem simular resultado.

## Antimalware

- Uploads de documentos e anexos passam por `UploadScannerService`.
- A varredura interna bloqueia EICAR, scripts e extensoes executaveis.
- `CLAMAV_BINARY` habilita varredura por ClamAV quando disponivel.

## Storage privado

Variaveis:

```env
DOCUMENT_STORAGE_PATH=D:\justraduz-private\documents
ATTACHMENT_STORAGE_PATH=D:\justraduz-private\message-attachments
```

Quando o caminho fica fora do projeto, o banco armazena referencias `private://...` e o download continua passando pelos controladores autorizados.

## Fila assíncrona

Tabela: `job_queue`.

Ativar:

```env
ASYNC_JOBS_ENABLED=true
```

Worker:

```powershell
C:\xampp\php\php.exe scripts\run-jobs.php --limit=20
```

Agende o worker a cada minuto no Agendador de Tarefas, cron ou supervisor equivalente. Jobs possuem status, tentativas, `last_error`, `available_at` e backoff.

## Custos e limites

Tabela: `usage_events`.

Variaveis:

```env
USAGE_DAILY_DOCUMENT_UPLOADS=20
USAGE_DAILY_DOCUMENT_AI=10
USAGE_DAILY_AI_CHAT=40
USAGE_DAILY_DATAJUD_CNJ=20
USAGE_DAILY_OCR=10
```

Limites bloqueiam consumo antes de chamar IA, OCR ou DataJud.

## E-mail transacional

- `MailerService` registra envios em `mail_logs`.
- `MAIL_LOG_ONLY=true` simula envio em homologacao.
- Falhas SMTP continuam no `error_log` e tambem entram em `mail_logs`.

## Auditoria

Administradores podem exportar CSV em `Admin > Auditoria > CSV`.

Rota:

```text
/backend/public/index.php?rota=/admin/audit/export
```

Os filtros da tela sao preservados na exportacao.
