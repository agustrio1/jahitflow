<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Otorisasi modul Pembayaran (§8.H). Uang masuk adalah ranah keuangan: hanya
 * admin & keuangan boleh melihat & mencatat pembayaran. Tidak ada edit/hapus —
 * transaksi bersifat log audit (koreksi lewat transaksi susulan). Penegakan nyata
 * lewat gate() yang melempar 403 (§12); boolean untuk UI.
 */
final class PaymentPolicy extends Policy
{
    private const VIEW_ROLES = [User::ROLE_ADMIN, User::ROLE_KEUANGAN];

    public function viewAny(?User $user): void
    {
        $this->gate($user?->hasRole(self::VIEW_ROLES) ?? false, 'Tidak punya akses ke modul Pembayaran.');
    }

    public function view(?User $user): void
    {
        $this->viewAny($user);
    }

    public function create(?User $user): void
    {
        $this->viewAny($user);
    }

    /** Boolean UI: boleh mencatat pembayaran (admin/keuangan). */
    public function allowsRecord(?User $user): bool
    {
        return $user?->hasRole(self::VIEW_ROLES) ?? false;
    }
}
