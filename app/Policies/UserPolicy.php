<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Contoh Policy konkret (§11). Manajemen pengguna adalah domain admin;
 * role lain hanya boleh melihat profil sendiri.
 *
 * Belum dipakai penuh di Fase 3 — disiapkan agar modul Fase 4/5 tinggal
 * memanggil, dan sebagai bukti pola authorization berjalan.
 */
final class UserPolicy extends Policy
{
    public function viewAny(?User $user): void
    {
        $this->gate($this->isAdmin($user), 'Hanya Administrator yang dapat mengelola daftar pengguna.');
    }

    public function create(?User $user): void
    {
        $this->gate($this->isAdmin($user), 'Hanya Administrator yang dapat menambah pengguna.');
    }

    public function delete(?User $user): void
    {
        $this->gate($this->isAdmin($user), 'Hanya Administrator yang dapat menghapus pengguna.');
    }

    /** User boleh melihat profilnya sendiri; selain itu harus admin. */
    public function view(?User $actor, User $target): void
    {
        $this->gate($this->isAdmin($actor) || $actor?->id === $target->id, 'Tidak boleh melihat profil pengguna lain.');
    }
}
