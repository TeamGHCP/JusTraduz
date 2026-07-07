<?php

namespace App\Core;

use Throwable;
use ErrorException;

class ErrorHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
    }

    public static function handleException(Throwable $e): void
    {
        // Tratamento especial para RedirectException (verifica se a classe existe)
        if ($e instanceof RedirectException) {
            $status = $e->getCode() ?: 302;
            header('Location: ' . $e->getUrl(), true, $status);
            exit;
        }

        error_log(sprintf("Uncaught exception: %s in %s:%d", $e->getMessage(), $e->getFile(), $e->getLine()));

        $code = (int) $e->getCode();
        $status = ($code >= 400 && $code < 600) ? $code : 500;
        $message = self::clientMessage($status, $e);

        if (php_sapi_name() === 'cli' || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
            http_response_code($status);
            header('Content-Type: application/json');
            echo json_encode(['error' => $message]);
        } else {
            http_response_code($status);
            if (self::renderErrorPage($status)) {
                return;
            }

            header('Content-Type: text/plain; charset=UTF-8');
            echo $message;
        }
        // Não expor detalhes do erro ao cliente
    }

    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if ((error_reporting() & $errno) === 0) {
            return true;
        }

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

    public static function renderErrorPage(int $status): bool
    {
        $pages = match ($status) {
            404 => ['404.php', '404.html'],
            default => ['500.php', '500.html'],
        };

        $path = null;
        foreach ($pages as $page) {
            $candidate = dirname(__DIR__, 3) . '/frontend/' . $page;
            if (is_file($candidate)) {
                $path = $candidate;
                break;
            }
        }

        if ($path === null) {
            return false;
        }

        header('Content-Type: text/html; charset=UTF-8');
        ob_start();
        require $path;
        $html = (string) ob_get_clean();
        $base = htmlspecialchars(self::frontendBasePath(), ENT_QUOTES, 'UTF-8');
        echo str_replace('<head>', '<head>' . PHP_EOL . '  <base href="' . $base . '">', $html);
        return true;
    }

    private static function frontendBasePath(): string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $marker = '/backend/public/';
        $base = '';
        $position = strpos($script, $marker);

        if ($position !== false) {
            $base = substr($script, 0, $position);
        }

        return rtrim($base, '/') . '/';
    }
}
