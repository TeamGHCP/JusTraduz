<?php

class ErrorHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
    }

    public static function handleException(Throwable $e): void
    {
        error_log(sprintf("Uncaught exception: %s in %s:%d", $e->getMessage(), $e->getFile(), $e->getLine()));

        // Tratamento especial para RedirectException (verifica se a classe existe)
        if (class_exists('RedirectException') && $e instanceof RedirectException) {
            $status = $e->getCode() ?: 302;
            header('Location: ' . $e->getUrl(), true, $status);
            // Retorna ao dispatcher para que o fluxo seja controlado centralmente.
            return;
        }

        $code = (int) $e->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : 500;

        if (php_sapi_name() === 'cli' || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
            http_response_code($status);
            header('Content-Type: application/json');
            echo json_encode(['error' => $status === 500 ? 'Erro interno do servidor' : $e->getMessage()]);
        } else {
            http_response_code($status);
            echo $status === 500 ? 'Erro interno do servidor.' : $e->getMessage();
        }
        // Não expor detalhes do erro ao cliente
    }

    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
}
