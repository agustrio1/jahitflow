<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use InvalidArgumentException;

/**
 * Router berbasis data: route didefinisikan sebagai array, dicocokkan
 * dengan regex yang di-compile sekali — tanpa eval, tanpa eksekusi input.
 *
 * Aksi controller disimpan sebagai string "ControllerClass@method"
 * dan di-resolve oleh Application melalui container.
 */
final class Router
{
    /** @var array<string, list<array{regex: string, params: list<string>, name: ?string, action: string, middleware: list<string>, pattern: string}>> */
    private array $routes = [];

    /** @var array<string, array{pattern: string, name: ?string}> Untuk named URL generation. */
    private array $namedRoutes = [];

    /** @var list<string> Grup middleware yang melekat saat registrasi. */
    private array $groupMiddleware = [];

    public function get(string $pattern, string $action, ?string $name = null, array $middleware = []): void
    {
        $this->add('GET', $pattern, $action, $name, $middleware);
    }

    public function post(string $pattern, string $action, ?string $name = null, array $middleware = []): void
    {
        $this->add('POST', $pattern, $action, $name, $middleware);
    }

    public function put(string $pattern, string $action, ?string $name = null, array $middleware = []): void
    {
        $this->add('PUT', $pattern, $action, $name, $middleware);
    }

    public function patch(string $pattern, string $action, ?string $name = null, array $middleware = []): void
    {
        $this->add('PATCH', $pattern, $action, $name, $middleware);
    }

    public function delete(string $pattern, string $action, ?string $name = null, array $middleware = []): void
    {
        $this->add('DELETE', $pattern, $action, $name, $middleware);
    }

    /**
     * Jalankan closure registrasi dengan tambahan middleware grup.
     *
     * @param list<string> $middleware
     */
    public function group(array $middleware, Closure $routes): void
    {
        $this->groupMiddleware = [...$this->groupMiddleware, ...$middleware];
        $routes($this);
        $this->groupMiddleware = array_slice($this->groupMiddleware, 0, -count($middleware));
    }

    /**
     * @return array{action: string, params: array<string, string>, middleware: list<string>}|null
     */
    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                $params = [];
                foreach ($route['params'] as $param) {
                    $params[$param] = $matches[$param];
                }
                return [
                    'action' => $route['action'],
                    'params' => $params,
                    'middleware' => $route['middleware'],
                ];
            }
        }

        return null;
    }

    /** Method path mana yang cocok tapi method-nya salah → untuk 405. */
    public function pathExists(string $path): bool
    {
        foreach ($this->routes as $routes) {
            foreach ($routes as $route) {
                if (preg_match($route['regex'], $path)) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Apakah sebuah route bernama sudah terdaftar (dipakai sidebar/nav). */
    public function has(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    /**
     * Bangun URL dari route name: route('orders.show', ['id' => 5]).
     *
     * @param array<string, scalar> $params
     */
    public function generate(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new InvalidArgumentException("Route bernama [{$name}] tidak terdaftar.");
        }

        $url = $this->namedRoutes[$name]['pattern'];

        foreach ($params as $key => $value) {
            $url = preg_replace('/\{' . preg_quote((string) $key, '/') . '\}/', rawurlencode((string) $value), $url) ?? $url;
        }

        if (preg_match('/\{[a-zA-Z_]+\}/', $url)) {
            throw new InvalidArgumentException("Param route [{$name}] tidak lengkap: {$url}");
        }

        $query = array_diff_key($params, $this->namedRoutes[$name]['params']);
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /** @return list<array{method: string, pattern: string, name: ?string, action: string, middleware: list<string>}> */
    public function all(): array
    {
        $list = [];
        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $route) {
                $list[] = [
                    'method' => $method,
                    'pattern' => $route['pattern'],
                    'name' => $route['name'],
                    'action' => $route['action'],
                    'middleware' => $route['middleware'],
                ];
            }
        }

        // Urut stabil untuk route:list: berdasarkan pattern lalu method.
        usort($list, static fn(array $a, array $b): int => [$a['pattern'], $a['method']] <=> [$b['pattern'], $b['method']]);

        return $list;
    }

    private function add(string $method, string $pattern, string $action, ?string $name, array $middleware): void
    {
        [$regex, $params] = $this->compile($pattern);

        $this->routes[$method][] = [
            'regex' => $regex,
            'params' => $params,
            'name' => $name,
            'action' => $action,
            'middleware' => [...$this->groupMiddleware, ...$middleware],
            'pattern' => $pattern,
        ];

        if ($name !== null) {
            $this->namedRoutes[$name] = [
                'pattern' => $pattern,
                'params' => array_combine($params, $params) ?: [],
            ];
        }
    }

    /**
     * Compile pola "/orders/{id}" menjadi regex dengan named group.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function compile(string $pattern): array
    {
        $params = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            function (array $m) use (&$params): string {
                $params[] = $m[1];
                return '(?P<' . $m[1] . '>[^\/]+)';
            },
            $pattern
        );

        return ['#^' . $regex . '$#', $params];
    }
}
