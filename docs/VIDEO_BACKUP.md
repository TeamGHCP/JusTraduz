# Vídeo backup da demo

## Objetivo

Ter um plano B caso a internet, o projetor, o banco, a IA ou o notebook falhem no dia da apresentação.

## Duração ideal

Entre 3 e 5 minutos.

## Estrutura do vídeo

1. Landing e problema.
2. Login cliente.
3. Documento analisado.
4. Solicitação/chat.
5. Dashboard advogado.
6. Admin com métricas e auditoria.
7. Fechamento com segurança/LGPD.

## Preparação antes de gravar

- [ ] Rodar `database/seed_demo.sql`.
- [ ] Abrir landing em `http://127.0.0.1:8080/frontend/index.html`.
- [ ] Confirmar documento demo abrindo.
- [ ] Confirmar admin abrindo.
- [ ] Ocultar abas ou arquivos com segredos.
- [ ] Fechar notificações do sistema operacional.
- [ ] Aumentar zoom do navegador para leitura no projetor.

## Narração curta

```text
Este é o JusTraduz, uma plataforma para transformar documentos jurídicos em linguagem simples. O cliente envia um documento, autoriza a análise por IA e recebe um resumo com explicação acessível. Quando precisa de ajuda, abre uma solicitação e conversa com advogado validado. O advogado acompanha casos, tarefas e agenda. Já o administrador monitora usuários, documentos, validação OAB, solicitações críticas e auditoria. O sistema usa consentimento para IA, controle por perfil e bloqueio de acesso direto aos documentos.
```

## Arquivos a levar para a banca

- Vídeo em `.mp4`.
- Backup do banco exportado.
- Cópia do projeto.
- PDF ou print das credenciais demo.
- Documento demo local.

## Plano B em caso de falha

- Se o banco falhar: mostrar o vídeo e explicar o fluxo pelo `docs/BANCA.md`.
- Se a IA falhar: usar dados já seedados em `ai_results`.
- Se o login falhar: mostrar prints/vídeo.
- Se o projetor cortar resolução: usar zoom do navegador e focar no admin/documento.

