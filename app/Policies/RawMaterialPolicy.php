<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RawMaterial;
use App\Models\User;

/**
 * Otorisasi modul bahan baku & stok (§8.F). Operator boleh MELIHAT & mencatat
 * pergerakan stok (mereka memegang barang); membuat/mengubah master bahan dan
 * mengaktifkan/nonaktifkan tetap Admin/Manajer. Boolean hanya untuk UI;
 * penegakan nyata lewat gate() yang melempar 403 (§12 DOC).
 */
final class RawMaterialPolicy extends Policy
{
    private const MANAGE_ROLES = [User::ROLE_ADMIN, User::ROLE_MANAGER];
    private const VIEW_ROLES = [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_OPERATOR];

    public function viewAny(?User $user): void
    {
        $this->gate($user?->hasRole(self::VIEW_ROLES) ?? false, 'Tidak punya akses ke modul bahan baku & stok.');
    }

    public function view(?User $user, RawMaterial $material): void
    {
        $this->viewAny($user);
    }

    public function create(?User $user): void
    {
        $this->gate($user?->hasRole(self::MANAGE_ROLES) ?? false, 'Hanya Admin dan Manajer yang dapat membuat master bahan.');
    }

    public function update(?User $user, RawMaterial $material): void
    {
        $this->create($user);
    }

    public function setStatus(?User $user, RawMaterial $material): void
    {
        $this->create($user);
    }

    /** Mencatat pergerakan stok boleh dilakukan peran operasional. */
    public function record(?User $user): void
    {
        $this->viewAny($user);
    }

    /** Boolean UI: boleh kelola master bahan (admin & manager). */
    public function allowsManage(?User $user): bool
    {
        return $user?->hasRole(self::MANAGE_ROLES) ?? false;
    }

    /** Boolean UI: boleh mencatat pergerakan stok (semua peran operasional). */
    public function allowsRecord(?User $user): bool
    {
        return $user?->hasRole(self::VIEW_ROLES) ?? false;
    }
}
