# Exibicao do Tempo Limite dos Planos - Cliente

Este documento descreve como o JusTraduz deve exibir para o cliente o tempo restante do plano, a proxima renovacao e o bloqueio por limite mensal.

## Objetivo

Deixar claro para o cliente:

- qual plano esta ativo;
- se a cobranca e mensal ou anual;
- ate quando o periodo atual vale;
- quantos dias faltam para renovar;
- quando uma cota mensal foi atingida;
- qual acao ele pode tomar para continuar usando.

## Fonte dos Dados

Os dados vem da assinatura ativa em `subscriptions`:

- `plan_name`: nome do plano exibido;
- `billing_cycle`: `monthly` ou `yearly`;
- `current_period_start`: inicio do periodo atual;
- `current_period_end`: fim do periodo atual/proxima renovacao;
- `status`: `active`, `past_due`, `canceled`, etc.

Os limites vem de `plans.limits_json`.

## Texto Principal no Perfil

Local sugerido: `Perfil > Faturamento`, dentro do card "Cobranca".

### Assinatura ativa

Exemplo mensal:

```text
Plano Pro
Cobranca mensal - R$ 49,90
Renova em 12/07/2026
Faltam 20 dias para a renovacao do seu plano.
```

Exemplo anual:

```text
Plano Escritorio
Cobranca anual - R$ 959,00
Renova em 22/06/2027
Faltam 365 dias para a renovacao do seu plano.
```

### Assinatura inadimplente

Quando `status = past_due`:

```text
Plano Pro
Pagamento pendente
Seu plano esta com pagamento em aberto. Regularize para evitar bloqueios.
```

Botao sugerido:

```text
Regularizar pagamento
```

### Sem assinatura

```text
Modo gratuito
Assine um plano para liberar mais volume e prioridade.
```

Botao:

```text
Assinar plano
```

## Regras de Calculo

### Dias restantes

Calculo:

```text
dias_restantes = current_period_end - hoje
```

Regras de exibicao:

- se faltar mais de 1 dia: `Faltam X dias para a renovacao do seu plano.`
- se faltar 1 dia: `Falta 1 dia para a renovacao do seu plano.`
- se for hoje: `Seu plano renova hoje.`
- se estiver vencido: `O periodo do plano terminou. Verifique o pagamento.`

## Mensagem de Limite Atingido

Quando uma cota mensal acabar, o backend ja retorna mensagem neste formato:

```text
Voce atingiu o limite mensal do seu plano para envio de documentos (30/30).
A cota renova em 22/07/2026. Para continuar agora, suba de plano.
```

Variacoes por recurso:

```text
Voce atingiu o limite mensal do seu plano para mensagens com IA Juridica (300/300).
A cota renova em 22/07/2026. Para continuar agora, suba de plano.
```

```text
Voce atingiu o limite mensal do seu plano para consulta CNJ (30/30).
A cota renova em 22/07/2026. Para continuar agora, suba de plano.
```

## Exibicao por Plano

### Essencial

```text
Plano Essencial
Cobranca mensal - R$ 14,90
Renova em 22/07/2026
Uso do periodo: ate 30 documentos por mes.
```

### Pro

```text
Plano Pro
Cobranca mensal - R$ 49,90
Renova em 22/07/2026
Uso do periodo: ate 500 documentos por mes.
```

### Escritorio

```text
Plano Escritorio
Cobranca mensal - R$ 99,90
Renova em 22/07/2026
Uso do periodo: documentos, OCR e IA documental ilimitados.
CNJ: ate 1.000 consultas por mes.
```

## Onde Aparece Hoje

Hoje o sistema ja mostra parcialmente:

- `Perfil > Faturamento`: `Periodo atual ate DD/MM/AAAA`;
- `Pagamento confirmado`: `Proxima renovacao`;
- mensagens de limite: `A cota renova em DD/MM/AAAA`.

## Ajuste Visual Recomendado

Trocar no Perfil:

```text
Periodo atual ate 22/07/2026
```

por:

```text
Renova em 22/07/2026
Faltam 30 dias para a renovacao do seu plano.
```

Assim o cliente entende melhor que nao e um "vencimento do acesso", mas sim o fim do ciclo atual e inicio da proxima renovacao.

