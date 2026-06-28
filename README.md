# JusTraduz

Projeto PHP/MySQL para apresentacao escolar da SA.

Este README aponta apenas para os comandos e documentos que continuam uteis para rodar, validar, apresentar e operar o projeto. Historico antigo, especificacoes ja implementadas e arquivos de status duplicados nao devem ficar versionados.

## Como Rodar Localmente

1. Inicie Apache e MySQL pelo XAMPP.
2. Copie o ambiente, se ainda nao existir:

```powershell
Copy-Item backend\.env.example backend\.env
```

3. Importe o banco com demo quando precisar resetar a apresentacao:

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

## Como Rodar com Docker

Para facilitar a inicialização e evitar erros de configuração de dependências locais (como PHP ou MySQL):

1. Certifique-se de que o **Docker** e o **Docker Compose** estejam instalados e em execução.
2. Copie o arquivo de ambiente, se ainda não existir:
   ```powershell
   Copy-Item backend\.env.example backend\.env
   ```
3. Suba os contêineres:
   ```bash
   docker compose up -d --build
   ```
4. O banco de dados (`justraduz`) será inicializado e carregado automaticamente com o schema e dados de demonstração (`justraduz_completo_com_demo.sql`).
5. Acesse no navegador:
   ```text
   http://localhost:8080/JusTraduz/frontend/index.html
   ```

*Nota: As credenciais e portas de banco de dados para o Docker são injetadas de forma automática pelo `docker-compose.yml`, permitindo rodar em paralelo com qualquer banco MySQL local na porta 3306 (a porta externa do banco do Docker mapeia para 3307).*

## Checks Obrigatorios

```powershell
php scripts\check-local-readiness.php
php scripts\check-orphan-storage.php
php backend\tests\run.php
php scripts\check-references.php
```

Para gerar um resumo operacional local:

```powershell
php scripts\operational-health-report.php --output=storage-private\reports\saude-operacional.md
```

Para validar apenas o template versionado:

```powershell
php scripts\check-production-readiness.php --env=backend/.env.example --allow-placeholders
```

Para producao/homologacao real:

```powershell
php scripts\check-production-readiness.php --env=backend/.env
```

## Documentacao Ativa

- `docs/O_QUE_FALTA_AGORA.md`: lista unica do que ainda falta.
- `docs/ROTEIRO_QA_MANUAL.md`: roteiro manual ainda nao executado por completo.
- `docs/CHECKLIST_APRESENTACAO_SA.md`: preparacao para a apresentacao escolar.
- `docs/ROTEIRO_APRESENTACAO.md`: sequencia curta, contas demo e plano B da apresentacao.
- `docs/CHECKLIST_RELEASE.md`: validacoes antes de deploy/homologacao.
- `docs/MATRIZ_WCAG_AA.md`: matriz de acessibilidade ainda pendente de evidencias formais.
- `docs/REGISTRO_REVISAO_JURIDICA.md`: registro de revisao juridica externa ainda pendente.
- `docs/CONFIGURAR_CLAMAV.md`: guia para configurar ClamAV quando houver servidor real.
- `docs/OPERACAO_BACKUP_RESTORE.md`: validacao operacional de backup, restore e healthcheck.
- `docs/MANUAL_OPERACIONAL_INTERNO.md`: rotina minima de admin/suporte.
- `docs/API_PUBLICA.md`: estado atual da superficie `/api/v1`, credenciais externas, rate limit e auditoria.
- `database/README.md`: instaladores SQL oficiais do banco.

## Pendencias Principais

Veja `docs/O_QUE_FALTA_AGORA.md`.
