<?php

require_once __DIR__ . '/Request.php';
require_once __DIR__ . '/Response.php';
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/services/PermissionService.php';
require_once dirname(__DIR__) . '/support/session.php';

if (!defined('APP_URL')) {
    define('APP_URL', app_base_path());
}

abstract class BaseController
{
    protected Request $request;
    protected Response $response;
    protected PDO $pdo;

    public function __construct()
    {
        require_once dirname(__DIR__) . '/config/database.php';
        $this->request = new Request();
        $this->response = new Response();
        $this->pdo = database_connection();
    }

    protected function startSession(): void
    {
        secure_session_start();
    }

    protected function requireLoggedIn(string $redirectUrl, string $message = 'Faça login para continuar.'): void
    {
        $this->startSession();

        if (empty($_SESSION['logado'])) {
            $this->response->redirect($redirectUrl . '?erro=' . urlencode($message));
        }
    }

    protected function currentUserId(): int
    {
        return (int) ($_SESSION['id'] ?? 0);
    }

    protected function currentUserType(): string
    {
        return (string) ($_SESSION['tipo'] ?? '');
    }

    protected function requirePermission(string $permission, string $redirectUrl, string $message = 'Acesso não autorizado.'): void
    {
        $this->startSession();

        if (empty($_SESSION['logado']) || !PermissionService::sessionHas($permission)) {
            $this->response->redirect($redirectUrl . '?erro=' . urlencode($message));
        }
    }
}
