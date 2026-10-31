<?php

declare(strict_types=1);

use App\Core\Seeder;

/**
 * Akun demo FIKTIF untuk pengembangan & portofolio.
 *
 * Idempoten: insertIfMissing berdasar email, aman dijalankan berulang.
 * Password demo sama untuk semua akun dan HANYA untuk lingkungan dummy.
 *
 * Urutan seed (Batch H): DemoNN dengan PREFIKS ANGKA agar SeederRunner
 * (glob alfabetis satu lintasan) menjalankan rantai dependensi dengan benar.
 * Seeder ini (Demo01) paling awal sehingga created_by/assigned_to downstream
 * bisa merujuk id pengguna nyata.
 *
 * Daftar akun (untuk README Fase 5):
 *   admin@jahitflow.test      Admin
 *   manager@jahitflow.test    Manajer
 *   operator@jahitflow.test   Operator Produksi
 *   keuangan@jahitflow.test   Keuangan
 * password: Password123!
 */
class Demo01UserSeeder extends Seeder
{
    private const PASSWORD = 'Password123!';

    public function run(): void
    {
        $hash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);

        $users = [
            ['Admin Konveksi', 'admin@jahitflow.test', 'admin'],
            ['Manajer Produksi', 'manager@jahitflow.test', 'manager'],
            ['Operator Jahit', 'operator@jahitflow.test', 'operator'],
            ['Staf Keuangan', 'keuangan@jahitflow.test', 'keuangan'],
        ];

        foreach ($users as [$name, $email, $role]) {
            $this->insertIfMissing(
                'users',
                ['email' => $email],
                ['name' => $name, 'email' => $email, 'password' => $hash, 'role' => $role, 'is_active' => 1],
            );
        }
    }
}
