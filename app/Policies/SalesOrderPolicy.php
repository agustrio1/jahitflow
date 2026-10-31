<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;

/**
 * Otorisasi sales order. Melihat pesanan terbuka untuk semua pengguna
 * login (operator produksi & keuangan butuh konteks); membuat/mengubah/
 * mengonfirmasi/membatalkan hanya Admin & Manajer (§9 DOC — konfirmasi
 * adalah persetujuan bisnis). Penandaan boolean hanya untuk UI; eksekusi
 * tetap dijaga method gate() yang melempar 403 (§12 DOC).
 */
final class SalesOrderPolicy extends Policy
{
    public function viewAny(?User $user): void
    {
        $this->gate($user !== null, 'Pengguna harus login.');
    }

    public function view(?User $user, SalesOrder $order): void
    {
        $this->viewAny($user);
    }

    public function create(?User $user): void
    {
        $this->gate(
            $user?->hasRole([User::ROLE_ADMIN, User::ROLE_MANAGER]) ?? false,
            'Hanya Admin dan Manajer yang dapat membuat pesanan.',
        );
    }

    public function update(?User $user, SalesOrder $order): void
    {
        $this->create($user);
    }

    public function confirm(?User $user, SalesOrder $order): void
    {
        $this->create($user);
    }

    public function cancel(?User $user, SalesOrder $order): void
    {
        $this->create($user);
    }

    public function delete(?User $user, SalesOrder $order): void
    {
        $this->create($user);
    }

    /** Versi boolean UI: boleh membuat/mengubah (admin & manager). */
    public function allowsManage(?User $user): bool
    {
        return $user?->hasRole([User::ROLE_ADMIN, User::ROLE_MANAGER]) ?? false;
    }
}
