<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Response HTTP dengan dukungan header HTMX (HX-Redirect, HX-Refresh).
 */
final class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    public function __construct(
        private string $body = '',
        private int $status = 200,
        array $headers = [],
    ) {
        foreach ($headers as $name => $value) {
            $this->headers[strtolower($name)] = "{$name}: {$value}";
        }
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return new self((string) $body, $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    /**
     * Unduhan CSV (§8.J ekspor laporan). Nama berkas disanitasi agar aman dari
     * header injection; BOM UTF-8 di depan agar Excel locale membaca karakter
     * non-ASCII (mis. 'Rp', 'µ') dengan benar.
     */
    public static function csv(string $body, string $filename): self
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'export.csv';
        return new self("\xEF\xBB\xBF" . $body, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $safe . '"',
        ]);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self('', $status, ['Location' => $to]);
    }

    /**
     * Redirect khusus HTMX: browser swap tanpa full reload.
     * Penting karena hx-get tidak bisa mengikuti redirect 302 cross-content.
     */
    public static function htmxRedirect(string $to): self
    {
        return new self('', 200, ['HX-Redirect' => $to]);
    }

    public static function notFound(string $message = 'Halaman tidak ditemukan.'): self
    {
        return self::html("<h1>404 — {$message}</h1>", 404);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[strtolower($name)] = "{$name}: {$value}";
        return $this;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $line) {
                header($line, replace: true);
            }
        }

        echo $this->body;
    }
}
