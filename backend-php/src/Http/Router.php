<?php

declare(strict_types=1);

namespace Vise\Http;

final class Router
{
    /** @var list<array{0: string, 1: string, 2: callable}> */
    private array $routes = [];

    /**
     * Padrão com parâmetros: /aplicacoes/{id:\d+}/categorias, /salas/{codigo}.
     *
     * @param callable(Request, array<string, string>): Response $handler
     */
    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = preg_replace_callback(
            '#\{(\w+)(?::([^}]+))?\}#',
            static fn (array $m) => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')',
            $pattern
        );
        $this->routes[] = [strtoupper($method), '#^' . $regex . '$#', $handler];
    }

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;
        $allowed = false;
        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if (!preg_match($regex, $request->path, $match)) {
                continue;
            }
            if ($routeMethod !== $method) {
                $allowed = true;
                continue;
            }
            $params = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
            return $handler($request, $params);
        }
        if ($allowed) {
            throw new HttpError(405, 'Method Not Allowed');
        }
        throw new HttpError(404, 'Not Found');
    }
}
