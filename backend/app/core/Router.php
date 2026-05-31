<?php

class Router
{
    private array $routes = [];

    // Registra uma rota GET
    public function get(string $path, string $controller, string $method): void
    {
        $this->routes[] = [
            'method'     => 'GET',
            'path'       => $path,
            'controller' => $controller,
            'action'     => $method,
        ];
    }

    // Registra uma rota POST
    public function post(string $path, string $controller, string $method): void
    {
        $this->routes[] = [
            'method'     => 'POST',
            'path'       => $path,
            'controller' => $controller,
            'action'     => $method,
        ];
    }

    // Executa a rota correspondente à requisição atual
    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        $uri    = $_GET['rota'] ?? '/';

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $uri) {
                $controllerFile = dirname(__DIR__) . '/controllers/' . $route['controller'] . '.php';

                if (!file_exists($controllerFile)) {
                    http_response_code(500);
                    echo "Controller {$route['controller']} não encontrado.";
                    return;
                }

                // Validar CSRF para requisições POST (exige token em _csrf ou cabeçalho X-CSRF-Token)
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
        }

        http_response_code(404);
        echo "Rota não encontrada.";
        return;
    }
}