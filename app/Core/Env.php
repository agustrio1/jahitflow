<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Loader .env minimal.
 *
 * Nilai tidak menimpa environment variable yang sudah ada (12-factor),
 * sehingga konfigurasi deployment selalu mengalahkan file .env lokal.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $entries = [];

    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = trim($value);

            // Strip quote tunggal/ganda di sekeliling nilai.
            if (strlen($value) > 1
                && (($value[0] === '"' && str_ends_with($value, '"'))
                    || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }

            if ($key === '') {
                continue;
            }

            self::$entries[$key] = $value;

            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $fromRuntime = $_ENV[$key] ?? getenv($key);

        if (is_string($fromRuntime) && $fromRuntime !== '') {
            return $fromRuntime;
        }

        return self::$entries[$key] ?? $default;
    }
}
