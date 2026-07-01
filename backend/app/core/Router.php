<?php

namespace App\Core;

use App\Middlewares\CsrfMiddleware;
use Throwable;

class Router
{
    private array $routes = [];

    public function get(string $path, string $controller, string $method): void
    {
        $this->routes[] = [
            'method' => 'GET',
            'path' => $path,
            'controller' => $controller,
            'action' => $method,
        ];
    }

    public function post(string $path, string $controller, string $method): void
    {
        $this->routes[] = [
            'method' => 'POST',
            'path' => $path,
            'controller' => $controller,
            'action' => $method,
        ];
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

        // Compatibility fallback: if not matched and has /api/v1 prefix, strip it and look for the internal route
        if (!$matchedRoute && str_starts_with($uri, '/api/v1/')) {
            $strippedUri = substr($uri, 7); // Strip '/api/v1'
            foreach ($this->routes as $route) {
                if ($route['method'] === $method && $route['path'] === $strippedUri) {
                    $matchedRoute = $route;
                    break;
                }
            }
        }

        if ($matchedRoute) {
            // Apply global rate limiting
            if (class_exists('App\Middlewares\RateLimiterMiddleware')) {
                \App\Middlewares\RateLimiterMiddleware::check($matchedRoute['path']);
            }

            $controllerClass = 'App\\Controllers\\' . $matchedRoute['controller'];
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

    private function isCsrfExempt(string $path): bool
    {
        return in_array($path, ['/billing/webhook'], true);
    }

    private static function expectsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }
}
