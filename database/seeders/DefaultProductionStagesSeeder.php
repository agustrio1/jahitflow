<?php

declare(strict_types=1);

use App\Core\Seeder;

/**
 * Tahap produksi DEFAULT (§8.E). Dibuat data-driven agar tim produksi dapat
 * menyesuaikan urutan/nama tanpa menyentuh kode. Nama "Default..." membuatnya
 * jalan paling awal (alfabet) sehingga DemoProductionOrderSeeder sudah dapat
 * membaca definisi ini saat snapshot.
 *
 * Idempoten lewat insertIfMissing berpatokan `position` (kolom UNIQUE) —
 * menjalankan ulang tidak akan duplikat atau menimpa hasil edit pengguna.
 */
class DefaultProductionStagesSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            ['position' => 1, 'name' => 'Persiapan Bahan'],
            ['position' => 2, 'name' => 'Cutting (Pemotongan)'],
            ['position' => 3, 'name' => 'Sewing (Penjahitan)'],
            ['position' => 4, 'name' => 'Sablon / Bordir'],
            ['position' => 5, 'name' => 'Finishing'],
            ['position' => 6, 'name' => 'Quality Control'],
            ['position' => 7, 'name' => 'Pengemasan'],
        ];

        foreach ($stages as $stage) {
            $this->insertIfMissing('production_stages', ['position' => $stage['position']], [
                'name' => $stage['name'],
                'position' => $stage['position'],
                'is_active' => 1,
            ]);
        }
    }
}
