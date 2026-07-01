<?php

namespace App\Services\Payments\Asaas;

use App\Services\Payments\Asaas\AsaasClient;
use RuntimeException;

class AsaasCustomer
{
    private AsaasClient $client;
    private string $sandboxCpfCnpj;

    public function __construct(AsaasClient $client, string $sandboxCpfCnpj)
    {
        $this->client = $client;
        $this->sandboxCpfCnpj = $sandboxCpfCnpj;
    }

    public function findOrCreateCustomer(array $user): string
    {
        $externalReference = 'justraduz_user_' . (int) $user['id'];
        $existing = $this->client->request('GET', '/customers', [
            'externalReference' => $externalReference,
            'limit' => 1,
        ]);

        if (!empty($existing['data'][0]['id'])) {
            return (string) $existing['data'][0]['id'];
        }

        $payload = [
            'name' => (string) $user['nome'],
            'email' => (string) $user['email'],
            'externalReference' => $externalReference,
            'notificationDisabled' => false,
        ];

        $cpfCnpj = $this->cpfCnpjForCustomer($user);
        if ($cpfCnpj !== '') {
            $payload['cpfCnpj'] = $cpfCnpj;
        }

        $phone = preg_replace('/\D+/', '', (string) ($user['telefone'] ?? '')) ?: '';
        if ($phone !== '') {
            $payload['mobilePhone'] = $phone;
        }

        $created = $this->client->request('POST', '/customers', $payload);
        $customerId = (string) ($created['id'] ?? '');
        if ($customerId === '') {
            throw new RuntimeException('Asaas não retornou o ID do cliente.');
        }

        return $customerId;
    }

    public function cpfCnpjForCustomer(array $user): string
    {
        $sandboxDocument = preg_replace('/\D+/', '', $this->sandboxCpfCnpj) ?: '';
        if ($sandboxDocument !== '' && str_contains($this->client->getApiUrl(), 'api-sandbox.asaas.com')) {
            return $sandboxDocument;
        }

        return preg_replace('/\D+/', '', (string) ($user['cpf'] ?? '')) ?: '';
    }
}
