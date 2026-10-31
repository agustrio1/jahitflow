<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Session;
use App\Models\User;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\UserRepository;

/**
 * Guard autentikasi berbasis session.
 *
 * Alur login (§12 DOC): cek throttle → cari user aktif → verifikasi hash
 * → sukses: reset throttle, regenerate session, simpan user id.
 * Pesan gagal dibuat umum agar tidak membocorkan email yang terdaftar.
 */
final class Auth
{
    public const SESSION_KEY = 'auth_user_id';

    private ?User $resolved = null;

    public function __construct(
        private readonly Session $session,
        private readonly UserRepository $users,
        private readonly LoginAttemptRepository $attempts,
        private readonly Config $config,
    ) {
    }

    /**
     * @return array{ok: bool, errors: array<string, list<string>>}
     */
    public function attempt(string $email, string $password, ?string $ip): array
    {
        $maxAttempts = (int) ($this->config->get('auth.login_throttle.max_attempts') ?? 5);
        $lockMinutes = (int) ($this->config->get('auth.login_throttle.lock_minutes') ?? 15);

        if ($this->attempts->isLocked($email)) {
            $menit = (int) ceil($this->attempts->secondsRemaining($email) / 60);
            return ['ok' => false, 'errors' => ['email' => ["Terlalu banyak percobaan. Coba lagi dalam {$menit} menit."]]];
        }

        $user = $this->users->findByEmail($email);
        $hash = $user?->password;

        if ($user === null || !$user->isActive || $hash === null || !password_verify($password, $hash)) {
            $this->attempts->recordFailure($email, $ip, $maxAttempts, $lockMinutes);
            return ['ok' => false, 'errors' => ['email' => ['Email atau password salah.']]];
        }

        $this->login($user);

        return ['ok' => true, 'errors' => []];
    }

    public function login(User $user): void
    {
        $this->attempts->clear($user->email);
        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, $user->id);
        $this->resolved = $user;
    }

    public function logout(): void
    {
        $this->session->flush();
        $this->resolved = null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        $id = $this->session->get(self::SESSION_KEY);
        return $id === null ? null : (int) $id;
    }

    public function user(): ?User
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $id = $this->id();
        if ($id === null) {
            return null;
        }

        $user = $this->users->find($id);
        // Akun dinonaktifkan / dihapus → sesi tidak berlaku lagi.
        if ($user === null || !$user->isActive) {
            $this->session->forget(self::SESSION_KEY);
            return null;
        }

        return $this->resolved = $user;
    }
}
