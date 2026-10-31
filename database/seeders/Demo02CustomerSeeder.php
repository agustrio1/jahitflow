<?php

declare(strict_types=1);

use App\Core\Seeder;

/**
 * Data pelanggan FIKTIF untuk demo portofolio (§7 DOC: seed fiktif).
 * Idempoten — aman dijalankan berulang lewat insertIfMissing.
 *
 * Urutan (Batch H): Demo02 — sesudah pengguna, sebelum katalog & pesanan.
 */
class Demo02CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'name' => 'SMK Negeri 1 Sukamaju',
                'type' => 'perusahaan',
                'contact_person' => 'Ibu Ratna Wati',
                'phone' => '022-8765432',
                'email' => 'tu.smkn1sukamaju@sekolahdummy.test',
                'address' => 'Jl. Pendidikan No. 12, Sukamaju',
                'city' => 'Bandung',
                'notes' => 'Langganan seragam batik sekolah; butuh 3 minggu sebelum tahun ajaran.',
            ],
            [
                'name' => 'CV Maju Bersama Konstruksi',
                'type' => 'perusahaan',
                'contact_person' => 'Pak Hendra Gunawan',
                'phone' => '081234567890',
                'email' => 'purchasing@majubersama-dummy.test',
                'address' => 'Ruko Griya Permata Blok B7, Cikarang',
                'city' => 'Bekasi',
                'notes' => 'Seragam proyek wajib reflective tape di lengan.',
            ],
            [
                'name' => 'Komunitas Lari Sagara Runners',
                'type' => 'perusahaan',
                'contact_person' => 'Dimas Prakoso',
                'phone' => '081122334455',
                'email' => null,
                'address' => 'Kompleks Olahraga Sagara No. 3',
                'city' => 'Bandung',
                'notes' => 'Kaus event tahunan; desain berubah tiap season.',
            ],
            [
                'name' => 'Siti Nurhaliza',
                'type' => 'perorangan',
                'contact_person' => null,
                'phone' => '085677788899',
                'email' => 'siti.nurhaliza@dummy.test',
                'address' => 'Perumahan Asri Hill No. 21, Dago',
                'city' => 'Bandung',
                'notes' => 'Pesanan kebaya custom couple keluarga.',
            ],
            [
                'name' => 'PAUD Tunas Bangsa',
                'type' => 'perusahaan',
                'contact_person' => 'Kak Linda',
                'phone' => '022-45451212',
                'email' => null,
                'address' => 'Jl. Cendekia No. 8',
                'city' => 'Cimahi',
                'notes' => 'Butuh bordir nama anak per piece.',
            ],
        ];

        foreach ($customers as $customer) {
            $this->insertIfMissing('customers', ['name' => $customer['name'], 'phone' => $customer['phone']], $customer);
        }
    }
}
