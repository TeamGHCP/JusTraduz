<?php

require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/services/AuditService.php';
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
        $senha  = $this->request->post('senha', '');
        $senha2 = $this->request->post('senha2', '');
        $tipo   = (string) $this->request->post('tipo', 'cliente');
        $oab    = preg_replace('/\D+/', '', (string) $this->request->post('inscricao', ''));
        $oab_uf = strtoupper(trim((string) $this->request->post('oab_uf', '')));
        $oab_status = null;
        $oab_parametro = null;
        $oab_verificado = false;

        $frontUrl = APP_URL . '/frontend/cadastro.html';

        // Validações
        if (!$nome || !$email || !$senha) {
            $this->response->redirectWithError($frontUrl, 'Preencha todos os campos obrigatórios.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->response->redirectWithError($frontUrl, 'Informe um e-mail válido.');
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
            } else {
                if ($oab_uf === '') {
                    $this->response->redirectWithError($frontUrl, 'Informe a UF da OAB enquanto a validação automática do CNA estiver indisponível.');
                }

                $oab_status = 'Pendente: validação automática do CNA indisponível';
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

        $sql = "INSERT INTO users (nome, email, senha, tipo, oab, oab_uf, oab_status, oab_parametro, oab_verificado)
                VALUES (:nome, :email, :senha, :tipo, :oab, :oab_uf, :oab_status, :oab_parametro, :oab_verificado)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':nome'   => $nome,
                ':email'  => $email,
                ':senha'  => $senhaCriptografada,
                ':tipo'   => $tipo,
                ':oab'    => $oab,
                ':oab_uf' => $oab_uf,
                ':oab_status' => $oab_status,
                ':oab_parametro' => $oab_parametro,
                ':oab_verificado' => $oab_verificado ? 1 : 0,
            ]);
            $userId = (int) $this->pdo->lastInsertId();
            $this->audit->log('auth.register', 'user', $userId, [
                'email' => $email,
                'tipo' => $tipo,
                'oab_verificado' => $oab_verificado,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '42S22') {
                $this->response->redirectWithError(
                    $frontUrl,
                    'Banco de dados desatualizado. Execute a migration mysql/migrations/2026_05_29_add_oab_columns.sql.'
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
        }

        $stmt = $this->pdo->prepare('UPDATE users SET nome = ?, email = ?, telefone = ? WHERE id = ?');
        $stmt->execute([$nome, $email, $telefone ?: null, (int) $_SESSION['id']]);

        $_SESSION['nome'] = $nome;
        $this->audit->log('profile.update', 'user', (int) $_SESSION['id'], ['email' => $email, 'telefone_informado' => $telefone !== '']);

        $this->response->redirect(APP_URL . '/frontend/perfil.php?sucesso=' . urlencode('Perfil atualizado.'));
    }

    public function resetPassword(): void
    {
        $email = trim((string) $this->request->post('email', ''));
        $senha = (string) $this->request->post('senha', '');
        $senha2 = (string) $this->request->post('senha2', '');
        $frontUrl = APP_URL . '/frontend/recuperar-senha.html';

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->response->redirectWithError($frontUrl, 'Informe um e-mail válido.');
        }

        if (strlen($senha) < 6) {
            $this->response->redirectWithError($frontUrl, 'A nova senha deve ter no mínimo 6 caracteres.');
        }

        if ($senha !== $senha2) {
            $this->response->redirectWithError($frontUrl, 'As senhas não coincidem.');
        }

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND status = 'ativo'");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            $this->response->redirectWithError($frontUrl, 'Conta ativa não encontrada para este e-mail.');
        }

        $stmt = $this->pdo->prepare('UPDATE users SET senha = ? WHERE id = ?');
        $stmt->execute([password_hash($senha, PASSWORD_DEFAULT), (int) $usuario['id']]);
        $this->audit->log('auth.password_reset', 'user', (int) $usuario['id'], ['email' => $email]);

        $this->response->redirect(APP_URL . '/frontend/login.html?sucesso=' . urlencode('Senha atualizada. Entre com a nova senha.'));
    }

    // -------------------------------------------------------
    // GET /auth/logout
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
