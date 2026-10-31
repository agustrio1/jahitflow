<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Otorisasi modul Laporan (§8.J). Laporan adalah analitik lintas modul —
 * boleh dilihat admin, manager, dan keuangan. Read-only: tidak ada aksi tulis,
 * maka cukup satu gerbang (viewAny) untuk hub, tiap laporan, dan ekspor CSV.
 */
final class ReportPolicy extends Policy
{
    private const VIEW_ROLES = [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_KEUANGAN];

    public function viewAny(?User $user): void
    {
        $this->gate($user?->hasRole(self::VIEW_ROLES) ?? false, 'Tidak punya akses ke modul Laporan.');
    }

    public function view(?User $user): void
    {
        $this->viewAny($user);
    }

    public function export(?User $user): void
    {
        $this->viewAny($user);
    }

    /** Boolean UI: boleh membuka modul laporan. */
    public function allowsView(?User $user): bool
    {
        return $user?->hasRole(self::VIEW_ROLES) ?? false;
    }
}
