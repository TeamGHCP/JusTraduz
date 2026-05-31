# Defesa para banca

## Pitch de 30 segundos

O JusTraduz é uma plataforma que ajuda pessoas a entender documentos jurídicos em linguagem simples. O usuário envia um PDF ou imagem, autoriza uma análise com IA, recebe resumo e explicação acessível e, se precisar, solicita ajuda de um advogado validado. A plataforma também organiza chat, tarefas, agenda, validação OAB/CNA e auditoria administrativa.

## Problema

Muitas pessoas recebem contratos, notificações, intimações ou outros documentos jurídicos e não entendem:

- o que o documento significa;
- quais pontos exigem atenção;
- se precisam procurar ajuda;
- qual é o próximo passo.

Essa dificuldade cria insegurança, atraso na tomada de decisão e dependência de explicações informais.

## Solução

O JusTraduz combina três camadas:

1. Compreensão inicial com IA.
2. Atendimento profissional com advogado validado.
3. Administração segura com auditoria e controle operacional.

## Diferenciais

- Problema real e específico.
- IA com consentimento explícito.
- Explicação em linguagem simples.
- Fluxo completo até atendimento humano.
- Validação OAB/CNA.
- Admin com métricas, gráficos e auditoria.
- Controle por perfil: cliente, advogado, estagiário e admin.
- Foco em segurança e LGPD.

## Comparação com um e-commerce comum

Um e-commerce demonstra cadastro, carrinho, produto e pedido. É útil, mas muito comum em trabalhos acadêmicos.

O JusTraduz demonstra:

- documento sensível;
- IA aplicada;
- validação profissional;
- atendimento jurídico;
- agenda;
- chat;
- auditoria;
- LGPD.

Isso torna o projeto mais memorável e mais fácil de defender como inovação.

## Arquitetura em linguagem simples

O sistema é um monólito PHP com MySQL. O frontend tem páginas PHP/HTML/CSS/JS. O backend tem controllers e services. O banco guarda usuários, documentos, análises de IA, solicitações, mensagens, tarefas, agenda, notificações e auditoria.

Essa arquitetura foi escolhida porque:

- roda bem em XAMPP;
- facilita demonstração local;
- reduz dependências externas;
- permite mostrar o fluxo completo de ponta a ponta.

## Segurança para explicar

- Login com senha hash.
- Sessão com regeneração de ID.
- CSRF em formulários sensíveis.
- Permissões por perfil.
- Cliente não acessa documento de outro cliente.
- Estagiário não herda perfil de admin.
- Storage de documentos bloqueado para acesso direto.
- IA só com autorização.
- Ações sensíveis auditadas.
- Chaves sensíveis fora do código versionado.

## LGPD para explicar

O sistema trata dados pessoais e documentos potencialmente sensíveis. Por isso:

- coleta apenas dados necessários ao fluxo;
- informa que IA é opcional e depende de consentimento;
- mantém auditoria de ações sensíveis;
- prevê política de retenção e exclusão como evolução;
- separa acesso por perfil;
- evita expor documentos diretamente.

## Perguntas prováveis

### A IA substitui o advogado?

Não. A IA fornece uma explicação inicial e informativa. A interface avisa que não substitui orientação jurídica. O fluxo estimula procurar atendimento profissional quando houver dúvida relevante.

### Como vocês protegem documentos sensíveis?

Os documentos ficam em storage interno, o roteador bloqueia acesso direto e o download passa por controller com validação de permissão. Além disso, há auditoria de envio, análise, download/exclusão quando aplicável e ações administrativas.

### Como saber se o advogado é real?

O cadastro exige OAB/UF para profissionais. O sistema tenta consulta CNA/OAB e, se necessário, o admin faz revisão manual. A decisão fica registrada.

### Por que PHP e MySQL?

Porque o foco do projeto é demonstrar produto, fluxo e regras de negócio. PHP/MySQL roda facilmente em XAMPP, simplifica a entrega acadêmica e permite uma demo completa localmente.

### O que falta para produção?

HTTPS, cookies endurecidos, backups, política formal de retenção, antivírus no upload, OCR, testes automatizados, observabilidade e revisão jurídica completa de LGPD.

### Qual é o maior diferencial técnico?

A integração entre IA, autorização do usuário, documentos sensíveis, validação profissional, atendimento humano, agenda e auditoria administrativa.

## Fechamento sugerido

O JusTraduz não é apenas um CRUD. Ele resolve uma dor concreta: tornar documentos jurídicos mais compreensíveis e organizar o caminho até atendimento profissional. A solução mostra domínio técnico, preocupação com segurança, uso responsável de IA e potencial real de produto.

