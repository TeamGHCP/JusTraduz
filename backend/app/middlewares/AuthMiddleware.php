<?php

require_once dirname(__DIR__) . '/config/app.php';

class AuthMiddleware
{
    // Verifica se o usuário está logado
    // Se passar $tipo, verifica também se o tipo bate
    public static function verificar(string $tipo = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Não está logado → vai para o login
        if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
            require_once dirname(__DIR__) . '/core/RedirectException.php';
            throw new RedirectException(app_url('/frontend/login.html'));
        }

        // Tipo não bate → acesso negado
        if ($tipo !== null) {
            $tipos = is_array($tipo) ? $tipo : [$tipo];

            if (!in_array($_SESSION['tipo'], $tipos)) {
                require_once dirname(__DIR__) . '/core/RedirectException.php';
                throw new RedirectException(app_url('/frontend/login.html?erro=' . urlencode('Acesso negado.')));
            }
        }
    }
}
