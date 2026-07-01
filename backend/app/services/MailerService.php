<?php

namespace App\Services {
    use Throwable;
    use RuntimeException;
    use PDO;

    class MailerService
    {
        private array $env;
        private ?string $lastSmtpResponse = null;

        public function __construct()
        {
            $this->env = $this->loadEnv(dirname(__DIR__, 2) . '/.env');
        }

        public function send(string $to, string $subject, string $message, bool $isHtml = false, array $inlineImages = []): bool
        {
            if (filter_var($this->env('MAIL_LOG_ONLY', 'false'), FILTER_VALIDATE_BOOLEAN)) {
                $this->logMail($to, $subject, 'log_only', true, null);
                return true;
            }

            $host = $this->env('MAIL_HOST', '');
            $sent = false;
            $transport = $host !== '' ? 'smtp' : 'mail';

            if ($host !== '') {
                $this->lastSmtpResponse = null;
                $sent = $this->sendSmtp($to, $subject, $message, $isHtml, $inlineImages);
                $this->logMail($to, $subject, $transport, $sent, $sent ? $this->lastSmtpResponse : 'Falha no envio SMTP. Consulte error_log.');
                return $sent;
            }

            $body = $message;
            $contentType = ($isHtml ? 'text/html' : 'text/plain') . '; charset=UTF-8';
            if ($inlineImages !== []) {
                [$contentType, $body] = $this->buildRelatedMessage($message, $inlineImages);
            }

            $headers = [
                'From: ' . $this->formatAddress($this->fromAddress(), $this->fromName()),
                'Reply-To: ' . $this->fromAddress(),
                'Date: ' . date(DATE_RFC2822),
                'Message-ID: ' . $this->messageId(),
                'X-Mailer: JusTraduz',
                'MIME-Version: 1.0',
                'Content-Type: ' . $contentType,
            ];

            $sent = @mail($to, $this->encodedHeader($subject), $body, implode("\r\n", $headers));
            $this->logMail($to, $subject, $transport, $sent, $sent ? null : 'Falha no envio via mail().');
            return $sent;
        }

        public function sendPasswordResetEmail(string $email, string $name, string $code): bool
        {
            $subject = 'Código de recuperação de senha - JusTraduz';
            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
            $logoPath = dirname(__DIR__, 3) . '/frontend/assets/img/email-logo.png';
            $homeUrl = htmlspecialchars($this->absoluteAppUrl('/frontend/index.html'), ENT_QUOTES, 'UTF-8');

            $message = <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;color:#121212;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#f6f8fb;">
    <tr>
      <td align="center" style="padding:32px 16px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:100%;max-width:610px;background:#ffffff;border:1px solid #dfe3e8;border-radius:8px;">
          <tr>
            <td align="center" style="padding:34px 40px 22px;">
              <a href="{$homeUrl}" target="_blank" style="display:inline-block;text-decoration:none;border:0;">
                <img src="cid:justraduz-logo" width="210" alt="JusTraduz" style="display:block;width:210px;max-width:100%;height:auto;border:0;margin:0 auto;">
              </a>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:0 40px 18px;color:#202124;font-size:22px;font-weight:400;line-height:28px;">
              Código de recuperação de senha
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:0 40px 28px;color:#3c4043;font-size:14px;line-height:20px;">
              Olá, {$safeName}.
            </td>
          </tr>
          <tr>
            <td style="padding:0 40px;">
              <div style="border-top:1px solid #e0e0e0;font-size:1px;line-height:1px;">&nbsp;</div>
            </td>
          </tr>
          <tr>
            <td style="padding:30px 40px 0;color:#202124;font-size:15px;line-height:22px;">
              Recebemos uma solicitação para redefinir a senha da sua conta no JusTraduz. Use o código abaixo para continuar:
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:28px 40px;">
              <div style="display:inline-block;background:#f1f3f4;border:1px solid #dadce0;border-radius:8px;padding:18px 26px;color:#202124;font-size:32px;font-weight:700;letter-spacing:7px;line-height:38px;">{$safeCode}</div>
            </td>
          </tr>
          <tr>
            <td style="padding:0 40px 30px;color:#202124;font-size:15px;line-height:22px;">
              Este código expira em 15 minutes. Se você não solicitou a recuperação de senha, ignore este e-mail.
            </td>
          </tr>
          <tr>
            <td style="padding:0 40px 34px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#e8f0fe;border-radius:8px;">
                <tr>
                  <td valign="top" style="padding:14px 16px;width:24px;">
                    <div style="width:20px;height:20px;border-radius:10px;background:#1a73e8;color:#ffffff;font-size:14px;font-weight:700;line-height:20px;text-align:center;">i</div>
                  </td>
                  <td style="padding:14px 16px 14px 0;color:#174ea6;font-size:13px;line-height:19px;">
                    Esta mensagem foi enviada automaticamente pela JusTraduz. Por segurança, nunca compartilhe este código com outras pessoas.
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

            return $this->send($email, $subject, $message, true, [
                'justraduz-logo' => [
                    'path' => $logoPath,
                    'content_type' => 'image/png',
                ],
            ]);
        }

        public function sendProfessionalPendingEmail(string $email, string $name, string $type): void
        {
            $subject = 'Cadastro recebido - aguardando validação da OAB';
            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $safeType = 'advogado';
            $message = "<p>Olá, {$safeName}.</p><p>Recebemos seu cadastro como {$safeType}. O acesso profissional ao JusTraduz depende da aprovação do administrador interno após validação da OAB/registro informado.</p><p>Você receberá um e-mail quando a revisão for concluída.</p>";
            $this->sendSystemEmail($email, $subject, $message);
        }

        public function sendProfessionalApprovedEmail(string $email, string $name): void
        {
            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $message = "<p>Olá, {$safeName}.</p><p>Seu cadastro profissional foi aprovado no JusTraduz. O acesso profissional está liberado.</p>";
            $this->sendSystemEmail($email, 'Cadastro aprovado no JusTraduz', $message);
        }

        public function sendProfessionalRejectedEmail(string $email, string $name, string $reason): void
        {
            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $safeReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
            $message = "<p>Olá, {$safeName}.</p><p>Seu cadastro profissional não foi aprovado.</p><p><strong>Motivo:</strong> {$safeReason}</p><p>Se necessário, entre em contato com o suporte para corrigir os dados enviados.</p>";
            $this->sendSystemEmail($email, 'Cadastro profissional não aprovado', $message);
        }

        public function sendSystemEmail(string $email, string $subject, string $message): void
        {
            try {
                if (!$this->send($email, $subject, $message, true)) {
                    error_log('MailerService failed for subject: ' . $subject);
                }
            } catch (Throwable $e) {
                error_log('MailerService error: ' . $e->getMessage());
            }
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
            $socket = $this->openSocket($transport . $host . ':' . $port, $errno, $errstr);

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
                    'Date: ' . date(DATE_RFC2822),
                    'Message-ID: ' . $this->messageId(),
                    'X-Mailer: JusTraduz',
                    'MIME-Version: 1.0',
                    'Content-Type: ' . $contentType,
                    'Content-Transfer-Encoding: 8bit',
                ];

                fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $this->escapeMessage($body) . "\r\n.\r\n");
                $this->lastSmtpResponse = $this->expect($socket, [250]);
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

        private function openSocket(string $address, ?int &$errno, ?string &$errstr)
        {
            $warning = null;
            set_error_handler(static function (int $level, string $message) use (&$warning): bool {
                $warning = $message;
                return true;
            });

            try {
                $socket = stream_socket_client($address, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
            } finally {
                restore_error_handler();
            }

            if (!$socket && $warning !== null && ($errstr === null || $errstr === '')) {
                $errstr = $warning;
            }

            return $socket;
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

        private function messageId(): string
        {
            $domain = substr(strrchr($this->fromAddress(), '@') ?: '@justraduz.local', 1) ?: 'justraduz.local';
            return '<' . bin2hex(random_bytes(16)) . '@' . preg_replace('/[^A-Za-z0-9.-]/', '', $domain) . '>';
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

        private function logMail(string $to, string $subject, string $transport, bool $sent, ?string $error): void
        {
            try {
                $pdo = database_connection();
                if (!database_table_has_column($pdo, 'mail_logs', 'recipient')) {
                    return;
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO mail_logs (recipient, subject, transport, status, error_message, created_at)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    mb_substr($to, 0, 190),
                    mb_substr($subject, 0, 190),
                    mb_substr($transport, 0, 40),
                    $sent ? 'sent' : 'failed',
                    $error,
                    date('Y-m-d H:i:s'),
                ]);
            } catch (Throwable $exception) {
                error_log('Mail log error: ' . $exception->getMessage());
            }
        }

        private function absoluteAppUrl(string $path): string
        {
            $env = function_exists('database_env_values')
                ? database_env_values(dirname(__DIR__, 2) . '/.env')
                : [];

            foreach (['APP_PUBLIC_URL', 'APP_URL'] as $key) {
                $configured = getenv($key);
                if ($configured === false || trim((string) $configured) === '') {
                    $configured = $env[$key] ?? '';
                }

                if (preg_match('#^https?://#i', (string) $configured)) {
                    return rtrim((string) $configured, '/') . '/' . ltrim($path, '/');
                }
            }

            $url = app_url($path);
            if (preg_match('#^https?://#i', $url)) {
                return $url;
            }

            $scheme = 'http';
            if (
                (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
                || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
            ) {
                $scheme = 'https';
            }

            $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
            if (in_array($forwardedProto, ['http', 'https'], true)) {
                $scheme = $forwardedProto;
            }

            $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $host = preg_replace('#^https?://#i', '', $host) ?: 'localhost';

            return $scheme . '://' . $host . $url;
        }
    }
}

namespace {
    if (!class_exists('MailerService')) {
        class_alias('App\Services\MailerService', 'MailerService');
    }
}
