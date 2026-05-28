<?php

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
            header("Location: /justraduz/frontend/login.html");
            exit();
        }

        // Tipo não bate → acesso negado
        if ($tipo !== null) {
            $tipos = is_array($tipo) ? $tipo : [$tipo];

            if (!in_array($_SESSION['tipo'], $tipos)) {
                header("Location: /justraduz/frontend/login.html?erro=Acesso+negado.");
                exit();
            }
        }
    }
}