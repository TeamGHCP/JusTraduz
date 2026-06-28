# O que falta agora

Data da revisao: 28/06/2026

Esta e a lista unica de pendencias reais do JusTraduz. Itens ja implementados ou validados localmente foram removidos.

## Apresentacao escolar da SA

- Rodar o roteiro completo de QA manual em `docs/ROTEIRO_QA_MANUAL.md`.
- Testar visualmente em desktop, notebook, celular e projetor.
- Conferir login e fluxo de Cliente, Advogado e Admin.
- Ensaiar Max Cliente, Max Advogado e Escritorio com dois advogados.
- Ensaiar pagamento, convite por e-mail, cadastro/login e aceite no Escritorio.
- Ensaiar fluxo principal: upload, analise/explicacao, solicitacao, chat, agenda, admin e LGPD.
- Preparar plano B para falha de internet, Gemini, DataJud, OAB, Asaas ou SMTP.

## Producao ou homologacao real

- Configurar `backend/.env` real com `APP_DEBUG=false`, URLs HTTPS reais e sem placeholders.
- Instalar certificado TLS e aplicar o modelo `docs/apache-justraduz-production.conf`.
- Configurar SMTP real e validar entregabilidade.
- Configurar Gemini, DataJud, Google OAuth, Asaas e demais integracoes externas somente quando forem usadas.
- Configurar monitoramento externo para `/backend/public/index.php?rota=/health`.
- Definir `BACKUP_ENCRYPTION_PASSWORD`.
- Executar backup e restore em ambiente limpo, incluindo banco e storage.
- Definir storage privado fora do webroot para documentos e anexos.
- Instalar/configurar ClamAV se houver uploads reais de usuarios.
- Instalar/configurar Tesseract se `OCR_ENABLED=true`.
- Agendar `scripts/run-jobs.php` se `ASYNC_JOBS_ENABLED=true`.
- Validar PWA em HTTPS real, incluindo instalacao em Android/iPhone.

## Banco de dados

- Usar somente `database/justraduz_completo_sem_demo.sql` e `database/justraduz_completo_com_demo.sql` em ambientes limpos.
- Para base real ja existente, gerar script incremental especifico a partir da diferenca entre o banco alvo e os instaladores consolidados.
- Testar rollback do script incremental em copia do banco antes de producao.

## Planos e cobranca

- Concluir a compra manual no sandbox Asaas para Max Cliente, Max Advogado e Escritorio.
- Confirmar webhook, ativacao, cancelamento e limites de cada plano.
- Validar a entregabilidade dos convites com dominio proprio antes da producao.

## Acessibilidade e revisao formal

- Completar `docs/MATRIZ_WCAG_AA.md` com evidencias reais.
- Rodar navegacao por teclado em todas as telas principais.
- Validar com leitor de tela, como NVDA ou equivalente.
- Medir contraste com ferramenta externa.
- Revisar textos juridicos com profissional habilitado e preencher `docs/REGISTRO_REVISAO_JURIDICA.md`.

## Polimento manual

- Revisar empty/loading/error states em todas as telas.
- Executar a rotina do manual operacional interno em `docs/MANUAL_OPERACIONAL_INTERNO.md` no ambiente alvo e registrar evidencias reais.
