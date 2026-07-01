<?php

namespace App\Services\Payments\Asaas;

use App\Services\Payments\Asaas\AsaasClient;
use Throwable;

class AsaasPix
{
    private AsaasClient $client;

    public function __construct(AsaasClient $client)
    {
        $this->client = $client;
    }

    public function pixQrCodeForPayment(string $providerPaymentId): array
    {
        try {
            $qrCode = $this->client->request('GET', '/payments/' . rawurlencode($providerPaymentId) . '/pixQrCode');
        } catch (Throwable) {
            return [];
        }

        return [
            'provider_payment_id' => $providerPaymentId,
            'encoded_image' => (string) ($qrCode['encodedImage'] ?? ''),
            'payload' => (string) ($qrCode['payload'] ?? ''),
            'expiration_date' => (string) ($qrCode['expirationDate'] ?? ''),
        ];
    }
}
