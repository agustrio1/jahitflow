<?php

declare(strict_types=1);

namespace App\Validators;

use App\Models\RawMaterial;

/**
 * Validasi input master bahan baku (§8.F). Uniqueness kode dicek service lewat
 * database; di sini hanya bentuk/isi bidang yang diperiksa (murni, teruji).
 */
final class RawMaterialValidator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, list<string>>
     */
    public function validate(array $data): array
    {
        $errors = [];

        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            $errors['code'][] = 'Kode bahan wajib diisi.';
        } elseif (preg_match('/^[A-Z0-9][A-Z0-9\-]{1,29}$/', $code) !== 1) {
            $errors['code'][] = 'Kode: 2-30 karakter, huruf/angka/tanda hubung, diawali huruf atau angka.';
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'][] = 'Nama bahan wajib diisi.';
        } elseif (mb_strlen($name) > 150) {
            $errors['name'][] = 'Nama maksimal 150 karakter.';
        }

        $type = (string) ($data['type'] ?? '');
        if (!in_array($type, RawMaterial::TYPES, true)) {
            $errors['type'][] = 'Pilih jenis bahan yang valid.';
        }

        $unit = trim((string) ($data['unit'] ?? ''));
        if ($unit === '') {
            $errors['unit'][] = 'Satuan wajib diisi (mis. meter, kg, pcs, lembar).';
        } elseif (mb_strlen($unit) > 20) {
            $errors['unit'][] = 'Satuan maksimal 20 karakter.';
        }

        $minStock = trim((string) ($data['min_stock'] ?? '0'));
        if ($minStock !== '' && preg_match('/^\d+(\.\d{1,3})?$/', $minStock) !== 1) {
            $errors['min_stock'][] = 'Stok minimum harus angka non-negatif (maks 3 desimal).';
        }

        if (mb_strlen(trim((string) ($data['notes'] ?? ''))) > 2000) {
            $errors['notes'][] = 'Catatan maksimal 2000 karakter.';
        }

        return $errors;
    }
}
