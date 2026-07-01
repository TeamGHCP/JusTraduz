<?php

namespace App\Services\Payments\Asaas;

use InvalidArgumentException;

class AsaasCard
{
    public function creditCardPayload(array $paymentData, array $user): array
    {
        $card = is_array($paymentData['card'] ?? null) ? $paymentData['card'] : [];
        $holder = is_array($paymentData['holder'] ?? null) ? $paymentData['holder'] : [];
        $number = preg_replace('/\D+/', '', (string) ($card['number'] ?? '')) ?: '';
        $expiryMonth = str_pad((string) (int) preg_replace('/\D+/', '', (string) ($card['expiry_month'] ?? '')), 2, '0', STR_PAD_LEFT);
        $expiryYear = preg_replace('/\D+/', '', (string) ($card['expiry_year'] ?? '')) ?: '';
        if (strlen($expiryYear) === 2) {
            $expiryYear = '20' . $expiryYear;
        }

        $ccv = preg_replace('/\D+/', '', (string) ($card['ccv'] ?? '')) ?: '';
        $holderName = trim((string) ($card['holder_name'] ?? $holder['name'] ?? $user['nome'] ?? ''));
        $cpfCnpj = preg_replace('/\D+/', '', (string) ($holder['cpf_cnpj'] ?? $user['cpf'] ?? '')) ?: '';
        $postalCode = preg_replace('/\D+/', '', (string) ($holder['postal_code'] ?? '')) ?: '';
        $addressNumber = trim((string) ($holder['address_number'] ?? ''));
        $phone = preg_replace('/\D+/', '', (string) ($holder['phone'] ?? $user['telefone'] ?? '')) ?: '';

        if ($number === '' || strlen($number) < 13 || $holderName === '' || $expiryMonth === '00' || $expiryYear === '' || $ccv === '') {
            throw new InvalidArgumentException('Informe os dados do cartão corretamente.');
        }

        if ($cpfCnpj === '' || $postalCode === '' || $addressNumber === '' || $phone === '') {
            throw new InvalidArgumentException('Informe CPF, CEP, número do endereço e telefone do titular do cartão.');
        }

        return [
            'creditCard' => [
                'holderName' => $holderName,
                'number' => $number,
                'expiryMonth' => $expiryMonth,
                'expiryYear' => $expiryYear,
                'ccv' => $ccv,
            ],
            'creditCardHolderInfo' => [
                'name' => trim((string) ($holder['name'] ?? $holderName)),
                'email' => trim((string) ($holder['email'] ?? $user['email'] ?? '')),
                'cpfCnpj' => $cpfCnpj,
                'postalCode' => $postalCode,
                'addressNumber' => $addressNumber,
                'addressComplement' => trim((string) ($holder['address_complement'] ?? '')),
                'phone' => $phone,
                'mobilePhone' => $phone,
            ],
            'remoteIp' => trim((string) ($paymentData['remote_ip'] ?? '127.0.0.1')),
        ];
    }
}
