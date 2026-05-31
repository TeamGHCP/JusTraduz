# Respostas técnicas para banca

## Por que usar PHP puro?

Porque o objetivo era entregar um produto completo, simples de rodar em XAMPP e fácil de demonstrar. PHP puro reduz dependências, evita etapa de build e permite focar em regras de negócio, segurança e fluxo de atendimento.

## Por que não separar API e frontend?

Para o escopo acadêmico, o monólito é mais simples e confiável. A separação faria sentido em uma fase de escala, com aplicativo móvel, múltiplos clientes ou equipe maior.

## Como a autorização funciona?

As páginas usam `require_login` e `require_role`. Os controllers também verificam sessão, perfil e propriedade do recurso. Assim, mesmo que alguém chame uma rota diretamente, a regra de acesso ainda é aplicada.

## Como documentos são protegidos?

Os arquivos ficam em `backend/storage/documents`. O roteador local bloqueia acesso direto a essa pasta. A visualização e o download passam pelo `DocumentController`, que valida se o usuário pode acessar o documento.

## O que acontece se a IA falhar?

O documento continua salvo. A análise pode ficar pendente e ser gerada depois. Erros da IA são registrados em auditoria, e a interface mostra estado pendente em vez de quebrar o fluxo.

## Como evitar que a IA invente informação?

O prompt orienta a IA a não inventar dados e a declarar quando houver trecho ilegível. A interface também deixa claro que a análise é informativa e não substitui orientação profissional.

## Como funciona a validação OAB/CNA?

No cadastro de advogado ou estagiário, o sistema tenta consultar a inscrição. Quando não consegue validar automaticamente, o profissional fica pendente. O admin pode aprovar, reprovar ou devolver para revisão, com registro em auditoria e `cna_validacao_logs`.

## Como o sistema lida com LGPD?

A fase atual implementa consentimento para IA, controle por perfil, bloqueio de storage, auditoria e documentação de privacidade. Para produção, ainda seria necessário formalizar retenção, exclusão, contratos com operadores e políticas de incidente.

## Como o admin prova maturidade do produto?

O admin mostra métricas, gráficos, documentos pendentes de IA, solicitações críticas, profissionais pendentes de OAB, auditoria recente e saúde de integrações. Isso mostra operação real da plataforma.

## Quais são os maiores riscos técnicos?

- Falta de testes automatizados.
- Dependência externa de IA/CNA/SMTP.
- Ausência de OCR para documentos escaneados.
- Necessidade de hardening para produção.

## Como evoluir tecnicamente?

1. Testes automatizados de autorização.
2. OCR.
3. Storage fora da raiz pública.
4. Políticas de retenção e exclusão.
5. Observabilidade e backup.
6. Separação API/frontend se o produto crescer.

