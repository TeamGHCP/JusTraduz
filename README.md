# JusTraduz

Sistema PHP/MySQL para envio de documentos jurídicos, análise em linguagem simples, solicitação de ajuda jurídica, agenda, chat, validação OAB manual e consulta processual por número CNJ via DataJud.

## Documentação

A documentação ativa fica em `docs/`:

- `docs/README.md`
- `docs/O_QUE_FALTA_AGORA.md`
- `docs/REGISTRO_REVISAO_JURIDICA.md`
- `docs/apache-justraduz-production.conf`

Os documentos antigos e os guias de entregas já implementadas foram removidos para evitar várias versões da verdade. A documentação ativa mostra apenas o que ainda falta para o sistema ficar pronto para uso real/comercial.

## Como rodar localmente

1. Inicie Apache e MySQL pelo XAMPP.
2. Copie o arquivo de ambiente:

```powershell
Copy-Item backend\.env.example backend\.env
```

3. Ajuste `backend/.env` com banco, SMTP e chaves externas. Não versionar esse arquivo.
4. Importe o banco sem demo:

```powershell
Get-Content database\justraduz_completo_sem_demo.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -h localhost -u root
```

5. Para demo local, use o instalador completo com dados:

```powershell
Get-Content database\justraduz_completo_com_demo.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -h localhost -u root
```

As contas `@justraduz.demo` usam a senha:

```text
Demo@2026!
```

6. Acesse pelo Apache/XAMPP:

```text
http://localhost/JusTraduz/frontend/index.html
```

Ou, se quiser usar o servidor PHP embutido:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8080 public-router.php
```

```text
http://127.0.0.1:8080/frontend/index.html
```

## Variáveis de ambiente

Use `backend/.env.example` como modelo. O arquivo `backend/.env` local fica ignorado pelo Git.

Principais grupos:

- Banco: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
- E-mail: `MAIL_*`.
- IA: `GEMINI_API_KEY`, `GEMINI_MODEL`.
- Google OAuth: `GOOGLE_*`.
- DataJud/CNJ: `DATAJUD_*`.

## Limpeza aplicada

- Páginas HTML antigas da área logada foram removidas.
- Scripts SQL incrementais antigos foram removidos; ficaram apenas os dois instaladores consolidados.
- Documentos antigos e entregas já implementadas foram consolidados/removidos; as pendências ficam em `docs/O_QUE_FALTA_AGORA.md`.
- Uploads locais órfãos fora do seed demo foram removidos.

## Qualidade e produção

Suite P0 local:

```powershell
C:\xampp\php\php.exe backend\tests\run.php
```

Checagem de referências:

```powershell
C:\xampp\php\php.exe scripts\check-references.php
```

Prontidão P0 de produção:

```powershell
C:\xampp\php\php.exe scripts\check-production-readiness.php --env=backend/.env
```

Health check:

```text
/backend/public/index.php?rota=/health
```

## Revisao completa do sistema

Revisao feita em 17/06/2026, somente com leitura de arquivos e comandos de
verificacao. Nenhuma correcao foi aplicada durante a revisao.

### Pontos fortes

- Backend PHP organizado por controllers, services, middlewares e helpers.
- Rotas centralizadas em `backend/routes/api.php`.
- CSRF global para requisicoes POST.
- Sessao configurada com cookies `HttpOnly`, `SameSite=Lax` e `secure` quando
  estiver em HTTPS.
- Headers de seguranca aplicados pelo backend.
- Separacao de perfis: cliente, advogado, estagiario e admin.
- Validacao manual de OAB para profissionais antes de liberar acesso.
- Upload de documentos com limite de tamanho, MIME permitido, nome seguro e
  scanner heuristico.
- Download de documentos e anexos com checagem de permissao.
- Storage com defesa contra path traversal e suporte a storage privado fora do
  webroot.
- Auditoria de acoes sensiveis.
- Fluxos LGPD para exportacao e encerramento de conta.
- Rate limit para IA e limites diarios por recurso.
- PWA com manifest, service worker, offline page e cache de assets.
- Suite de testes PHP cobrindo permissoes, fluxos criticos, IA e operacao P1.

### Bugs para corrigir

1. Reset de senha no perfil esta quebrado.
   Em `frontend/pages/app/perfil.php`, o campo do codigo usa `name="codigo"`
   com acento no arquivo atual exibido como `name="código"`, mas o backend le
   `codigo` em `AuthController::profilePasswordReset()`. O resultado provavel e
   o backend receber codigo vazio e rejeitar a troca de senha.
   O nome correto do campo deve ser exatamente `codigo`.

2. O README cita `backend/.env.example`, mas esse arquivo nao existe no repo.
   Isso quebra o passo de setup local e deixa a checagem de producao sem modelo
   claro de variaveis.

3. Anexo `.docx` aceita MIME `application/zip`.
   Em `CaseController`, isso provavelmente existe porque DOCX e um ZIP por
   baixo, mas tambem aumenta o risco de um ZIP generico passar se vier com
   extensao `.docx`. O ideal e validar a estrutura real de DOCX.

4. O PWA pode servir versao antiga.
   `frontend/service-worker.js` depende de `CACHE_VERSION`. Se o codigo mudar e
   a versao do cache nao mudar, o navegador pode continuar usando assets antigos.

5. CSP ainda permite `unsafe-eval`.
   `backend/app/support/security.php` inclui `'unsafe-eval'` em `script-src`.
   Isso reduz a protecao contra XSS. Vale remover se nenhuma biblioteca exigir.

6. Producao esta bloqueada por configuracao.
   `scripts/check-production-readiness.php` falhou porque `backend/.env` nao
   existe no ambiente atual.

### Riscos e observacoes

- Fotos de perfil sao salvas em `backend/storage/profile_photos`, que nao esta
  bloqueado pelo `.htaccess` como `documents` e `message-attachments`. Para
  avatar publico pode ser aceitavel, mas precisa ser decisao explicita.
- Login, cadastro e recuperacao de senha em paginas HTML dependem de JavaScript
  para buscar/injetar CSRF. Com JS habilitado funciona; sem JS, os formularios
  tendem a falhar.
- Parte do texto aparece com acentuacao quebrada no terminal. Pode ser apenas
  leitura do PowerShell, mas vale revisar no navegador e padronizar encoding.
- A documentacao ja lista varias pendencias reais de producao em
  `docs/O_QUE_FALTA_AGORA.md`.
- A suite de testes foi executada na rodada completa abaixo e apresentou falhas
  reais que precisam ser corrigidas antes de considerar o sistema verde.

### O que e bom adicionar

1. Criar `backend/.env.example` completo, sem segredos reais.
2. Automatizar a versao do PWA/cache ou gerar `CACHE_VERSION` por build/data.
3. Adicionar teste especifico para reset de senha pelo modal do perfil.
4. Validar `.docx` por estrutura interna, nao apenas por MIME `application/zip`.
5. Revisar CSP e remover `unsafe-eval` se possivel.
6. Criar checklist de deploy com `.env`, HTTPS, SMTP, ClamAV, Tesseract,
   storage privado, backup e restore.
7. Adicionar testes E2E minimos para login, cadastro, upload, analise, chat,
   agenda e reset de senha.
8. Monitorar `/backend/public/index.php?rota=/health` em ambiente real.
9. Fazer matriz visual mobile/tablet/desktop, principalmente depois da PWA.
10. Implementar RBAC granular se o sistema crescer para permissoes mais finas.
11. Criar rotina de limpeza de arquivos orfaos, jobs antigos e relatorio de
    saude periodico.

### Verificacao completa executada

Rodada feita em 17/06/2026, na branch `main`, com 180 arquivos rastreados no
workspace. O `README.md` ja estava modificado pela revisao anterior; nao havia
outras alteracoes locais relevantes antes da verificacao.

Comandos e resultados:

```powershell
php -l em todos os arquivos PHP
php scripts\check-references.php
php backend\tests\run.php
php backend\tests\AiGuardrailsTest.php
php backend\tests\PermissionAndCriticalFlowsTest.php
php backend\tests\P1OperationsTest.php
php scripts\check-production-readiness.php --env=backend/.env
```

Resultados detalhados:

- Sintaxe PHP: OK. Foram verificados 98 arquivos PHP com `php -l`.
- Referencias internas: OK. `scripts/check-references.php` retornou
  `Reference check: OK`.
- Suite completa `backend/tests/run.php`: FALHOU no primeiro teste,
  `AiGuardrailsTest.php`.
- `AiGuardrailsTest.php`: FALHOU na assercao `Prompt deve proibir calculo de
  prazos.`. O teste procura o texto sem acento `Nao calcule prazos
  processuais`, enquanto o prompt atual em `GeminiService.php` contem `Não
  calcule prazos processuais`. Isso indica desalinhamento entre teste e prompt.
- `PermissionAndCriticalFlowsTest.php`: FALHOU. Primeiro apareceram varios
  warnings de sessao (`headers already sent`) e depois a assercao
  `Exportacao LGPD deve incluir cadastro do titular` falhou: esperado
  `cliente1@teste.local`, obtido vazio.
- `P1OperationsTest.php`: OK.
- Prontidao de producao: FALHOU porque `backend/.env` nao existe no ambiente
  atual.

Verificacoes complementares:

- `frontend/site.webmanifest`: JSON valido.
- `frontend/service-worker.js`: OK. Os 37 assets estaticos declarados para
  cache existem.
- `backend/.env.example`: AUSENTE.
- `backend/.env`: AUSENTE.
- Busca por padroes de segredo real versionado: OK. Nao foram encontrados
  padroes obvios de chave Gemini, Google, DataJud, OpenAI, senha SMTP ou chave
  privada fora de SQL demo/testes/documentacao.
- Arquivos `.htaccess` de protecao: OK para raiz, `backend/`,
  `backend/storage/documents/` e `backend/storage/message-attachments/`.
- Marcadores de conflito Git (`<<<<<<<`, `=======`, `>>>>>>>`): OK, nenhum
  marcador encontrado.
- `git diff --check`: OK, apenas aviso normal de LF/CRLF no Windows para
  `README.md`.

Conclusao da verificacao:

- O projeto nao esta "verde" hoje.
- O codigo PHP nao tem erro de sintaxe.
- As referencias internas passam.
- O PWA esta estruturalmente consistente.
- Os bloqueadores atuais sao: testes falhando, ausencia de `backend/.env`,
  ausencia de `backend/.env.example`, bug do reset de senha do perfil e ajustes
  de seguranca/robustez listados acima.

### Backlog QA - o que falta fazer

Esta lista organiza o que falta para deixar o sistema pronto para homologacao
forte e, depois, producao.

#### P0 - Bloqueadores antes de colocar em producao

1. Corrigir o reset de senha do perfil.
   - Arquivo: `frontend/pages/app/perfil.php`.
   - Problema: o campo de codigo precisa enviar exatamente `codigo`.
   - Backend relacionado: `backend/app/controllers/AuthController.php`, que le
     `codigo`.
   - Impacto: fluxo de redefinicao de senha pode falhar para o usuario.

2. Criar e versionar `backend/.env.example`.
   - O README e os scripts citam esse arquivo, mas ele nao existe.
   - Deve conter todas as variaveis obrigatorias sem segredos reais.
   - Impacto: outro ambiente nao consegue configurar o sistema com seguranca.

3. Criar/configurar `backend/.env` no ambiente local e de producao.
   - O script de prontidao falhou porque o arquivo nao existe.
   - Deve incluir URL da aplicacao, banco, SMTP, chaves externas, caminhos de
     storage, backup, healthcheck e ambiente.
   - Impacto: nao da para validar prontidao operacional sem configuracao real.

4. Fazer a suite completa de testes passar.
   - `backend/tests/run.php` falhou em `AiGuardrailsTest.php`.
   - `PermissionAndCriticalFlowsTest.php` tambem falhou quando executado
     isoladamente.
   - Impacto: hoje nao existe sinal verde automatizado para release.

5. Corrigir o desalinhamento do teste de guardrails de IA.
   - Teste espera `Nao calcule prazos processuais`.
   - Prompt atual contem a mesma regra com acentuacao diferente.
   - Decidir se o teste deve normalizar acentos ou se o prompt deve seguir a
     string esperada.

6. Corrigir o teste de permissoes e fluxos criticos.
   - Ha warnings de sessao com `headers already sent`.
   - A exportacao LGPD nao trouxe o e-mail esperado do titular.
   - Impacto: risco em fluxo legal/sensivel e risco de regressao em controle de
     acesso.

7. Revisar a validacao de uploads de DOCX.
   - `CaseController.php` aceita `application/zip`, provavelmente por causa de
     arquivos DOCX.
   - Essa permissao precisa ser validada por extensao, MIME real e conteudo.
   - Impacto: risco de aceitar ZIP generico como documento.

8. Revisar a politica CSP.
   - `backend/app/support/security.php` contem `unsafe-eval`.
   - Confirmar qual dependencia exige isso e remover se possivel.
   - Impacto: CSP fica mais fraca contra XSS.

9. Garantir storage fora do webroot em producao.
   - Validar `DOCUMENT_STORAGE_PATH` e `ATTACHMENT_STORAGE_PATH`.
   - Os `.htaccess` ajudam no Apache, mas nao substituem isolamento correto.
   - Impacto: documentos juridicos e anexos nao podem ficar expostos.

10. Definir estrategia de versao do service worker.
    - `frontend/service-worker.js` usa `CACHE_VERSION = "justraduz-pwa-v4"`.
    - Todo deploy que muda asset cacheado precisa atualizar essa versao.
    - Impacto: usuarios podem continuar vendo tela antiga.

#### P1 - Alta prioridade para homologacao

1. Fazer QA manual completo no navegador.
   - Cadastro, login, logout, recuperar senha e reset por codigo.
   - Dashboard do cliente, dashboard admin e dashboard juridico.
   - Criacao de solicitacao/caso.
   - Upload, download e exclusao de documentos.
   - Chat/mensagens e anexos.
   - Agenda, prazos e eventos.
   - Consulta OAB.
   - Exportacao LGPD.
   - Instalacao PWA, modo offline e atualizacao de cache.

2. Adicionar testes E2E.
   - Cobrir os fluxos principais com navegador real.
   - Priorizar login, cadastro, perfil, documentos, mensagens, admin e PWA.

3. Auditar todos os usos de `innerHTML`.
   - Alguns pontos escapam conteudo corretamente, mas a revisao deve cobrir
     todos os arquivos JS antes de producao.
   - Impacto: reduzir risco de XSS em dados vindos do backend.

4. Validar dependencias externas no frontend.
   - Google Fonts, VLibras, Instagram, GitHub e links externos.
   - Confirmar privacidade, disponibilidade, CSP e comportamento offline.

5. Validar e-mail transacional.
   - Cadastro, recuperacao de senha, alertas e notificacoes.
   - Testar SMTP real, SPF/DKIM/DMARC e mensagens em spam.

6. Testar arquivos reais e casos ruins em upload.
   - PDF valido, DOCX valido, arquivo grande, MIME falso, extensao falsa,
     arquivo vazio, arquivo corrompido e conteudo suspeito.

7. Testar backup e restore.
   - Nao basta gerar backup; precisa restaurar em ambiente limpo.
   - Validar criptografia, retencao e permissao de acesso.

8. Validar integracoes externas.
   - Gemini, DataJud/OAB e qualquer servico de IA ou consulta publica.
   - Testar timeout, chave invalida, limite de uso, resposta vazia e erro 500.

9. Revisar logs e observabilidade.
   - Garantir que erros criticos sejam registrados sem vazar dados sensiveis.
   - Adicionar alerta para falha de login excessiva, falha de backup, falha de
     upload e erro de integracao externa.

10. Fazer QA responsivo e acessibilidade.
    - Desktop, tablet e celular.
    - Teclado, foco visivel, contraste, labels, leitores de tela e estados de
      erro.

#### P2 - Melhorias importantes depois dos bloqueadores

1. Automatizar CI.
   - Rodar `php -l`, `scripts/check-references.php`, testes backend e
     verificacao de prontidao a cada push/PR.

2. Criar checklist de release.
   - Migracoes, backup, `.env`, cache PWA, permissao de storage, cron/jobs,
     testes, rollback e monitoramento.

3. Melhorar documentacao operacional.
   - Manual de instalacao.
   - Manual de suporte.
   - Procedimento de backup/restore.
   - Procedimento de troca de chaves.

4. Completar backlog de produto ja citado na documentacao.
   - Planos/pagamentos.
   - Multiempresa.
   - Permissoes granulares.
   - Relatorios avancados.
   - SLA e escalonamento.
   - API publica versionada.
   - Rotinas de limpeza e retencao.

5. Polir estados de interface.
   - Loading, vazio, erro, permissao negada, offline e sucesso.
   - Garantir textos claros sem expor detalhes tecnicos.

6. Revisar performance.
   - Peso dos assets.
   - Cache.
   - Consultas ao banco.
   - Paginacao em listas grandes.
   - Tamanho e compressao de documentos.

### Matriz de QA recomendada

| Area | Status atual | Proximo passo |
| --- | --- | --- |
| Sintaxe PHP | OK | Manter no CI |
| Referencias internas | OK | Manter no CI |
| Testes backend completos | Falhando | Corrigir `AiGuardrailsTest` e rodar novamente |
| Permissoes e LGPD | Falhando | Corrigir sessoes e exportacao do titular |
| PWA estrutural | OK | Validar em navegador e controlar versao de cache |
| Configuracao de ambiente | Incompleta | Criar `.env.example` e configurar `.env` |
| Uploads | Requer revisao | Testar MIME, extensao e conteudo real |
| Seguranca HTTP/CSP | Requer revisao | Remover ou justificar `unsafe-eval` |
| Storage sensivel | Requer validacao | Confirmar caminhos fora do webroot |
| Email | Nao validado nesta rodada | Testar SMTP real e entregabilidade |
| Integracoes externas | Nao validado nesta rodada | Testar sucesso, falha e limites |
| Acessibilidade | Nao validado nesta rodada | Fazer checklist WCAG/manual |
| Responsivo | Nao validado nesta rodada | Testar desktop, tablet e celular |
| Backup/restore | Nao validado nesta rodada | Fazer restore real em ambiente limpo |

### Criterio minimo para considerar pronto

O sistema so deve ser considerado pronto para producao quando:

1. Todos os itens P0 estiverem corrigidos.
2. A suite automatizada estiver verde.
3. `backend/.env.example` existir e estiver completo.
4. `backend/.env` de producao passar no check de prontidao.
5. O QA manual dos fluxos principais estiver aprovado.
6. Backup e restore tiverem sido testados.
7. Uploads e documentos sensiveis tiverem sido validados com foco em seguranca.
8. O PWA tiver sido testado em instalacao, offline e atualizacao.
9. Logs, alertas e monitoramento estiverem ativos.
10. Houver um plano documentado de rollback.
