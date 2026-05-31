<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
require_once dirname(__DIR__) . '/services/MailerService.php';
require_once dirname(__DIR__) . '/services/OabService.php';
require_once dirname(__DIR__) . '/middlewares/CsrfMiddleware.php';

class AuthController extends BaseController
{
    private AuditService $audit;
    private OabService $oabService;

    public function __construct()
    {
        parent::__construct();
        $this->audit = new AuditService($this->pdo);
        $this->oabService = new OabService();
    }

    // -------------------------------------------------------
    // POST /auth/registrar
    // -------------------------------------------------------
    public function registrar(): void
    {
        $nome   = trim((string) $this->request->post('nome', ''));
        $email  = trim((string) $this->request->post('email', ''));
        $telefone = trim((string) $this->request->post('telefone', ''));
        $senha  = $this->request->post('senha', '');
        $senha2 = $this->request->post('senha2', '');
        $tipo   = (string) $this->request->post('tipo', 'cliente');
        $oab    = preg_replace('/\D+/', '', (string) $this->request->post('inscricao', ''));
        $oab_uf = strtoupper(trim((string) $this->request->post('oab_uf', '')));
        $oab_status = null;
        $oab_parametro = null;
        $oab_verificado = false;
        $oab_tipo = null;
        $status_cna = 'pendente';

        $frontUrl = APP_URL . '/frontend/cadastro.html';

        // Validações
        if (!$nome || !$email || !$senha) {
            $this->response->redirectWithError($frontUrl, 'Preencha todos os campos obrigatórios.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->response->redirectWithError($frontUrl, 'Informe um e-mail válido.');
        }

        $telefone = preg_replace('/[^\d()+\-\s]/', '', $telefone) ?? '';
        if ($telefone !== '' && strlen(preg_replace('/\D+/', '', $telefone) ?? '') < 10) {
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

        if (($tipo === 'advogado' || $tipo === 'estagiario') && !$oab) {
            $this->response->redirectWithError($frontUrl, 'Número da OAB é obrigatório.');
        }

        if ($tipo === 'advogado' || $tipo === 'estagiario') {
            $lookup = $this->oabService->lookup(
                $oab,
                $oab_uf,
                $tipo,
                $nome,
                (string) $this->request->post('recaptcha_token', ''),
                (string) $this->request->post('recaptcha_version', 'v3')
            );

            if (($lookup['source_available'] ?? true) && !($lookup['verified'] ?? false)) {
                $this->response->redirectWithError($frontUrl, (string) ($lookup['message'] ?? 'Não foi possível validar a OAB no CNA.'));
            }

            if ($lookup['verified'] ?? false) {
                $data = $lookup['data'] ?? [];
                $oab_uf = strtoupper((string) ($data['uf'] ?? $oab_uf));
                $oab_status = trim((string) (($data['situacao'] ?? 'REGULAR') . ' - ' . ucfirst((string) ($data['tipo'] ?? $tipo))));
                $oab_parametro = $data['parametro'] ?? null;
                $oab_verificado = true;
                $oab_tipo = $data['tipo'] ?? null;
                $status_cna = 'verificado';
            } else {
                if ($oab_uf === '') {
                    $this->response->redirectWithError($frontUrl, 'Informe a UF da OAB enquanto a validação automática do CNA estiver indisponível.');
                }

                $oab_status = 'Pendente: validação automática do CNA indisponível';
                $status_cna = 'pendente';
            }
        }

        // Verifica se e-mail já existe
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $this->response->redirectWithError($frontUrl, 'E-mail já cadastrado.');
        }

        // Insere no banco
        $senhaCriptografada = password_hash($senha, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (nome, email, senha, tipo, telefone, oab, oab_uf, oab_status, oab_parametro, oab_verificado, oab_tipo, status_cna)
                VALUES (:nome, :email, :senha, :tipo, :telefone, :oab, :oab_uf, :oab_status, :oab_parametro, :oab_verificado, :oab_tipo, :status_cna)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':nome'   => $nome,
                ':email'  => $email,
                ':senha'  => $senhaCriptografada,
                ':tipo'   => $tipo,
                ':telefone' => $telefone ?: null,
                ':oab'    => $oab,
                ':oab_uf' => $oab_uf,
                ':oab_status' => $oab_status,
                ':oab_parametro' => $oab_parametro,
                ':oab_verificado' => $oab_verificado ? 1 : 0,
                ':oab_tipo' => $oab_tipo,
                ':status_cna' => $status_cna,
            ]);
            $userId = (int) $this->pdo->lastInsertId();
            $this->audit->log('auth.register', 'user', $userId, [
                'email' => $email,
                'tipo' => $tipo,
                'oab_verificado' => $oab_verificado,
            ]);
            if (in_array($tipo, ['advogado', 'estagiario'], true)) {
                $this->logCnaValidation($userId, 'cadastro', null, $status_cna, $oab_verificado ? 'cna' : 'fallback', $oab_status);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '42S22') {
                $this->response->redirectWithError(
                    $frontUrl,
                    'Banco de dados desatualizado. Execute as migrations em database/.'
                );
            }

            throw $e;
        }

        $this->response->redirect(APP_URL . '/frontend/login.html?sucesso=conta_criada');
    }

    // -------------------------------------------------------
    // POST /auth/login
    // -------------------------------------------------------
    public function login(): void
    {
        $this->startSession();

        $email = $this->request->post('email', '');
        $senha = $this->request->post('senha', '');

        $frontUrl = APP_URL . '/frontend/login.html';

        if (!$email || !$senha) {
            $this->response->redirectWithError($frontUrl, 'Preencha todos os campos.');
        }

        if ($this->tooManyLoginFailures('auth.login_failed')) {
            $this->response->redirectWithError($frontUrl, 'Muitas tentativas recentes. Aguarde alguns minutos e tente novamente.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, nome, senha, tipo FROM users WHERE email = ? AND status = 'ativo'"
        );
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            $this->audit->log('auth.login_failed', 'user', null, ['email' => $email, 'reason' => 'not_found']);
            $this->response->redirectWithError($frontUrl, 'E-mail não encontrado.');
        }

        if (!password_verify($senha, $usuario['senha'])) {
            $this->audit->log('auth.login_failed', 'user', (int) $usuario['id'], ['email' => $email, 'reason' => 'wrong_password']);
            $this->response->redirectWithError($frontUrl, 'Senha incorreta.');
        }

        // Cria sessão
        // Protege contra fixation e rotaciona token CSRF
        session_regenerate_id(true);
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
        session_unset();
        session_destroy();

        // Expire common session cookies (PHPSESSID and legacy 'session')
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        if (is_string($domain) && strpos($domain, ':') !== false) {
            $domain = explode(':', $domain, 2)[0];
        }

        setcookie('PHPSESSID', '', time() - 3600, '/', '', false, true);
        setcookie('session', '', time() - 3600, '/', '', false, true);
        if ($domain !== '') {
            setcookie('PHPSESSID', '', time() - 3600, '/', $domain, false, true);
            setcookie('session', '', time() - 3600, '/', $domain, false, true);
        }

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

        $email = $this->request->post('email', '');
        $senha = $this->request->post('senha', '');

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
        session_regenerate_id(true);
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
        $email = trim((string) $this->request->post('email', ''));
        $telefone = trim((string) $this->request->post('telefone', ''));
        $senhaAtual = (string) $this->request->post('senha_atual', '');
        $novaSenha = (string) $this->request->post('nova_senha', '');
        $novaSenha2 = (string) $this->request->post('nova_senha2', '');
        $passwordUpdated = false;

        if (!$nome || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->response->redirect(APP_URL . '/frontend/perfil.php?erro=' . urlencode('Informe nome e e-mail válidos.'));
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

            $stmt = $this->pdo->prepare('UPDATE users SET senha = ? WHERE id = ?');
            $stmt->execute([password_hash($novaSenha, PASSWORD_DEFAULT), (int) $_SESSION['id']]);
            $passwordUpdated = true;
        }

        $profilePhotoPath = $this->handleProfilePhotoUpload((int) $_SESSION['id']);

        if ($profilePhotoPath !== null) {
            $stmt = $this->pdo->prepare('SELECT foto_perfil FROM users WHERE id = ?');
            $stmt->execute([(int) $_SESSION['id']]);
            $oldPhoto = (string) ($stmt->fetchColumn() ?: '');

            $stmt = $this->pdo->prepare('UPDATE users SET nome = ?, email = ?, telefone = ?, foto_perfil = ? WHERE id = ?');
            $stmt->execute([$nome, $email, $telefone ?: null, $profilePhotoPath, (int) $_SESSION['id']]);
            $this->deleteOldProfilePhoto($oldPhoto, $profilePhotoPath);
        } else {
            $stmt = $this->pdo->prepare('UPDATE users SET nome = ?, email = ?, telefone = ? WHERE id = ?');
            $stmt->execute([$nome, $email, $telefone ?: null, (int) $_SESSION['id']]);
        }

        $_SESSION['nome'] = $nome;
        if ($passwordUpdated) {
            session_regenerate_id(true);
            unset($_SESSION['_csrf_token']);
            CsrfMiddleware::generateToken();
        }

        $this->audit->log('profile.update', 'user', (int) $_SESSION['id'], [
            'email' => $email,
            'telefone_informado' => $telefone !== '',
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
        $email = trim((string) $this->request->post('email', ''));
        $frontUrl = APP_URL . '/frontend/recuperar-senha.html';

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->response->redirectWithError($frontUrl, 'Informe um e-mail válido.');
        }

        if ($action === 'request_code') {
            $this->sendPasswordResetCode($email, $frontUrl);
            return;
        }

        $codigo = preg_replace('/\D+/', '', (string) $this->request->post('codigo', '')) ?? '';
        $senha = (string) $this->request->post('senha', '');
        $senha2 = (string) $this->request->post('senha2', '');

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
            $stmt = $this->pdo->prepare('UPDATE users SET senha = ? WHERE id = ?');
            $stmt->execute([password_hash($senha, PASSWORD_DEFAULT), (int) $reset['user_id']]);

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
        $senha = (string) $this->request->post('senha', '');
        $senha2 = (string) $this->request->post('senha2', '');

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
            $stmt = $this->pdo->prepare('UPDATE users SET senha = ? WHERE id = ?');
            $stmt->execute([password_hash($senha, PASSWORD_DEFAULT), (int) $reset['user_id']]);

            $stmt = $this->pdo->prepare('UPDATE password_reset_codes SET used_at = NOW() WHERE id = ?');
            $stmt->execute([(int) $reset['reset_id']]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        session_regenerate_id(true);
        unset($_SESSION['_csrf_token']);
        CsrfMiddleware::generateToken();

        $this->audit->log('profile.password_reset', 'user', (int) $reset['user_id'], ['email' => $reset['email']]);
        $this->response->json(['success' => true, 'message' => 'Senha atualizada com sucesso.']);
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
             AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );
        $stmt->execute([$action, $ip]);

        return (int) $stmt->fetchColumn() >= 10;
    }

    private function tooManyPasswordResetRequests(int $userId, string $email): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM password_reset_codes
             WHERE user_id = ?
             AND email = ?
             AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );
        $stmt->execute([$userId, $email]);

        return (int) $stmt->fetchColumn() >= 3;
    }

    private function sendPasswordResetEmail(string $email, string $name, string $code): bool
    {
        $subject = 'Código de recuperação de senha - JusTraduz';
        $message = "Olá, {$name}.\n\n"
            . "Seu código para recuperar a senha no JusTraduz é: {$code}\n\n"
            . "Este código expira em 15 minutos. Se você não solicitou a recuperação, ignore este e-mail.\n";

        return (new MailerService())->send($email, $subject, $message);
    }

    private function logCnaValidation(
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
            error_log('CNA validation log error: ' . $e->getMessage());
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
        session_unset();
        session_destroy();

        $this->response->redirect(APP_URL . '/frontend/login.html');
    }
}
