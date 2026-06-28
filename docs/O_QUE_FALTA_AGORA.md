# O que falta agora

Data da revisão: 27/06/2026

Esta é a lista única de pendências reais do JusTraduz. Itens já implementados foram removidos.

## Apresentação escolar da SA

- Rodar o roteiro completo de QA manual em `docs/ROTEIRO_QA_MANUAL.md`.
- Testar visualmente em desktop, notebook, celular e projetor.
- Conferir login e fluxo de Cliente, Advogado e Admin.
- Ensaiar Max Cliente, Max Advogado e Escritório com dois advogados.
- Ensaiar pagamento, convite por e-mail, cadastro/login e aceite no Escritório.
- Ensaiar fluxo principal: upload, análise/explicação, solicitação, chat, agenda, admin e LGPD.
- Preparar plano B para falha de internet, Gemini, DataJud ou SMTP.

## Produção ou homologação real

- Configurar `backend/.env` real com `APP_DEBUG=false`, URLs HTTPS reais e sem placeholders.
- Instalar certificado TLS e aplicar o modelo `docs/apache-justraduz-production.conf`.
- Configurar SMTP real e validar entregabilidade.
- Configurar Gemini, DataJud, Google OAuth e demais integrações externas somente quando forem usadas.
- Configurar monitoramento externo para `/backend/public/index.php?rota=/health`.
- Definir `BACKUP_ENCRYPTION_PASSWORD`.
- Executar backup e restore em ambiente limpo.
- Definir storage privado fora do webroot para documentos e anexos.
- Instalar/configurar ClamAV se houver uploads reais de usuários.
- Instalar/configurar Tesseract se `OCR_ENABLED=true`.
- Agendar `scripts/run-jobs.php` se `ASYNC_JOBS_ENABLED=true`.
- Validar PWA em HTTPS real, incluindo instalação em Android/iPhone.

## Banco de dados

- Criar script incremental específico para qualquer base real existente a partir dos instaladores consolidados.
- Testar rollback do script incremental em cópia do banco antes de produção.

## Planos e cobrança

- Concluir a compra manual no sandbox Asaas para Max Cliente, Max Advogado e Escritório.
- Confirmar webhook, ativação, cancelamento e limites de cada plano.
- Validar a entregabilidade dos convites com domínio próprio antes da produção.

## Acessibilidade e revisão formal

- Completar `docs/MATRIZ_WCAG_AA.md` com evidências reais.
- Rodar navegação por teclado em todas as telas principais.
- Validar com leitor de tela, como NVDA ou equivalente.
- Medir contraste com ferramenta externa.
- Revisar textos jurídicos com profissional habilitado e preencher `docs/REGISTRO_REVISAO_JURIDICA.md`.

## Polimento

- Revisar empty/loading/error states em todas as telas.
- Executar a rotina do manual operacional interno em `docs/MANUAL_OPERACIONAL_INTERNO.md` e registrar evidências reais.
- Rodar periodicamente `scripts/operational-health-report.php`.
- Quando houver órfãos confirmados, revisar o relatório de `scripts/check-orphan-storage.php` e limpar com `scripts/cleanup-orphan-storage.php`.
