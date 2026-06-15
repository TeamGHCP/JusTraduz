# O que falta agora

Data da revisao: 14/06/2026

Depois da entrega P0/P1, o repositorio ja contem os controles obrigatorios e a base operacional para producao. O que ainda falta se divide em configuracao real de ambiente, validacoes externas e evolucao de produto.

## Antes de colocar em producao real

- Configurar `backend/.env` real com `APP_URL` e `HEALTHCHECK_URL` em HTTPS, sem placeholders.
- Instalar certificado TLS no servidor e aplicar o modelo `docs/apache-justraduz-production.conf`.
- Definir `BACKUP_ENCRYPTION_PASSWORD` e rodar um restore testado em ambiente separado.
- Configurar SMTP real ou provedor transacional e validar entregabilidade.
- Configurar monitoramento externo para `/backend/public/index.php?rota=/health`.
- Agendar `scripts/run-jobs.php` para processar a fila quando `ASYNC_JOBS_ENABLED=true`.
- Definir storage privado fora do webroot em `DOCUMENT_STORAGE_PATH` e `ATTACHMENT_STORAGE_PATH`.
- Instalar/configurar ClamAV via `CLAMAV_BINARY` se a producao exigir scanner externo alem da heuristica interna.
- Instalar/configurar Tesseract se `OCR_ENABLED=true`.
- Preencher e assinar `docs/REGISTRO_REVISAO_JURIDICA.md` com profissional juridico.
- Rodar em producao/homologacao:

```powershell
C:\xampp\php\php.exe backend\tests\run.php
C:\xampp\php\php.exe scripts\check-references.php
C:\xampp\php\php.exe scripts\check-production-readiness.php --env=backend/.env
```

## Produto P2 implementado nesta branch

- Planos e cobranca: planos exclusivos para clientes, assinaturas, eventos de pagamento manual e bloqueio por inadimplencia.
- Multiempresa/escritorios: organizacoes, membros, convites e `organization_id` nas entidades principais.
- RBAC granular por recurso: permissoes customizadas por usuario e defaults por perfil.
- Relatorios gerenciais: tela admin de status, prioridade, responsavel, SLA e receita registrada.
- SLA, prioridade operacional e escalonamento: `sla_due_at`, `sla_status` e calculo por prioridade.
- API versionada `/api/v1`: endpoints `me`, `cases` e `reports` com envelope versionado.
- Acessibilidade: novas telas usam labels, componentes existentes e badges textuais.

Ainda faltam para producao comercial real: conectar provedor de pagamento com webhook assinado, homologar nota/recibo conforme modelo fiscal escolhido e executar matriz formal WCAG AA.

## Polimento P3 ainda nao implementado

- Teste visual em mobile/tablet/projetor com matriz real.
- Empty/loading/error states revisados em todas as telas.
- Revisao final de copy juridica por profissional.
- Manual operacional interno para admin/suporte.
- Scripts adicionais de manutencao, como limpeza de arquivos orfaos e relatorio de saude periodico.

## Itens removidos nesta limpeza

- `node_modules/`: dependencia local gerada, sem `package.json` do projeto e sem uso nos testes atuais.
- `justraduz_note_arquivos_corrigidos_apenas_com_erro_de_ficar_verde.zip`: arquivo ZIP solto na raiz, fora do fluxo do app.
- imagens antigas sem referencia atual: mockup antigo, logo antigo com slogan, logo escuro nao usado, imagem antiga de ideia de index e fotos da secao de depoimentos removida do HTML.
