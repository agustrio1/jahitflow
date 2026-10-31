<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Otorisasi katalog produk. Semua role boleh MELIHAT katalog
 * (operator perlu spesifikasi bahan, keuangan perlu harga);
 * hanya admin & manager yang boleh mengubah.
 */
final class ProductPolicy extends Policy
{
    public function viewAny(?User $user): void
    {
        $this->gate($user !== null, 'Pengguna harus login.');
    }

    public function view(?User $user, Product $product): void
    {
        $this->viewAny($user);
    }

    public function manage(?User $user): void
    {
        $this->gate(
            $user?->hasRole([User::ROLE_ADMIN, User::ROLE_MANAGER]) ?? false,
            'Hanya Admin dan Manajer yang dapat mengubah katalog produk.',
        );
    }

    public function setStatus(?User $user, Product $product): void
    {
        $this->gate($this->isAdmin($user), 'Hanya Administrator yang dapat mengaktifkan/nonaktifkan produk.');
    }

    /**
     * Versi boolean untuk UI (sembunyikan tombol) — BUKAN authorization;
     * eksekusi tetap dijaga manage()/setStatus() yang melempar 403.
     */
    public function allowsManage(?User $user): bool
    {
        return $user?->hasRole([User::ROLE_ADMIN, User::ROLE_MANAGER]) ?? false;
    }

    /** Versi boolean UI untuk tombol aktif/nonaktif (admin saja). */
    public function allowsSetStatus(?User $user): bool
    {
        return $this->isAdmin($user);
    }
}
