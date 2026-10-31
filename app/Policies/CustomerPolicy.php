<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/**
 * Otorisasi modul pelanggan (§11). Penyembunyian tombol BUKAN
 * authorization — controller memanggil policy ini di setiap aksi.
 *
 * Keputusan peran (konsisten dgn navigasi):
 * - lihat/tambah/edit: admin & manager
 * - nonaktifkan/aktifkan kembali: admin saja (menyembunyikan data
 *   dari pilihan pesanan adalah keputusan manager-level ke atas)
 */
final class CustomerPolicy extends Policy
{
    public function viewAny(?User $user): void
    {
        $this->gate($user?->hasRole([User::ROLE_ADMIN, User::ROLE_MANAGER]) ?? false, 'Hanya Admin dan Manajer yang dapat mengakses data pelanggan.');
    }

    public function view(?User $user, Customer $customer): void
    {
        $this->viewAny($user);
    }

    public function create(?User $user): void
    {
        $this->viewAny($user);
    }

    public function update(?User $user, Customer $customer): void
    {
        $this->viewAny($user);
    }

    public function setStatus(?User $user, Customer $customer): void
    {
        $this->gate($this->isAdmin($user), 'Hanya Administrator yang dapat mengaktifkan/nonaktifkan pelanggan.');
    }

    /**
     * Versi boolean untuk UI (sembunyikan tombol) — BUKAN authorization;
     * eksekusi tetap dijaga method gate() yang melempar 403.
     */
    public function allowsViewAny(?User $user): bool
    {
        return $user?->hasRole([User::ROLE_ADMIN, User::ROLE_MANAGER]) ?? false;
    }

    public function allowsSetStatus(?User $user): bool
    {
        return $this->isAdmin($user);
    }
}
