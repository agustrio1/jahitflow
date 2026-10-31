<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\Auth;
use App\Validators\LoginValidator;

/**
 * Autentikasi: tampilkan form, proses login (validator + guard + throttle),
 * logout. Tanpa logika bisnis di sini — didelegasi ke Auth & LoginValidator
 * (§7.1 DOC). Controller tipis, mengikuti pola HomeController.
 */
final class AuthController
{
    public function __construct(
        private readonly View $view,
        private readonly Session $session,
        private readonly Auth $auth,
        private readonly LoginValidator $validator,
    ) {
    }

    public function showLogin(Request $request): Response
    {
        return Response::html($this->view->render('auth.login', ['title' => 'Masuk — JahitFlow']));
    }

    public function login(Request $request): Response
    {
        $credentials = [
            'email' => trim((string) $request->input('email', '')),
            'password' => (string) $request->input('password', ''),
        ];

        $errors = $this->validator->validate($credentials);

        if ($errors === []) {
            $result = $this->auth->attempt($credentials['email'], $credentials['password'], $request->ip());

            if ($result['ok']) {
                $intended = $this->session->get('url.intended');
                $this->session->forget('url.intended');
                $this->session->flash('success', 'Selamat datang kembali, ' . ($this->auth->user()?->name ?? '') . '.');

                // Lanjut ke URL tujuan hanya jika path internal (anti open-redirect).
                $target = (is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//'))
                    ? $intended
                    : route('dashboard');

                return Response::redirect($target);
            }

            $errors = $result['errors'];
        }

        $this->session->flashErrors($errors);
        $this->session->flashInput(['email' => $credentials['email']]);

        return Response::redirect(route('login'));
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();
        // flush() sudah membentuk session kosong + ID baru; flash berikutnya
        // aman tampil pada request setelah redirect.
        $this->session->flash('success', 'Anda telah keluar.');

        return Response::redirect(route('login'));
    }
}
