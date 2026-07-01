<?php

namespace App\Services\Payments\Asaas;

use RuntimeException;

class AsaasClient
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct(string $apiUrl, string $apiKey)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
    }

    public function assertConfigured(): void
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('Configure ASAAS_API_KEY no backend/.env.');
        }
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function request(string $method, string $path, array $payload = []): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extensão cURL do PHP não está habilitada.');
        }

        $method = strtoupper($method);
        $url = $this->apiUrl . '/' . ltrim($path, '/');
        if ($method === 'GET' && $payload) {
            $url .= '?' . http_build_query($payload);
        }

        $curl = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'content-type: application/json',
                'User-Agent: JusTraduz/1.0 billing-integration',
                'access_token: ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 25,
        ];

        if ($method !== 'GET') {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false) {
            throw new RuntimeException('Falha cURL Asaas: ' . $error);
        }

        $data = json_decode((string) $response, true);
        if (!is_array($data)) {
            $data = ['raw' => (string) $response];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $message = $this->errorMessage($data) ?: 'Erro HTTP ' . $httpCode . ' no Asaas.';
            throw new RuntimeException($message, $httpCode);
        }

        return $data;
    }

    public function errorMessage(array $data): string
    {
        if (!empty($data['errors'][0]['description'])) {
            return (string) $data['errors'][0]['description'];
        }

        if (!empty($data['message'])) {
            return (string) $data['message'];
        }

        return '';
    }
}
