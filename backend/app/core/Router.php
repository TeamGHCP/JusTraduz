<?php

namespace App\Core;

use App\Middlewares\CsrfMiddleware;
use Throwable;

class Router
{
    private array $routes = [];
    private array $legacyRedirects = [];

    public function get(string $path, string $controller, string $method, array $options = []): void
    {
        $this->routes[] = [
            'method' => 'GET',
            'path' => $path,
            'controller' => $controller,
            'action' => $method,
            'options' => $options,
        ];
    }

    public function post(string $path, string $controller, string $method, array $options = []): void
    {
        $this->routes[] = [
            'method' => 'POST',
            'path' => $path,
            'controller' => $controller,
            'action' => $method,
            'options' => $options,
        ];
    }

    public function legacyRedirect(string $legacyPath, string $targetPath, array $methods = ['GET', 'POST']): void
    {
        foreach ($methods as $method) {
            $this->legacyRedirects[strtoupper($method) . ' ' . $legacyPath] = $targetPath;
        }
    }

    public function dispatch(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_GET['rota'] ?? '/');

        $matchedRoute = null;
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $uri) {
                $matchedRoute = $route;
                break;
            }
        }

        $legacyTarget = $this->legacyRedirects[$method . ' ' . $uri] ?? null;
        if (!$matchedRoute && $legacyTarget !== null) {
            $this->redirectLegacyRoute($legacyTarget);
            return;
        }

        if ($matchedRoute) {
            // Apply global rate limiting
            if (class_exists('App\Middlewares\RateLimiterMiddleware')) {
                \App\Middlewares\RateLimiterMiddleware::check($matchedRoute['path'], (array) ($matchedRoute['options']['rate_limit'] ?? []));
            }

            $controllerClass = $this->resolveControllerClass($matchedRoute['controller']);
            if (!class_exists($controllerClass)) {
                error_log('Controller class not found: ' . $controllerClass);
                http_response_code(500);
                if (!self::expectsJson() && class_exists('App\\Core\\ErrorHandler') && ErrorHandler::renderErrorPage(500)) {
                    return;
                }

                echo 'Erro interno do servidor.';
                return;
            }

            if ($method === 'POST' && !$this->isCsrfExempt($matchedRoute['path'])) {
                if (class_exists('App\\Middlewares\\CsrfMiddleware')) {
                    CsrfMiddleware::validate();
                }
            }

            $controller = new $controllerClass();
            $action = $matchedRoute['action'];
            $controller->$action();
            return;
        }

        http_response_code(404);
        if (!self::expectsJson() && class_exists('App\\Core\\ErrorHandler') && ErrorHandler::renderErrorPage(404)) {
            return;
        }

        echo 'Recurso não encontrado.';
    }

    private function resolveControllerClass(string $controller): string
    {
        $namespacedClass = 'App\\Controllers\\' . $controller;
        if (class_exists($namespacedClass)) {
            return $namespacedClass;
        }

        if (class_exists($controller)) {
            return $controller;
        }

        return $namespacedClass;
    }

    private function isCsrfExempt(string $path): bool
    {
        return in_array($path, ['/billing/webhook'], true);
    }

    private function redirectLegacyRoute(string $targetPath): void
    {
        $query = $_GET;
        $query['rota'] = $targetPath;
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/backend/public/index.php');
        $location = $script . '?' . http_build_query($query);

        http_response_code(307);
        header('Location: ' . $location);
        header('X-Robots-Tag: noindex');
        echo 'Rota movida para ' . htmlspecialchars($targetPath, ENT_QUOTES, 'UTF-8') . '.';
    }

    private static function expectsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }
}
