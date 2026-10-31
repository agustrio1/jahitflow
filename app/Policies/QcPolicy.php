<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Otorisasi modul Quality Control (§8.G). QC adalah gerbang operasional:
 * peran lapangan (operator) bersama manager/admin boleh melihat & mencatat
 * pemeriksaan. Tidak ada edit/hapus — pemeriksaan bersifat log audit.
 * Penegakan nyata lewat gate() yang melempar 403 (§12); boolean untuk UI.
 */
final class QcPolicy extends Policy
{
    private const VIEW_ROLES = [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_OPERATOR];

    public function viewAny(?User $user): void
    {
        $this->gate($user?->hasRole(self::VIEW_ROLES) ?? false, 'Tidak punya akses ke modul Quality Control.');
    }

    public function view(?User $user): void
    {
        $this->viewAny($user);
    }

    public function create(?User $user): void
    {
        $this->viewAny($user);
    }

    /** Boolean UI: boleh mencatat pemeriksaan (peran operasional). */
    public function allowsRecord(?User $user): bool
    {
        return $user?->hasRole(self::VIEW_ROLES) ?? false;
    }
}
