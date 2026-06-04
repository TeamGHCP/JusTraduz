<?php

class MailerService
{
    private array $env;

    public function __construct()
    {
        $this->env = $this->loadEnv(dirname(__DIR__, 2) . '/.env');
    }

    public function send(string $to, string $subject, string $message, bool $isHtml = false, array $inlineImages = []): bool
    {
        $host = $this->env('MAIL_HOST', '');

        if ($host !== '') {
            return $this->sendSmtp($to, $subject, $message, $isHtml, $inlineImages);
        }

        $body = $message;
        $contentType = ($isHtml ? 'text/html' : 'text/plain') . '; charset=UTF-8';
        if ($inlineImages !== []) {
            [$contentType, $body] = $this->buildRelatedMessage($message, $inlineImages);
        }

        $headers = [
            'From: ' . $this->formatAddress($this->fromAddress(), $this->fromName()),
            'Reply-To: ' . $this->fromAddress(),
            'MIME-Version: 1.0',
            'Content-Type: ' . $contentType,
        ];

        return @mail($to, $this->encodedHeader($subject), $body, implode("\r\n", $headers));
    }

    private function sendSmtp(string $to, string $subject, string $message, bool $isHtml = false, array $inlineImages = []): bool
    {
        $host = $this->env('MAIL_HOST', '');
        $port = (int) $this->env('MAIL_PORT', '587');
        $username = $this->env('MAIL_USERNAME', '');
        $password = $this->env('MAIL_PASSWORD', '');
        $encryption = strtolower($this->env('MAIL_ENCRYPTION', 'tls'));
        $from = $this->fromAddress();

        if ($host === '' || $username === '' || $from === '') {
            error_log('SMTP não configurado: confira MAIL_HOST, MAIL_USERNAME e MAIL_FROM_ADDRESS.');
            return false;
        }

        if ($password === '') {
            error_log('SMTP não configurado: informe MAIL_PASSWORD com a senha de app do e-mail.');
            return false;
        }

        if (str_contains($host, 'gmail.com')) {
            $password = preg_replace('/\s+/', '', $password) ?? $password;
        }

        $transport = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $socket = @stream_socket_client(
            $transport . $host . ':' . $port,
            $errno,
            $errstr,
            20,
            STREAM_CLIENT_CONNECT
        );

        if (!$socket) {
            error_log("Erro SMTP ao conectar em {$host}:{$port}: {$errstr} ({$errno})");
            return false;
        }

        stream_set_timeout($socket, 20);

        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO localhost', [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Não foi possível iniciar TLS no SMTP.');
                }
                $this->command($socket, 'EHLO localhost', [250]);
            }

            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode($username), [334]);
            $this->command($socket, base64_encode($password), [235]);
            $this->command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);

            $body = $message;
            $contentType = ($isHtml ? 'text/html' : 'text/plain') . '; charset=UTF-8';
            if ($inlineImages !== []) {
                [$contentType, $body] = $this->buildRelatedMessage($message, $inlineImages);
            }

            $headers = [
                'From: ' . $this->formatAddress($from, $this->fromName()),
                'To: ' . $to,
                'Subject: ' . $this->encodedHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: ' . $contentType,
                'Content-Transfer-Encoding: 8bit',
            ];

            fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $this->escapeMessage($body) . "\r\n.\r\n");
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
            fclose($socket);
            return true;
        } catch (Throwable $e) {
            error_log('Erro SMTP: ' . $e->getMessage());
            if (is_resource($socket)) {
                @fclose($socket);
            }
            return false;
        }
    }

    private function buildRelatedMessage(string $html, array $inlineImages): array
    {
        $boundary = 'justraduz_related_' . bin2hex(random_bytes(12));
        $body = "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $html . "\r\n";

        foreach ($inlineImages as $contentId => $image) {
            $path = (string) ($image['path'] ?? '');
            if ($path === '' || !is_file($path)) {
                continue;
            }

            $contentType = (string) ($image['content_type'] ?? 'image/png');
            $data = chunk_split(base64_encode((string) file_get_contents($path)));
            $safeContentId = preg_replace('/[^a-zA-Z0-9._-]/', '', (string) $contentId) ?: 'image';

            $body .= "--{$boundary}\r\n"
                . "Content-Type: {$contentType}\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . "Content-ID: <{$safeContentId}>\r\n"
                . "X-Attachment-Id: {$safeContentId}\r\n\r\n"
                . $data . "\r\n";
        }

        $body .= "--{$boundary}--";

        return ['multipart/related; boundary="' . $boundary . '"', $body];
    }

    private function command($socket, string $command, array $expected): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $expected);
    }

    private function expect($socket, array $expected): string
    {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (preg_match('/^\d{3}\s/', $line)) {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException(trim($response) ?: 'Resposta SMTP vazia.');
        }

        return $response;
    }

    private function escapeMessage(string $message): string
    {
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $lines = explode("\n", $message);

        foreach ($lines as &$line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
        }

        return implode("\r\n", $lines);
    }

    private function encodedHeader(string $value): string
    {
        if (preg_match('/^[\x20-\x7E]+$/', $value)) {
            return $value;
        }

        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function formatAddress(string $email, string $name): string
    {
        return $this->encodedHeader($name) . ' <' . $email . '>';
    }

    private function fromAddress(): string
    {
        return $this->env('MAIL_FROM_ADDRESS', $this->env('MAIL_USERNAME', 'no-reply@justraduz.local'));
    }

    private function fromName(): string
    {
        return $this->env('MAIL_FROM_NAME', 'JusTraduz');
    }

    private function env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value !== false) {
            return trim((string) $value);
        }

        return trim((string) ($this->env[$key] ?? $default));
    }

    private function loadEnv(string $path): array
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
