<?php

declare(strict_types=1);

namespace App\Support;
final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function match(array $methods, string $path, callable|array $handler): void
    {
        foreach ($methods as $method) {
            $this->add(strtoupper($method), $path, $handler);
        }
    }

    private function add(string $method, string $path, callable|array $handler): void
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_-]*)\}#', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
                $handler = $route['handler'];

                if (is_array($handler)) {
                    [$class, $action] = $handler;
                    $instance = new $class();
                    $instance->$action(...array_values($params));
                    return;
                }

                $handler(...array_values($params));
                return;
            }
        }

        if (str_starts_with($uri, '/api/desktop')) {
            json_response([
                'ok' => false,
                'message' => 'Rota da API nao encontrada.',
            ], 404);
        }

        http_response_code(404);
        View::render('404');
    }
}
