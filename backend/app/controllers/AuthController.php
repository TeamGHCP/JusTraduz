<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/GoogleOAuthService.php';
require_once dirname(__DIR__) . '/services/MailerService.php';
require_once dirname(__DIR__) . '/middlewares/CsrfMiddleware.php';

class AuthController extends BaseController
{
    private AuditService $audit;

    public function __construct()
    {
        parent::__construct();
        $this->audit = new AuditService($this->pdo);
    }

    // -------------------------------------------------------
    // POST /auth/registrar
    // -------------------------------------------------------
    public function registrar(): void
    {
        $nome   = trim((string) $this->request->post('nome', ''));
        $email  = $this->normalizeEmail((string) $this->request->post('email', ''));
        $telefone = trim((string) $this->request->post('telefone', ''));
        $cpf = preg_replace('/\D+/', '', (string) $this->request->post('cpf', '')) ?? '';
        $senha  = trim((string) $this->request->post('senha', ''));
        $senha2 = trim((string) $this->request->post('senha2', ''));
        $tipo   = (string) $this->request->post('tipo', 'cliente');
        $oab    = preg_replace('/\D+/', '', (string) $this->request->post('inscricao', ''));
        $oab_uf = strtoupper(trim((string) $this->request->post('oab_uf', '')));
        $oab_status = 'not_required';
        $oab_parametro = null;
        $oab_verificado = false;
        $oab_tipo = null;
        $status_cna = null;

        $frontUrl = APP_URL . '/frontend/login.html?cadastro';

        // Validações
        if (!$nome || !$email || !$senha) {
            $this->response->redirectWithError($frontUrl, 'Preencha todos os campos obrigatórios.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->response->redirectWithError($frontUrl, 'Informe um e-mail válido.');
        }

        $telefone = preg_replace('/[^\d()+\-\s]/', '', $telefone) ?? '';
        if ($telefone === '' || strlen(preg_replace('/\D+/', '', $telefone) ?? '') < 10) {
            $this->response->redirectWithError($frontUrl, 'Informe um telefone válido com DDD.');
        }

        if (!in_array($tipo, ['cliente', 'advogado', 'estagiario'], true)) {
            $tipo = 'cliente';
        }

        if ($senha !== $senha2) {
            $this->response->redirectWithError($frontUrl, 'As senhas não coincidem.');
        }

        if (strlen($senha) < 6) {
            $this->response->redirectWithError($frontUrl, 'A senha deve ter no mínimo 6 caracteres.');
        }

        $isProfessional = in_array($tipo, ['advogado', 'estagiario'], true);
        if ($tipo === 'cliente') {
            if (strlen($cpf) !== 11) {
                $this->response->redirectWithError($frontUrl, 'Informe um CPF valido para consultar seus processos.');
            }
        } else {
            $cpf = null;
        }

        if ($isProfessional) {
            $validUfs = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

            if ($oab === '') {
                $this->response->redirectWithError($frontUrl, 'Numero da OAB e obrigatorio.');
            }

            if (!in_array($oab_uf, $validUfs, true)) {
                $this->response->redirectWithError($frontUrl, 'Informe a UF da OAB.');
            }

            $oab_status = 'pending';
            $oab_tipo = $tipo;
            $status_cna = 'pendente';
        } else {
            $oab = null;
            $oab_uf = null;
        }

        // Verifica se e-mail já existe
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $this->response->redirectWithError($frontUrl, 'E-mail já cadastrado.');
        }

        // Insere no banco
        $senhaCriptografada = password_hash($senha, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (nome, email, senha, tipo, telefone, cpf, oab, oab_uf, oab_status, oab_parametro, oab_verificado, oab_tipo, status_cna, oab_submitted_at, profile_completed)
                VALUES (:nome, :email, :senha, :tipo, :telefone, :cpf, :oab, :oab_uf, :oab_status, :oab_parametro, :oab_verificado, :oab_tipo, :status_cna, :oab_submitted_at, 1)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':nome'   => $nome,
                ':email'  => $email,
                ':senha'  => $senhaCriptografada,
                ':tipo'   => $tipo,
                ':telefone' => $telefone ?: null,
                ':cpf' => $cpf ?: null,
                ':oab'    => $oab,
                ':oab_uf' => $oab_uf,
                ':oab_status' => $oab_status,
                ':oab_parametro' => $oab_parametro,
                ':oab_verificado' => $oab_verificado ? 1 : 0,
                ':oab_tipo' => $oab_tipo,
                ':status_cna' => $status_cna,
                ':oab_submitted_at' => $isProfessional ? date('Y-m-d H:i:s') : null,
            ]);
            $userId = (int) $this->pdo->lastInsertId();
            $this->audit->log('auth.register', 'user', $userId, [
                'email' => $email,
                'tipo' => $tipo,
                'oab_verificado' => $oab_verificado,
            ]);
            if (in_array($tipo, ['advogado', 'estagiario'], true)) {
                $this->logOabValidation($userId, 'cadastro', null, 'pendente', 'admin_manual', $oab_status);
                $this->sendProfessionalPendingEmail($email, $nome, $tipo);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '42S22') {
                $this->response->redirectWithError(
                    $frontUrl,
                    'Banco de dados desatualizado. Importe um dos SQLs consolidados em database/.'
                );
            }

            throw $e;
        }

        $success = $isProfessional
            ? 'Cadastro recebido. Seu acesso profissional aguardara aprovacao interna.'
            : 'conta_criada';

        $this->response->redirect(APP_URL . '/frontend/login.html?sucesso=' . urlencode($success));
    }

    // -------------------------------------------------------
    // POST /auth/login
    // -------------------------------------------------------
    public function login(): void
    {
        $this->startSession();

        $email = $this->normalizeEmail((string) $this->request->post('email', ''));
        $senha = trim((string) $this->request->post('senha', ''));

        $frontUrl = APP_URL . '/frontend/login.html';

        if (!$email || !$senha) {
            $this->response->redirectWithError($frontUrl, 'Preencha todos os campos.');
        }

        if ($this->tooManyLoginFailures('auth.login_failed')) {
            $this->response->redirectWithError($frontUrl, 'Muitas tentativas recentes. Aguarde alguns minutos e tente novamente.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, nome, email, senha, tipo, oab_verificado, oab_status, status_cna, cna_ultimo_erro, oab_rejection_reason, profile_completed
             FROM users
             WHERE email = ? AND status = 'ativo'"
        );
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            $this->audit->log('auth.login_failed', 'user', null, ['email' => $email, 'reason' => 'not_found']);
            $this->response->redirectWithError($frontUrl, 'Credenciais inválidas.');
        }

        if (!password_verify($senha, $usuario['senha'])) {
            $this->audit->log('auth.login_failed', 'user', (int) $usuario['id'], ['email' => $email, 'reason' => 'wrong_password']);
            $this->response->redirectWithError($frontUrl, 'Credenciais inválidas.');
        }

        if ((int) ($usuario['profile_completed'] ?? 1) !== 1) {
            $_SESSION['google_pending_user_id'] = (int) $usuario['id'];
            $this->response->redirect(APP_URL . '/frontend/completar-cadastro-google.php');
        }

        $professionalBlockMessage = $this->professionalBlockMessage($usuario);
        if ($professionalBlockMessage !== null) {
            $this->audit->log('auth.login_failed', 'user', (int) $usuario['id'], ['email' => $email, 'reason' => 'oab_blocked']);
            $this->response->redirectWithError($frontUrl, $professionalBlockMessage);
        }

        // Cria sessão
        // Protege contra fixation e rotaciona token CSRF
        secure_session_regenerate_now();
        $_SESSION['id']     = $usuario['id'];
        $_SESSION['nome']   = $usuario['nome'];
        $_SESSION['tipo']   = $usuario['tipo'];
        $_SESSION['logado'] = true;
        CsrfMiddleware::generateToken();
        $this->audit->log('auth.login', 'user', (int) $usuario['id'], ['tipo' => $usuario['tipo']]);

        // Redireciona por tipo
        $destinos = [
            'advogado'   => '/frontend/dashboard-advogado.php',
            'estagiario' => '/frontend/dashboard-estagiario.php',
            'admin'      => '/frontend/admin/dashboard-admin.php',
            'cliente'    => '/frontend/dashboard-cliente.php',
        ];

        $destino = $destinos[$usuario['tipo']] ?? '/frontend/dashboard-cliente.php';
        $this->response->redirect(APP_URL . $destino);
    }

    // -------------------------------------------------------
    // GET /auth/google
    // -------------------------------------------------------
    public function googleRedirect(): void
    {
        $this->startSession();

        $google = new GoogleOAuthService();
        $frontUrl = APP_URL . '/frontend/login.html';

        if (!$google->isConfigured()) {
            $this->response->redirectWithError($frontUrl, 'Login com Google não configurado.');
        }

        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));

        $_SESSION['google_oauth_state'] = $state;
        $_SESSION['google_oauth_nonce'] = $nonce;

        $this->response->redirect($google->authorizationUrl($this->googleRedirectUri(), $state, $nonce));
    }

    // -------------------------------------------------------
    // GET /auth/google/callback
    // -------------------------------------------------------
    public function googleCallback(): void
    {
        $this->startSession();

        $frontUrl = APP_URL . '/frontend/login.html';
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
            $google = new GoogleOAuthService();
            if (!$google->isConfigured()) {
                $this->response->redirectWithError($frontUrl, 'Login com Google não configurado.');
            }

            $token = $google->fetchToken($code, $this->googleRedirectUri());
            $claims = $google->validateIdToken((string) ($token['id_token'] ?? ''), $expectedNonce);
            $userInfo = [];
            try {
                $userInfo = $google->fetchUserInfo((string) ($token['access_token'] ?? ''));
            } catch (Throwable $userInfoError) {
                error_log('Google userinfo error: ' . $userInfoError->getMessage());
            }
            if ($userInfo !== []) {
                $claims = array_merge($claims, array_filter([
                    'name' => $userInfo['name'] ?? null,
                    'email' => $userInfo['email'] ?? null,
                    'picture' => $userInfo['picture'] ?? null,
                ], static fn ($value) => $value !== null && $value !== ''));
            }
            $usuario = $this->findOrCreateGoogleUser($claims);

            if ((int) ($usuario['profile_completed'] ?? 1) !== 1) {
                $_SESSION['google_pending_user_id'] = (int) $usuario['id'];
                $this->audit->log('auth.google_profile_required', 'user', (int) $usuario['id'], ['email' => $usuario['email'] ?? null]);
                $this->response->redirect(APP_URL . '/frontend/completar-cadastro-google.php');
            }

            $professionalBlockMessage = $this->professionalBlockMessage($usuario);
            if ($professionalBlockMessage !== null) {
                $this->audit->log('auth.google_login_blocked', 'user', (int) $usuario['id'], ['email' => $usuario['email'] ?? null]);
                $this->response->redirectWithError($frontUrl, $professionalBlockMessage);
            }

            $this->signInUser($usuario);
            $this->audit->log('auth.google_login', 'user', (int) $usuario['id'], ['email' => $usuario['email'] ?? null]);
            $this->response->redirect(APP_URL . $this->dashboardPathFor((string) $usuario['tipo']));
        } catch (PDOException $e) {
            if ($e->getCode() === '42S22') {
                $this->response->redirectWithError($frontUrl, 'Banco desatualizado. Importe um dos SQLs consolidados em database/.');
            }

            throw $e;
        } catch (RedirectException $e) {
            throw $e;
        } catch (Throwable $e) {
            error_log('Google OAuth error: ' . $e->getMessage());
            $this->response->redirectWithError($frontUrl, 'Não foi possível entrar com Google agora.');
        }
    }

    // -------------------------------------------------------
    // POST /auth/google/complete-profile
    // -------------------------------------------------------
    public function completeGoogleProfile(): void
    {
        $this->startSession();

        $pendingUserId = (int) ($_SESSION['google_pending_user_id'] ?? 0);
        $frontUrl = APP_URL . '/frontend/completar-cadastro-google.php';

        if ($pendingUserId <= 0) {
            $this->response->redirectWithError(APP_URL . '/frontend/login.html', 'Sessao Google expirada. Entre com Google novamente.');
        }

        $tipo = (string) $this->request->post('tipo', '');
        $telefone = preg_replace('/[^\d()+\-\s]/', '', trim((string) $this->request->post('telefone', ''))) ?? '';
        $cpf = preg_replace('/\D+/', '', (string) $this->request->post('cpf', '')) ?? '';
        $oab = preg_replace('/\D+/', '', (string) $this->request->post('inscricao', '')) ?? '';
        $oabUf = strtoupper(trim((string) $this->request->post('oab_uf', '')));

        if (!in_array($tipo, ['cliente', 'advogado', 'estagiario'], true)) {
            $this->response->redirectWithError($frontUrl, 'Escolha o tipo de conta.');
        }

        if ($telefone === '' || strlen(preg_replace('/\D+/', '', $telefone) ?? '') < 10) {
            $this->response->redirectWithError($frontUrl, 'Informe um telefone valido com DDD.');
        }

        $validUfs = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
        $isProfessional = in_array($tipo, ['advogado', 'estagiario'], true);

        if ($tipo === 'cliente') {
            if (strlen($cpf) !== 11) {
                $this->response->redirectWithError($frontUrl, 'Informe um CPF valido para consultar seus processos.');
            }
            $oab = null;
            $oabUf = null;
            $oabStatus = 'not_required';
            $statusCna = null;
            $oabVerified = 0;
            $submittedAt = null;
        } else {
            $cpf = null;
            if ($oab === '') {
                $this->response->redirectWithError($frontUrl, 'Numero da OAB e obrigatorio.');
            }
            if (!in_array($oabUf, $validUfs, true)) {
                $this->response->redirectWithError($frontUrl, 'Informe a UF da OAB.');
            }
            $oabStatus = 'pending';
            $statusCna = 'pendente';
            $oabVerified = 0;
            $submittedAt = date('Y-m-d H:i:s');
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, nome, email, tipo, profile_completed
             FROM users
             WHERE id = ? AND status = 'ativo'
             LIMIT 1"
        );
        $stmt->execute([$pendingUserId]);
        $usuario = $stmt->fetch();

        if (!$usuario || (int) ($usuario['profile_completed'] ?? 1) === 1) {
            unset($_SESSION['google_pending_user_id']);
            $this->response->redirectWithError(APP_URL . '/frontend/login.html', 'Cadastro Google ja foi concluido.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE users
             SET tipo = ?, telefone = ?, cpf = ?, oab = ?, oab_uf = ?, oab_status = ?,
                 oab_verificado = ?, oab_tipo = ?, status_cna = ?, oab_submitted_at = ?,
                 profile_completed = 1, updated_at = NOW()
             WHERE id = ? AND profile_completed = 0'
        );
        $stmt->execute([
            $tipo,
            $telefone,
            $cpf ?: null,
            $oab,
            $oabUf,
            $oabStatus,
            $oabVerified,
            $isProfessional ? $tipo : null,
            $statusCna,
            $submittedAt,
            $pendingUserId,
        ]);

        unset($_SESSION['google_pending_user_id']);
        $usuario['tipo'] = $tipo;
        $usuario['telefone'] = $telefone;
        $usuario['oab_verificado'] = $oabVerified;
        $usuario['oab_status'] = $oabStatus;
        $usuario['status_cna'] = $statusCna;
        $usuario['profile_completed'] = 1;

        if ($isProfessional) {
            $this->logOabValidation($pendingUserId, 'google_cadastro', null, 'pendente', 'admin_manual', 'pending');
            $this->sendProfessionalPendingEmail((string) $usuario['email'], (string) $usuario['nome'], $tipo);
            $this->response->redirectWithSuccess(APP_URL . '/frontend/login.html', 'Cadastro recebido. Seu acesso profissional aguardara aprovacao interna.');
        }

        $this->signInUser($usuario);
        $this->audit->log('auth.google_profile_completed', 'user', $pendingUserId, ['tipo' => $tipo]);
        $this->response->redirect(APP_URL . $this->dashboardPathFor($tipo));
    }

    // -------------------------------------------------------
    // GET /auth/csrf
    // Returns current CSRF token as JSON for frontend injection
    // -------------------------------------------------------
    public function csrf(): void
    {
        $this->startSession();

        // Ensure token exists
        $token = CsrfMiddleware::token();

        $this->response->json(['csrf' => $token]);
    }

    // -------------------------------------------------------
    // POST /auth/force-logout
    // Destroys any existing session server-side and expires session cookies.
    // Returns JSON when requested by fetch (credentials include), otherwise redirects to login page.
    // -------------------------------------------------------
    public function forceLogout(): void
    {
        $this->startSession();

        $userId = (int) ($_SESSION['id'] ?? 0);
        if ($userId > 0) {
            $this->audit->log('auth.force_logout', 'user', $userId, []);
        }

        // Destroy session
        $this->destroySessionCookies();

        // If requested via fetch/ajax, return JSON. Otherwise redirect to login page.
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isAjax) {
            $this->response->json(['ok' => true]);
            return;
        }

        $this->response->redirect(APP_URL . '/frontend/login.html');
    }

    // -------------------------------------------------------
    // POST /auth/admin-login
    // -------------------------------------------------------
    public function adminLogin(): void
    {
        $this->startSession();

        $email = $this->normalizeEmail((string) $this->request->post('email', ''));
        $senha = trim((string) $this->request->post('senha', ''));

        $frontUrl = APP_URL . '/frontend/admin/login-admin.html';

        if (!$email || !$senha) {
            $this->response->redirectWithError($frontUrl, 'Preencha e-mail e senha.');
        }

        if ($this->tooManyLoginFailures('auth.admin_login_failed')) {
            $this->response->redirectWithError($frontUrl, 'Muitas tentativas recentes. Aguarde alguns minutos e tente novamente.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, nome, senha, tipo FROM users WHERE email = ? AND status = 'ativo' AND tipo = 'admin'"
        );
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify($senha, $usuario['senha'])) {
            $this->audit->log('auth.admin_login_failed', 'user', $usuario ? (int) $usuario['id'] : null, ['email' => $email]);
            $this->response->redirectWithError($frontUrl, 'Credenciais administrativas inválidas.');
        }

        $_SESSION['id']     = $usuario['id'];
        $_SESSION['nome']   = $usuario['nome'];
        $_SESSION['tipo']   = $usuario['tipo'];
        $_SESSION['logado'] = true;
        secure_session_regenerate_now();
        CsrfMiddleware::generateToken();
        $this->audit->log('auth.admin_login', 'user', (int) $usuario['id']);

        $this->response->redirect(APP_URL . '/frontend/admin/dashboard-admin.php');
    }

    public function updateProfile(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado'])) {
            $this->response->redirect(APP_URL . '/frontend/login.html?erro=' . urlencode('Faça login para continuar.'));
        }

        $nome = trim((string) $this->request->post('nome', ''));
        $email = $this->normalizeEmail((string) $this->request->post('email', ''));
        $telefone = trim((string) $this->request->post('telefone', ''));
        $cpf = preg_replace('/\D+/', '', (string) $this->request->post('cpf', '')) ?? '';
        $senhaAtual = trim((string) $this->request->post('senha_atual', ''));
        $novaSenha = trim((string) $this->request->post('nova_senha', ''));
        $novaSenha2 = trim((string) $this->request->post('nova_senha2', ''));
        $passwordUpdated = false;

        if (!$nome || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->response->redirect(APP_URL . '/frontend/perfil.php?erro=' . urlencode('Informe nome e e-mail válidos.'));
        }

        $currentType = (string) ($_SESSION['tipo'] ?? '');
        if ($currentType === 'cliente' && $cpf !== '' && strlen($cpf) !== 11) {
            $this->response->redirect(APP_URL . '/frontend/perfil.php?erro=' . urlencode('Informe um CPF valido.'));
        }

        if ($currentType !== 'cliente') {
            $cpf = '';
        }

        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
        $stmt->execute([$email, (int) $_SESSION['id']]);

        if ($stmt->fetch()) {
            $this->response->redirect(APP_URL . '/frontend/perfil.php?erro=' . urlencode('E-mail já cadastrado por outro usuário.'));
        }

        if ($novaSenha !== '' || $novaSenha2 !== '' || $senhaAtual !== '') {
            if (strlen($novaSenha) < 6) {
                $this->response->redirect(APP_URL . '/frontend/perfil.php?erro=' . urlencode('A nova senha deve ter no mínimo 6 caracteres.'));
            }

            if ($novaSenha !== $novaSenha2) {
                $this->response->redirect(APP_URL . '/frontend/perfil.php?erro=' . urlencode('As novas senhas não coincidem.'));
            }

            $stmt = $this->pdo->prepare('SELECT senha FROM users WHERE id = ?');
            $stmt->execute([(int) $_SESSION['id']]);
            $hash = (string) $stmt->fetchColumn();

            if (!password_verify($senhaAtual, $hash)) {
                $this->response->redirect(APP_URL . '/frontend/perfil.php?erro=' . urlencode('Senha atual incorreta.'));
            }

            $this->updateUserPassword((int) $_SESSION['id'], $novaSenha);
            $passwordUpdated = true;
        }

        $profilePhotoPath = $this->handleProfilePhotoUpload((int) $_SESSION['id']);

        if ($profilePhotoPath !== null) {
            $stmt = $this->pdo->prepare('SELECT foto_perfil FROM users WHERE id = ?');
            $stmt->execute([(int) $_SESSION['id']]);
            $oldPhoto = (string) ($stmt->fetchColumn() ?: '');

            $stmt = $this->pdo->prepare('UPDATE users SET nome = ?, email = ?, telefone = ?, cpf = ?, foto_perfil = ? WHERE id = ?');
            $stmt->execute([$nome, $email, $telefone ?: null, $cpf ?: null, $profilePhotoPath, (int) $_SESSION['id']]);
            $this->deleteOldProfilePhoto($oldPhoto, $profilePhotoPath);
        } else {
            $stmt = $this->pdo->prepare('UPDATE users SET nome = ?, email = ?, telefone = ?, cpf = ? WHERE id = ?');
            $stmt->execute([$nome, $email, $telefone ?: null, $cpf ?: null, (int) $_SESSION['id']]);
        }

        $_SESSION['nome'] = $nome;
        if ($passwordUpdated) {
            secure_session_regenerate_now();
            unset($_SESSION['_csrf_token']);
            CsrfMiddleware::generateToken();
        }

        $this->audit->log('profile.update', 'user', (int) $_SESSION['id'], [
            'email' => $email,
            'telefone_informado' => $telefone !== '',
            'cpf_informado' => $cpf !== '',
            'foto_atualizada' => $profilePhotoPath !== null,
            'senha_atualizada' => $passwordUpdated,
        ]);

        $this->response->redirect(APP_URL . '/frontend/perfil.php?sucesso=' . urlencode('Perfil atualizado.'));
    }

    private function handleProfilePhotoUpload(int $userId): ?string
    {
        $file = $_FILES['foto_perfil'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $this->response->redirect(APP_URL . '/frontend/perfil.php?erro=' . urlencode('Não foi possível enviar a foto.'));
        }

        if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
            $this->response->redirect(APP_URL . '/frontend/perfil.php?erro=' . urlencode('A foto deve ter no máximo 2 MB.'));
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $mime = '';
        if (is_file($tmpPath) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? (string) finfo_file($finfo, $tmpPath) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
        }

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($extensions[$mime])) {
            $this->response->redirect(APP_URL . '/frontend/perfil.php?erro=' . urlencode('Envie uma imagem JPG, PNG ou WebP.'));
        }

        $projectRoot = dirname(__DIR__, 3);
        $relativeDir = 'backend/storage/profile_photos';
        $targetDir = $projectRoot . '/' . $relativeDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Não foi possível criar a pasta de fotos de perfil.');
        }

        $filename = $userId . '_' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
        $targetPath = $targetDir . '/' . $filename;

        if (!move_uploaded_file($tmpPath, $targetPath)) {
            $this->response->redirect(APP_URL . '/frontend/perfil.php?erro=' . urlencode('Não foi possível salvar a foto.'));
        }

        return $relativeDir . '/' . $filename;
    }

    private function deleteOldProfilePhoto(string $oldPhoto, string $newPhoto): void
    {
        if ($oldPhoto === '' || $oldPhoto === $newPhoto) {
            return;
        }

        $projectRoot = dirname(__DIR__, 3);
        $baseDir = realpath($projectRoot . '/backend/storage/profile_photos');
        $oldPath = realpath($projectRoot . '/' . ltrim($oldPhoto, '/'));

        if ($baseDir && $oldPath && str_starts_with($oldPath, $baseDir) && is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    public function resetPassword(): void
    {
        $action = (string) $this->request->post('acao', 'confirm_code');
        $email = $this->normalizeEmail((string) $this->request->post('email', ''));
        $frontUrl = APP_URL . '/frontend/recuperar-senha.html';

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->response->redirectWithError($frontUrl, 'Informe um e-mail válido.');
        }

        if ($action === 'request_code') {
            $this->sendPasswordResetCode($email, $frontUrl);
            return;
        }

        $codigo = preg_replace('/\D+/', '', (string) $this->request->post('codigo', '')) ?? '';
        $senha = trim((string) $this->request->post('senha', ''));
        $senha2 = trim((string) $this->request->post('senha2', ''));

        if (strlen($codigo) !== 6) {
            $this->response->redirectWithError($frontUrl, 'Informe o código de 6 dígitos enviado por e-mail.');
        }

        if (strlen($senha) < 6) {
            $this->response->redirectWithError($frontUrl, 'A nova senha deve ter no mínimo 6 caracteres.');
        }

        if ($senha !== $senha2) {
            $this->response->redirectWithError($frontUrl, 'As senhas não coincidem.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT pr.id AS reset_id, pr.code_hash, pr.attempts, u.id AS user_id
             FROM password_reset_codes pr
             INNER JOIN users u ON u.id = pr.user_id
             WHERE pr.email = ?
             AND u.status = 'ativo'
             AND pr.used_at IS NULL
             AND pr.expires_at >= NOW()
             ORDER BY pr.created_at DESC
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $this->response->redirectWithError($frontUrl, 'Código inválido ou expirado. Solicite um novo código.');
        }

        if ((int) ($reset['attempts'] ?? 0) >= 5) {
            $this->response->redirectWithError($frontUrl, 'Muitas tentativas incorretas. Solicite um novo código.');
        }

        if (!password_verify($codigo, (string) $reset['code_hash'])) {
            $stmt = $this->pdo->prepare('UPDATE password_reset_codes SET attempts = attempts + 1 WHERE id = ?');
            $stmt->execute([(int) $reset['reset_id']]);
            $this->response->redirectWithError($frontUrl, 'Código incorreto.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->updateUserPassword((int) $reset['user_id'], $senha);

            $stmt = $this->pdo->prepare('UPDATE password_reset_codes SET used_at = NOW() WHERE id = ?');
            $stmt->execute([(int) $reset['reset_id']]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->audit->log('auth.password_reset', 'user', (int) $reset['user_id'], ['email' => $email]);

        $this->response->redirect(APP_URL . '/frontend/login.html?sucesso=' . urlencode('Senha atualizada. Entre com a nova senha.'));
    }

    public function profilePasswordCode(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado']) || empty($_SESSION['id'])) {
            $this->response->json(['success' => false, 'message' => 'Faça login para continuar.'], 401);
            return;
        }

        $stmt = $this->pdo->prepare("SELECT id, nome, email FROM users WHERE id = ? AND status = 'ativo'");
        $stmt->execute([(int) $_SESSION['id']]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->response->json(['success' => false, 'message' => 'Conta ativa não encontrada.'], 404);
            return;
        }

        if ($this->tooManyPasswordResetRequests((int) $user['id'], (string) $user['email'])) {
            $this->response->json(['success' => false, 'message' => 'Muitas solicitações recentes. Aguarde alguns minutos antes de pedir outro código.'], 429);
            return;
        }

        if (!$this->issuePasswordResetCode($user)) {
            $this->response->json(['success' => false, 'message' => 'Não foi possível enviar o e-mail agora. Verifique a configuração de e-mail do servidor.'], 500);
            return;
        }

        $this->audit->log('profile.password_reset_code_sent', 'user', (int) $user['id'], ['email' => $user['email']]);
        $this->response->json(['success' => true, 'message' => 'Código enviado por e-mail. Ele expira em 15 minutos.']);
    }

    public function profilePasswordReset(): void
    {
        $this->startSession();

        if (empty($_SESSION['logado']) || empty($_SESSION['id'])) {
            $this->response->json(['success' => false, 'message' => 'Faça login para continuar.'], 401);
            return;
        }

        $codigo = preg_replace('/\D+/', '', (string) $this->request->post('codigo', '')) ?? '';
        $senha = trim((string) $this->request->post('senha', ''));
        $senha2 = trim((string) $this->request->post('senha2', ''));

        if (strlen($codigo) !== 6) {
            $this->response->json(['success' => false, 'message' => 'Informe o código de 6 dígitos enviado por e-mail.'], 422);
            return;
        }

        if (strlen($senha) < 6) {
            $this->response->json(['success' => false, 'message' => 'A nova senha deve ter no mínimo 6 caracteres.'], 422);
            return;
        }

        if ($senha !== $senha2) {
            $this->response->json(['success' => false, 'message' => 'As senhas não coincidem.'], 422);
            return;
        }

        $stmt = $this->pdo->prepare(
            "SELECT pr.id AS reset_id, pr.code_hash, pr.attempts, u.id AS user_id, u.email
             FROM password_reset_codes pr
             INNER JOIN users u ON u.id = pr.user_id
             WHERE pr.user_id = ?
             AND u.status = 'ativo'
             AND pr.used_at IS NULL
             AND pr.expires_at >= NOW()
             ORDER BY pr.created_at DESC
             LIMIT 1"
        );
        $stmt->execute([(int) $_SESSION['id']]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $this->response->json(['success' => false, 'message' => 'Código inválido ou expirado. Solicite um novo código.'], 422);
            return;
        }

        if ((int) ($reset['attempts'] ?? 0) >= 5) {
            $this->response->json(['success' => false, 'message' => 'Muitas tentativas incorretas. Solicite um novo código.'], 429);
            return;
        }

        if (!password_verify($codigo, (string) $reset['code_hash'])) {
            $stmt = $this->pdo->prepare('UPDATE password_reset_codes SET attempts = attempts + 1 WHERE id = ?');
            $stmt->execute([(int) $reset['reset_id']]);
            $this->response->json(['success' => false, 'message' => 'Código incorreto.'], 422);
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $this->updateUserPassword((int) $reset['user_id'], $senha);

            $stmt = $this->pdo->prepare('UPDATE password_reset_codes SET used_at = NOW() WHERE id = ?');
            $stmt->execute([(int) $reset['reset_id']]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->audit->log('profile.password_reset', 'user', (int) $reset['user_id'], ['email' => $reset['email']]);
        $this->destroySessionCookies();
        $this->response->json([
            'success' => true,
            'message' => 'Senha atualizada com sucesso. Entre novamente com a nova senha.',
            'redirect' => APP_URL . '/frontend/login.html?sucesso=' . urlencode('Senha atualizada. Entre com a nova senha.'),
        ]);
    }

    private function sendPasswordResetCode(string $email, string $frontUrl): void
    {
        $stmt = $this->pdo->prepare("SELECT id, nome, email FROM users WHERE email = ? AND status = 'ativo'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->response->redirectWithSuccess($frontUrl, 'Se o e-mail estiver cadastrado, enviaremos um código de recuperação.');
        }

        if ($this->tooManyPasswordResetRequests((int) $user['id'], $email)) {
            $this->response->redirectWithError($frontUrl, 'Muitas solicitações recentes. Aguarde alguns minutos antes de pedir outro código.');
        }

        if (!$this->issuePasswordResetCode($user)) {
            $this->response->redirectWithError($frontUrl, 'Não foi possível enviar o e-mail agora. Verifique a configuração de e-mail do servidor.');
        }

        $this->audit->log('auth.password_reset_code_sent', 'user', (int) $user['id'], ['email' => $email]);
        $this->response->redirectWithSuccess($frontUrl, 'Código enviado por e-mail. Ele expira em 15 minutos.');
    }

    private function issuePasswordResetCode(array $user): bool
    {
        $code = (string) random_int(100000, 999999);
        $email = (string) $user['email'];

        $stmt = $this->pdo->prepare('UPDATE password_reset_codes SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
        $stmt->execute([(int) $user['id']]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO password_reset_codes (user_id, email, code_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))'
        );
        $stmt->execute([(int) $user['id'], $email, password_hash($code, PASSWORD_DEFAULT)]);

        return $this->sendPasswordResetEmail($email, (string) $user['nome'], $code);
    }

    private function tooManyLoginFailures(string $action): bool
    {
        $ip = mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        if ($ip === '') {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM audit_logs
             WHERE action = ?
             AND ip_address = ?
             AND created_at >= ?'
        );
        $stmt->execute([$action, $ip, date('Y-m-d H:i:s', time() - 900)]);

        return (int) $stmt->fetchColumn() >= 10;
    }

    private function tooManyPasswordResetRequests(int $userId, string $email): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM password_reset_codes
             WHERE user_id = ?
             AND email = ?
             AND created_at >= ?'
        );
        $stmt->execute([$userId, $email, date('Y-m-d H:i:s', time() - 900)]);

        return (int) $stmt->fetchColumn() >= 3;
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function updateUserPassword(int $userId, string $plainPassword): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET senha = ? WHERE id = ? AND status = 'ativo'");
        $stmt->execute([password_hash($plainPassword, PASSWORD_DEFAULT), $userId]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Não foi possível atualizar a senha da conta ativa.');
        }
    }

    private function destroySessionCookies(): void
    {
        secure_session_destroy_current();
    }

    private function sendPasswordResetEmail(string $email, string $name, string $code): bool
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
              Este código expira em 15 minutos. Se você não solicitou a recuperação de senha, ignore este e-mail.
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

        return (new MailerService())->send($email, $subject, $message, true, [
            'justraduz-logo' => [
                'path' => $logoPath,
                'content_type' => 'image/png',
            ],
        ]);
    }

    private function absoluteAppUrl(string $path): string
    {
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

    private function findOrCreateGoogleUser(array $claims): array
    {
        $googleSub = (string) $claims['sub'];
        $email = strtolower(trim((string) $claims['email']));
        $nome = trim((string) ($claims['name'] ?? ''));
        $picture = $this->normalizeGooglePictureUrl(trim((string) ($claims['picture'] ?? '')));

        if ($nome === '') {
            $nome = explode('@', $email)[0] ?: 'Usuário Google';
        }

        $stmt = $this->pdo->prepare("SELECT id, nome, email, tipo, oab_verificado, oab_status, status_cna, cna_ultimo_erro, oab_rejection_reason, profile_completed FROM users WHERE google_sub = ? AND status = 'ativo' LIMIT 1");
        $stmt->execute([$googleSub]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            $this->updateGoogleProfile((int) $usuario['id'], $googleSub, $picture);
            return $usuario;
        }

        $stmt = $this->pdo->prepare("SELECT id, nome, email, tipo, oab_verificado, oab_status, status_cna, cna_ultimo_erro, oab_rejection_reason, profile_completed FROM users WHERE email = ? AND status = 'ativo' LIMIT 1");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            $this->updateGoogleProfile((int) $usuario['id'], $googleSub, $picture);
            return $usuario;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO users (nome, email, senha, tipo, google_sub, google_picture, google_linked_at, provider, oab_status, profile_completed, email_verified_at, status)
             VALUES (?, ?, ?, 'cliente', ?, ?, NOW(), 'google', 'not_required', 0, NOW(), 'ativo')"
        );
        $stmt->execute([
            mb_substr($nome, 0, 100),
            $email,
            password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            $googleSub,
            $picture !== '' ? mb_substr($picture, 0, 255) : null,
        ]);

        $userId = (int) $this->pdo->lastInsertId();
        $this->audit->log('auth.google_register', 'user', $userId, ['email' => $email]);

        return [
            'id' => $userId,
            'nome' => mb_substr($nome, 0, 100),
            'email' => $email,
            'tipo' => 'cliente',
            'oab_verificado' => 0,
            'oab_status' => 'not_required',
            'status_cna' => null,
            'profile_completed' => 0,
        ];
    }

    private function updateGoogleProfile(int $userId, string $googleSub, string $picture): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users
             SET google_sub = ?,
                 google_picture = ?,
                 google_linked_at = COALESCE(google_linked_at, NOW()),
                 provider = COALESCE(provider, 'google'),
                 email_verified_at = COALESCE(email_verified_at, NOW()),
                 updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$googleSub, $picture !== '' ? mb_substr($picture, 0, 255) : null, $userId]);
    }

    private function normalizeGooglePictureUrl(string $picture): string
    {
        if ($picture === '' || !preg_match('#^https://#i', $picture)) {
            return '';
        }

        if (str_contains($picture, '/a/default-user')) {
            return '';
        }

        return (string) preg_replace('/=s\d+(?:-c)?$/', '=s160-c', $picture);
    }

    private function signInUser(array $usuario): void
    {
        secure_session_regenerate_now();
        $_SESSION['id'] = $usuario['id'];
        $_SESSION['nome'] = $usuario['nome'];
        $_SESSION['tipo'] = $usuario['tipo'];
        $_SESSION['logado'] = true;
        CsrfMiddleware::generateToken();
    }

    private function dashboardPathFor(string $tipo): string
    {
        $destinos = [
            'advogado' => '/frontend/dashboard-advogado.php',
            'estagiario' => '/frontend/dashboard-estagiario.php',
            'admin' => '/frontend/admin/dashboard-admin.php',
            'cliente' => '/frontend/dashboard-cliente.php',
        ];

        return $destinos[$tipo] ?? '/frontend/dashboard-cliente.php';
    }

    private function professionalBlockMessage(array $usuario): ?string
    {
        $tipo = (string) ($usuario['tipo'] ?? '');
        if (!in_array($tipo, ['advogado', 'estagiario'], true)) {
            return null;
        }

        if ((int) ($usuario['oab_verificado'] ?? 0) === 1) {
            return null;
        }

        $status = (string) (($usuario['oab_status'] ?? '') ?: ($usuario['status_cna'] ?? 'pending'));
        if (in_array($status, ['rejected', 'invalido', 'nao_encontrado'], true)) {
            $reason = trim((string) (($usuario['oab_rejection_reason'] ?? '') ?: ($usuario['cna_ultimo_erro'] ?? '')));
            return 'Seu cadastro profissional nao foi aprovado.' . ($reason !== '' ? ' Motivo: ' . $reason : '');
        }

        return 'Seu cadastro profissional esta aguardando aprovacao do administrador interno. Voce recebera um e-mail quando for aprovado.';
    }

    private function sendProfessionalPendingEmail(string $email, string $name, string $type): void
    {
        $subject = 'Cadastro recebido - aguardando validacao da OAB';
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeType = $type === 'estagiario' ? 'estagiario' : 'advogado';
        $message = "<p>Ola, {$safeName}.</p><p>Recebemos seu cadastro como {$safeType}. O acesso profissional ao JusTraduz depende da aprovacao do administrador interno apos validacao da OAB/registro informado.</p><p>Voce recebera um e-mail quando a revisao for concluida.</p>";
        $this->sendSystemEmail($email, $subject, $message);
    }

    private function sendProfessionalApprovedEmail(string $email, string $name): void
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $message = "<p>Ola, {$safeName}.</p><p>Seu cadastro profissional foi aprovado no JusTraduz. O acesso profissional esta liberado.</p>";
        $this->sendSystemEmail($email, 'Cadastro aprovado no JusTraduz', $message);
    }

    private function sendProfessionalRejectedEmail(string $email, string $name, string $reason): void
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
        $message = "<p>Ola, {$safeName}.</p><p>Seu cadastro profissional nao foi aprovado.</p><p><strong>Motivo:</strong> {$safeReason}</p><p>Se necessario, entre em contato com o suporte para corrigir os dados enviados.</p>";
        $this->sendSystemEmail($email, 'Cadastro profissional nao aprovado', $message);
    }

    private function sendSystemEmail(string $email, string $subject, string $message): void
    {
        try {
            if (!(new MailerService())->send($email, $subject, $message, true)) {
                error_log('MailerService failed for subject: ' . $subject);
            }
        } catch (Throwable $e) {
            error_log('MailerService error: ' . $e->getMessage());
        }
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

        return $scheme . '://' . $host . app_url('/backend/public/index.php') . '?rota=/auth/google/callback';
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

    private function logOabValidation(
        int $professionalId,
        string $action,
        ?string $previousStatus,
        string $newStatus,
        string $origin,
        ?string $message
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO cna_validacao_logs (profissional_id, acao, status_anterior, status_novo, origem, mensagem)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$professionalId, $action, $previousStatus, $newStatus, $origin, $message]);
        } catch (PDOException $e) {
            error_log('OAB validation log error: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------
    // POST /auth/logout
    // -------------------------------------------------------
    public function logout(): void
    {
        $this->startSession();
        $userId = (int) ($_SESSION['id'] ?? 0);
        if ($userId > 0) {
            $this->audit->log('auth.logout', 'user', $userId);
        }
        $this->destroySessionCookies();

        $this->response->redirect(APP_URL . '/frontend/login.html');
    }
}
