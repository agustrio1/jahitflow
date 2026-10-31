<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Exception;

/**
 * Dilempar oleh FormRequest/Validator saat data tidak valid.
 * Membawa pesan error per field + input lama untuk mengisi ulang form.
 */
final class ValidationException extends Exception
{
    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed>        $old
     */
    public function __construct(
        public readonly array $errors,
        public readonly array $old = [],
    ) {
        parent::__construct('Validasi gagal.');
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            return $messages[0] ?? null;
        }
        return null;
    }
}
