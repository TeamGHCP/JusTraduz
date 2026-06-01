<?php

class GoogleOAuthService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $env = $this->envValues(dirname(__DIR__, 2) . '/.env');
        $this->clientId = $this->env('GOOGLE_CLIENT_ID', $env);
        $this->clientSecret = $this->env('GOOGLE_CLIENT_SECRET', $env);
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    public function authorizationUrl(string $redirectUri, string $state, string $nonce): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function fetchToken(string $code, string $redirectUri): array
    {
        return $this->httpJson(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);
    }

    public function validateIdToken(string $idToken, string $nonce): array
    {
        [$header, $payload, $signature, $signedData] = $this->decodeJwt($idToken);

        if (($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
            throw new RuntimeException('Token Google com assinatura inválida.');
        }

        $this->verifySignature((string) $header['kid'], $signedData, $signature);

        $issuer = (string) ($payload['iss'] ?? '');
        if (!in_array($issuer, ['https://accounts.google.com', 'accounts.google.com'], true)) {
            throw new RuntimeException('Emissor Google inválido.');
        }

        if ((string) ($payload['aud'] ?? '') !== $this->clientId) {
            throw new RuntimeException('Audiência Google inválida.');
        }

        if ((int) ($payload['exp'] ?? 0) < time()) {
            throw new RuntimeException('Token Google expirado.');
        }

        if ((string) ($payload['nonce'] ?? '') !== $nonce) {
            throw new RuntimeException('Nonce Google inválido.');
        }

        if (empty($payload['sub'])) {
            throw new RuntimeException('Identificador Google ausente.');
        }

        if (empty($payload['email']) || !filter_var((string) $payload['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Google não retornou um e-mail válido.');
        }

        if (($payload['email_verified'] ?? false) !== true) {
            throw new RuntimeException('E-mail Google ainda não verificado.');
        }

        return $payload;
    }

    private function verifySignature(string $kid, string $signedData, string $signature): void
    {
        $keys = $this->httpJson(self::JWKS_URL);
        foreach (($keys['keys'] ?? []) as $key) {
            if (($key['kid'] ?? '') !== $kid) {
                continue;
            }

            $publicKey = $this->publicKeyFromCertificate($key)
                ?? $this->publicKeyFromJwk($key);

            if ($publicKey === null) {
                continue;
            }

            $verified = openssl_verify($signedData, $signature, $publicKey, OPENSSL_ALGO_SHA256);
            if ($verified === 1) {
                return;
            }
        }

        throw new RuntimeException('Não foi possível validar a assinatura do Google.');
    }

    private function publicKeyFromCertificate(array $key)
    {
        if (empty($key['x5c'][0])) {
            return null;
        }

        $certificate = "-----BEGIN CERTIFICATE-----\n"
            . chunk_split((string) $key['x5c'][0], 64, "\n")
            . "-----END CERTIFICATE-----\n";

        $x509 = openssl_x509_read($certificate);
        if ($x509 === false) {
            return null;
        }

        $publicKey = openssl_pkey_get_public($x509);

        return $publicKey === false ? null : $publicKey;
    }

    private function publicKeyFromJwk(array $key)
    {
        if (($key['kty'] ?? '') !== 'RSA' || empty($key['n']) || empty($key['e'])) {
            return null;
        }

        $modulus = $this->base64UrlDecode((string) $key['n']);
        $exponent = $this->base64UrlDecode((string) $key['e']);

        $rsaPublicKey = $this->asn1Sequence(
            $this->asn1Integer($modulus)
            . $this->asn1Integer($exponent)
        );

        $publicKeyInfo = $this->asn1Sequence(
            $this->asn1Sequence(
                $this->asn1ObjectIdentifier('1.2.840.113549.1.1.1')
                . $this->asn1Null()
            )
            . $this->asn1BitString($rsaPublicKey)
        );

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($publicKeyInfo), 64, "\n")
            . "-----END PUBLIC KEY-----\n";

        $publicKey = openssl_pkey_get_public($pem);

        return $publicKey === false ? null : $publicKey;
    }

    private function asn1Sequence(string $value): string
    {
        return "\x30" . $this->asn1Length(strlen($value)) . $value;
    }

    private function asn1Integer(string $value): string
    {
        if ($value === '') {
            $value = "\x00";
        }

        if ((ord($value[0]) & 0x80) !== 0) {
            $value = "\x00" . $value;
        }

        return "\x02" . $this->asn1Length(strlen($value)) . $value;
    }

    private function asn1BitString(string $value): string
    {
        return "\x03" . $this->asn1Length(strlen($value) + 1) . "\x00" . $value;
    }

    private function asn1Null(): string
    {
        return "\x05\x00";
    }

    private function asn1ObjectIdentifier(string $oid): string
    {
        $parts = array_map('intval', explode('.', $oid));
        $encoded = chr((40 * $parts[0]) + $parts[1]);

        foreach (array_slice($parts, 2) as $part) {
            $bytes = [chr($part & 0x7f)];
            $part >>= 7;

            while ($part > 0) {
                array_unshift($bytes, chr(($part & 0x7f) | 0x80));
                $part >>= 7;
            }

            $encoded .= implode('', $bytes);
        }

        return "\x06" . $this->asn1Length(strlen($encoded)) . $encoded;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xff) . $bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private function decodeJwt(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new RuntimeException('Token Google malformado.');
        }

        $header = json_decode($this->base64UrlDecode($parts[0]), true);
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        if (!is_array($header) || !is_array($payload)) {
            throw new RuntimeException('Token Google inválido.');
        }

        return [
            $header,
            $payload,
            $this->base64UrlDecode($parts[2]),
            $parts[0] . '.' . $parts[1],
        ];
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) {
            throw new RuntimeException('Base64URL inválido.');
        }

        return $decoded;
    }

    private function httpJson(string $url, array $postFields = []): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);

            if ($postFields !== []) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields, '', '&', PHP_QUERY_RFC3986));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: application/json',
                    'Content-Type: application/x-www-form-urlencoded',
                ]);
            }

            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
        } else {
            $context = null;
            if ($postFields !== []) {
                $context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => "Accept: application/json\r\nContent-Type: application/x-www-form-urlencoded\r\n",
                        'content' => http_build_query($postFields, '', '&', PHP_QUERY_RFC3986),
                        'timeout' => 15,
                    ],
                ]);
            }

            $body = @file_get_contents($url, false, $context);
            $statusLine = $http_response_header[0] ?? '';
            preg_match('/\s(\d{3})\s/', $statusLine, $matches);
            $status = (int) ($matches[1] ?? 0);
            $error = '';
        }

        if (!is_string($body) || $body === '' || $status < 200 || $status >= 300) {
            throw new RuntimeException($error !== '' ? $error : 'Falha na comunicação com o Google.');
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('Resposta Google inválida.');
        }

        return $data;
    }

    private function env(string $key, array $env): string
    {
        $value = getenv($key);
        if ($value !== false) {
            return trim((string) $value);
        }

        return trim((string) ($env[$key] ?? ''));
    }

    private function envValues(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $values = [];
        foreach ((array) file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim(trim($value), "\"'");
        }

        return $values;
    }
}
