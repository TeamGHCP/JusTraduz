<?php

namespace App\Core;

use App\Services\PermissionService;
use PDO;

if (!defined('APP_URL')) {
    define('APP_URL', \app_base_path());
}

abstract class BaseController
{
    protected Request $request;
    protected Response $response;
    protected PDO $pdo;

    public function __construct()
    {
        $this->request = new Request();
        $this->response = new Response();
        require_once dirname(__DIR__) . '/config/database.php';
        $this->pdo = database_connection();
    }

    protected function startSession(): void
    {
        \secure_session_start();
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
