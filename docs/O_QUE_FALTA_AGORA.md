# O que falta agora

Data da revisão: 23/06/2026

Esta é a lista única de pendências reais do JusTraduz. Itens já implementados foram removidos.

## Apresentação escolar da SA

- Rodar o roteiro completo de QA manual em `docs/ROTEIRO_QA_MANUAL.md`.
- Testar visualmente em desktop, notebook, celular e projetor.
- Conferir login e fluxo dos quatro perfis: Cliente, Advogado, Estagiário e Admin.
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

- Revisar antes de aplicar a migration `database/migrations/2026_06_23_cases_sla.sql`.
- Decidir se SLA, escalonamento e responsável operacional serão persistidos no banco ou mantidos apenas calculados em tela.
- Criar migration incremental específica para qualquer base real existente.
- Testar rollback de migration em cópia do banco antes de produção.

## Produto ainda futuro

- Planos e cobrança.
- Multiempresa/escritórios.
- Tela administrativa para editar permissões dinamicamente.
- Relatórios gerenciais avançados e exportáveis.
- Escalonamento operacional com notificações automáticas e regras anti-spam.
- API pública documentada para integrações externas, caso o projeto vire produto real.

## Acessibilidade e revisão formal

- Completar `docs/MATRIZ_WCAG_AA.md` com evidências reais.
- Rodar navegação por teclado em todas as telas principais.
- Validar com leitor de tela, como NVDA ou equivalente.
- Medir contraste com ferramenta externa.
- Revisar textos jurídicos com profissional habilitado e preencher `docs/REGISTRO_REVISAO_JURIDICA.md`.

## Polimento

- Revisar empty/loading/error states em todas as telas.
- Criar manual operacional interno para admin/suporte.
- Automatizar limpeza de arquivos órfãos somente depois de revisar o relatório de `scripts/check-orphan-storage.php`.
- Criar relatório periódico de saúde operacional.
