# MELHORIAS\_PARA\_100.md — JusTraduz

Data da revisão: 14/06/2026  
Arquivo revisado: `JusTraduz.zip`

## Diagnóstico direto

O JusTraduz já saiu do estágio de protótipo simples. A estrutura atual tem backend separado, rotas centralizadas, controllers, services, proteção de sessão, CSRF, auditoria, notificações, Google OAuth, IA Gemini, DataJud por CNJ, upload de documentos, chat, agenda e dashboards.

Contexto correto: o JusTraduz agora deve ser tratado como **sistema para apresentação da SA**, não como venda imediata. Para banca/apresentação, ele está forte e acima de um MVP comum. O objetivo atual não é virar SaaS comercial agora; é chegar com fluxo estável, visual profissional, demonstração convincente, escopo coerente e sem erros óbvios durante a apresentação. Melhorias de venda, multiempresa, billing e operação pesada ficam como roadmap pós-SA.

Nota prática atual:

|Área|Nota atual|Motivo|
|-|-:|-|
|Apresentação da SA|85/100|Fluxos principais existem e a aparência está bem mais completa que um MVP comum.|
|Demo local estável|72/100|Funciona como sistema interno, mas depende de configuração correta, banco importado e teste manual.|
|Produto comercial futuro|55/100|Falta billing, multiempresa, observabilidade, backup, LGPD operacional e testes fortes; isso não é prioridade para a SA.|
|Mobile/app para demo|35/100|Tem CSS responsivo, mas ainda não é PWA instalável nem tem fluxo mobile tratado como parte da apresentação.|
|Segurança para apresentação|60/100|Tem boas bases, mas ainda faltam ajustes para evitar falhas visíveis, permissões frouxas, uploads frágeis e erros em formulários.|



## Contexto atualizado: foco na SA, não em venda agora

A régua correta mudou. O objetivo atual é **apresentar bem**, não vender agora.

Isso muda a ordem das melhorias:

1. Primeiro: estabilidade da demo, visual, fluxo completo e permissões coerentes.
2. Depois: PWA básico para dar sensação de aplicativo.
3. Depois: segurança suficiente para não passar vergonha nem expor dados demo.
4. Por último: roadmap comercial, como billing, multiempresa, quotas, backup robusto e operação real.

Não gaste energia implementando cobrança, assinatura, multiempresa avançado ou infraestrutura de SaaS antes da SA. Isso é vaidade técnica agora. Para banca, vale mais um fluxo impecável do que dez módulos incompletos.

## O que já está bom e deve ser preservado

* Estrutura mais limpa: `backend/app/controllers`, `backend/app/services`, `backend/routes/api.php`, `frontend/pages/app` e `frontend/pages/admin`.
* Lint PHP passou nos arquivos `.php` revisados: não encontrei erro de sintaxe nos arquivos PHP.
* Uso de `PDO` com prepared statements na maior parte do sistema.
* Sessão com `httponly`, `SameSite=Lax`, regeneração de ID e expiração por inatividade.
* CSRF central no router para rotas `POST`.
* Bloqueio de acesso profissional quando OAB ainda não foi validada.
* Validação manual administrativa de OAB com justificativa, log e notificação.
* Auditoria com tentativa de mascarar dados sensíveis.
* Upload de documentos com nome aleatório, validação de extensão/MIME e download autorizado por controller.
* Chat vinculado ao caso, com permissão por participante.
* IA com prompt mais cuidadoso, disclaimers e bloqueios para dados sensíveis no chatbot público.
* DataJud limitado por número CNJ, evitando prometer consulta por CPF como se fosse simples.
* Dashboard admin já mostra visão operacional, OAB, documentos, auditoria e indicadores.
* Existe documentação básica e SQL consolidado com/sem demo.

Esses pontos são o chão. Não refaça tudo do zero. O erro seria destruir uma base que já tem corpo.

\---

# P0 — Corrigir antes da apresentação da SA

## 1\. Oficializar e endurecer o perfil `estagiario`

### Problema

O projeto contém `estagiario` em banco, login, cadastro, dashboards, agenda, validação OAB, navegação, admin, processos e seed demo. Como o estagiário vai continuar no escopo, isso não deve ser tratado como sobra para remover; deve ser tratado como papel oficial do produto.

Arquivos envolvidos incluem:

* `database/justraduz\_completo\_sem\_demo.sql`
* `database/justraduz\_completo\_com\_demo.sql`
* `backend/app/controllers/AuthController.php`
* `backend/app/controllers/AdminController.php`
* `backend/app/controllers/ScheduleController.php`
* `backend/app/controllers/CaseController.php`
* `backend/app/controllers/OnboardingController.php`
* `frontend/login.html`
* `frontend/completar-cadastro-google.php`
* `frontend/dashboard-estagiario.php`
* `frontend/pages/app/dashboard-estagiario.php`
* `frontend/app/ui/navigation.php`
* `frontend/app/ui/components.php`
* `frontend/pages/admin/\*`
* `frontend/pages/app/agenda.php`
* `frontend/pages/app/processos.php`
* `frontend/pages/app/tarefas.php`

### Verdade objetiva

Manter estagiário aumenta o produto, mas também aumenta risco. Não dá para deixar como “quase advogado” sem regra clara. Estagiário mexe com dado jurídico sensível, então precisa de permissões menores, supervisão, aprovação, rastreabilidade e limites explícitos.

### O que fazer

Assumir oficialmente os quatro perfis: `cliente`, `advogado`, `estagiario` e `admin`. O estagiário deve existir como perfil profissional limitado, validado e supervisionado, não como cópia fraca do advogado.

### Regras recomendadas para `estagiario`

* Pode se cadastrar e completar perfil profissional.
* Deve informar OAB de estagiário quando aplicável, UF e dados de formação/instituição se o sistema exigir.
* Fica bloqueado até aprovação administrativa.
* Não deve aparecer publicamente como advogado.
* Não deve aceitar caso sozinho sem vínculo/supervisão, se a proposta da SA quiser ser juridicamente conservadora.
* Pode atuar em triagem, organização de documentos, tarefas internas, atendimento inicial e suporte sob responsabilidade de advogado/admin.
* Não deve emitir parecer final como se fosse advogado.
* Todas as ações devem cair em auditoria com `user\_id`, `tipo`, entidade afetada, IP e timestamp.

### Critério de pronto

* `users.tipo` deve aceitar oficialmente `cliente`, `advogado`, `estagiario`, `admin`.
* Cadastro comum e Google devem exibir estagiário de forma clara, com texto de limitação.
* Dashboard de estagiário deve ter navegação própria, sem atalhos indevidos de advogado/admin.
* Admin deve conseguir aprovar, rejeitar, suspender e revisar estagiários.
* Agenda/processos/tarefas devem ter regra explícita para o que estagiário pode ou não fazer.
* Seed demo deve ter pelo menos um estagiário realista para teste.
* `grep -R "estagiario" .` deve encontrar usos ativos documentados, coerentes e intencionais, não restos contraditórios.

\---

## 2\. Fortalecer CSRF nos formulários públicos

### Problema

Os formulários públicos HTML dependem do JavaScript (`frontend/assets/js/auth.js`) para buscar `/auth/csrf` e injetar `<input name="\_csrf">`.

Formulários afetados:

* `frontend/login.html`
* `frontend/admin/login-admin.html`
* `frontend/recuperar-senha.html`

### Risco

Com JavaScript quebrado, lento, bloqueado ou erro de carregamento, login/cadastro/recuperação podem falhar com “CSRF token inválido”. Segurança não deve depender só de JS carregando corretamente.

### O que fazer

Transformar essas páginas públicas críticas em PHP ou criar um pequeno include servidor-side para inserir `csrf\_input()` direto no HTML.

### Critério de pronto

* Login, cadastro, admin login e recuperação de senha devem funcionar mesmo com JavaScript desativado.
* O JS pode continuar melhorando UX, mas não pode ser pré-requisito para segurança básica.
* Todo formulário `method="post"` deve conter token CSRF renderizado pelo servidor.

\---

## 3\. Criar PWA de verdade antes de pensar em app nativo

### Problema

Não existe:

* `manifest.webmanifest`
* `service-worker.js` ou `sw.js`
* ícones PWA 192/512
* tela offline
* registro de service worker
* configuração de display standalone

### O que fazer

Implementar `JusTraduz Mobile PWA`.

### Arquivos sugeridos

* `frontend/manifest.webmanifest`
* `frontend/sw.js`
* `frontend/assets/img/icons/icon-192.png`
* `frontend/assets/img/icons/icon-512.png`
* `frontend/assets/js/pwa-register.js`
* incluir registro em páginas públicas e logadas.

### Regras do PWA

* Cachear apenas assets públicos: CSS, JS, logos, ícones e páginas públicas essenciais.
* Não cachear documentos, PDFs, anexos, respostas de IA, chat, dados pessoais ou páginas autenticadas sensíveis.
* Criar fallback offline simples: “Sem conexão. Reconecte para acessar documentos e atendimentos.”
* Usar `display: standalone`, `theme\_color`, `background\_color`, `name`, `short\_name`, `icons`.

### Critério de pronto

* Chrome Android permite “Instalar app”.
* Ícone aparece na tela inicial.
* Ao abrir instalado, não parece uma aba normal do navegador.
* Login, dashboard cliente, upload e solicitar ajuda são utilizáveis em 390px de largura.
* Lighthouse PWA deve passar nos itens básicos.

\---

## 4\. Corrigir upload para demonstração segura

### Problema

O upload atual valida extensão/MIME e salva com nome aleatório. Isso é bom para MVP, mas ainda pode gerar problema na apresentação se arquivo inválido, imagem corrompida ou documento grande travar o fluxo.

Pontos críticos:

* Não há antimalware.
* Não há OCR para PDF/imagem escaneada.
* Não há fila assíncrona.
* `doc`/`docx` são permitidos no anexo do chat; `docx` pode aparecer como `application/zip`, o que aumenta o risco se a validação for superficial.
* O limite de 50 MB para documentos pode travar análise síncrona e piorar UX.

### O que fazer

Criar um `FileSecurityService` e centralizar validação de upload.

### Regras mínimas

* Validar extensão + MIME real + assinatura básica do arquivo.
* Para imagem: validar com `getimagesize()` e recusar imagem corrompida.
* Para PDF: validar header `%PDF` e limitar páginas/tamanho quando possível.
* Para DOCX: validar estrutura ZIP esperada (`\[Content\_Types].xml`, `word/document.xml`) e recusar ZIP genérico.
* Escanear com ClamAV ou serviço equivalente antes de liberar download/análise.
* Guardar status do arquivo: `uploaded`, `scanning`, `safe`, `infected`, `rejected`, `processing`, `processed`, `failed`.
* Nunca processar IA/OCR antes do status `safe`.

### Critério de pronto

* Arquivos maliciosos ou inválidos não entram no fluxo.
* Usuário vê status claro do arquivo.
* Download só funciona se o arquivo estiver autorizado e seguro.
* Logs registram motivo de recusa sem expor conteúdo sensível.

\---

## 5\. Tirar IA, OCR e DataJud do fluxo síncrono

### Problema

Hoje a análise por IA e a consulta DataJud são chamadas diretamente durante o request. Isso pode funcionar em demo, mas é frágil para apresentação se a internet, Gemini, DataJud ou OCR falharem no momento.

### Risco

* Timeout.
* Tela travada.
* Duplicidade de tentativas.
* Usuário reenviando formulário.
* Custo sem controle.
* Falha externa derrubando UX.

### O que fazer

Criar fila de jobs.

### Tabelas sugeridas

```sql
CREATE TABLE jobs (
  id INT AUTO\_INCREMENT PRIMARY KEY,
  type VARCHAR(80) NOT NULL,
  entity\_type VARCHAR(80) NOT NULL,
  entity\_id INT NOT NULL,
  status ENUM('pending','running','done','failed','cancelled') NOT NULL DEFAULT 'pending',
  attempts INT NOT NULL DEFAULT 0,
  max\_attempts INT NOT NULL DEFAULT 3,
  available\_at DATETIME NOT NULL DEFAULT CURRENT\_TIMESTAMP,
  started\_at DATETIME NULL,
  finished\_at DATETIME NULL,
  error\_message TEXT NULL,
  payload JSON NULL,
  created\_at TIMESTAMP DEFAULT CURRENT\_TIMESTAMP,
  updated\_at TIMESTAMP DEFAULT CURRENT\_TIMESTAMP ON UPDATE CURRENT\_TIMESTAMP,
  INDEX idx\_jobs\_status\_available (status, available\_at),
  INDEX idx\_jobs\_entity (entity\_type, entity\_id)
);
```

### Jobs mínimos

* `document\_ai\_analysis`
* `document\_ocr`
* `datajud\_cnj\_sync`
* `email\_send`

### Critério de pronto

* Upload retorna rápido.
* Tela mostra “processando”.
* Job tenta novamente em falha temporária.
* Falha final aparece para o usuário sem stack trace.
* Admin consegue ver fila/falhas.

\---

## 6\. Implementar LGPD operacional, não só texto bonito

### Problema

Existem termos, privacidade, consentimento em alguns fluxos e mensagens de cuidado. Isso ajuda, mas ainda não é LGPD operacional.

### O que falta

* Registro formal de consentimento por versão e finalidade.
* Solicitação de exclusão/exportação de dados.
* Política de retenção de documentos/anexos.
* Base legal registrada para cada tratamento sensível.
* Rotina de resposta a incidente.
* Minimização real de dados enviados à IA.

### Tabelas sugeridas

```sql
CREATE TABLE consent\_records (
  id INT AUTO\_INCREMENT PRIMARY KEY,
  user\_id INT NULL,
  purpose VARCHAR(120) NOT NULL,
  version VARCHAR(50) NOT NULL,
  accepted BOOLEAN NOT NULL DEFAULT TRUE,
  ip\_address VARCHAR(45) NULL,
  user\_agent VARCHAR(255) NULL,
  created\_at TIMESTAMP DEFAULT CURRENT\_TIMESTAMP,
  INDEX idx\_consent\_user\_purpose (user\_id, purpose, version)
);

CREATE TABLE data\_subject\_requests (
  id INT AUTO\_INCREMENT PRIMARY KEY,
  user\_id INT NOT NULL,
  request\_type ENUM('export','delete','rectify','revoke\_consent') NOT NULL,
  status ENUM('open','in\_review','completed','rejected') NOT NULL DEFAULT 'open',
  admin\_notes TEXT NULL,
  created\_at TIMESTAMP DEFAULT CURRENT\_TIMESTAMP,
  completed\_at DATETIME NULL,
  FOREIGN KEY (user\_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Critério de pronto

* Todo uso de IA em documento tem consentimento versionado.
* Toda consulta DataJud por CNJ tem consentimento versionado.
* Usuário consegue solicitar exportação/exclusão.
* Admin consegue tratar solicitação com log.
* Documentos e anexos têm retenção definida.

\---

## 7\. Melhorar política de senha e recuperação

### Problema

A senha mínima atual é de 6 caracteres. Isso é fraco para um sistema com documentos jurídicos.

### O que fazer

* Mínimo de 10 ou 12 caracteres.
* Bloquear senhas comuns.
* Exigir confirmação de senha atual em alterações sensíveis.
* Invalidar códigos antigos de recuperação ao emitir novo código, já há parte disso, mas precisa teste.
* Rate limit por IP + e-mail + usuário, não só por audit log.
* Opcional para 100%: 2FA por e-mail ou TOTP para admin.

### Critério de pronto

* Admin precisa de senha forte.
* Recuperação de senha tem limite real, expiração e log.
* Troca de senha encerra sessões antigas.
* Testes automatizados cobrem recuperação, erro de código, expiração e excesso de tentativas.

\---

## 8\. Criar validação real de CPF/CNPJ/CNJ

### Problema

O sistema valida CPF por tamanho e CNJ por quantidade de dígitos. Isso evita lixo óbvio, mas ainda aceita número matematicamente inválido.

### O que fazer

Criar `ValidationService` com:

* `isValidCpf()` com dígitos verificadores.
* `isValidCnpj()` se CNPJ entrar em cadastro futuro.
* `isValidCnj()` com validação oficial do número CNJ.
* `normalizePhoneBr()` para telefone.
* `normalizeOab()` para inscrição e UF.

### Critério de pronto

* CPF `111.111.111-11` deve ser recusado.
* CNJ com 20 dígitos, mas DV errado, deve ser recusado.
* Testes automatizados cobrindo casos válidos e inválidos.

\---

## 9\. Limpar o ZIP/repositório

### Problema

O ZIP enviado está muito pesado por motivos que não agregam produto.

Achados objetivos:

* O ZIP inclui a pasta `.git`, com aproximadamente 49 MB.
* Existe outro ZIP dentro do projeto: `justraduz\_note\_arquivos\_corrigidos\_apenas\_com\_erro\_de\_ficar\_verde.zip`, com cerca de 5,8 MB.
* Algumas imagens de depoimentos têm tamanho absurdo para web:

  * `rafael-costa.jpg`: cerca de 15,5 MB.
  * `ana-paula-lima.jpg`: cerca de 14,7 MB.
  * `mariana-souza.jpg`: cerca de 9 MB.
* `chat-bot-logo.png` tem cerca de 1,5 MB.

### O que fazer

* Remover `.git` de entregáveis ZIP.
* Remover ZIPs de vídeo/rascunho de dentro do projeto.
* Otimizar imagens para WebP/AVIF.
* Reduzir depoimentos para no máximo 250 KB cada.
* Criar pasta `docs/archive/` se algo precisar ser guardado, mas não empacotar em produção.

### Critério de pronto

* ZIP final do projeto sem `.git`, sem arquivos de vídeo/rascunho e com imagens otimizadas.
* Entregável final muito menor e mais profissional.

\---

# P1 — Necessário para uma apresentação forte e estável

## 10\. Criar Composer, PHPUnit e documentação de extensões PHP

### Problema

Não há `composer.json`, `phpunit.xml` ou lista formal de extensões necessárias.

O código depende de funções/extensões como:

* `PDO` + `pdo\_mysql`
* `curl`
* `mbstring`
* `openssl`
* `fileinfo`
* `json`
* `session`
* `iconv`

### O que fazer

Criar `composer.json` mínimo:

```json
{
  "name": "teamghcp/justraduz",
  "type": "project",
  "require": {
    "php": ">=8.2",
    "ext-pdo": "\*",
    "ext-pdo\_mysql": "\*",
    "ext-curl": "\*",
    "ext-mbstring": "\*",
    "ext-openssl": "\*",
    "ext-fileinfo": "\*",
    "ext-json": "\*"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.5"
  },
  "scripts": {
    "lint": "find backend frontend -name '\*.php' -print0 | xargs -0 -n1 php -l",
    "test": "phpunit"
  }
}
```

### Critério de pronto

* `composer install` funciona.
* `composer lint` funciona.
* `composer test` funciona.
* README informa extensões obrigatórias no XAMPP.

\---

## 11\. Criar CI no GitHub Actions

### Problema

Hoje qualquer alteração pode quebrar rotas, SQL ou permissões sem aviso.

### O que fazer

Criar `.github/workflows/ci.yml` com:

* PHP 8.2/8.3.
* Instalação de extensões necessárias.
* `composer install`.
* `composer lint`.
* Importação do SQL sem demo em MySQL de teste.
* Testes automatizados.
* Checagem de rotas usadas pelo frontend.

### Critério de pronto

* Todo push roda CI.
* PR quebrado fica vermelho.
* SQL consolidado é importado em banco limpo no CI.

\---

## 12\. Aumentar muito os testes automatizados

### Problema

Existe apenas um teste manual/scriptado de guardrails de IA. Isso é pouco para um sistema com documentos, permissões e dados sensíveis.

### Testes mínimos

#### Auth

* Login cliente.
* Login admin.
* Login advogado pendente deve bloquear.
* Login advogado validado deve permitir.
* Cadastro com CPF inválido deve falhar.
* Cadastro com e-mail duplicado deve falhar.
* Recuperação de senha: código correto, errado, expirado e excesso de tentativas.

#### Documentos

* Cliente só vê documento próprio.
* Advogado só vê documento de caso aceito.
* Admin vê todos.
* Usuário não consegue baixar arquivo fora de `backend/storage/documents`.
* Upload inválido é recusado.

#### Casos/chat

* Cliente cria caso.
* Advogado aceita caso aberto.
* Advogado não aceita caso já aceito.
* Usuário fora do caso não lê chat/anexo.
* Caso finalizado não aceita mensagem comum.

#### Agenda

* Advogado cria horário futuro.
* Bloqueia horário sobreposto.
* Cliente agenda horário livre.
* Dois clientes não reservam mesmo slot.

#### Admin

* Admin altera status de usuário.
* Admin não inativa a própria conta.
* Admin valida/rejeita OAB com justificativa.
* Usuário comum não acessa rotas admin.

#### DataJud/IA

* CNJ inválido é recusado.
* Sem consentimento LGPD, DataJud não consulta.
* Gemini sem chave cai em fallback seguro.
* IA não processa sem consentimento.

### Critério de pronto

* Cobertura real dos fluxos críticos.
* Fixtures resetáveis.
* Banco de teste isolado.
* CI executando tudo.

\---

## 13\. Transformar SQL consolidado em migrações seguras

### Problema

Os SQLs atuais começam com `DROP DATABASE IF EXISTS justraduz`. Isso é aceitável para instalação limpa, mas perigoso para manutenção real.

### O que fazer

Manter os instaladores, mas criar pasta de migrações reais:

```text
database/migrations/
  2026\_06\_14\_001\_create\_consent\_records.sql
  2026\_06\_14\_002\_create\_jobs.sql
  2026\_06\_14\_003\_harden\_estagiario\_permissions.sql
```

### Critério de pronto

* Existe instalador limpo para ambiente novo.
* Existe migração incremental para ambiente existente.
* README explica quando usar cada um.
* Nenhum comando destrutivo roda sem aviso explícito.

\---

## 14\. Endurecer CSP e headers

### Problema

A CSP atual ainda permite `unsafe-inline`, `unsafe-eval` e vários domínios externos. Entendo o motivo: compatibilidade com VLibras, scripts e código legado. Para a SA, a prioridade é não quebrar a tela; depois da apresentação, isso precisa ser reduzido com calma.

### O que fazer

* Remover scripts inline progressivamente.
* Usar arquivos JS externos.
* Usar nonce para scripts inevitáveis.
* Remover `unsafe-eval` se não for indispensável.
* Separar CSP pública, app logado e admin.
* Testar VLibras sem afrouxar demais o resto do app.

### Critério de pronto

* CSP não quebra a interface.
* Admin e área logada têm política mais restrita.
* Relatórios de violação podem ser coletados no futuro, em produção real.

\---

## 15\. Criar observabilidade e health check

### Problema

Se Gemini, SMTP, MySQL, DataJud ou storage falharem, hoje a operação depende de perceber manualmente.

### O que fazer

Criar rota admin ou CLI:

* Banco conectado.
* Tabelas esperadas existem.
* Storage gravável.
* SMTP configurado.
* Gemini configurado e aprovado para dados reais.
* DataJud configurado.
* Fila de jobs com pendências/falhas.
* Espaço em disco.

### Arquivos sugeridos

* `backend/app/services/HealthCheckService.php`
* `frontend/pages/admin/saude-sistema.php`

### Critério de pronto

* Admin vê painel de saúde.
* Erros críticos aparecem sem expor segredo.
* Logs têm correlação mínima por ação/usuário.

\---

## 16\. Backup e restore testados

### Problema

Sistema com documento jurídico sem backup testado é roleta.

### O que fazer

* Script de backup do banco.
* Script de backup do storage.
* Criptografia dos backups.
* Retenção: diário 7 dias, semanal 4 semanas, mensal 6 meses, ou política definida.
* Teste de restore em ambiente separado.

### Critério de pronto

* Existe `scripts/backup.ps1` ou `.bat` para Windows/XAMPP local.
* Existe documentação de restore.
* Um restore foi testado e documentado.

\---

## 17\. Melhorar UX de erro mantendo dados preenchidos

### Problema

Vários fluxos redirecionam com `?erro=...`. Isso é simples, mas perde dados preenchidos e frustra o usuário.

### Fluxos prioritários

* Cadastro.
* Solicitar ajuda.
* Upload/análise.
* Perfil.
* Agenda.
* Admin validação OAB.

### O que fazer

* Para formulários críticos, salvar old input em sessão por uma requisição ou processar via AJAX com JSON.
* Mostrar erro no campo específico, não só no topo.
* Manter usuário na mesma tela.

### Critério de pronto

* Se o usuário erra um campo, os outros continuam preenchidos.
* Campo inválido recebe foco e mensagem clara.
* Nenhum erro genérico quando dá para apontar o campo exato.

\---

## 18\. Melhorar mobile de verdade

### Problema

Há responsividade, mas ainda falta teste de produto mobile.

### Checklist obrigatório

Testar em:

* 360x800
* 390x844
* 412x915
* 768x1024

Fluxos:

* Login.
* Cadastro.
* Dashboard cliente.
* Upload de PDF/imagem pelo celular.
* Solicitar ajuda.
* Chat com anexo.
* Agenda.
* Visualizar documento.
* Admin básico em tablet.

### Critério de pronto

* Nenhuma tabela estoura a tela sem rolagem adequada.
* Botões têm área clicável confortável.
* Sidebar mobile abre/fecha sem cobrir ações importantes.
* Upload pelo celular aceita câmera/galeria quando aplicável.

\---

# P2 — Pós-SA: para vender ou escalar como SaaS

Esta parte deve ser tratada como **roadmap futuro**. Não é prioridade para a apresentação da SA. Implementar isso agora pode consumir tempo que deveria ir para estabilidade, visual, demo e documentação.

## 19\. Multiempresa/escritórios

### Problema

O sistema ainda parece operar como uma plataforma global única. Para apresentação da SA, isso não precisa ser implementado agora. Para SaaS jurídico futuro, escritórios precisariam de isolamento.

### O que fazer

Criar:

* `organizations`
* `organization\_members`
* `roles`
* `permissions`
* `organization\_id` em casos, documentos, agenda e usuários quando fizer sentido.

### Critério de pronto

* Escritório A não vê nada do escritório B.
* Admin global é diferente de admin do escritório.
* Convites e membros funcionam.

\---

## 20\. RBAC granular

### Problema

Perfis fixos (`cliente`, `advogado`, `admin`) são simples, mas rígidos.

### O que fazer

Criar permissões por recurso:

* `documents.view`
* `documents.delete`
* `cases.accept`
* `cases.update\_status`
* `audit.view`
* `oab.validate`
* `users.manage`
* `billing.manage`

### Critério de pronto

* Permissões não dependem só de `tipo`.
* Rotas validam permissão centralizada.
* Testes cobrem permissão negada.

\---

## 21\. Billing e planos

### Problema

Sem cobrança, não é SaaS comercial completo. Para a SA, isso deve ficar como roadmap, não como tarefa obrigatória.

### O que fazer

Criar planos:

* Free/demo limitado.
* Cliente individual.
* Profissional.
* Escritório.
* Admin interno.

Controlar limites:

* Quantidade de documentos.
* Tamanho total de storage.
* Análises IA/mês.
* Consultas DataJud/mês.
* Profissionais por organização.

### Critério de pronto

* Usuário inadimplente fica limitado sem apagar dados.
* Admin vê consumo.
* IA/API externa tem limite de custo por plano.

\---

## 22\. Relatórios comerciais e operacionais

### Relatórios úteis

* Documentos enviados por período.
* Casos abertos/finalizados.
* Tempo médio até aceite do advogado.
* Profissionais mais ativos.
* Uso de IA por usuário/plano.
* Falhas de IA/DataJud/SMTP.
* Auditoria exportável.

### Critério de pronto

* Admin exporta CSV.
* Filtros por período.
* Indicadores batem com o banco.

\---

# P3 — Polimento final

## 23\. Padronizar copy jurídica

### Problema

O sistema precisa evitar parecer que entrega parecer jurídico definitivo.

### Regras

* Trocar promessas fortes por linguagem informativa.
* Reforçar que IA é apoio, não decisão jurídica.
* Não prometer consulta por CPF se o sistema não faz isso.
* Evitar “garantia”, “parecer final”, “solução jurídica automática”.

### Critério de pronto

* Landing page, IA, termos, dashboards e e-mails usam linguagem consistente.

\---

## 24\. Manual operacional do admin

### O que documentar

* Como validar/rejeitar OAB.
* Como lidar com denúncia/incidente.
* Como restaurar backup.
* Como consultar auditoria.
* Como bloquear usuário.
* Como responder solicitação LGPD.
* Como verificar falha de e-mail/IA/DataJud.

### Critério de pronto

* Um admin novo consegue operar o sistema sem perguntar ao dev.

\---

## 25\. Melhorar README final para banca da SA

### O que incluir

* Requisitos.
* Instalação local.
* Configuração XAMPP.
* Porta correta do MySQL.
* Como importar banco com/sem demo.
* Contas demo.
* Como configurar Google OAuth.
* Como configurar SMTP.
* Como configurar Gemini.
* Como configurar DataJud.
* Como instalar PWA.
* Como rodar testes.
* Limitações conhecidas.

\---

# Ordem de execução recomendada para a SA

## Sprint 1 — Coerência de escopo e demo sem erro

1. Oficializar `estagiario` como perfil real, com permissões limitadas e aprovação administrativa.
2. Limpar ZIP/repositório: `.git`, ZIP interno, imagens gigantes e arquivos duplicados.
3. Corrigir CSRF servidor-side em login/cadastro/admin/recuperação.
4. Criar `composer.json`, documentação de extensões e `composer lint`.
5. Garantir que o SQL com demo importe sem erro em banco limpo.

## Sprint 2 — Fluxo principal da apresentação

6. Revisar o caminho completo: visitante → cadastro/login → envio de documento → IA → solicitar ajuda → advogado/estagiário/admin.
7. Criar dados demo realistas para cliente, advogado, estagiário e admin.
8. Preservar dados nos formulários após erro.
9. Criar estados de loading/empty/error padronizados.
10. Criar mensagens amigáveis para falha de IA, DataJud, upload e e-mail.

## Sprint 3 — Mobile/PWA para parecer app

11. Implementar PWA básico.
12. Melhorar responsividade dos fluxos principais.
13. Ajustar upload pelo celular.
14. Testar em largura de 390px e 430px.
15. Criar ícone, nome curto e tela offline simples.

## Sprint 4 — Segurança mínima defendível na banca

16. Criar `ValidationService` para CPF/CNPJ/CNJ.
17. Criar `FileSecurityService` básico.
18. Melhorar política de senha e recuperação.
19. Revisar permissões de cliente, advogado, estagiário e admin.
20. Garantir auditoria das ações principais.

## Sprint 5 — Documentação e defesa

21. Melhorar README final para banca da SA.
22. Criar manual curto do admin.
23. Documentar limitações conhecidas sem parecer desculpa.
24. Preparar roteiro de apresentação de 5 a 8 minutos.
25. Criar checklist de pré-apresentação: XAMPP, banco, `.env`, contas demo, internet, Gemini/DataJud e fallback.

## Adiar para pós-SA

Não implemente agora, a menos que sobre tempo real:

* Billing e planos.
* Quotas por assinatura.
* Multiempresa/escritórios.
* Observabilidade avançada.
* Backup automatizado robusto.
* Fila completa para IA/OCR/DataJud.
* CI/CD completo.
* Publicação em loja de app.

# Prompt direto para mandar ao Codex

Use este prompt para aplicar as melhorias com foco na **apresentação da SA**, sem transformar o projeto em um SaaS comercial agora.

```md
Você está trabalhando no projeto JusTraduz, em PHP, MySQL, HTML, CSS e JavaScript puro. Não use React, Tailwind, shadcn, Laravel ou framework novo. Preserve a estrutura atual do projeto.

Contexto: o JusTraduz não será vendido agora. O objetivo atual é deixar o sistema pronto para apresentação da SA: estável, bonito, coerente, demonstrável no celular, com dados demo realistas e sem erro óbvio na frente da banca.

Objetivo desta rodada: melhorar o projeto para chegar o mais perto possível de 100/100 na apresentação da SA, priorizando estabilidade, UX, PWA básico, segurança defendível, LGPD básica e fluxo completo.

Tarefas obrigatórias desta rodada:

1. Oficializar e endurecer o perfil `estagiario`, mantendo quatro perfis: `cliente`, `advogado`, `estagiario` e `admin`.
   - Manter opção de estagiário no cadastro comum e cadastro Google, com texto claro de limitação.
   - Manter/criar dashboard própria de estagiário.
   - Ajustar SQL com/sem demo para incluir estagiário de forma intencional.
   - Ajustar controllers, middleware, páginas admin, navegação, agenda, processos e tarefas.
   - Estagiário deve ter permissões menores que advogado.
   - Estagiário deve depender de aprovação administrativa antes de atuar.
   - Estagiário não pode aparecer como advogado nem emitir parecer final como advogado.
   - Toda ação importante de estagiário deve ser auditada.
   - Ao final, `grep -R "estagiario" .` deve encontrar apenas usos ativos, coerentes e documentados.

2. Corrigir CSRF nos formulários públicos.
   - Login, cadastro, admin login e recuperação de senha não podem depender de JavaScript para ter `\_csrf`.
   - Converter essas páginas para PHP ou criar renderização server-side segura do token.
   - Manter compatibilidade com o JS existente.

3. Criar PWA básico para parecer aplicativo na apresentação.
   - Adicionar `frontend/manifest.webmanifest`.
   - Adicionar `frontend/sw.js`.
   - Adicionar ícones 192x192 e 512x512.
   - Adicionar registro do service worker.
   - Cachear somente assets públicos seguros.
   - Não cachear documentos, anexos, chat, dados pessoais, IA nem páginas autenticadas sensíveis.
   - Criar fallback offline simples.

4. Criar `composer.json` com extensões obrigatórias e scripts `lint` e `test`.
   - Exigir PHP >= 8.2.
   - Declarar `ext-pdo`, `ext-pdo\_mysql`, `ext-curl`, `ext-mbstring`, `ext-openssl`, `ext-fileinfo`, `ext-json`.
   - Adicionar script para rodar `php -l` nos arquivos PHP.

5. Criar `ValidationService`.
   - Validar CPF com dígitos verificadores.
   - Validar CNPJ, se o sistema aceitar pessoa jurídica.
   - Validar CNJ com algoritmo correto, não só tamanho.
   - Usar esse serviço em cadastro, perfil e DataJud.

6. Melhorar estabilidade da demonstração.
   - Criar estados de loading, erro e vazio para IA, DataJud, upload, login e dashboards.
   - Quando ocorrer erro em formulário, permanecer na mesma tela com os dados já preenchidos.
   - Criar mensagens claras para falha de internet/API sem quebrar a tela.
   - Garantir que o SQL com demo importe em banco limpo.
   - Criar contas demo realistas para cliente, advogado, estagiário e admin.

7. Melhorar mobile.
   - Testar e ajustar as telas principais em largura de 390px e 430px.
   - Priorizar login, cadastro, dashboard cliente, upload, solicitar ajuda, dashboard advogado, dashboard estagiário e admin.
   - Botões devem ser fáceis de tocar no celular.
   - Sidebar/menu não pode bloquear o conteúdo.

8. Criar documentação final para banca.
   - README com instalação local via XAMPP.
   - Como importar banco com/sem demo.
   - Contas demo.
   - Configuração `.env`.
   - Como configurar Google OAuth, SMTP, Gemini e DataJud.
   - Como instalar o PWA no celular.
   - Limitações conhecidas.
   - Roteiro curto de apresentação.

Regras:
- Não quebrar rotas existentes sem criar wrapper/redirect compatível.
- Não remover segurança existente.
- Não expor dados sensíveis em logs.
- Não alterar stack.
- Não usar consulta por CPF em DataJud.
- Não prometer que IA substitui advogado.
- Não implementar billing, planos, multiempresa ou SaaS avançado agora.
- Manter visual profissional atual.
- Priorizar o que melhora a apresentação da SA.

Critério final:
- `php -l` deve passar em todos os arquivos PHP.
- O SQL com demo e sem demo deve importar em banco limpo.
- Login/cadastro funcionam sem JavaScript.
- PWA é instalável no Chrome Android.
- O sistema possui fluxo ativo de estagiário com permissões limitadas, aprovação administrativa e auditoria.
- O fluxo principal da apresentação funciona sem travar.
- O README permite que outra pessoa rode o projeto localmente.
```

# O que não prometer na apresentação

Não apresente como “produto comercial pronto para vender” enquanto faltar:

* PWA instalável.
* Testes automatizados de permissão.
* Antimalware no upload.
* LGPD operacional com consentimento versionado e solicitação de exclusão/exportação.
* Backup/restore testado.
* Health check.
* Fila para IA/OCR/DataJud.
* Billing/planos, apenas se virar SaaS depois da SA.
* Multiempresa, apenas se for vender para escritórios depois da SA.
* Revisão jurídica real dos termos.

A verdade: o projeto está com cara de produto, mas a meta atual é vencer a apresentação da SA, não sustentar uma operação comercial real. A prioridade agora não é adicionar mais tela. É fechar o escopo, evitar erro ao vivo, deixar mobile apresentável, corrigir permissões, preparar dados demo e ter uma narrativa clara para a banca.

