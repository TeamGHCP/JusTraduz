# Manual operacional interno

Este manual cobre a rotina minima de admin/suporte do JusTraduz. Ele nao substitui revisao juridica, monitoramento externo ou validacao em ambiente real.

## Rotina diaria

1. Abrir o painel admin e conferir usuarios, OAB pendente, solicitacoes abertas e documentos recentes.
2. Rodar o healthcheck operacional:

```powershell
php scripts\operational-health-report.php --output=storage-private\reports\saude-operacional.md
```

3. Verificar se ha SLA vencido, fila de jobs pendente, e-mails falhos ou erros de IA.
4. Conferir se uploads e anexos continuam gravando no storage privado configurado.
5. Registrar qualquer incidente com data, impacto, causa provavel e acao tomada.

## Atendimento de usuarios

- Nunca solicitar senha do usuario.
- Para problemas de acesso, orientar recuperacao de senha ou reset pelo perfil.
- Para profissional com OAB pendente, revisar documento, UF, numero informado e historico no admin.
- Para documentos, validar apenas metadados e permissao de acesso. Conteudo juridico deve ser tratado por profissional habilitado.
- Para LGPD, usar exportacao/encerramento pelo perfil do titular sempre que possivel.

## Operacao de solicitacoes

- Casos sem advogado devem ser priorizados antes dos casos ja atribuidos.
- Casos de prioridade alta ou urgente devem ser acompanhados pelo relatorio de SLA.
- Atualizacoes administrativas precisam ter justificativa clara quando afetarem acesso profissional ou andamento do caso.
- Chat e anexos devem ser usados apenas dentro do caso correspondente.

## Limpeza de storage

1. Gerar o relatorio:

```powershell
php scripts\check-orphan-storage.php > storage-private\reports\orfaos.txt
```

2. Revisar manualmente a lista e confirmar que nenhum arquivo pertence a caso/documento recuperavel.
3. Executar a limpeza controlada:

```powershell
php scripts\cleanup-orphan-storage.php --delete --confirm-reviewed-report --report=storage-private\reports\orfaos.txt
```

## Backup e restore

- Antes de deploy ou mudanca de banco, gerar backup com `scripts\backup-database.ps1`.
- Validar o arquivo com `php scripts\check-backup-file.php`.
- Testar restore em ambiente limpo antes de usar em producao.
- Incluir `DOCUMENT_STORAGE_PATH` e `ATTACHMENT_STORAGE_PATH` na rotina de copia.

## Plano B para apresentacao

- Manter banco demo importavel.
- Manter um PDF/DOCX local para upload.
- Nao depender de Cloudflare AI, DataJud ou SMTP no fluxo principal.
- Se uma API externa falhar, demonstrar os dados demo e explicar que integracoes externas ficam desativaveis por ambiente.
