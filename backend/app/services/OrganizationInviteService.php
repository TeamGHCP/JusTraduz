<?php

namespace App\Services;

use App\Services\MailerService;
use App\Services\NotificationService;
use App\Services\OrganizationService;
use PDO;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

class OrganizationInviteService
{
    public const OFFICE_INVITE_LIMIT = 5;

    public function __construct(private PDO $pdo)
    {
    }

    public function normalizeEmails(array|string|null $emails): array
    {
        $items = is_array($emails) ? $emails : preg_split('/[\s,;]+/', (string) $emails);
        $normalized = [];

        foreach ((array) $items as $email) {
            $email = strtolower(trim((string) $email));
            if ($email === '') {
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Informe apenas e-mails validos para os participantes do escritorio.');
            }

            $normalized[$email] = $email;
        }

        return array_values($normalized);
    }

    public function validateOfficeInviteRequest(array $plan, array|string|null $emails): array
    {
        if ((string) ($plan['slug'] ?? '') !== 'escritorio') {
            return [];
        }

        $emails = $this->normalizeEmails($emails);
        if (count($emails) > self::OFFICE_INVITE_LIMIT) {
            throw new InvalidArgumentException('O plano Escritorio permite ate ' . self::OFFICE_INVITE_LIMIT . ' participantes convidados.');
        }

        foreach ($emails as $email) {
            $existingUser = $this->fetchUserByEmail($email);
            if ($existingUser && (string) ($existingUser['tipo'] ?? '') !== 'advogado') {
                throw new InvalidArgumentException('O plano Escritorio permite convidar somente contas de advogados.');
            }
        }

        return $emails;
    }

    public function issueForOfficeSubscription(int $ownerUserId, array $subscription, array $emails): array
    {
        if ((string) ($subscription['plan_slug'] ?? '') !== 'escritorio') {
            return [];
        }

        $emails = array_slice($this->normalizeEmails($emails), 0, self::OFFICE_INVITE_LIMIT);
        if ($emails === [] || !OrganizationService::enabled($this->pdo) || !database_table_exists($this->pdo, 'organization_invites')) {
            return [];
        }

        $owner = $this->fetchUser($ownerUserId);
        if (!$owner || (string) ($owner['tipo'] ?? '') !== 'advogado') {
            return [];
        }

        $emails = $this->validateOfficeInviteRequest(['slug' => 'escritorio'], $emails);

        $organizationId = $this->ensureOfficeOrganization($owner);
        if ($organizationId <= 0) {
            return [];
        }

        $sent = [];
        foreach ($emails as $email) {
            if ($email === strtolower((string) ($owner['email'] ?? ''))) {
                continue;
            }

            $token = bin2hex(random_bytes(24));
            $expiresAt = (new DateTimeImmutable('+14 days'))->format('Y-m-d H:i:s');
            $this->upsertInvite($organizationId, $email, $token, $ownerUserId, $expiresAt);
            $invite = $this->pendingInviteByToken($token);
            if (!$invite) {
                continue;
            }

            $emailSent = $this->sendInviteEmail($email, $owner, $invite, $token);
            $this->notifyExistingUser($email, $owner, $organizationId);
            if ($emailSent) {
                $sent[] = $email;
            } else {
                error_log('Organization invite mail failed for ' . $email);
            }
        }

        return $sent;
    }

    public function acceptToken(string $token, ?int $currentUserId = null): array
    {
        $token = trim($token);
        $invite = $this->pendingInviteByToken($token);
        if (!$invite) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        if (strtotime((string) $invite['expires_at']) < time()) {
            $this->markInvite((int) $invite['id'], 'expired');
            return ['ok' => false, 'reason' => 'expired', 'email' => (string) $invite['email']];
        }

        if ($currentUserId === null || $currentUserId <= 0) {
            $existingUser = $this->fetchUserByEmail((string) $invite['email']);
            if ($existingUser && (string) ($existingUser['tipo'] ?? '') !== 'advogado') {
                return ['ok' => false, 'reason' => 'lawyer_required', 'email' => (string) $invite['email']];
            }

            return [
                'ok' => false,
                'reason' => 'auth_required',
                'email' => (string) $invite['email'],
                'has_account' => $this->userIdByEmail((string) $invite['email']) > 0,
            ];
        }

        $user = $this->fetchUser($currentUserId);
        if (!$user || strtolower((string) ($user['email'] ?? '')) !== strtolower((string) $invite['email'])) {
            return ['ok' => false, 'reason' => 'wrong_user', 'email' => (string) $invite['email']];
        }
        if ((string) ($user['tipo'] ?? '') !== 'advogado') {
            return ['ok' => false, 'reason' => 'lawyer_required', 'email' => (string) $invite['email']];
        }

        $inviter = $this->fetchUser((int) ($invite['invited_by'] ?? 0));
        if (!$inviter || (string) ($inviter['tipo'] ?? '') !== 'advogado') {
            return ['ok' => false, 'reason' => 'invalid_inviter', 'email' => (string) $invite['email']];
        }

        $role = in_array((string) ($invite['role'] ?? 'member'), ['admin', 'member', 'viewer'], true)
            ? (string) $invite['role']
            : 'member';
        $this->addMember((int) $invite['organization_id'], $currentUserId, $role, (int) ($invite['invited_by'] ?? 0));
        $this->markInvite((int) $invite['id'], 'accepted');
        if (database_table_exists($this->pdo, 'notifications')) {
            (new NotificationService($this->pdo))->notify($currentUserId, 'Você agora faz parte do plano Escritório no JusTraduz.');
        }

        return ['ok' => true, 'organization_id' => (int) $invite['organization_id']];
    }

    public function acceptPendingForUser(int $userId): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $token = trim((string) ($_SESSION['pending_org_invite_token'] ?? ''));
        if ($token === '') {
            return null;
        }

        $result = $this->acceptToken($token, $userId);
        if (($result['ok'] ?? false) === true || !in_array((string) ($result['reason'] ?? ''), ['auth_required'], true)) {
            unset($_SESSION['pending_org_invite_token']);
        }

        return $result;
    }

    public function acceptPendingByUserId(int $userId): array
    {
        if (!OrganizationService::enabled($this->pdo) || !database_table_exists($this->pdo, 'organization_invites')) {
            return ['ok' => false, 'reason' => 'feature_disabled'];
        }

        $user = $this->fetchUser($userId);
        if (!$user || (string) ($user['tipo'] ?? '') !== 'advogado') {
            return ['ok' => false, 'reason' => 'invalid_user'];
        }

        $invite = $this->pendingInviteByEmail((string) ($user['email'] ?? ''));
        if (!$invite) {
            return ['ok' => false, 'reason' => 'no_pending_invite'];
        }

        $this->pdo->beginTransaction();
        try {
            $this->addMember((int) $invite['organization_id'], $userId, 'member', (int) ($invite['invited_by'] ?? 0));
            $this->markInvite((int) $invite['id'], 'accepted');
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->notifyOwnerOnAccept($invite, $user);
        return [
            'ok' => true,
            'organization_id' => (int) $invite['organization_id'],
            'invite_id' => (int) $invite['id']
        ];
    }

    public function pendingOfficeInviteRequirement(): ?array
    {
        $id = $this->currentUserId();
        if ($id <= 0 || !database_table_exists($this->pdo, 'organization_invites')) {
            return null;
        }

        $email = $this->fetchUserEmail($id);
        if ($email === '') {
            return null;
        }

        $invite = $this->pendingInviteByEmail($email);
        return $invite ?: null;
    }

    private function fetchUser(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, nome, email, tipo FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function fetchUserEmail(int $id): string
    {
        $stmt = $this->pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return (string) ($stmt->fetchColumn() ?: '');
    }

    private function fetchUserByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, nome, email, tipo FROM users WHERE email = ? AND status = "ativo" LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function ensureOfficeOrganization(array $owner): int
    {
        $ownerId = (int) ($owner['id'] ?? 0);
        $current = (new OrganizationService($this->pdo))->currentOrganizationId($ownerId);
        if ($current !== null) {
            return $current;
        }

        $name = trim((string) ($owner['nome'] ?? 'Escritorio JusTraduz'));
        $name = $name !== '' ? 'Escritório de ' . $name : 'Escritório JusTraduz';
        $organizationId = (new OrganizationService($this->pdo))->create($name, $ownerId);
        if ($organizationId > 0 && database_table_has_column($this->pdo, 'users', 'organization_id')) {
            $this->pdo->prepare('UPDATE users SET organization_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$organizationId, $ownerId]);
        }

        return $organizationId;
    }

    private function addMember(int $organizationId, int $userId, string $role, int $invitedBy): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->pdo->prepare(
                "INSERT INTO organization_members (organization_id, user_id, role, status, invited_by)
                 VALUES (?, ?, ?, 'active', ?)
                 ON CONFLICT(organization_id, user_id) DO UPDATE SET role = excluded.role, status = 'active'"
            )->execute([$organizationId, $userId, $role, $invitedBy ?: null]);
        } else {
            $this->pdo->prepare(
                "INSERT INTO organization_members (organization_id, user_id, role, status, invited_by)
                 VALUES (?, ?, ?, 'active', ?)
                 ON DUPLICATE KEY UPDATE role = VALUES(role), status = 'active', updated_at = CURRENT_TIMESTAMP"
            )->execute([$organizationId, $userId, $role, $invitedBy ?: null]);
        }

        if (database_table_has_column($this->pdo, 'users', 'organization_id')) {
            $this->pdo->prepare('UPDATE users SET organization_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$organizationId, $userId]);
        }
    }

    private function upsertInvite(int $organizationId, string $email, string $token, int $invitedBy, string $expiresAt): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $hash = password_hash($token, PASSWORD_DEFAULT);

        if ($driver === 'sqlite') {
            $this->pdo->prepare(
                "INSERT INTO organization_invites (organization_id, email, role, token_hash, status, invited_by, expires_at)
                 VALUES (?, ?, 'member', ?, 'pending', ?, ?)"
            )->execute([$organizationId, $email, $hash, $invitedBy, $expiresAt]);
            return;
        }

        $this->pdo->prepare(
            "UPDATE organization_invites
             SET status = 'revoked'
             WHERE organization_id = ? AND email = ? AND status = 'pending'"
        )->execute([$organizationId, $email]);

        $this->pdo->prepare(
            "INSERT INTO organization_invites (organization_id, email, role, token_hash, status, invited_by, expires_at)
             VALUES (?, ?, 'member', ?, 'pending', ?, ?)"
        )->execute([$organizationId, $email, $hash, $invitedBy, $expiresAt]);
    }

    private function pendingInviteByToken(string $token): ?array
    {
        if (!database_table_exists($this->pdo, 'organization_invites')) {
            return null;
        }

        $stmt = $this->pdo->query("SELECT * FROM organization_invites WHERE status = 'pending' ORDER BY id DESC");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $invite) {
            if (password_verify($token, (string) $invite['token_hash'])) {
                return $invite;
            }
        }

        return null;
    }

    private function pendingInviteByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM organization_invites WHERE email = ? AND status = "pending" AND expires_at >= ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$email, date('Y-m-d H:i:s')]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function markInvite(int $inviteId, string $status): void
    {
        $acceptedAt = $status === 'accepted' ? date('Y-m-d H:i:s') : null;
        $this->pdo->prepare('UPDATE organization_invites SET status = ?, accepted_at = ? WHERE id = ?')
            ->execute([$status, $acceptedAt, $inviteId]);
    }

    private function sendInviteEmail(string $email, array $owner, array $invite, string $token): bool
    {
        $ownerName = htmlspecialchars((string) ($owner['nome'] ?? 'Um advogado'), ENT_QUOTES, 'UTF-8');
        $link = htmlspecialchars($this->absoluteAppUrl('/backend/public/index.php?rota=/organization/invite&token=' . rawurlencode($token)), ENT_QUOTES, 'UTF-8');
        $logoPath = dirname(__DIR__, 3) . '/frontend/assets/img/email-logo.png';
        $subject = 'Convite para equipe no JusTraduz (Invitation to join the team at JusTraduz)';
        $message = <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background:#f3f6fa;color:#121212;font-family:Arial,Helvetica,sans-serif;">
  <div style="display:none;max-height:0;overflow:hidden;">{$ownerName} convidou voce para participar do plano Escritorio no JusTraduz. Aceitar convite. O convite expira em 14 dias.</div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#f3f6fa;">
    <tr>
      <td align="center" style="padding:30px 16px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:100%;max-width:610px;background:#ffffff;border:1px solid #d9e0e8;">
          <tr>
            <td align="center" style="padding:42px 40px 28px;">
              <a href="{$link}" target="_blank" style="display:inline-block;text-decoration:none;border:0;">
                <img src="cid:justraduz-logo" width="198" alt="JusTraduz" style="display:block;width:198px;max-width:100%;height:auto;border:0;margin:0 auto;">
              </a>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:0 40px 10px;color:#008f80;font-size:12px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;line-height:18px;">
              OFFICE PLAN
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:0 40px 22px;color:#121212;font-size:22px;font-weight:400;line-height:30px;">
              You received a team invitation.
            </td>
          </tr>
          <tr>
            <td style="padding:0 40px;">
              <div style="border-top:1px solid #d9dfe7;font-size:1px;line-height:1px;">&nbsp;</div>
            </td>
          </tr>
          <tr>
            <td style="padding:30px 40px 20px;color:#202124;font-size:14px;line-height:22px;text-align:left;">
              {$ownerName} invited you to participate in the Escritório plan on JusTraduz.
            </td>
          </tr>
          <tr>
            <td style="padding:0 40px 26px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#f8fafc;border:1px solid #dfe5ec;text-align:left;">
                <tr>
                  <td style="padding:16px 16px;border-bottom:1px solid #dfe5ec;color:#52627b;font-size:12px;font-weight:700;line-height:18px;text-transform:uppercase;">Invited by</td>
                  <td style="padding:16px 16px;border-bottom:1px solid #dfe5ec;text-align:right;color:#121212;font-size:13px;font-weight:700;line-height:18px;">{$ownerName}</td>
                </tr>
                <tr>
                  <td style="padding:16px 16px;color:#52627b;font-size:12px;font-weight:700;line-height:18px;text-transform:uppercase;">Validity</td>
                  <td style="padding:16px 16px;text-align:right;color:#121212;font-size:13px;font-weight:700;line-height:18px;">14 days</td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:0 40px 32px;">
              <a href="{$link}" target="_blank" style="display:inline-block;background:#008f80;border-radius:8px;color:#ffffff;font-size:14px;font-weight:700;line-height:20px;padding:13px 24px;text-decoration:none;">
                Accept invitation
              </a>
            </td>
          </tr>
          <tr>
            <td style="padding:0 40px 34px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#e8f1ff;border-radius:8px;text-align:left;">
        <tr>
          <td valign="top" style="padding:16px 14px 16px 16px;width:24px;">
            <div style="width:20px;height:20px;border-radius:10px;background:#1a73e8;color:#ffffff;font-size:14px;font-weight:700;line-height:20px;text-align:center;">i</div>
          </td>
          <td style="padding:16px 18px 16px 0;color:#0042a6;font-size:13px;line-height:20px;">If you already have an account, log in to accept the invitation. If you don't have one yet, the link will take you to the registration page using the invited email address.</td>
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

        $mailer = new MailerService();
        return $mailer->send($email, $subject, $message, true, [
            'justraduz-logo' => [
                'path' => $logoPath,
                'content_type' => 'image/png',
            ],
        ]);
    }

    private function notifyExistingUser(string $email, array $owner, int $organizationId): void
    {
        $userId = $this->userIdByEmail($email);
        if ($userId <= 0) {
            return;
        }
        if (!database_table_exists($this->pdo, 'notifications')) {
            return;
        }

        $notifications = new NotificationService($this->pdo);
        $notifications->notify(
            $userId,
            'Você recebeu um convite para participar do plano Escritório de ' . (string) ($owner['nome'] ?? 'um advogado') . '.'
        );
    }

    private function userIdByEmail(string $email): int
    {
        $user = $this->fetchUserByEmail($email);
        return (int) ($user['id'] ?? 0);
    }

    private function notifyOwnerOnAccept(array $invite, array $member): void
    {
        $ownerId = (int) ($invite['invited_by'] ?? 0);
        if ($ownerId <= 0) {
            return;
        }

        $memberName = (string) ($member['nome'] ?? 'Novo Advogado');
        $msg = "O advogado {$memberName} aceitou o seu convite e agora faz parte da sua equipe no plano Escritório.";

        $notifications = new NotificationService($this->pdo);
        $notifications->notify($ownerId, $msg);
    }

    private function absoluteAppUrl(string $path): string
    {
        if (str_starts_with($path, '/frontend/')) {
            $path = substr($path, strlen('/frontend'));
        }

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

        $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
        return $scheme . '://' . $host . '/' . ltrim($url, '/');
    }

    private function currentUserId(): int
    {
        secure_session_start();
        return (int) ($_SESSION['id'] ?? 0);
    }
}
