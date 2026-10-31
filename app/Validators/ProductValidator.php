<?php

declare(strict_types=1);

namespace App\Validators;

/**
 * Validasi input produk katalog (§8.C). Kategori wajib ada — diverifikasi
 * service terhadap database; validator menjaga bentuk input.
 */
final class ProductValidator
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
            $errors['name'][] = 'Nama produk wajib diisi.';
        } elseif (mb_strlen($name) > 150) {
            $errors['name'][] = 'Nama maksimal 150 karakter.';
        }

        $categoryId = (int) ($data['category_id'] ?? 0);
        if ($categoryId <= 0) {
            $errors['category_id'][] = 'Kategori produk wajib dipilih.';
        }

        $material = trim((string) ($data['material_spec'] ?? ''));
        if (mb_strlen($material) > 255) {
            $errors['material_spec'][] = 'Spesifikasi bahan maksimal 255 karakter.';
        }

        if (mb_strlen(trim((string) ($data['custom_spec'] ?? ''))) > 2000) {
            $errors['custom_spec'][] = 'Spesifikasi custom maksimal 2000 karakter.';
        }

        return $errors;
    }
}
