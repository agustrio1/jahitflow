<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Wrapper read-only atas superglobal PHP.
 *
 * Method spoofing (`_method` pada form POST) hanya diterima dari field
 * terkontrol, tidak pernah dari header bebas.
 */
final class Request
{
    /** @var array<string, mixed> */
    private array $routeParams = [];

    /** @var array<string, string> */
    private array $headers;

    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $files,
        private readonly array $server,
    ) {
        $this->headers = $this->collectHeaders($server);
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Method spoofing aman: POST dengan field _method bernilai method valid.
        if ($method === 'POST' && isset($_POST['_method'])) {
            $spoof = strtoupper((string) $_POST['_method']);
            if (in_array($spoof, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $spoof;
            }
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
        $script = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script !== '' && $script !== '/' && str_starts_with($path, $script)) {
            $path = substr($path, strlen($script));
        }
        $path = '/' . trim((string) $path, '/');

        return new self($method, $path, $_GET, $_POST, $_FILES, $_SERVER);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function is(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /** Semua input gabungan body + query (body menang). */
    public function all(): array
    {
        return $this->body + $this->query;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    /** Penanda request berasal dari HTMX (header HX-Request). */
    public function isHtmx(): bool
    {
        return ($this->header('hx-request') ?? '') === 'true';
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /** @param array<string, mixed> $params */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    /** @param array<string, mixed> $server */
    private function collectHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }
        return $headers;
    }
}
