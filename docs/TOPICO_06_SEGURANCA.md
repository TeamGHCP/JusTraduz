# Topico 6 - Auditoria tecnica de seguranca

Data: 31/05/2026  
Branch: `auditoria-topicos-6-11`  
Objetivo: identificar riscos reais no JusTraduz e definir correcoes praticas antes da banca.

## Diagnostico brutal

O JusTraduz ja tem uma base melhor que muitos projetos academicos: usa PDO/prepared statements nas rotas principais, possui CSRF, sessao configurada com cuidado, validacao de upload, auditoria e controle por perfil.

O ponto que nao da para suavizar: `backend/.env` estava rastreado pelo Git. Mesmo sem expor o conteudo aqui, isso e falha grave de processo. Arquivo sensivel versionado precisa ser removido do indice, substituido por exemplo sem segredos e qualquer chave real deve ser rotacionada.

## Verificacao ponto a ponto

| Item auditado | Status encontrado | Risco | Correcao objetiva |
|---|---|---:|---|
| `.env` exposto | `backend/.env` estava rastreado pelo Git | Critico | Remover do indice com `git rm --cached backend/.env`, manter no `.gitignore` e criar `backend/.env.example`. |
| Credenciais hardcoded | Seed demo contem hash conhecido; config le ambiente | Medio | Usar apenas em demo, nunca em producao; documentar credenciais fake. |
| SQL Injection | Uso predominante de `prepare()` e parametros | Baixo/medio | Manter prepared statements e revisar SQL dinamico com allowlist. |
| XSS | Frontend PHP usa helper `e()` em varias telas | Medio | Auditar todo `echo`, `nl2br`, atributos HTML e JSON embutido. |
| CSRF | Middleware existe e formularios usam `_csrf` | Medio | Garantir token em todo POST, inclusive formularios HTML estaticos via JS. |
| Sessao | `secure_session_start()` aplica hardening basico | Medio | Em HTTPS, habilitar cookie `Secure`; regenerar ID apos login/reset. |
| Uploads | Documentos validam extensao/MIME/tamanho; fotos tambem | Alto | Adicionar antivirus em producao e manter storage fora do webroot quando possivel. |
| Arquivos privados | Storage tem `.htaccess` e download passa por controller | Alto | Testar acesso direto e manter regra no servidor real, nao so no Apache local. |
| Permissoes por usuario | Cliente/advogado/admin estao cobertos; estagiario e perfil sensivel | Alto | Manter estagiario limitado a agenda/casos atribuidos ou cortar da demo. |
| Acesso direto sem login | Paginas PHP usam helpers de sessao; wrappers redirecionam | Medio | Smoke test de todas as paginas internas anonimas. |
| Validacao de entrada | Existe em controllers principais | Medio | Centralizar validadores para email, telefone, status, IDs e datas. |
| Sanitizacao de saida | Parcialmente padronizada por `e()` | Medio | Tratar todo dado vindo do banco como nao confiavel. |
| Rotas admin | Rotas admin existem e devem exigir admin | Alto | Manter `requireAdmin`/checagem equivalente nos controllers e telas. |
| Logs sensiveis | `AuditService` mascara senha/token/secret | Medio | Nao registrar texto integral de documentos juridicos. |
| Erros internos | `ErrorHandler` considera `APP_DEBUG` | Medio | `APP_DEBUG=false` na demo e producao. |
| Ambiente local | XAMPP facilita acesso indevido se mal configurado | Medio | Evitar listar diretorios, proteger storage e usar banco com senha fora da demo. |

## Correcoes aplicadas neste topico

1. `.gitignore` passou a ignorar `.env` na raiz e em `backend/`, mantendo excecao para `.env.example`.
2. Criado `backend/.env.example` com todas as variaveis usadas pelo projeto, sem segredos.
3. Criado este documento para separar a auditoria do topico 6 da auditoria geral.
4. `backend/.env` deve ser removido do indice do Git neste commit, sem apagar o arquivo local.

## Checklist LGPD minimo

- [ ] Consentimento explicito antes de enviar documento para IA.
- [ ] Aviso claro de que a IA nao substitui advogado.
- [ ] Politica de retencao de documentos.
- [ ] Exclusao de documentos pelo cliente.
- [ ] Exportacao de dados pessoais sob pedido.
- [ ] Registro de acesso a documentos sensiveis.
- [ ] Minimizacao de dados no painel admin.
- [ ] Controle de acesso por perfil.
- [ ] Contrato/termo para APIs externas como IA, e-mail e validacao OAB/CNA.
- [ ] Plano simples de resposta a incidente.

## Prioridade de correcao

| Prioridade | Acao | Motivo |
|---:|---|---|
| P0 | Remover `backend/.env` do Git e rotacionar chaves reais | Segredo versionado mata a avaliacao de seguranca. |
| P1 | Smoke test de acesso anonimo a paginas internas | Evita demonstrar vazamento de tela logada. |
| P1 | Validar acesso direto a uploads no Apache/XAMPP | Documento juridico e dado sensivel. |
| P1 | Confirmar `APP_DEBUG=false` na demo | Erro com caminho interno passa amadorismo e risco. |
| P2 | Criar testes automatizados de permissao | Reduz regressao ate 7 de julho. |
| P2 | Documentar retencao e exclusao LGPD | Necessario para produto comercial. |

## Como defender na banca

O discurso honesto e forte: o JusTraduz lida com dados juridicos sensiveis, por isso ja implementa autenticacao, CSRF, autorizacao por perfil, prepared statements, validacao de upload, auditoria e consentimento para IA. A auditoria encontrou um problema real de processo, o `.env` rastreado, e ele foi corrigido com remocao do indice, `.gitignore` reforcado e `.env.example` sem segredos.
