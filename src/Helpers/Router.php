<?php
declare(strict_types=1);

namespace App\Helpers;

class Router
{
    private array $routes = [];
    private array $middleware = [];

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, mixed $handler, array $middleware): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'middleware');
    }

    public function dispatch(): void
    {
        $method  = $_SERVER['REQUEST_METHOD'];
        $uri     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri     = '/' . trim($uri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            $pattern = $this->toPattern($route['path']);
            if (!preg_match($pattern, $uri, $matches)) continue;

            // Run middleware chain
            foreach ($route['middleware'] as $mw) {
                (new $mw())->handle();
            }

            // Extract named params
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            // Resolve handler
            [$class, $action] = $route['handler'];
            (new $class())->$action($params);
            return;
        }

        $this->notFound();
    }

    private function toPattern(string $path): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function notFound(): void
    {
        http_response_code(404);
        require ROOT . '/views/partials/404.php';
    }
}
