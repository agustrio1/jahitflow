<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Otorisasi modul Pengiriman (§8.I). Pengiriman adalah ranah operasional/managerial:
 * admin & manager boleh melihat, membuat batch, dan mengubah status. Penegakan nyata
 * lewat gate() yang melempar 403 (§12); boolean untuk UI (sembunyikan tombol ≠ aman).
 */
final class DeliveryPolicy extends Policy
{
    private const VIEW_ROLES = [User::ROLE_ADMIN, User::ROLE_MANAGER];

    public function viewAny(?User $user): void
    {
        $this->gate($user?->hasRole(self::VIEW_ROLES) ?? false, 'Tidak punya akses ke modul Pengiriman.');
    }

    public function view(?User $user): void
    {
        $this->viewAny($user);
    }

    public function create(?User $user): void
    {
        $this->viewAny($user);
    }

    public function updateStatus(?User $user): void
    {
        $this->viewAny($user);
    }

    /** Boolean UI: boleh membuat batch pengiriman (admin/manager). */
    public function allowsCreate(?User $user): bool
    {
        return $user?->hasRole(self::VIEW_ROLES) ?? false;
    }

    /** Boolean UI: boleh mengubah status pengiriman. */
    public function allowsManage(?User $user): bool
    {
        return $user?->hasRole(self::VIEW_ROLES) ?? false;
    }
}
