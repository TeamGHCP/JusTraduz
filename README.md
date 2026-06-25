# JusTraduz

Projeto PHP/MySQL para apresentação escolar da SA.

Este README aponta apenas para pendências reais, validações manuais e comandos necessários para manter o projeto testável. Documentos de histórico e entregas já concluídas foram removidos para evitar várias versões da verdade.

## Como rodar localmente

1. Inicie Apache e MySQL pelo XAMPP.
2. Copie o ambiente, se ainda não existir:

```powershell
Copy-Item backend\.env.example backend\.env
```

3. Importe o banco com demo, quando precisar resetar a apresentação:

```powershell
Get-Content database\justraduz_completo_com_demo.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -h localhost -u root
```

4. Acesse:

```text
http://localhost/JusTraduz/frontend/index.html
```

As contas demo usam:

```text
Demo@2026!
```

## Checks obrigatórios

```powershell
php scripts\check-local-readiness.php
php scripts\check-orphan-storage.php
php backend\tests\run.php
php scripts\check-references.php
```

Para gerar um resumo operacional periodico:

```powershell
php scripts\operational-health-report.php --output=storage-private\reports\saude-operacional.md
```

Para validar apenas o template versionado:

```powershell
php scripts\check-production-readiness.php --env=backend/.env.example --allow-placeholders
```

Para produção/homologação real:

```powershell
php scripts\check-production-readiness.php --env=backend/.env
```

## Documentação de pendências

- `docs/O_QUE_FALTA_AGORA.md`: lista única do que ainda falta.
- `docs/ROTEIRO_QA_MANUAL.md`: QA manual ainda necessário.
- `docs/CHECKLIST_APRESENTACAO_SA.md`: preparação pendente para o dia da apresentação.
- `docs/CHECKLIST_RELEASE.md`: validações pendentes antes de deploy.
- `docs/MATRIZ_WCAG_AA.md`: acessibilidade ainda pendente de evidências formais.
- `docs/REGISTRO_REVISAO_JURIDICA.md`: revisão jurídica externa ainda pendente.
- `docs/CONFIGURAR_CLAMAV.md`: configuração pendente quando houver servidor real.
- `docs/OPERACAO_BACKUP_RESTORE.md`: backup, restore e operação ainda precisam ser validados fora do ambiente local.
- `docs/MANUAL_OPERACIONAL_INTERNO.md`: rotina de admin/suporte.
- `docs/API_PUBLICA.md`: estado atual da superficie `/api/v1`.

## Pendências principais

Veja `docs/O_QUE_FALTA_AGORA.md`.
