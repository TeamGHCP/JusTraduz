<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/OnboardingService.php';
require_once dirname(__DIR__) . '/services/AuditService.php';

class OnboardingController extends BaseController
{
    private OnboardingService $onboarding;
    private AuditService $audit;

    public function __construct()
    {
        parent::__construct();
        $this->onboarding = new OnboardingService($this->pdo);
        $this->audit = new AuditService($this->pdo);
    }

    public function state(): void
    {
        $this->requireApiUser();
        [$tourKey, $tourVersion] = $this->tourIdentity();
        $state = $this->onboarding->state($this->currentUserId(), $tourKey, $tourVersion);
        $this->response->json(array_merge([
            'ok' => true,
            'tour_key' => $tourKey,
            'tour_version' => $tourVersion,
        ], $state));
    }

    public function start(): void
    {
        $this->requireApiUser();
        [$tourKey, $tourVersion, $profile] = $this->tourPayload();
        $step = $this->step();
        $manual = (string) $this->request->post('manual', '') === '1';
        $this->onboarding->start($this->currentUserId(), $tourKey, $tourVersion, $profile, $step, $manual);
        $this->audit('onboarding.start', $tourKey, $tourVersion, $profile);
        $this->response->json(['ok' => true]);
    }

    public function complete(): void
    {
        $this->requireApiUser();
        [$tourKey, $tourVersion, $profile] = $this->tourPayload();
        $this->onboarding->complete($this->currentUserId(), $tourKey, $tourVersion, $profile, $this->step());
        $this->audit('onboarding.complete', $tourKey, $tourVersion, $profile);
        $this->response->json(['ok' => true, 'status' => 'completed']);
    }

    public function skip(): void
    {
        $this->requireApiUser();
        [$tourKey, $tourVersion, $profile] = $this->tourPayload();
        $mode = (string) $this->request->post('skip_mode', '');
        if (!in_array($mode, ['remind_later', 'dont_show_again'], true)) {
            throw new RuntimeException('Modo de adiamento inválido.', 422);
        }

        $manual = (string) $this->request->post('manual', '') === '1';
        $this->onboarding->skip($this->currentUserId(), $tourKey, $tourVersion, $profile, $mode, $this->step(), $manual);
        $this->audit('onboarding.skip', $tourKey, $tourVersion, $profile, ['skip_mode' => $mode]);
        $this->response->json(['ok' => true, 'status' => $mode === 'remind_later' ? 'remind_later' : 'skipped']);
    }

    public function reset(): void
    {
        $this->requireApiUser();
        [$tourKey, $tourVersion] = $this->tourIdentity(true);
        $this->onboarding->reset($this->currentUserId(), $tourKey, $tourVersion);
        $this->audit('onboarding.reset', $tourKey, $tourVersion, $this->profile());
        $this->response->json(['ok' => true, 'status' => 'pending']);
    }

    private function requireApiUser(): void
    {
        $this->startSession();
        if (empty($_SESSION['logado']) || $this->currentUserId() <= 0) {
            $this->response->json(['ok' => false, 'error' => 'Faça login para continuar.'], 401);
            exit;
        }
    }

    private function tourIdentity(bool $fromPost = false): array
    {
        $source = $fromPost ? 'post' : 'get';
        $tourKey = $this->clean((string) $this->request->{$source}('tour_key', ''), 80, '/^[a-z0-9_]+$/');
        $version = $this->clean((string) $this->request->{$source}('tour_version', ''), 30, '/^[0-9A-Za-z._-]+$/');
        if ($tourKey === '' || $version === '') {
            throw new RuntimeException('Tour ou versao invalida.', 422);
        }
        return [$tourKey, $version];
    }

    private function tourPayload(): array
    {
        [$tourKey, $version] = $this->tourIdentity(true);
        return [$tourKey, $version, $this->profile()];
    }

    private function profile(): string
    {
        $profile = $this->clean((string) $this->request->post('dashboard_profile', $this->currentUserType()), 30, '/^[a-z_]+$/');
        $allowed = ['cliente', 'advogado', 'admin', 'estagiario'];
        if (!in_array($profile, $allowed, true) || $profile !== $this->currentUserType()) {
            throw new RuntimeException('Perfil inválido.', 422);
        }
        return $profile;
    }

    private function step(): int
    {
        return max(0, min(100, (int) $this->request->post('last_seen_step', 0)));
    }

    private function clean(string $value, int $limit, string $pattern): string
    {
        $value = trim($value);
        return strlen($value) <= $limit && preg_match($pattern, $value) === 1 ? $value : '';
    }

    private function audit(string $action, string $tourKey, string $version, string $profile, array $extra = []): void
    {
        try {
            $this->audit->log($action, 'onboarding', null, array_merge([
                'tour_key' => $tourKey,
                'tour_version' => $version,
                'dashboard_profile' => $profile,
            ], $extra));
        } catch (Throwable $e) {
            error_log('Onboarding audit failed: ' . $e->getMessage());
        }
    }
}
