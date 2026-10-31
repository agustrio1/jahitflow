<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Wrapper session dengan storage file privat di storage/sessions,
 * token CSRF, flash message, dan regenerasi ID yang aman.
 *
 * Konfigurasi cookie berasal dari config/session.php:
 * HttpOnly + SameSite=Lax selalu aktif; Secure ditambahkan saat produksi.
 */
final class Session
{
    public const TOKEN_KEY = '_token';

    private bool $started = false;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        $path = $this->config['path'] ?? sys_get_temp_dir();
        if (!is_dir($path)) {
            mkdir($path, 0770, recursive: true);
        }
        session_save_path($path);

        session_name($this->config['name'] ?? 'jahitflow_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => (bool) ($this->config['secure'] ?? false),
            'httponly' => true,
            'samesite' => $this->config['samesite'] ?? 'Lax',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) ($this->config['lifetime'] ?? 7200));

        session_start();
        $this->started = true;

        // Idle timeout: hanguskan session jika terlalu lama diam.
        $now = time();
        if (isset($_SESSION['_last_active']) && ($now - (int) $_SESSION['_last_active']) > (int) ($this->config['idle_timeout'] ?? 7200)) {
            $this->flush();
        }
        $_SESSION['_last_active'] = $now;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function flush(): void
    {
        $_SESSION = [];
        $this->regenerate();
    }

    // ---------------------------------------------------------------
    // CSRF
    // ---------------------------------------------------------------

    public function token(): string
    {
        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::TOKEN_KEY];
    }

    public function validateToken(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION[self::TOKEN_KEY])
            && hash_equals($_SESSION[self::TOKEN_KEY], $token);
    }

    // ---------------------------------------------------------------
    // Flash message (sekali baca)
    // ---------------------------------------------------------------

    /** @param 'success'|'error'|'info'|'warning' $type */
    public function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type: string, message: string}> */
    public function pullFlash(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flashes;
    }

    /** Simpan input lama untuk mengisi ulang form setelah redirect. */
    public function flashInput(array $input): void
    {
        unset($input[self::TOKEN_KEY], $input['_method']);
        $_SESSION['_old'] = $input;
    }

    /** @return array<string, mixed> */
    public function pullOldInput(): array
    {
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);
        return $old;
    }

    /** @param array<string, list<string>> $errors */
    public function flashErrors(array $errors): void
    {
        $_SESSION['_errors'] = $errors;
    }

    /** @return array<string, list<string>> */
    public function pullErrors(): array
    {
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
        return $errors;
    }
}
