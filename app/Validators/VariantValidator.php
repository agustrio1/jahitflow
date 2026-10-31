<?php

declare(strict_types=1);

namespace App\Validators;

/**
 * Validasi varian produk. Harga satuan WAJIB angka >= 0 dan bertipe
 * DECIMAL di database — jangan pernah percaya harga dari browser saat
 * menghitung pesanan (perhitungan ulang memakai nilai database).
 */
final class VariantValidator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, list<string>>
     */
    public function validate(array $data): array
    {
        $errors = [];

        if (trim((string) ($data['color'] ?? '')) === '') {
            $errors['color'][] = 'Warna wajib diisi.';
        } elseif (mb_strlen(trim((string) $data['color'])) > 60) {
            $errors['color'][] = 'Warna maksimal 60 karakter.';
        }

        if (trim((string) ($data['size'] ?? '')) === '') {
            $errors['size'][] = 'Ukuran wajib diisi.';
        } elseif (mb_strlen(trim((string) $data['size'])) > 20) {
            $errors['size'][] = 'Ukuran maksimal 20 karakter.';
        }

        $price = $data['unit_price'] ?? null;
        if ($price === null || $price === '') {
            $errors['unit_price'][] = 'Harga satuan wajib diisi.';
        } elseif (!is_numeric($price) || (float) $price < 0) {
            $errors['unit_price'][] = 'Harga satuan harus angka dan tidak boleh negatif.';
        } elseif ((float) $price > 999_999_999) {
            $errors['unit_price'][] = 'Harga satuan melebihi batas yang diizinkan.';
        }

        $sku = trim((string) ($data['sku'] ?? ''));
        if ($sku !== '' && (mb_strlen($sku) > 60 || preg_match('/^[A-Za-z0-9._\-\/]+$/', $sku) !== 1)) {
            $errors['sku'][] = 'SKU maksimal 60 karakter, hanya huruf/angka/dot/garis/miring.';
        }

        return $errors;
    }
}
