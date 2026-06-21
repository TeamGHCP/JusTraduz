<?php

require_once __DIR__ . '/MailerService.php';
require_once dirname(__DIR__) . '/config/app.php';

class BillingEmailService
{
    public function sendPlanPaid(array $user, array $subscription, int $amountCents): bool
    {
        $planName = (string) ($subscription['plan_name'] ?? 'Plano JusTraduz');
        $subject = 'Plano ' . $planName . ' confirmado - JusTraduz';
        $periodEnd = (string) ($subscription['current_period_end'] ?? '');
        $details = [
            'Plano' => $planName,
            'Valor' => $amountCents > 0 ? $this->money($amountCents) : 'Confirmado',
            'Status' => 'Ativo',
        ];

        if ($periodEnd !== '') {
            $details['Válido até'] = $this->date($periodEnd);
        }

        return $this->sendBillingEmail(
            $user,
            $subject,
            'Plano confirmado',
            'Seu plano está ativo',
            'Recebemos a confirmação do pagamento e sua assinatura JusTraduz já está liberada.',
            $details,
            'Ver faturamento'
        );
    }

    public function sendPlanCanceled(array $user, array $subscription): bool
    {
        $planName = (string) ($subscription['plan_name'] ?? 'Plano JusTraduz');

        return $this->sendBillingEmail(
            $user,
            'Plano cancelado - JusTraduz',
            'Plano cancelado',
            'Sua conta voltou para o modo gratuito',
            'Confirmamos o cancelamento da sua assinatura. Você ainda pode consultar seu histórico de faturas no perfil.',
            [
                'Plano anterior' => $planName,
                'Status' => 'Cancelado',
                'Data' => date('d/m/Y'),
            ],
            'Ver faturamento'
        );
    }

    public function sendPlanChanged(array $user, array $previousSubscription, array $newSubscription): bool
    {
        $previousPlanName = (string) ($previousSubscription['plan_name'] ?? 'Plano anterior');
        $newPlanName = (string) ($newSubscription['plan_name'] ?? 'Plano JusTraduz');
        $periodEnd = (string) ($newSubscription['current_period_end'] ?? '');
        $details = [
            'Plano anterior' => $previousPlanName,
            'Novo plano' => $newPlanName,
            'Status' => 'Substituido',
        ];

        if ($periodEnd !== '') {
            $details['Novo ciclo valido ate'] = $this->date($periodEnd);
        }

        return $this->sendBillingEmail(
            $user,
            'Plano alterado - JusTraduz',
            'Troca de plano',
            'Seu novo plano esta ativo',
            'Confirmamos a troca: sua assinatura anterior foi substituida e o novo plano ja esta liberado na sua conta.',
            $details,
            'Ver faturamento'
        );
    }

    private function sendBillingEmail(
        array $user,
        string $subject,
        string $eyebrow,
        string $title,
        string $intro,
        array $details,
        string $buttonLabel
    ): bool {
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $safeName = htmlspecialchars((string) ($user['nome'] ?? 'cliente'), ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeEyebrow = htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeIntro = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');
        $safeButton = htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8');
        $logoPath = dirname(__DIR__, 3) . '/frontend/assets/img/email-logo.png';
        $homeUrl = htmlspecialchars($this->absoluteAppUrl('/frontend/index.html'), ENT_QUOTES, 'UTF-8');
        $billingUrl = htmlspecialchars($this->absoluteAppUrl('/frontend/perfil.php?tab=faturamento'), ENT_QUOTES, 'UTF-8');
        $detailsRows = $this->detailsRows($details);

        $message = <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$safeSubject}</title>
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
            <td align="center" style="padding:0 40px 8px;color:#008f80;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;line-height:18px;">
              {$safeEyebrow}
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:0 40px 18px;color:#202124;font-size:22px;font-weight:400;line-height:28px;">
              {$safeTitle}
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
            <td style="padding:30px 40px 18px;color:#202124;font-size:15px;line-height:22px;">
              {$safeIntro}
            </td>
          </tr>
          <tr>
            <td style="padding:0 40px 26px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
                {$detailsRows}
              </table>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:0 40px 32px;">
              <a href="{$billingUrl}" target="_blank" style="display:inline-block;background:#008f80;border-radius:8px;color:#ffffff;font-size:14px;font-weight:700;line-height:20px;padding:13px 22px;text-decoration:none;">
                {$safeButton}
              </a>
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
                    Esta mensagem foi enviada automaticamente pela JusTraduz. Se você não reconhece esta alteração, acesse sua conta e fale com o suporte.
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

        return (new MailerService())->send($email, $subject, $message, true, [
            'justraduz-logo' => [
                'path' => $logoPath,
                'content_type' => 'image/png',
            ],
        ]);
    }

    private function detailsRows(array $details): string
    {
        $rows = '';
        foreach ($details as $label => $value) {
            $safeLabel = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
            $safeValue = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $rows .= '<tr>'
                . '<td style="padding:12px 16px;color:#64748b;font-size:13px;line-height:18px;border-bottom:1px solid #e2e8f0;">' . $safeLabel . '</td>'
                . '<td align="right" style="padding:12px 16px;color:#0f172a;font-size:13px;font-weight:700;line-height:18px;border-bottom:1px solid #e2e8f0;">' . $safeValue . '</td>'
                . '</tr>';
        }

        return $rows;
    }

    private function absoluteAppUrl(string $path): string
    {
        foreach (['APP_PUBLIC_URL', 'APP_URL'] as $key) {
            $configured = $this->env($key);
            if ($configured !== '' && preg_match('#^https?://#i', $configured)) {
                return rtrim($configured, '/') . '/' . ltrim($path, '/');
            }
        }

        $url = app_url($path);
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $basePath = trim($this->env('APP_BASE_PATH'), '/');
        if ($basePath !== '') {
            $normalizedBase = '/' . $basePath;
            if (!str_starts_with('/' . ltrim($url, '/'), $normalizedBase . '/')) {
                $url = $normalizedBase . '/' . ltrim($path, '/');
            }
        }

        $url = '/' . ltrim($url, '/');

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

    private function env(string $key): string
    {
        $value = getenv($key);
        if ($value !== false && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        $values = function_exists('database_env_values')
            ? database_env_values(dirname(__DIR__, 2) . '/.env')
            : [];

        return trim((string) ($values[$key] ?? ''));
    }

    private function money(int $cents): string
    {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }

    private function date(string $date): string
    {
        $timestamp = strtotime($date);
        return $timestamp ? date('d/m/Y', $timestamp) : $date;
    }
}
