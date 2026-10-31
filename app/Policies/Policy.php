<?php

declare(strict_types=1);

namespace App\Policies;

use App\Core\Exceptions\AuthorizationException;
use App\Models\User;

/**
 * Basis Policy (§7.1 DOC): memutuskan apakah user boleh melakukan
 * suatu aksi terhadap resource. Controller memanggil policy, bukan
 * memeriksa role secara inline.
 */
abstract class Policy
{
    /** Melempar 403 bila cek gagal. Gunakan di awal method aksi. */
    protected function gate(bool $allowed, string $message = 'Aksi tidak diizinkan.'): void
    {
        if (!$allowed) {
            throw new AuthorizationException($message);
        }
    }

    protected function isAdmin(?User $user): bool
    {
        return $user?->isAdmin() ?? false;
    }
}
