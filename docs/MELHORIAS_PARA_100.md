# MELHORIAS_PARA_100.md — JusTraduz

Data da revisão: 14/06/2026  
Arquivo revisado: `JusTraduz(7).zip`

## Diagnóstico direto

O JusTraduz já saiu do estágio de protótipo simples. A estrutura atual tem backend separado, rotas centralizadas, controllers, services, proteção de sessão, CSRF, auditoria, notificações, Google OAuth, IA Gemini, DataJud por CNJ, upload de documentos, chat, agenda e dashboards.

Mas ele ainda não está pronto para ser vendido como produto comercial. Para banca/apresentação, está forte. Para produção real com usuários, dados jurídicos e LGPD, ainda falta endurecer segurança, operação, testes, mobile/PWA, governança de dados, filas assíncronas e limpeza de escopo.

Nota prática atual:

| Área | Nota atual | Motivo |
|---|---:|---|
| Demonstração/banca | 85/100 | Fluxos principais existem e a aparência está bem mais completa que um MVP comum. |
| Produto real local | 72/100 | Funciona como sistema interno, mas depende de configuração correta e teste manual. |
| SaaS comercial | 55/100 | Falta billing, multiempresa, observabilidade, backup, política LGPD operacional e testes fortes. |
| Mobile/app | 35/100 | Tem CSS responsivo, mas ainda não é PWA instalável nem tem fluxo mobile tratado como produto. |
| Segurança de produção | 60/100 | Tem boas bases, mas ainda falta antimalware, política forte de senha, CI, CSP mais rígida, auditoria exportável e testes de permissão. |

## O que já está bom e deve ser preservado

- Estrutura mais limpa: `backend/app/controllers`, `backend/app/services`, `backend/routes/api.php`, `frontend/pages/app` e `frontend/pages/admin`.
- Lint PHP passou nos arquivos `.php` revisados: não encontrei erro de sintaxe nos arquivos PHP.
- Uso de `PDO` com prepared statements na maior parte do sistema.
- Sessão com `httponly`, `SameSite=Lax`, regeneração de ID e expiração por inatividade.
- CSRF central no router para rotas `POST`.
- Bloqueio de acesso profissional quando OAB ainda não foi validada.
- Validação manual administrativa de OAB com justificativa, log e notificação.
- Auditoria com tentativa de mascarar dados sensíveis.
- Upload de documentos com nome aleatório, validação de extensão/MIME e download autorizado por controller.
- Chat vinculado ao caso, com permissão por participante.
- IA com prompt mais cuidadoso, disclaimers e bloqueios para dados sensíveis no chatbot público.
- DataJud limitado por número CNJ, evitando vender consulta por CPF como se fosse simples.
- Dashboard admin já mostra visão operacional, OAB, documentos, auditoria e indicadores.
- Existe documentação básica e SQL consolidado com/sem demo.

Esses pontos são o chão. Não refaça tudo do zero. O erro seria destruir uma base que já tem corpo.

---

# P0 — Corrigir antes de qualquer apresentação séria como “produto 100%”

## 1. Decidir e limpar definitivamente o perfil `estagiario`

### Problema
O projeto ainda contém `estagiario` em banco, login, cadastro, dashboards, agenda, validação OAB, navegação, admin, processos e seed demo.

Arquivos envolvidos incluem:

- `database/justraduz_completo_sem_demo.sql`
- `database/justraduz_completo_com_demo.sql`
- `backend/app/controllers/AuthController.php`
- `backend/app/controllers/AdminController.php`
- `backend/app/controllers/ScheduleController.php`
- `backend/app/controllers/CaseController.php`
- `backend/app/controllers/OnboardingController.php`
- `frontend/login.html`
- `frontend/completar-cadastro-google.php`
- `frontend/dashboard-estagiario.php`
- `frontend/pages/app/dashboard-estagiario.php`
- `frontend/app/ui/navigation.php`
- `frontend/app/ui/components.php`
- `frontend/pages/admin/*`
- `frontend/pages/app/agenda.php`
- `frontend/pages/app/processos.php`
- `frontend/pages/app/tarefas.php`

### Verdade objetiva
Isso é conflito de escopo. Se a regra atual é “não tem mais estagiário”, o sistema não pode continuar oferecendo esse perfil escondido. Isso vira dúvida para banca, bug para usuário e risco de permissão em produção.

### O que fazer
Remover totalmente o perfil `estagiario`, ou assumir oficialmente que ele existe. Minha recomendação: remover agora.

### Critério de pronto
- `users.tipo` deve ficar apenas com `cliente`, `advogado`, `admin`.
- `external_processes.owner_type` deve ficar apenas com `cliente`, `advogado` se ainda for usado para profissionais.
- Remover `dashboard-estagiario.php` e referências de navegação.
- Remover opção “Estagiário” do cadastro comum e Google.
- Remover seed demo de estagiário.
- Ajustar controllers para não aceitarem `estagiario`.
- Rodar grep final: `grep -R "estagiario" .` deve retornar zero ou apenas histórico/documentação explicando remoção.

---

## 2. Fortalecer CSRF nos formulários públicos

### Problema
Os formulários públicos HTML dependem do JavaScript (`frontend/assets/js/auth.js`) para buscar `/auth/csrf` e injetar `<input name="_csrf">`.

Formulários afetados:

- `frontend/login.html`
- `frontend/admin/login-admin.html`
- `frontend/recuperar-senha.html`

### Risco
Com JavaScript quebrado, lento, bloqueado ou erro de carregamento, login/cadastro/recuperação podem falhar com “CSRF token inválido”. Segurança não deve depender só de JS carregando corretamente.

### O que fazer
Transformar essas páginas públicas críticas em PHP ou criar um pequeno include servidor-side para inserir `csrf_input()` direto no HTML.

### Critério de pronto
- Login, cadastro, admin login e recuperação de senha devem funcionar mesmo com JavaScript desativado.
- O JS pode continuar melhorando UX, mas não pode ser pré-requisito para segurança básica.
- Todo formulário `method="post"` deve conter token CSRF renderizado pelo servidor.

---

## 3. Criar PWA de verdade antes de pensar em app nativo

### Problema
Não existe:

- `manifest.webmanifest`
- `service-worker.js` ou `sw.js`
- ícones PWA 192/512
- tela offline
- registro de service worker
- configuração de display standalone

### O que fazer
Implementar `JusTraduz Mobile PWA`.

### Arquivos sugeridos
- `frontend/manifest.webmanifest`
- `frontend/sw.js`
- `frontend/assets/img/icons/icon-192.png`
- `frontend/assets/img/icons/icon-512.png`
- `frontend/assets/js/pwa-register.js`
- incluir registro em páginas públicas e logadas.

### Regras do PWA
- Cachear apenas assets públicos: CSS, JS, logos, ícones e páginas públicas essenciais.
- Não cachear documentos, PDFs, anexos, respostas de IA, chat, dados pessoais ou páginas autenticadas sensíveis.
- Criar fallback offline simples: “Sem conexão. Reconecte para acessar documentos e atendimentos.”
- Usar `display: standalone`, `theme_color`, `background_color`, `name`, `short_name`, `icons`.

### Critério de pronto
- Chrome Android permite “Instalar app”.
- Ícone aparece na tela inicial.
- Ao abrir instalado, não parece uma aba normal do navegador.
- Login, dashboard cliente, upload e solicitar ajuda são utilizáveis em 390px de largura.
- Lighthouse PWA deve passar nos itens básicos.

---

## 4. Corrigir upload para produção real

### Problema
O upload atual valida extensão/MIME e salva com nome aleatório. Isso é bom para MVP, mas insuficiente para produção com documentos jurídicos.

Pontos críticos:

- Não há antimalware.
- Não há OCR para PDF/imagem escaneada.
- Não há fila assíncrona.
- `doc`/`docx` são permitidos no anexo do chat; `docx` pode aparecer como `application/zip`, o que aumenta o risco se a validação for superficial.
- O limite de 50 MB para documentos pode travar análise síncrona e piorar UX.

### O que fazer
Criar um `FileSecurityService` e centralizar validação de upload.

### Regras mínimas
- Validar extensão + MIME real + assinatura básica do arquivo.
- Para imagem: validar com `getimagesize()` e recusar imagem corrompida.
- Para PDF: validar header `%PDF` e limitar páginas/tamanho quando possível.
- Para DOCX: validar estrutura ZIP esperada (`[Content_Types].xml`, `word/document.xml`) e recusar ZIP genérico.
- Escanear com ClamAV ou serviço equivalente antes de liberar download/análise.
- Guardar status do arquivo: `uploaded`, `scanning`, `safe`, `infected`, `rejected`, `processing`, `processed`, `failed`.
- Nunca processar IA/OCR antes do status `safe`.

### Critério de pronto
- Arquivos maliciosos ou inválidos não entram no fluxo.
- Usuário vê status claro do arquivo.
- Download só funciona se o arquivo estiver autorizado e seguro.
- Logs registram motivo de recusa sem expor conteúdo sensível.

---

## 5. Tirar IA, OCR e DataJud do fluxo síncrono

### Problema
Hoje a análise por IA e a consulta DataJud são chamadas diretamente durante o request. Isso funciona em demo, mas é frágil em produção.

### Risco
- Timeout.
- Tela travada.
- Duplicidade de tentativas.
- Usuário reenviando formulário.
- Custo sem controle.
- Falha externa derrubando UX.

### O que fazer
Criar fila de jobs.

### Tabelas sugeridas
```sql
CREATE TABLE jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id INT NOT NULL,
  status ENUM('pending','running','done','failed','cancelled') NOT NULL DEFAULT 'pending',
  attempts INT NOT NULL DEFAULT 0,
  max_attempts INT NOT NULL DEFAULT 3,
  available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  error_message TEXT NULL,
  payload JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_jobs_status_available (status, available_at),
  INDEX idx_jobs_entity (entity_type, entity_id)
);
```

### Jobs mínimos
- `document_ai_analysis`
- `document_ocr`
- `datajud_cnj_sync`
- `email_send`

### Critério de pronto
- Upload retorna rápido.
- Tela mostra “processando”.
- Job tenta novamente em falha temporária.
- Falha final aparece para o usuário sem stack trace.
- Admin consegue ver fila/falhas.

---

## 6. Implementar LGPD operacional, não só texto bonito

### Problema
Existem termos, privacidade, consentimento em alguns fluxos e mensagens de cuidado. Isso ajuda, mas ainda não é LGPD operacional.

### O que falta
- Registro formal de consentimento por versão e finalidade.
- Solicitação de exclusão/exportação de dados.
- Política de retenção de documentos/anexos.
- Base legal registrada para cada tratamento sensível.
- Rotina de resposta a incidente.
- Minimização real de dados enviados à IA.

### Tabelas sugeridas
```sql
CREATE TABLE consent_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  purpose VARCHAR(120) NOT NULL,
  version VARCHAR(50) NOT NULL,
  accepted BOOLEAN NOT NULL DEFAULT TRUE,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_consent_user_purpose (user_id, purpose, version)
);

CREATE TABLE data_subject_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  request_type ENUM('export','delete','rectify','revoke_consent') NOT NULL,
  status ENUM('open','in_review','completed','rejected') NOT NULL DEFAULT 'open',
  admin_notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Critério de pronto
- Todo uso de IA em documento tem consentimento versionado.
- Toda consulta DataJud por CNJ tem consentimento versionado.
- Usuário consegue solicitar exportação/exclusão.
- Admin consegue tratar solicitação com log.
- Documentos e anexos têm retenção definida.

---

## 7. Melhorar política de senha e recuperação

### Problema
A senha mínima atual é de 6 caracteres. Isso é fraco para um sistema com documentos jurídicos.

### O que fazer
- Mínimo de 10 ou 12 caracteres.
- Bloquear senhas comuns.
- Exigir confirmação de senha atual em alterações sensíveis.
- Invalidar códigos antigos de recuperação ao emitir novo código, já há parte disso, mas precisa teste.
- Rate limit por IP + e-mail + usuário, não só por audit log.
- Opcional para 100%: 2FA por e-mail ou TOTP para admin.

### Critério de pronto
- Admin precisa de senha forte.
- Recuperação de senha tem limite real, expiração e log.
- Troca de senha encerra sessões antigas.
- Testes automatizados cobrem recuperação, erro de código, expiração e excesso de tentativas.

---

## 8. Criar validação real de CPF/CNPJ/CNJ

### Problema
O sistema valida CPF por tamanho e CNJ por quantidade de dígitos. Isso evita lixo óbvio, mas ainda aceita número matematicamente inválido.

### O que fazer
Criar `ValidationService` com:

- `isValidCpf()` com dígitos verificadores.
- `isValidCnpj()` se CNPJ entrar em cadastro futuro.
- `isValidCnj()` com validação oficial do número CNJ.
- `normalizePhoneBr()` para telefone.
- `normalizeOab()` para inscrição e UF.

### Critério de pronto
- CPF `111.111.111-11` deve ser recusado.
- CNJ com 20 dígitos, mas DV errado, deve ser recusado.
- Testes automatizados cobrindo casos válidos e inválidos.

---

## 9. Limpar o ZIP/repositório

### Problema
O ZIP enviado está muito pesado por motivos que não agregam produto.

Achados objetivos:

- O ZIP inclui a pasta `.git`, com aproximadamente 49 MB.
- Existe outro ZIP dentro do projeto: `justraduz_note_arquivos_corrigidos_apenas_com_erro_de_ficar_verde.zip`, com cerca de 5,8 MB.
- Algumas imagens de depoimentos têm tamanho absurdo para web:
  - `rafael-costa.jpg`: cerca de 15,5 MB.
  - `ana-paula-lima.jpg`: cerca de 14,7 MB.
  - `mariana-souza.jpg`: cerca de 9 MB.
- `chat-bot-logo.png` tem cerca de 1,5 MB.

### O que fazer
- Remover `.git` de entregáveis ZIP.
- Remover ZIPs de vídeo/rascunho de dentro do projeto.
- Otimizar imagens para WebP/AVIF.
- Reduzir depoimentos para no máximo 250 KB cada.
- Criar pasta `docs/archive/` se algo precisar ser guardado, mas não empacotar em produção.

### Critério de pronto
- ZIP final do projeto sem `.git`, sem arquivos de vídeo/rascunho e com imagens otimizadas.
- Entregável final muito menor e mais profissional.

---

# P1 — Necessário para produto comercial sério

## 10. Criar Composer, PHPUnit e documentação de extensões PHP

### Problema
Não há `composer.json`, `phpunit.xml` ou lista formal de extensões necessárias.

O código depende de funções/extensões como:

- `PDO` + `pdo_mysql`
- `curl`
- `mbstring`
- `openssl`
- `fileinfo`
- `json`
- `session`
- `iconv`

### O que fazer
Criar `composer.json` mínimo:

```json
{
  "name": "teamghcp/justraduz",
  "type": "project",
  "require": {
    "php": ">=8.2",
    "ext-pdo": "*",
    "ext-pdo_mysql": "*",
    "ext-curl": "*",
    "ext-mbstring": "*",
    "ext-openssl": "*",
    "ext-fileinfo": "*",
    "ext-json": "*"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.5"
  },
  "scripts": {
    "lint": "find backend frontend -name '*.php' -print0 | xargs -0 -n1 php -l",
    "test": "phpunit"
  }
}
```

### Critério de pronto
- `composer install` funciona.
- `composer lint` funciona.
- `composer test` funciona.
- README informa extensões obrigatórias no XAMPP.

---

## 11. Criar CI no GitHub Actions

### Problema
Hoje qualquer alteração pode quebrar rotas, SQL ou permissões sem aviso.

### O que fazer
Criar `.github/workflows/ci.yml` com:

- PHP 8.2/8.3.
- Instalação de extensões necessárias.
- `composer install`.
- `composer lint`.
- Importação do SQL sem demo em MySQL de teste.
- Testes automatizados.
- Checagem de rotas usadas pelo frontend.

### Critério de pronto
- Todo push roda CI.
- PR quebrado fica vermelho.
- SQL consolidado é importado em banco limpo no CI.

---

## 12. Aumentar muito os testes automatizados

### Problema
Existe apenas um teste manual/scriptado de guardrails de IA. Isso é pouco para um sistema com documentos, permissões e dados sensíveis.

### Testes mínimos

#### Auth
- Login cliente.
- Login admin.
- Login advogado pendente deve bloquear.
- Login advogado validado deve permitir.
- Cadastro com CPF inválido deve falhar.
- Cadastro com e-mail duplicado deve falhar.
- Recuperação de senha: código correto, errado, expirado e excesso de tentativas.

#### Documentos
- Cliente só vê documento próprio.
- Advogado só vê documento de caso aceito.
- Admin vê todos.
- Usuário não consegue baixar arquivo fora de `backend/storage/documents`.
- Upload inválido é recusado.

#### Casos/chat
- Cliente cria caso.
- Advogado aceita caso aberto.
- Advogado não aceita caso já aceito.
- Usuário fora do caso não lê chat/anexo.
- Caso finalizado não aceita mensagem comum.

#### Agenda
- Advogado cria horário futuro.
- Bloqueia horário sobreposto.
- Cliente agenda horário livre.
- Dois clientes não reservam mesmo slot.

#### Admin
- Admin altera status de usuário.
- Admin não inativa a própria conta.
- Admin valida/rejeita OAB com justificativa.
- Usuário comum não acessa rotas admin.

#### DataJud/IA
- CNJ inválido é recusado.
- Sem consentimento LGPD, DataJud não consulta.
- Gemini sem chave cai em fallback seguro.
- IA não processa sem consentimento.

### Critério de pronto
- Cobertura real dos fluxos críticos.
- Fixtures resetáveis.
- Banco de teste isolado.
- CI executando tudo.

---

## 13. Transformar SQL consolidado em migrações seguras

### Problema
Os SQLs atuais começam com `DROP DATABASE IF EXISTS justraduz`. Isso é aceitável para instalação limpa, mas perigoso para manutenção real.

### O que fazer
Manter os instaladores, mas criar pasta de migrações reais:

```text
database/migrations/
  2026_06_14_001_create_consent_records.sql
  2026_06_14_002_create_jobs.sql
  2026_06_14_003_remove_estagiario.sql
```

### Critério de pronto
- Existe instalador limpo para ambiente novo.
- Existe migração incremental para ambiente existente.
- README explica quando usar cada um.
- Nenhum comando destrutivo roda sem aviso explícito.

---

## 14. Endurecer CSP e headers

### Problema
A CSP atual ainda permite `unsafe-inline`, `unsafe-eval` e vários domínios externos. Entendo o motivo: compatibilidade com VLibras, scripts e código legado. Mas para produção, isso precisa ser reduzido.

### O que fazer
- Remover scripts inline progressivamente.
- Usar arquivos JS externos.
- Usar nonce para scripts inevitáveis.
- Remover `unsafe-eval` se não for indispensável.
- Separar CSP pública, app logado e admin.
- Testar VLibras sem afrouxar demais o resto do app.

### Critério de pronto
- CSP não quebra a interface.
- Admin e área logada têm política mais restrita.
- Relatórios de violação podem ser coletados em produção.

---

## 15. Criar observabilidade e health check

### Problema
Se Gemini, SMTP, MySQL, DataJud ou storage falharem, hoje a operação depende de perceber manualmente.

### O que fazer
Criar rota admin ou CLI:

- Banco conectado.
- Tabelas esperadas existem.
- Storage gravável.
- SMTP configurado.
- Gemini configurado e aprovado para dados reais.
- DataJud configurado.
- Fila de jobs com pendências/falhas.
- Espaço em disco.

### Arquivos sugeridos
- `backend/app/services/HealthCheckService.php`
- `frontend/pages/admin/saude-sistema.php`

### Critério de pronto
- Admin vê painel de saúde.
- Erros críticos aparecem sem expor segredo.
- Logs têm correlação mínima por ação/usuário.

---

## 16. Backup e restore testados

### Problema
Sistema com documento jurídico sem backup testado é roleta.

### O que fazer
- Script de backup do banco.
- Script de backup do storage.
- Criptografia dos backups.
- Retenção: diário 7 dias, semanal 4 semanas, mensal 6 meses, ou política definida.
- Teste de restore em ambiente separado.

### Critério de pronto
- Existe `scripts/backup.ps1` ou `.bat` para Windows/XAMPP local.
- Existe documentação de restore.
- Um restore foi testado e documentado.

---

## 17. Melhorar UX de erro mantendo dados preenchidos

### Problema
Vários fluxos redirecionam com `?erro=...`. Isso é simples, mas perde dados preenchidos e frustra o usuário.

### Fluxos prioritários
- Cadastro.
- Solicitar ajuda.
- Upload/análise.
- Perfil.
- Agenda.
- Admin validação OAB.

### O que fazer
- Para formulários críticos, salvar old input em sessão por uma requisição ou processar via AJAX com JSON.
- Mostrar erro no campo específico, não só no topo.
- Manter usuário na mesma tela.

### Critério de pronto
- Se o usuário erra um campo, os outros continuam preenchidos.
- Campo inválido recebe foco e mensagem clara.
- Nenhum erro genérico quando dá para apontar o campo exato.

---

## 18. Melhorar mobile de verdade

### Problema
Há responsividade, mas ainda falta teste de produto mobile.

### Checklist obrigatório
Testar em:

- 360x800
- 390x844
- 412x915
- 768x1024

Fluxos:

- Login.
- Cadastro.
- Dashboard cliente.
- Upload de PDF/imagem pelo celular.
- Solicitar ajuda.
- Chat com anexo.
- Agenda.
- Visualizar documento.
- Admin básico em tablet.

### Critério de pronto
- Nenhuma tabela estoura a tela sem rolagem adequada.
- Botões têm área clicável confortável.
- Sidebar mobile abre/fecha sem cobrir ações importantes.
- Upload pelo celular aceita câmera/galeria quando aplicável.

---

# P2 — Para escalar como SaaS

## 19. Multiempresa/escritórios

### Problema
O sistema ainda parece operar como uma plataforma global única. Para SaaS jurídico, escritórios precisam de isolamento.

### O que fazer
Criar:

- `organizations`
- `organization_members`
- `roles`
- `permissions`
- `organization_id` em casos, documentos, agenda e usuários quando fizer sentido.

### Critério de pronto
- Escritório A não vê nada do escritório B.
- Admin global é diferente de admin do escritório.
- Convites e membros funcionam.

---

## 20. RBAC granular

### Problema
Perfis fixos (`cliente`, `advogado`, `admin`) são simples, mas rígidos.

### O que fazer
Criar permissões por recurso:

- `documents.view`
- `documents.delete`
- `cases.accept`
- `cases.update_status`
- `audit.view`
- `oab.validate`
- `users.manage`
- `billing.manage`

### Critério de pronto
- Permissões não dependem só de `tipo`.
- Rotas validam permissão centralizada.
- Testes cobrem permissão negada.

---

## 21. Billing e planos

### Problema
Sem cobrança, não é SaaS comercial completo.

### O que fazer
Criar planos:

- Free/demo limitado.
- Cliente individual.
- Profissional.
- Escritório.
- Admin interno.

Controlar limites:

- Quantidade de documentos.
- Tamanho total de storage.
- Análises IA/mês.
- Consultas DataJud/mês.
- Profissionais por organização.

### Critério de pronto
- Usuário inadimplente fica limitado sem apagar dados.
- Admin vê consumo.
- IA/API externa tem limite de custo por plano.

---

## 22. Relatórios comerciais e operacionais

### Relatórios úteis
- Documentos enviados por período.
- Casos abertos/finalizados.
- Tempo médio até aceite do advogado.
- Profissionais mais ativos.
- Uso de IA por usuário/plano.
- Falhas de IA/DataJud/SMTP.
- Auditoria exportável.

### Critério de pronto
- Admin exporta CSV.
- Filtros por período.
- Indicadores batem com o banco.

---

# P3 — Polimento final

## 23. Padronizar copy jurídica

### Problema
O sistema precisa evitar parecer que entrega parecer jurídico definitivo.

### Regras
- Trocar promessas fortes por linguagem informativa.
- Reforçar que IA é apoio, não decisão jurídica.
- Não prometer consulta por CPF se o sistema não faz isso.
- Evitar “garantia”, “parecer final”, “solução jurídica automática”.

### Critério de pronto
- Landing page, IA, termos, dashboards e e-mails usam linguagem consistente.

---

## 24. Manual operacional do admin

### O que documentar
- Como validar/rejeitar OAB.
- Como lidar com denúncia/incidente.
- Como restaurar backup.
- Como consultar auditoria.
- Como bloquear usuário.
- Como responder solicitação LGPD.
- Como verificar falha de e-mail/IA/DataJud.

### Critério de pronto
- Um admin novo consegue operar o sistema sem perguntar ao dev.

---

## 25. Melhorar README final para banca e produção

### O que incluir
- Requisitos.
- Instalação local.
- Configuração XAMPP.
- Porta correta do MySQL.
- Como importar banco com/sem demo.
- Contas demo.
- Como configurar Google OAuth.
- Como configurar SMTP.
- Como configurar Gemini.
- Como configurar DataJud.
- Como instalar PWA.
- Como rodar testes.
- Limitações conhecidas.

---

# Ordem de execução recomendada

## Sprint 1 — Limpeza e coerência de escopo

1. Remover `estagiario` ou assumir oficialmente esse perfil.
2. Limpar ZIP/repositório: `.git`, ZIP interno, imagens gigantes.
3. Corrigir CSRF servidor-side em login/cadastro/admin/recuperação.
4. Criar `composer.json`, documentação de extensões e `composer lint`.
5. Adicionar validação real de CPF/CNJ.

## Sprint 2 — Produto mobile e UX

6. Implementar PWA básico.
7. Melhorar responsividade dos fluxos principais.
8. Preservar dados nos formulários após erro.
9. Ajustar upload pelo celular.
10. Criar estados de loading/empty/error padronizados.

## Sprint 3 — Segurança e LGPD

11. Criar `FileSecurityService`.
12. Adicionar antimalware/scanner ou ponto de integração.
13. Criar `consent_records` e `data_subject_requests`.
14. Melhorar política de senha.
15. Endurecer CSP progressivamente.

## Sprint 4 — Operação real

16. Criar jobs/fila para IA, OCR, DataJud e e-mail.
17. Criar health check admin.
18. Criar backup/restore.
19. Criar logs operacionais e exportação de auditoria.
20. Criar testes automatizados e CI.

## Sprint 5 — SaaS

21. Billing e planos.
22. Quotas de IA/storage/DataJud.
23. Multiempresa.
24. RBAC granular.
25. Relatórios comerciais.

---

# Prompt direto para mandar ao Codex

Use este prompt se quiser aplicar as melhorias por etapas sem bagunçar o projeto.

```md
Você está trabalhando no projeto JusTraduz, em PHP, MySQL, HTML, CSS e JavaScript puro. Não use React, Tailwind, shadcn, Laravel ou framework novo. Preserve a estrutura atual do projeto.

Objetivo: transformar o JusTraduz em um produto mais próximo de produção, corrigindo segurança, escopo, mobile/PWA, LGPD e testes.

Tarefas obrigatórias desta rodada:

1. Remover completamente o perfil `estagiario` do sistema, mantendo apenas `cliente`, `advogado` e `admin`.
   - Remover opções de cadastro comum e cadastro Google.
   - Remover dashboard, navegação, seed demo e regras de agenda/processos/tarefas relacionadas a estagiário.
   - Ajustar SQL com/sem demo.
   - Ajustar controllers e páginas admin.
   - Ao final, `grep -R "estagiario" .` não deve encontrar uso ativo do perfil.

2. Corrigir CSRF nos formulários públicos.
   - Login, cadastro, admin login e recuperação de senha não podem depender de JavaScript para ter `_csrf`.
   - Converter essas páginas para PHP ou criar renderização server-side segura do token.
   - Manter compatibilidade com o JS existente.

3. Criar PWA básico.
   - Adicionar `frontend/manifest.webmanifest`.
   - Adicionar `frontend/sw.js`.
   - Adicionar ícones 192x192 e 512x512.
   - Adicionar registro do service worker.
   - Cachear somente assets públicos seguros.
   - Não cachear documentos, anexos, chat, dados pessoais, IA nem páginas autenticadas sensíveis.

4. Criar `composer.json` com extensões obrigatórias e scripts `lint` e `test`.
   - Exigir PHP >= 8.2.
   - Declarar `ext-pdo`, `ext-pdo_mysql`, `ext-curl`, `ext-mbstring`, `ext-openssl`, `ext-fileinfo`, `ext-json`.

5. Criar `ValidationService`.
   - Validar CPF com dígitos verificadores.
   - Validar CNJ com algoritmo correto, não só tamanho.
   - Usar esse serviço em cadastro, perfil e DataJud.

6. Criar testes mínimos para autenticação, permissões, documentos, casos e validações.
   - Não precisa cobrir tudo de uma vez, mas precisa deixar a base de testes organizada.

Regras:
- Não quebrar rotas existentes sem criar wrapper/redirect compatível.
- Não remover segurança existente.
- Não expor dados sensíveis em logs.
- Não alterar stack.
- Não usar consulta por CPF em DataJud.
- Não prometer que IA substitui advogado.
- Manter visual profissional atual.

Critério final:
- `php -l` deve passar em todos os arquivos PHP.
- O SQL sem demo deve importar em banco limpo.
- Login/cadastro funcionam sem JavaScript.
- PWA é instalável no Chrome Android.
- O sistema não possui mais fluxo ativo de estagiário.
```

---

# O que não vender como pronto ainda

Não venda como “100% comercial” enquanto faltar:

- PWA instalável.
- Testes automatizados de permissão.
- Antimalware no upload.
- LGPD operacional com consentimento versionado e solicitação de exclusão/exportação.
- Backup/restore testado.
- Health check.
- Fila para IA/OCR/DataJud.
- Billing/planos se for SaaS.
- Multiempresa se for vender para escritórios.
- Revisão jurídica real dos termos.

A verdade: o projeto está com cara de produto, mas ainda não tem sustentação operacional de produto. A prioridade agora não é adicionar mais tela. É parar vazamento de escopo, endurecer segurança, deixar mobile instalável, criar testes e preparar operação.
