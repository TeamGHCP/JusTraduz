<?php

require_once dirname(__DIR__) . '/core/Request.php';
require_once dirname(__DIR__) . '/core/Response.php';

define('APP_URL', 'http://localhost:9999/justraduz');

class AuthController
{
    private Request  $request;
    private Response $response;
    private PDO      $pdo;

    public function __construct()
    {
        require_once dirname(__DIR__) . '/config/database.php';
        $this->request  = new Request();
        $this->response = new Response();
        $this->pdo      = $pdo;
    }

    // -------------------------------------------------------
    // POST /auth/registrar
    // -------------------------------------------------------
    public function registrar(): void
    {
        $nome   = $this->request->post('nome', '');
        $email  = $this->request->post('email', '');
        $senha  = $this->request->post('senha', '');
        $senha2 = $this->request->post('senha2', '');
        $tipo   = $this->request->post('tipo', 'cliente');
        $oab    = $this->request->post('inscricao');
        $oab_uf = $this->request->post('oab_uf');

        $frontUrl = APP_URL . '/frontend/cadastro.html';

        // Validações
        if (!$nome || !$email || !$senha) {
            $this->response->redirectWithError($frontUrl, 'Preencha todos os campos obrigatórios.');
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

        // Verifica se e-mail já existe
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $this->response->redirectWithError($frontUrl, 'E-mail já cadastrado.');
        }

        // Insere no banco
        $senhaCriptografada = password_hash($senha, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (nome, email, senha, tipo, oab, oab_uf)
                VALUES (:nome, :email, :senha, :tipo, :oab, :oab_uf)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nome'   => $nome,
            ':email'  => $email,
            ':senha'  => $senhaCriptografada,
            ':tipo'   => $tipo,
            ':oab'    => $oab,
            ':oab_uf' => $oab_uf,
        ]);

        $this->response->redirect(APP_URL . '/frontend/login.html?sucesso=conta_criada');
    }

    // -------------------------------------------------------
    // POST /auth/login
    // -------------------------------------------------------
    public function login(): void
    {
        session_start();

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
            $this->response->redirectWithError($frontUrl, 'E-mail não encontrado.');
        }

        if (!password_verify($senha, $usuario['senha'])) {
            $this->response->redirectWithError($frontUrl, 'Senha incorreta.');
        }

        // Cria sessão
        $_SESSION['id']     = $usuario['id'];
        $_SESSION['nome']   = $usuario['nome'];
        $_SESSION['tipo']   = $usuario['tipo'];
        $_SESSION['logado'] = true;

        // Redireciona por tipo
        $destinos = [
            'advogado'   => '/frontend/dashboard-advogado.php',
            'estagiario' => '/frontend/dashboard-advogado.php',
            'admin'      => '/frontend/admin/dashboard-admin.php',
            'cliente'    => '/frontend/dashboard-cliente.php',
        ];

        $destino = $destinos[$usuario['tipo']] ?? '/frontend/dashboard-cliente.php';
        $this->response->redirect(APP_URL . $destino);
    }

    // -------------------------------------------------------
    // GET /auth/logout
    // -------------------------------------------------------
    public function logout(): void
    {
        session_start();
        session_unset();
        session_destroy();

        $this->response->redirect(APP_URL . '/frontend/login.html');
    }
}