<?php
declare(strict_types=1);

namespace App\Helpers;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'middleware');
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Strip APP_BASE prefix so subfolder installs route correctly
        $base = rtrim(\App\Helpers\UrlHelper::base(), '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $uri = '/' . trim($uri, '/');
        if ($uri === '') $uri = '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            $pattern = $this->toPattern($route['path']);
            if (!preg_match($pattern, $uri, $matches)) continue;

            // Run middleware
            foreach ($route['middleware'] as $mw) {
                (new $mw())->handle();
            }

            // Named params only
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

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
