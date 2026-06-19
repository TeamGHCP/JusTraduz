<?php

require_once dirname(__DIR__) . '/backend/app/config/database.php';
require_once dirname(__DIR__) . '/backend/app/services/SubscriptionService.php';
require_once dirname(__DIR__) . '/backend/app/services/payments/AsaasPaymentProvider.php';

$options = getopt('', ['payment-id::', 'subscription-id::', 'user-id::', 'sync-local', 'help']);

if (isset($options['help'])) {
    echo "Uso: php scripts/confirm-asaas-sandbox-payment.php [--payment-id=pay_...] [--subscription-id=sub_...] [--user-id=1] [--sync-local]\n";
    echo "Sem argumentos, confirma a cobrança Asaas pendente mais recente registrada localmente.\n";
    exit(0);
}

$env = database_env_values(dirname(__DIR__) . '/backend/.env');
$apiUrl = rtrim((string) (getenv('ASAAS_API_URL') ?: ($env['ASAAS_API_URL'] ?? 'https://api-sandbox.asaas.com/v3')), '/');
$apiKey = (string) (getenv('ASAAS_API_KEY') ?: ($env['ASAAS_API_KEY'] ?? ''));

if ($apiKey === '') {
    fwrite(STDERR, "Configure ASAAS_API_KEY no backend/.env.\n");
    exit(1);
}

if (!str_contains($apiUrl, 'api-sandbox.asaas.com')) {
    fwrite(STDERR, "Este script so pode rodar no sandbox do Asaas. ASAAS_API_URL atual: {$apiUrl}\n");
    exit(1);
}

$pdo = database_connection();
$paymentId = trim((string) ($options['payment-id'] ?? ''));
$subscriptionId = trim((string) ($options['subscription-id'] ?? ''));
$userId = (int) ($options['user-id'] ?? 0);
$candidate = null;

if ($paymentId === '') {
    $candidate = find_pending_asaas_payment($pdo, $subscriptionId, $userId);
    if (!$candidate) {
        fwrite(STDERR, "Nenhuma cobrança pendente Asaas foi encontrada em payment_events.\n");
        fwrite(STDERR, "Tente informar manualmente: php scripts/confirm-asaas-sandbox-payment.php --payment-id=pay_...\n");
        exit(1);
    }

    $paymentId = (string) $candidate['provider_payment_id'];
    $subscriptionId = (string) ($candidate['provider_subscription_id'] ?? $subscriptionId);
    $userId = (int) ($candidate['user_id'] ?? $userId);
}

try {
    $response = asaas_request($apiUrl, $apiKey, 'POST', '/sandbox/payment/' . rawurlencode($paymentId) . '/confirm');

    echo "Pagamento confirmado no sandbox Asaas.\n";
    echo json_encode([
        'ok' => true,
        'payment_id' => $paymentId,
        'subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
        'user_id' => $userId > 0 ? $userId : null,
        'asaas_status' => $response['status'] ?? null,
        'asaas_response_id' => $response['id'] ?? null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    if (isset($options['sync-local'])) {
        if ($subscriptionId === '' || $userId <= 0) {
            fwrite(STDERR, "Confirmado no Asaas, mas nao foi possivel sincronizar localmente sem subscription-id e user-id.\n");
            exit(1);
        }

        $subscriptions = new SubscriptionService($pdo);
        $provider = new AsaasPaymentProvider($pdo, $subscriptions);
        $sync = $provider->syncCheckoutPayment($userId, $subscriptionId);

        echo "Assinatura sincronizada localmente.\n";
        echo json_encode($sync, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        echo "Agora volte ao sistema e clique em \"Já realizei o pagamento\" para o JusTraduz consultar o Asaas e ativar o plano.\n";
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Erro ao confirmar pagamento no sandbox Asaas: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

function find_pending_asaas_payment(PDO $pdo, string $subscriptionId, int $userId): ?array
{
    if (!database_table_exists($pdo, 'payment_events')) {
        return null;
    }

    $sql = "SELECT id, user_id, provider_event_id, payload_json
            FROM payment_events
            WHERE provider = 'asaas'
              AND event_type = 'subscription.created'
              AND status = 'pending'";
    $params = [];

    if ($subscriptionId !== '') {
        $sql .= ' AND provider_event_id = ?';
        $params[] = $subscriptionId;
    }

    if ($userId > 0) {
        $sql .= ' AND user_id = ?';
        $params[] = $userId;
    }

    $sql .= ' ORDER BY id DESC LIMIT 25';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    foreach ($stmt->fetchAll() as $row) {
        $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            continue;
        }

        $payment = is_array($payload['asaas_first_payment'] ?? null) ? $payload['asaas_first_payment'] : [];
        $paymentId = trim((string) ($payment['id'] ?? $payload['provider_payment_id'] ?? ''));
        if ($paymentId === '') {
            continue;
        }

        $providerSubscriptionId = (string) ($payload['provider_subscription_id'] ?? $row['provider_event_id'] ?? '');
        if (has_terminal_asaas_event($pdo, $providerSubscriptionId, $paymentId)) {
            continue;
        }

        return [
            'event_id' => (int) $row['id'],
            'user_id' => (int) ($row['user_id'] ?? 0),
            'provider_subscription_id' => $providerSubscriptionId,
            'provider_payment_id' => $paymentId,
        ];
    }

    return null;
}

function has_terminal_asaas_event(PDO $pdo, string $providerSubscriptionId, string $providerPaymentId): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM payment_events
         WHERE provider = 'asaas'
           AND (
                (provider_event_id = ? AND status IN ('paid', 'failed', 'refunded'))
                OR (provider_event_id = ? AND event_type IN ('subscription.canceled', 'checkout.canceled'))
           )"
    );
    $stmt->execute([$providerPaymentId, $providerSubscriptionId]);

    return (int) $stmt->fetchColumn() > 0;
}

function asaas_request(string $apiUrl, string $apiKey, string $method, string $path, array $payload = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Extensao cURL do PHP nao esta habilitada.');
    }

    $curl = curl_init($apiUrl . '/' . ltrim($path, '/'));
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'content-type: application/json',
            'User-Agent: JusTraduz/1.0 sandbox-payment-confirm',
            'access_token: ' . $apiKey,
        ],
        CURLOPT_TIMEOUT => 25,
    ];

    if ($payload) {
        $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    curl_setopt_array($curl, $options);
    $raw = curl_exec($curl);
    $error = curl_error($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($raw === false) {
        throw new RuntimeException('Falha cURL Asaas: ' . $error);
    }

    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        $data = ['raw' => (string) $raw];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = (string) ($data['errors'][0]['description'] ?? $data['message'] ?? ('Erro HTTP ' . $httpCode));
        throw new RuntimeException($message, $httpCode);
    }

    return $data;
}
