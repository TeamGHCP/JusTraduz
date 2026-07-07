<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class PixPayloadService
{
    public function build(float $amount, string $pixKey, string $receiverName, string $receiverCity): string
    {
        $pixKey = trim($pixKey);
        $receiverName = $this->sanitizeReceiverName($receiverName);
        $receiverCity = $this->sanitizeReceiverCity($receiverCity);

        if ($pixKey === '') {
            throw new InvalidArgumentException('PIX_CHAVE nao configurada.');
        }

        if ($receiverName === '') {
            throw new InvalidArgumentException('PIX_NOME nao configurado.');
        }

        if ($receiverCity === '') {
            throw new InvalidArgumentException('PIX_CIDADE nao configurado.');
        }

        $merchantAccount = $this->emv('00', 'br.gov.bcb.pix') . $this->emv('01', $pixKey);
        $merchantInfo = $this->emv('26', $merchantAccount);
        $transactionAmount = $this->emv('54', number_format($amount, 2, '.', ''));
        $txid = $this->emv('05', 'JUSTRADUZ' . (int) round($amount * 100));

        $payload = $this->emv('00', '01')
            . $this->emv('01', '12')
            . $merchantInfo
            . $this->emv('52', '0000')
            . $this->emv('53', '986')
            . $transactionAmount
            . $this->emv('58', 'BR')
            . $this->emv('59', $receiverName)
            . $this->emv('60', $receiverCity)
            . $this->emv('62', $txid);

        $payloadForCrc = $payload . '6304';

        return $payloadForCrc . strtoupper(str_pad(dechex($this->crc16($payloadForCrc)), 4, '0', STR_PAD_LEFT));
    }

    private function emv(string $id, string $value): string
    {
        return $id . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
    }

    private function crc16(string $payload): int
    {
        $crc = 0xFFFF;
        $length = strlen($payload);

        for ($i = 0; $i < $length; $i++) {
            $crc ^= ord($payload[$i]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                if (($crc & 0x8000) !== 0) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return $crc;
    }

    private function sanitizeReceiverName(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9 \\-]/', '', $value) ?: '';

        return substr($value, 0, 25);
    }

    private function sanitizeReceiverCity(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9 ]/', '', $value) ?: '';

        return substr($value, 0, 15);
    }
}

