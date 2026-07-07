<?php

require_once __DIR__ . '/ManualPaymentProvider.php';
require_once __DIR__ . '/AsaasPaymentProvider.php';
require_once __DIR__ . '/PixPaymentProvider.php';

class PaymentProviderFactory
{
    public static function make(PDO $pdo, SubscriptionService $subscriptions): PaymentProviderInterface
    {
        return self::makeNamed($pdo, $subscriptions, strtolower(trim(self::env('PAYMENT_PROVIDER', 'manual'))));
    }

    public static function makeNamed(PDO $pdo, SubscriptionService $subscriptions, string $provider): PaymentProviderInterface
    {
        $provider = strtolower(trim($provider));

        return match ($provider) {
            'manual', 'manual_checkout' => new ManualPaymentProvider($pdo, $subscriptions),
            'asaas' => new AsaasPaymentProvider($pdo, $subscriptions),
            'pix' => new PixPaymentProvider($pdo, $subscriptions),
            default => throw new RuntimeException('Provedor de pagamento nao configurado: ' . $provider),
        };
    }

    private static function env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value !== false && (string) $value !== '') {
            return (string) $value;
        }

        $env = function_exists('database_env_values') ? database_env_values(dirname(__DIR__, 3) . '/.env') : [];
        return (string) ($env[$key] ?? $default);
    }
}
