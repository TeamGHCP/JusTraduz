# O que falta agora

Data da revisao: 22/06/2026

Este documento e a lista unica de pendencias reais do JusTraduz. Itens ja implementados no sistema foram removidos da documentacao ativa para evitar varias versoes da verdade.

## Ambiente local

O ambiente local esta separado do criterio de producao. Para validar a maquina de desenvolvimento/apresentacao, use:

```powershell
php scripts\check-local-readiness.php
php scripts\check-orphan-storage.php
php backend\tests\run.php
php scripts\check-references.php
```

O check de producao (`scripts/check-production-readiness.php --env=backend/.env`) exige HTTPS e dominio real.

## Antes de colocar em producao real

- Configurar `backend/.env` real com `APP_URL` e `HEALTHCHECK_URL` em HTTPS, sem placeholders.
- Instalar certificado TLS no servidor e aplicar o modelo `docs/apache-justraduz-production.conf`.
- Definir `BACKUP_ENCRYPTION_PASSWORD` e rodar restore testado em ambiente separado.
- Configurar SMTP real ou provedor transacional e validar entregabilidade.
- Configurar monitoramento externo para `/backend/public/index.php?rota=/health`.
- Agendar `scripts/run-jobs.php` para processar a fila quando `ASYNC_JOBS_ENABLED=true`.
- Definir storage privado fora do webroot em `DOCUMENT_STORAGE_PATH` e `ATTACHMENT_STORAGE_PATH`.
- Instalar/configurar ClamAV via `CLAMAV_BINARY` se a producao exigir scanner externo alem da heuristica interna.
- Instalar/configurar Tesseract se `OCR_ENABLED=true`.
- Preencher e assinar `docs/REGISTRO_REVISAO_JURIDICA.md` com profissional juridico.
- Validar o PWA em HTTPS real no dominio final, incluindo instalacao em Android e iPhone.
- Rodar em producao/homologacao:

```powershell
php backend\tests\run.php
php scripts\check-references.php
php scripts\check-production-readiness.php --env=backend/.env
```

## Produto ainda nao implementado

- Planos e cobranca.
- Multiempresa/escritorios.
- RBAC granular por recurso.
- Relatorios gerenciais.
- SLA, prioridade operacional e escalonamento.
- API versionada `/api/v1`.
- Acessibilidade WCAG AA validada por matriz formal.
- Consulta processual por CPF com API juridica paga, contrato, consentimento reforcado e auditoria.

## Polimento ainda nao implementado

- Teste visual em mobile/tablet/projetor com matriz real.
- Empty/loading/error states revisados em todas as telas.
- Revisao final de copy juridica por profissional.
- Manual operacional interno para admin/suporte.
- Automatizar limpeza de arquivos orfaos somente depois de revisar o relatorio de `scripts/check-orphan-storage.php`.
- Relatorio periodico de saude operacional.
