<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductionOrder;
use App\Models\User;

/**
 * Otorisasi perintah produksi (§8.E). Operator boleh MEMBUKA & membarui
 * progres tahap (mereka melaksanakan pekerjaan); membuat perintah dari pesanan &
 * membatalkan tetap Admin/Manajer. Boolean hanya untuk UI; penegakan nyata
 * lewat method gate() yang melempar 403 (§12 DOC).
 */
final class ProductionOrderPolicy extends Policy
{
    private const MANAGE_ROLES = [User::ROLE_ADMIN, User::ROLE_MANAGER];
    private const VIEW_ROLES = [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_OPERATOR];

    public function viewAny(?User $user): void
    {
        $this->gate($user?->hasRole(self::VIEW_ROLES) ?? false, 'Tidak punya akses ke modul produksi.');
    }

    public function view(?User $user, ProductionOrder $order): void
    {
        $this->viewAny($user);
    }

    public function create(?User $user): void
    {
        $this->gate($user?->hasRole(self::MANAGE_ROLES) ?? false, 'Hanya Admin dan Manajer yang dapat membuat perintah produksi.');
    }

    public function cancel(?User $user, ProductionOrder $order): void
    {
        $this->create($user);
    }

    public function updateStage(?User $user, ProductionOrder $order): void
    {
        $this->viewAny($user);
    }

    /** Boolean UI: boleh membuat/membatalkan (admin & manager). */
    public function allowsManage(?User $user): bool
    {
        return $user?->hasRole(self::MANAGE_ROLES) ?? false;
    }

    /** Boolean UI: boleh memperbarui progres tahap (semua peran produksi). */
    public function allowsProgress(?User $user): bool
    {
        return $user?->hasRole(self::VIEW_ROLES) ?? false;
    }
}
