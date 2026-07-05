<?php

namespace App\Controllers {
    use App\Core\BaseController;
    use App\Middlewares\CsrfMiddleware;
    use App\Services\AuditService;
    use App\Services\AvatarService;
    use App\Services\LoginService;
    use App\Services\OrganizationInviteService;
    use App\Services\RegisterService;
    use App\Services\OAuthService;
    use App\Services\PasswordResetService;
    use App\Services\ProfileService;
    use App\Exceptions\ValidationException;
    use App\Core\RedirectException;
    use Throwable;

    class AuthController extends BaseController
    {
        private AuditService $audit;

        public function __construct()
        {
            parent::__construct();
            $this->audit = new AuditService($this->pdo);
        }

        public function registrar(): void
        {
            $this->startSession();
            try {
                $registerService = new RegisterService($this->pdo);
                $result = $registerService->registrar($this->request->all());

                $successMsg = $result['success_message'];
                $this->response->redirect(APP_URL . '/login.html?sucesso=' . urlencode($successMsg));
            } catch (ValidationException $e) {
                $this->response->redirectWithError(APP_URL . '/login.html?cadastro', $e->getMessage());
            }
        }

        public function login(): void
        {
            $this->startSession();
            $email = (string) $this->request->post('email', '');
            $senha = (string) $this->request->post('senha', '');

            try {
                $loginService = new LoginService($this->pdo);
                $result = $loginService->attemptLogin($email, $senha);

                if ($result['profile_pending']) {
                    $_SESSION['google_pending_user_id'] = (int) $result['user']['id'];
                    $this->response->redirect(APP_URL . '/completar-cadastro-google.php');
                    return;
                }

                secure_session_regenerate_now();
                $_SESSION['id']     = $result['user']['id'];
                $_SESSION['nome']   = $result['user']['nome'];
                $_SESSION['tipo']   = $result['user']['tipo'];
                $_SESSION['logado'] = true;
                CsrfMiddleware::generateToken();

                $destinos = [
                    'advogado'   => '/dashboard-advogado.php',
                    'admin'      => '/admin/dashboard-admin.php',
                    'cliente'    => '/dashboard-cliente.php',
                ];
                $destino = $destinos[$result['user']['tipo']] ?? '/dashboard-cliente.php';

                if ($result['invite_accepted']) {
                    $this->response->redirect(APP_URL . $destino . '?sucesso=' . urlencode('Convite aceito. Você agora faz parte do plano Escritório.'));
                    return;
                }
                $this->response->redirect(APP_URL . $destino);
            } catch (ValidationException $e) {
                $this->response->redirectWithError(APP_URL . '/login.html', $e->getMessage());
            }
        }

        public function googleRedirect(): void
        {
            $this->startSession();
            $oauthService = new OAuthService($this->pdo);
            $frontUrl = APP_URL . '/login.html';

            if (!$oauthService->isConfigured()) {
                $this->response->redirectWithError($frontUrl, 'Login com Google não configurado.');
            }

            $state = bin2hex(random_bytes(32));
            $nonce = bin2hex(random_bytes(32));

            $_SESSION['google_oauth_state'] = $state;
            $_SESSION['google_oauth_nonce'] = $nonce;

            $this->response->redirect($oauthService->getAuthorizationUrl($this->googleRedirectUri(), $state, $nonce));
        }

        public function googleCallback(): void
        {
            $this->startSession();
            $frontUrl = APP_URL . '/login.html';
            $state = (string) ($_GET['state'] ?? '');
            $code = (string) ($_GET['code'] ?? '');
            $expectedState = (string) ($_SESSION['google_oauth_state'] ?? '');
            $expectedNonce = (string) ($_SESSION['google_oauth_nonce'] ?? '');

            unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_nonce']);

            if (!empty($_GET['error'])) {
                $this->response->redirectWithError($frontUrl, 'Login com Google cancelado ou recusado.');
            }

            if ($state === '' || $code === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
                $this->response->redirectWithError($frontUrl, 'Sessão Google inválida. Tente novamente.');
            }

            try {
                $oauthService = new OAuthService($this->pdo);
                $usuario = $oauthService->handleCallback($code, $expectedNonce, $this->googleRedirectUri());

                if ((int) ($usuario['profile_completed'] ?? 1) !== 1) {
                    $_SESSION['google_pending_user_id'] = (int) $usuario['id'];
                    $this->audit->log('auth.google_profile_required', 'user', (int) $usuario['id'], ['email' => $usuario['email'] ?? null]);
                    $this->response->redirect(APP_URL . '/completar-cadastro-google.php');
                    return;
                }

                $this->signInUser($usuario);
                $this->audit->log('auth.google_login', 'user', (int) $usuario['id'], ['email' => $usuario['email'] ?? null]);
                $this->response->redirect(APP_URL . $this->dashboardPathFor((string) $usuario['tipo']));
            } catch (RedirectException $e) {
                throw $e;
            } catch (ValidationException $e) {
                $this->response->redirectWithError($frontUrl, $e->getMessage());
            } catch (Throwable $e) {
                error_log('Google OAuth error: ' . $e->getMessage());
                $this->response->redirectWithError($frontUrl, 'Não foi possível entrar com Google agora.');
            }
        }

        public function completeGoogleProfile(): void
        {
            $this->startSession();
            $pendingUserId = (int) ($_SESSION['google_pending_user_id'] ?? 0);
            $frontUrl = APP_URL . '/completar-cadastro-google.php';

            if ($pendingUserId <= 0) {
                $this->response->redirectWithError(APP_URL . '/login.html', 'Sessao Google expirada. Entre com Google novamente.');
            }

            try {
                $oauthService = new OAuthService($this->pdo);
                $usuario = $oauthService->completeProfile($pendingUserId, $this->request->all());

                unset($_SESSION['google_pending_user_id']);

                if ((string) ($usuario['oab_status'] ?? '') === 'pending') {
                    $this->response->redirectWithSuccess(APP_URL . '/login.html', 'Cadastro recebido. Seu acesso profissional aguardará aprovação interna.');
                    return;
                }

                $this->signInUser($usuario);
                $this->audit->log('auth.google_profile_completed', 'user', $pendingUserId, ['tipo' => $usuario['tipo']]);
                $this->response->redirect(APP_URL . $this->dashboardPathFor($usuario['tipo']));
            } catch (ValidationException $e) {
                $this->response->redirectWithError($frontUrl, $e->getMessage());
            }
        }

        public function csrf(): void
        {
            $this->startSession();
            $token = $_SESSION['_csrf_token'] ?? '';
            if ($token === '') {
                CsrfMiddleware::generateToken();
                $token = $_SESSION['_csrf_token'] ?? '';
            }
            $this->response->json([
                'csrf' => $token,
                'csrf_token' => $token
            ]);
        }

        public function forceLogout(): void
        {
            $this->startSession();
            $userId = (int) ($_SESSION['id'] ?? 0);
            if ($userId > 0) {
                $this->audit->log('auth.force_logout', 'user', $userId);
            }
            $this->destroySessionCookies();
            $this->response->redirect(APP_URL . '/login.html?erro=' . urlencode('Sua sessão foi encerrada por segurança.'));
        }

        public function adminLogin(): void
        {
            $this->startSession();
            $email = (string) $this->request->post('email', '');
            $senha = (string) $this->request->post('senha', '');

            try {
                $loginService = new LoginService($this->pdo);
                $result = $loginService->attemptAdminLogin($email, $senha);

                secure_session_regenerate_now();
                $_SESSION['id']     = $result['user']['id'];
                $_SESSION['nome']   = $result['user']['nome'];
                $_SESSION['tipo']   = $result['user']['tipo'];
                $_SESSION['logado'] = true;
                CsrfMiddleware::generateToken();

                $this->response->redirect(APP_URL . '/admin/dashboard-admin.php');
            } catch (ValidationException $e) {
                $this->response->redirectWithError(APP_URL . '/admin/login-admin.html', $e->getMessage());
            }
        }

        public function updateProfile(): void
        {
            $this->startSession();
            if (empty($_SESSION['logado'])) {
                $this->response->redirect(APP_URL . '/login.html?erro=' . urlencode('Faça login para continuar.'));
            }

            try {
                $avatarService = new AvatarService();
                $profilePhotoPath = $avatarService->handleProfilePhotoUpload((int) $_SESSION['id'], $_FILES['foto_perfil'] ?? null);

                $profileService = new ProfileService($this->pdo);
                $passwordUpdated = $profileService->updateProfile((int) $_SESSION['id'], $this->request->all(), $profilePhotoPath);

                $_SESSION['nome'] = trim((string) $this->request->post('nome', ''));
                if ($passwordUpdated) {
                    secure_session_regenerate_now();
                    unset($_SESSION['_csrf_token']);
                    CsrfMiddleware::generateToken();
                }

                $this->response->redirect(APP_URL . '/perfil.php?sucesso=' . urlencode('Perfil atualizado.'));
            } catch (ValidationException $e) {
                $this->response->redirect(APP_URL . '/perfil.php?erro=' . urlencode($e->getMessage()));
            }
        }

        public function resetPassword(): void
        {
            $action = (string) $this->request->post('acao', 'confirm_code');
            $email = (string) $this->request->post('email', '');
            $frontUrl = APP_URL . '/recuperar-senha.html';

            try {
                $resetService = new PasswordResetService($this->pdo);
                if ($action === 'request_code') {
                    $msg = $resetService->requestResetCode($email);
                    $this->response->redirectWithSuccess($frontUrl, $msg);
                    return;
                }

                $codigo = (string) $this->request->post('codigo', '');
                $senha = (string) $this->request->post('senha', '');
                $senha2 = (string) $this->request->post('senha2', '');

                $resetService->confirmResetCode($email, $codigo, $senha, $senha2);
                $this->response->redirect(APP_URL . '/login.html?sucesso=' . urlencode('Senha atualizada. Entre com a nova senha.'));
            } catch (ValidationException $e) {
                $this->response->redirectWithError($frontUrl, $e->getMessage());
            }
        }

        public function profilePasswordCode(): void
        {
            $this->startSession();
            if (empty($_SESSION['logado']) || empty($_SESSION['id'])) {
                $this->response->json(['success' => false, 'message' => 'Faça login para continuar.'], 401);
                return;
            }

            try {
                $resetService = new PasswordResetService($this->pdo);
                $msg = $resetService->requestProfileResetCode((int) $_SESSION['id']);
                $this->response->json(['success' => true, 'message' => $msg]);
            } catch (ValidationException $e) {
                $status = str_contains($e->getMessage(), 'Muitas solicitações') ? 429 : 422;
                $this->response->json(['success' => false, 'message' => $e->getMessage()], $status);
            }
        }

        public function profilePasswordReset(): void
        {
            $this->startSession();
            if (empty($_SESSION['logado']) || empty($_SESSION['id'])) {
                $this->response->json(['success' => false, 'message' => 'Faça login para continuar.'], 401);
                return;
            }

            $codigo = (string) $this->request->post('codigo', '');
            $senha = (string) $this->request->post('senha', '');
            $senha2 = (string) $this->request->post('senha2', '');

            try {
                $resetService = new PasswordResetService($this->pdo);
                $resetService->confirmProfileResetCode((int) $_SESSION['id'], $codigo, $senha, $senha2);

                $this->destroySessionCookies();
                $this->response->json([
                    'success' => true,
                    'message' => 'Senha atualizada com sucesso. Entre novamente com a nova senha.',
                    'redirect' => APP_URL . '/login.html?sucesso=' . urlencode('Senha atualizada. Entre com a nova senha.'),
                ]);
            } catch (ValidationException $e) {
                $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        }

        public function logout(): void
        {
            $this->startSession();
            $userId = (int) ($_SESSION['id'] ?? 0);
            if ($userId > 0) {
                $this->audit->log('auth.logout', 'user', $userId);
            }
            $this->destroySessionCookies();
            $this->response->redirect(APP_URL . '/login.html');
        }

        private function signInUser(array $usuario): void
        {
            secure_session_regenerate_now();
            $_SESSION['id']     = $usuario['id'];
            $_SESSION['nome']   = $usuario['nome'];
            $_SESSION['tipo']   = $usuario['tipo'];
            $_SESSION['logado'] = true;
            CsrfMiddleware::generateToken();

            if (class_exists('App\Services\OrganizationInviteService')) {
                $inviteService = new OrganizationInviteService($this->pdo);
                try {
                    $inviteService->acceptPendingByUserId((int) $usuario['id']);
                } catch (\Throwable $e) {
                    error_log('Error accepting pending invite on sign in: ' . $e->getMessage());
                }
            }
        }

        private function dashboardPathFor(string $tipo): string
        {
            $destinos = [
                'advogado' => '/dashboard-advogado.php',
                'admin'    => '/admin/dashboard-admin.php',
                'cliente'  => '/dashboard-cliente.php',
            ];
            return $destinos[$tipo] ?? '/dashboard-cliente.php';
        }

        private function googleRedirectUri(): string
        {
            $configuredUri = $this->envValue('GOOGLE_REDIRECT_URI');
            if ($configuredUri !== '') {
                return $configuredUri;
            }

            $scheme = 'http';
            if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
                $scheme = 'https';
            }

            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            return $scheme . '://' . $host . \app_url('/backend/public/index.php') . '?rota=/auth/google/callback';
        }

        private function envValue(string $key): string
        {
            $value = getenv($key);
            if ($value !== false) {
                return trim((string) $value);
            }

            $env = [];
            if (function_exists('database_env_values')) {
                $env = database_env_values(dirname(__DIR__, 2) . '/.env');
            }

            return trim((string) ($env[$key] ?? ''));
        }

        private function isValidCpf(?string $cpf): bool
        {
            return (new RegisterService($this->pdo))->isValidCpf($cpf);
        }

        private function passwordValidationError(string $password): ?string
        {
            return (new PasswordResetService($this->pdo))->passwordValidationError($password);
        }

        private function passwordHashAlgorithm()
        {
            return (new PasswordResetService($this->pdo))->passwordHashAlgorithm();
        }

        private function passwordHashOptions(): array
        {
            return (new PasswordResetService($this->pdo))->passwordHashOptions();
        }

        private function rehashUserPasswordIfNeeded(int $userId, string $plainPassword, string $currentHash): void
        {
            (new LoginService($this->pdo))->rehashUserPasswordIfNeeded($userId, $plainPassword, $currentHash);
        }

        private function profilePhotoStorage(): array
        {
            return (new AvatarService())->profilePhotoStorage();
        }

        private function isAbsolutePath(string $path): bool
        {
            return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1
                || str_starts_with($path, DIRECTORY_SEPARATOR)
                || str_starts_with($path, '\\\\');
        }

        private function destroySessionCookies(): void
        {
            \secure_session_destroy_current();
        }
    }
}

namespace {
    if (!class_exists('AuthController')) {
        class_alias('App\Controllers\AuthController', 'AuthController');
    }
}
