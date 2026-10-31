<?php

declare(strict_types=1);

namespace App\Validators;

/**
 * Validasi input login. Aturan sengaja minimal & jelas; pemrosesan
 * kredensial (throttle, hash verify) ditangani Auth service.
 */
final class LoginValidator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, list<string>> peta field => pesan
     */
    public function validate(array $data): array
    {
        $errors = [];

        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '') {
            $errors['email'][] = 'Email wajib diisi.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'][] = 'Format email tidak valid.';
        }

        if (($data['password'] ?? '') === '') {
            $errors['password'][] = 'Password wajib diisi.';
        }

        return $errors;
    }
}
