<?php

function billing_payload(array $event): array
{
    $payload = json_decode((string) ($event['payload_json'] ?? ''), true);
    return is_array($payload) ? $payload : [];
}

function billing_money(int $cents): string
{
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

function billing_date(?string $date): string
{
    if (!$date) {
        return '-';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y', $timestamp) : $date;
}

function billing_datetime(?string $date): string
{
    if (!$date) {
        return '-';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : $date;
}

function billing_status_label(string $status): array
{
    return match ($status) {
        'paid' => ['Pago', 'badge-success'],
        'failed' => ['Falhou', 'badge-danger'],
        'refunded' => ['Cancelado', 'badge-warning'],
        default => ['Pendente', 'badge-info'],
    };
}

function billing_event_title(string $eventType, string $status = ''): string
{
    if ($status === 'paid') {
        return 'Pagamento recebido';
    }

    return match ($eventType) {
        'subscription.created' => 'Assinatura criada',
        'subscription.canceled', 'checkout.canceled' => 'Assinatura cancelada',
        'payment.sync_paid', 'PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED' => 'Pagamento recebido',
        default => 'Movimento de cobrança',
    };
}

function billing_method_label(array $event): string
{
    $payload = billing_payload($event);
    $asaasPayment = is_array($payload['asaas_payment'] ?? null) ? $payload['asaas_payment'] : [];
    $rawMethod = (string) (
        $asaasPayment['billingType']
        ?? $payload['billing_type']
        ?? $payload['payment_method']
        ?? ''
    );

    return match (strtoupper($rawMethod)) {
        'PIX' => 'Pix',
        'CREDIT_CARD', 'CREDITCARD', 'CARTAO', 'CARTÃO' => 'Cartão',
        default => $rawMethod !== '' ? $rawMethod : 'Asaas',
    };
}

function billing_cycle_label(array $event): string
{
    $payload = billing_payload($event);
    $cycle = (string) (($event['billing_cycle'] ?? '') ?: ($payload['billing_cycle'] ?? ''));

    return match ($cycle) {
        'yearly' => 'Anual',
        'monthly' => 'Mensal',
        default => 'Ciclo único',
    };
}

function billing_document_number(string $prefix, int $eventId): string
{
    return $prefix . '-' . str_pad((string) $eventId, 6, '0', STR_PAD_LEFT);
}

function billing_document_event(PDO $pdo, int $eventId, int $userId): ?array
{
    if (!database_table_exists($pdo, 'payment_events')) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT pe.*, u.nome AS user_name, u.email AS user_email, u.cpf AS user_cpf, u.telefone AS user_phone,
                s.billing_cycle, s.current_period_start, s.current_period_end,
                p.name AS plan_name, p.description AS plan_description
         FROM payment_events pe
         LEFT JOIN users u ON u.id = pe.user_id
         LEFT JOIN subscriptions s ON s.id = pe.subscription_id
         LEFT JOIN plans p ON p.id = s.plan_id
         WHERE pe.id = ? AND pe.user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$eventId, $userId]);
    $event = $stmt->fetch();

    if (!$event) {
        return null;
    }

    $payload = billing_payload($event);
    $planId = (int) ($payload['plan_id'] ?? 0);
    if (empty($event['plan_name']) && $planId > 0 && database_table_exists($pdo, 'plans')) {
        $plan = fetch_one($pdo, 'SELECT name, description FROM plans WHERE id = ? LIMIT 1', [$planId]);
        if ($plan) {
            $event['plan_name'] = $plan['name'] ?? null;
            $event['plan_description'] = $plan['description'] ?? null;
        }
    }

    return $event;
}
