<?php

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

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method || $route['path'] !== $uri) {
                continue;
            }

            $controllerFile = dirname(__DIR__) . '/controllers/' . $route['controller'] . '.php';
            if (!file_exists($controllerFile)) {
                error_log('Controller not found: ' . $route['controller']);
                http_response_code(500);
                if (!self::expectsJson() && class_exists('ErrorHandler') && ErrorHandler::renderErrorPage(500)) {
                    return;
                }

                echo 'Erro interno do servidor.';
                return;
            }

            if ($method === 'POST') {
                $csrfFile = dirname(__DIR__) . '/middlewares/CsrfMiddleware.php';
                if (file_exists($csrfFile)) {
                    require_once $csrfFile;
                    CsrfMiddleware::validate();
                }
            }

            require_once $controllerFile;

            $controller = new $route['controller']();
            $action = $route['action'];
            $controller->$action();
            return;
        }

        http_response_code(404);
        if (!self::expectsJson() && class_exists('ErrorHandler') && ErrorHandler::renderErrorPage(404)) {
            return;
        }

        echo 'Recurso nao encontrado.';
    }

    private static function expectsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }
}
