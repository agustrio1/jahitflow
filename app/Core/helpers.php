<?php

declare(strict_types=1);

use App\Core\Application;

/**
 * Helper global otomatis (loader `files` Composer).
 * Fungsinya sengaja sedikit dan dangkal — logika apa pun yang
 * punya keputusan bisnis tinggal di class, bukan di sini.
 */

if (!function_exists('e')) {
    /** Escape kontekstual untuk HTML — senjata utama anti-XSS. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('route')) {
    /**
     * URL dari route name: route('orders.show', ['id' => 5]).
     *
     * @param array<string, scalar> $params
     */
    function route(string $name, array $params = []): string
    {
        return Application::instance()->router()->generate($name, $params);
    }
}

if (!function_exists('asset')) {
    /** URL aset publik dengan cache-buster versi aplikasi. */
    function asset(string $path): string
    {
        $version = Application::instance()->container()->make(App\Core\Config::class)->get('app.asset_version', '1');
        return '/assets/' . ltrim($path, '/') . '?v=' . rawurlencode((string) $version);
    }
}

if (!function_exists('csrf_field')) {
    /** Hidden input CSRF untuk form HTML. */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Application::instance()->session()->token();
    }
}

if (!function_exists('old')) {
    /** Nilai input lama setelah validasi gagal. */
    function old(array $oldInput, string $key, mixed $default = ''): mixed
    {
        return $oldInput[$key] ?? $default;
    }
}

if (!function_exists('money')) {
    /** Format rupiah: Rp1.250.000 (pemisah titik, tanpa desimal). */
    function money(mixed $value): string
    {
        return 'Rp' . number_format((float) $value, 0, ',', '.');
    }
}

if (!function_exists('tanggal')) {
    /** Format tanggal Indonesia: 28 Okt 2026, atau 28-10-2026 14:30 dengan waktu. */
    function tanggal(?string $datetime, bool $denganWaktu = false): string
    {
        if ($datetime === null || $datetime === '') {
            return '-';
        }

        $ts = strtotime($datetime);
        if ($ts === false) {
            return $datetime;
        }

        $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $dasar = date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);

        return $denganWaktu ? $dasar . ', ' . date('H:i', $ts) : $dasar;
    }
}

if (!function_exists('icon')) {
    /**
     * Ikon SVG garis (stroke) inline — BUKAN emoji (§ UI DOC).
     * Mengikuti warna teks (currentColor) & ukuran 1.1em.
     * Nama tak dikenal menghasilkan kotak kosong (tidak bocor ke HTML mentah).
     */
    function icon(string $name, string $class = 'jf-icon'): string
    {
        static $paths = [
            'grid'   => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
            'users'  => '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 6a3 3 0 0 1 0 6"/><path d="M18 20a6 6 0 0 0-3-5.2"/>',
            'box'    => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
            'tag'    => '<path d="M20.6 13.4L13.4 20.6a2 2 0 0 1-2.8 0l-7.2-7.2A2 2 0 0 1 3 12V5a2 2 0 0 1 2-2h7a2 2 0 0 1 1.4.6l7.2 7.2a2 2 0 0 1 0 2.6z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
            'cart'   => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.4 12.3a1 1 0 0 0 1 .7h9.2a1 1 0 0 0 1-.8L21 7H6"/>',
            'factory'=> '<path d="M3 21V9l6 4V9l6 4V5h6v16z"/><path d="M7 21v-4"/><path d="M13 21v-4"/>',
            'layers' => '<path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/>',
            'check'  => '<path d="M9 11l3 3 7-7"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/>',
            'wallet' => '<path d="M3 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2"/><rect x="3" y="7" width="18" height="12" rx="2"/><path d="M16 13h2"/>',
            'truck'  => '<rect x="1" y="6" width="14" height="10" rx="1"/><path d="M15 9h4l3 3v4h-7"/><circle cx="6" cy="18" r="1.6"/><circle cx="18" cy="18" r="1.6"/>',
            'chart'  => '<path d="M3 3v18h18"/><rect x="7" y="11" width="3" height="6"/><rect x="12" y="7" width="3" height="10"/><rect x="17" y="13" width="3" height="4"/>',
            'beaker' => '<path d="M9 3h6"/><path d="M10 3v6l-5 9a2 2 0 0 0 1.8 3h10.4a2 2 0 0 0 1.8-3l-5-9V3"/><path d="M7 15h10"/>',
            'logout' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
            'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'download' => '<path d="M12 4v10"/><path d="M8 11l4 4 4-4"/><path d="M5 20h14"/>',
            'printer'  => '<path d="M7 8V4h10v4"/><rect x="4" y="8" width="16" height="8" rx="2"/><path d="M7 14h10v6H7z"/>',
        ];

        $body = $paths[$name] ?? '<rect x="4" y="4" width="16" height="16" rx="2"/>';

        return '<svg class="' . e($class) . '" viewBox="0 0 24 24" width="20" height="20" fill="none" '
            . 'stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" '
            . 'aria-hidden="true">' . $body . '</svg>';
    }
}
