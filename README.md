# JusTraduz

Sistema PHP/MySQL para envio de documentos, análise em linguagem simples e solicitação de ajuda jurídica.

## Como rodar localmente

1. Inicie o MySQL pelo XAMPP.
2. Importe o banco:

```powershell
C:\xampp\mysql\bin\mysql.exe -h localhost -u root --execute="source C:/Users/djona/Documents/GitHub/JusTraduz/mysql/schema.sql"
```

3. Inicie o servidor PHP a partir da raiz do projeto:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8080 -t .
```

4. Acesse:

```text
http://127.0.0.1:8080/frontend/index.html
```

## Acesso admin

```text
E-mail: admin@justraduz.com
Senha: admin
```

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

Depois disso, novos uploads de PDF ou imagem tentam gerar automaticamente:

- resumo objetivo;
- explicação em linguagem simples;
- percentual de confiança.

Documentos antigos podem ser analisados abrindo o documento e usando o botão `Gerar análise com IA`, ou rodando:

```powershell
C:\xampp\php\php.exe backend\scripts\analisar_documentos_gemini.php
```

## Funcionalidades principais

- Cliente: cadastro, login, recuperação de senha, envio de documentos, análise por IA, criação de solicitação, acompanhamento e chat.
- Cliente: visualização de agenda livre de advogados/estagiários e agendamento de atendimento.
- Advogado: cadastro com OAB, visualização de solicitações abertas, casos atribuídos, documentos dos clientes, agenda, tarefas e chat.
- Estagiário: painel restrito, agenda, tarefas, consulta de casos/documentos e participação no chat.
- Admin: painel de gestão, filtros, ativação/inativação de usuários, atualização de solicitações, atribuição de advogado, agenda geral e auditoria completa do sistema.
- Auditoria: logs de autenticação, perfil, documentos, IA, casos, mensagens, tarefas, agenda, notificações e ações administrativas.

As páginas HTML antigas de área logada redirecionam para as versões PHP atuais.
