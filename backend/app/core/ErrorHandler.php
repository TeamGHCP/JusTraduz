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
        $message = self::clientMessage($status, $e);

        if (php_sapi_name() === 'cli' || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
            http_response_code($status);
            header('Content-Type: application/json');
            echo json_encode(['error' => $message]);
        } else {
            http_response_code($status);
            echo $message;
        }
        // Não expor detalhes do erro ao cliente
    }

    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    private static function clientMessage(int $status, Throwable $e): string
    {
        $debug = in_array(strtolower((string) getenv('APP_DEBUG')), ['1', 'true', 'yes'], true);
        if ($debug && $status !== 500) {
            return $e->getMessage();
        }

        return match ($status) {
            400 => 'Requisição inválida.',
            401 => 'Faça login para continuar.',
            403 => 'Acesso negado ou sessão expirada.',
            404 => 'Recurso não encontrado.',
            default => 'Erro interno do servidor.',
        };
    }
}
