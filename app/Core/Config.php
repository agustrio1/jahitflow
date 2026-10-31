<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Penyimpan konfigurasi berbasis file PHP di direktori config/.
 * Akses dengan dot-notation: config()->get('database.host').
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function __construct(private readonly string $configPath)
    {
    }

    public function load(): void
    {
        foreach (glob($this->configPath . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            $this->items[$key] = require $file;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref = &$this->items;

        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        $ref = $value;
    }
}
