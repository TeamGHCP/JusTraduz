# O que falta agora

Data da revisão: 17/06/2026

Este documento é a lista única de pendências reais do JusTraduz. Itens já implementados no sistema foram removidos da documentação ativa para evitar várias versões da verdade.

## Antes de colocar em produção real

- Configurar `backend/.env` real com `APP_URL` e `HEALTHCHECK_URL` em HTTPS, sem placeholders.
- Instalar certificado TLS no servidor e aplicar o modelo `docs/apache-justraduz-production.conf`.
- Definir `BACKUP_ENCRYPTION_PASSWORD` e rodar um restore testado em ambiente separado.
- Configurar SMTP real ou provedor transacional e validar entregabilidade.
- Configurar monitoramento externo para `/backend/public/index.php?rota=/health`.
- Agendar `scripts/run-jobs.php` para processar a fila quando `ASYNC_JOBS_ENABLED=true`.
- Definir storage privado fora do webroot em `DOCUMENT_STORAGE_PATH` e `ATTACHMENT_STORAGE_PATH`.
- Instalar/configurar ClamAV via `CLAMAV_BINARY` se a produção exigir scanner externo além da heurística interna.
- Instalar/configurar Tesseract se `OCR_ENABLED=true`.
- Preencher e assinar `docs/REGISTRO_REVISAO_JURIDICA.md` com profissional jurídico.
- Validar o PWA em HTTPS real no domínio final, incluindo instalação em Android e iPhone.
- Rodar em produção/homologação:

```powershell
C:\xampp\php\php.exe backend\tests\run.php
C:\xampp\php\php.exe scripts\check-references.php
C:\xampp\php\php.exe scripts\check-production-readiness.php --env=backend/.env
```

## Produto ainda não implementado

- Planos e cobrança.
- Multiempresa/escritórios.
- RBAC granular por recurso.
- Relatórios gerenciais.
- SLA, prioridade operacional e escalonamento.
- API versionada `/api/v1`.
- Acessibilidade WCAG AA validada por matriz formal.
- Consulta processual por CPF com API jurídica paga, contrato, consentimento reforçado e auditoria.

## Polimento ainda não implementado

- Teste visual em mobile/tablet/projetor com matriz real.
- Empty/loading/error states revisados em todas as telas.
- Revisão final de copy jurídica por profissional.
- Manual operacional interno para admin/suporte.
- Scripts adicionais de manutenção, como limpeza de arquivos órfãos e relatório de saúde periódico.
