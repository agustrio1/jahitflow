<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

/**
 * Dilempar ketika user tidak diizinkan mengakses sebuah resource
 * (hasil dari Policy). Diterjemahkan menjadi 403.
 */
final class AuthorizationException extends RuntimeException
{
    public function __construct(string $message = 'Anda tidak memiliki akses untuk tindakan ini.')
    {
        parent::__construct($message);
    }
}
