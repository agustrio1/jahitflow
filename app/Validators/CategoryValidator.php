<?php

declare(strict_types=1);

namespace App\Validators;

/**
 * Validasi kategori produk.
 */
final class CategoryValidator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, list<string>>
     */
    public function validate(array $data): array
    {
        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'][] = 'Nama kategori wajib diisi.';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'][] = 'Nama kategori maksimal 100 karakter.';
        }

        if (mb_strlen(trim((string) ($data['description'] ?? ''))) > 255) {
            $errors['description'][] = 'Deskripsi maksimal 255 karakter.';
        }

        return $errors;
    }
}
