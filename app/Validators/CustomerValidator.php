<?php

declare(strict_types=1);

namespace App\Validators;

use App\Models\Customer;

/**
 * Validasi input pelanggan (§14 DOC: validasi terpisah & teruji).
 * Aturan domain: tipe perusahaan wajib punya satu kontak PIC.
 */
final class CustomerValidator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, list<string>>
     */
    public function validate(array $data): array
    {
        // Telepon: format longgar namun jelas — 6-20 char digit/+/-/spasi.
        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'][] = 'Nama pelanggan wajib diisi.';
        } elseif (mb_strlen($name) > 150) {
            $errors['name'][] = 'Nama maksimal 150 karakter.';
        }

        $type = (string) ($data['type'] ?? '');
        if (!in_array($type, [Customer::TYPE_PERORANGAN, Customer::TYPE_PERUSAHAAN], true)) {
            $errors['type'][] = 'Pilih tipe pelanggan: perorangan atau perusahaan.';
        }

        $phone = trim((string) ($data['phone'] ?? ''));
        if ($phone === '') {
            $errors['phone'][] = 'Telepon wajib diisi.';
        } elseif (preg_match('/^[0-9+\-\s]{6,20}$/', $phone) !== 1) {
            $errors['phone'][] = 'Telepon hanya boleh berisi angka, +, -, spasi (6-20 karakter).';
        }

        if ($type === Customer::TYPE_PERUSAHAAN && trim((string) ($data['contact_person'] ?? '')) === '') {
            $errors['contact_person'][] = 'Pelanggan perusahaan wajib punya satu nama kontak PIC.';
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'][] = 'Format email tidak valid.';
        }

        if (mb_strlen(trim((string) ($data['notes'] ?? ''))) > 2000) {
            $errors['notes'][] = 'Catatan maksimal 2000 karakter.';
        }

        return $errors;
    }
}
