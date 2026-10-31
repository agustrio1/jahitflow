<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\Auth;

/**
 * HomeController — halaman verifikasi Core Foundation (Fase 1).
 *
 * Sekaligus contoh pola controller tipis: tanpa logika bisnis,
 * hanya merangkai data untuk view. Modul bisnis akan mengikuti
 * pola yang sama dengan tambahan Service + Validator di Fase 4.
 */
final class HomeController
{
    public function __construct(
        private readonly View $view,
        private readonly Session $session,
        private readonly Database $database,
        private readonly Auth $auth,
    ) {
    }

    /**
     * Titik masuk '/': lempar ke dashboard bila sudah login, ke login bila tamu.
     * Tidak menyentuh DB, jadi aman walau database belum siap.
     */
    public function root(Request $request): Response
    {
        return Response::redirect($this->auth->check() ? route('dashboard') : route('login'));
    }

    public function index(Request $request): Response
    {
        return Response::html($this->view->render('home', [
            'title' => 'Fondasi Verifikasi',
            'checks' => $this->foundationChecks(),
        ]));
    }

    /**
     * Endpoint demo: membuktikan rantai CSRF → flash → redirect → render
     * bekerja end-to-end sebelum modul bisnis dibangun.
     */
    public function flashDemo(Request $request): Response
    {
        $name = trim((string) $request->input('nama', ''));

        $this->session->flash('success', $name === ''
            ? 'Flash message berhasil dikirim (tanpa nama).'
            : "Halo, {$name}! Flash + CSRF bekerja.");
        $this->session->flashInput($request->all());

        return Response::redirect(route('foundation'));
    }

    /**
     * Status komponen fondasi — data nyata, bukan angka dekoratif.
     *
     * @return list<array{label: string, ok: bool, note: string}>
     */
    private function foundationChecks(): array
    {
        $dbOk = false;
        $dbNote = 'belum terhubung';
        try {
            $dbOk = (bool) $this->database->pdo()->query('SELECT 1')->fetchColumn();
            $dbNote = $dbOk ? 'terhubung (SELECT 1)' : $dbNote;
        } catch (\Throwable $e) {
            $dbNote = 'gagal: ' . $e->getMessage();
        }

        return [
            ['label' => 'PHP ' . PHP_VERSION, 'ok' => version_compare(PHP_VERSION, '8.4.0', '>='), 'note' => 'baseline >= 8.4'],
            ['label' => 'Autoload PSR-4', 'ok' => class_exists(self::class), 'note' => 'vendor/autoload.php jalan'],
            ['label' => 'Session + CSRF', 'ok' => $this->session->validateToken($this->session->token()), 'note' => 'token aktif'],
            ['label' => 'View renderer', 'ok' => is_dir(dirname(__DIR__, 2) . '/resources/views'), 'note' => 'resources/views ada'],
            ['label' => 'Database MySQL', 'ok' => $dbOk, 'note' => $dbNote],
        ];
    }
}
