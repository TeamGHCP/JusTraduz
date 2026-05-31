# JusTraduz

Sistema PHP/MySQL para envio de documentos, análise em linguagem simples e solicitação de ajuda jurídica.

## Documentação

A documentação de apoio para desenvolvimento, segurança e banca fica em `docs/`:

- `docs/ARQUITETURA.md`
- `docs/SEGURANCA.md`
- `docs/ROTAS.md`
- `docs/requisitos.md`
- `docs/DEMO.md`
- `docs/BANCA.md`
- `docs/CREDENCIAIS_DEMO.md`
- `docs/ENSAIO_DEMO.md`
- `docs/PITCH_COMERCIAL.md`
- `docs/RESPOSTAS_TECNICAS.md`
- `docs/VIDEO_BACKUP.md`
- `docs/CHECKLIST_LGPD.md`
- `docs/SMOKE_TEST.md`
- `docs/AUDITORIA_PRODUTO_JUSTRADUZ.md`

## Como rodar localmente

1. Inicie o MySQL pelo XAMPP.
2. Copie o arquivo de ambiente:

```powershell
Copy-Item backend\.env.example backend\.env
```

Depois ajuste `backend/.env` com banco, SMTP e chaves externas. Não versionar esse arquivo.

3. Importe o banco:

```powershell
C:\xampp\mysql\bin\mysql.exe -h localhost -u root --execute="source C:/Users/djona/Documents/GitHub/JusTraduz/database/schema.sql"
```

4. Crie um usuário administrador local.

Copie `database/seed_admin.example.sql`, troque e-mail e hash de senha, e execute a cópia revisada. Para gerar um hash:

```powershell
C:\xampp\php\php.exe -r "echo password_hash('SENHA_FORTE_AQUI', PASSWORD_DEFAULT);"
```

Para uma apresentação com dados prontos, use o seed resetável:

```powershell
Get-Content database\seed_demo.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -h localhost -u root justraduz
```

As credenciais ficam em `docs/CREDENCIAIS_DEMO.md`.

5. Inicie o servidor PHP a partir da raiz do projeto usando o roteador local, que bloqueia acesso direto a documentos enviados:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8080 public-router.php
```

6. Acesse:

```text
http://127.0.0.1:8080/frontend/index.html
```

## Variáveis de ambiente

Copie `backend/.env.example` para `backend/.env` e ajuste banco, e-mail e chaves externas. O arquivo `.env` local não deve ser versionado.

## Acesso admin

O schema principal não cria administrador com senha padrão. Para ambiente local, copie `database/seed_admin.example.sql`, troque e-mail e hash de senha, e execute o seed revisado.

## Migrações

Em banco existente, aplique as migrations de `database/` na ordem descrita em `database/README.md`.

## Configuração da IA

A análise por Gemini é opcional. Para ativar, copie:

```text
backend/app/config/gemini.example.php
```

para:

```text
backend/app/config/gemini.php
```

e preencha `GEMINI_API_KEY`.

Depois disso, uploads de PDF ou imagem só são enviados para IA quando o usuário marca a autorização explícita. A análise gera:

- resumo objetivo;
- explicação em linguagem simples;
- percentual de confiança.

Documentos antigos podem ser analisados abrindo o documento e usando o botão `Gerar análise com IA`, ou rodando:

```powershell
C:\xampp\php\php.exe backend\scripts\analisar_documentos_gemini.php --confirm-ai
```

## Funcionalidades principais

- Cliente: cadastro, login, recuperação de senha, envio de documentos, análise por IA, criação de solicitação, acompanhamento e chat.
- Cliente: visualização de agenda livre de advogados/estagiários e agendamento de atendimento.
- Advogado: cadastro com OAB, visualização de solicitações abertas, casos atribuídos, documentos dos clientes, agenda, tarefas e chat.
- Estagiário: painel restrito, agenda, tarefas, consulta de casos/documentos e participação no chat.
- Admin: painel de gestão, filtros, ativação/inativação de usuários, atualização de solicitações, atribuição de advogado, agenda geral e auditoria completa do sistema.
- Auditoria: logs de autenticação, perfil, documentos, IA, casos, mensagens, tarefas, agenda, notificações e ações administrativas.

As páginas HTML antigas de área logada redirecionam para as versões PHP atuais.
