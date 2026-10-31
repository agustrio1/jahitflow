<?php

declare(strict_types=1);

use App\Core\Seeder;

/**
 * Pesanan demo FIKTIF (§7 DOC). Bergantung pada Demo02CustomerSeeder &
 * Demo03ProductCatalogSeeder (keduanya bernomor lebih kecil → berjalan dulu).
 * Total dihitung dari harga varian di DB — sama seperti service runtime.
 *
 * SKENARIO RANTAI (Batch H — data demo realistis & satu-kali-jalan). Lima
 * pesanan ini membuat Dashboard (§8.A) & Laporan (§8.J) menampilkan semua
 * keadaan "perlu ditindaklanjuti" pada lintasan db:seed PERTAMA:
 *
 *   ...-0001  confirmed  tenggat LEWAT (-4)   -> PO berjalan, TANPA QC (menunggu QC), DP saja
 *   ...-0002  confirmed  mendekati tenggat (+5)-> PO berjalan, QC lolos+perbaikan, DP+cicilan, pengiriman "disiapkan"
 *   ...-0003  confirmed  selesai penuh (lewat)  -> PO completed, QC lolos, LUNAS (dp+cicilan+pelunasan), terkirim "diterima"
 *   ...-0004  offered    tenggat +20            -> (tanpa turunan; pipeline)
 *   ...-0005  draft      tenggat +30            -> (tanpa turunan; pesanan baru)
 *
 * Seeder downstream (Demo06..09) mencari pesanan lewat document_number di atas
 * sehingga tidak bergantung pada urutan baris atau "pesanan pertama".
 *
 * Idempoten: bila kelima nomor demo sudah ada, seluruh set dilewati. Counter
 * sequensi "SO:YyMM" dinaikkan ke 0006 agar pesanan runtime tidak menabrak.
 * created_by diisi admin (pengguna sudah ditanam lebih dulu oleh Demo01).
 */
class Demo05SalesOrderSeeder extends Seeder
{
    public function run(): void
    {
        $label = date('Y-m');
        $key = 'SO:' . date('Ym');
        $numbers = [
            "SO-{$label}-0001",
            "SO-{$label}-0002",
            "SO-{$label}-0003",
            "SO-{$label}-0004",
            "SO-{$label}-0005",
        ];

        // Lewati bila set demo sudah pernah di-seed (cukup cek satu nomor).
        $existing = $this->scalar('SELECT COUNT(*) FROM sales_orders WHERE document_number = ? LIMIT 1', [$numbers[0]]);
        if ($existing > 0) {
            $this->bumpSequence($key, 6);
            return;
        }

        $adminId = $this->scalar('SELECT id FROM users WHERE role = ? ORDER BY id LIMIT 1', ['admin']);

        $blueprints = [
            [
                'number' => $numbers[0],
                'customer' => 'SMK Negeri 1 Sukamaju',
                'status' => 'confirmed',
                'order_offset' => -22,
                'due_offset' => -4,
                'discount' => 250000,
                'additional' => 0,
                'dp' => 2302500,
                'notes' => 'Batik OSIS kelas 7-9. Nama siswa bordir per piece. Terlambat — prioritas produksi.',
                'items' => [
                    ['sku' => 'BTS-M-NV', 'qty' => 40],
                    ['sku' => 'BTS-L-NV', 'qty' => 25],
                ],
            ],
            [
                'number' => $numbers[1],
                'customer' => 'CV Maju Bersama Konstruksi',
                'status' => 'confirmed',
                'order_offset' => -15,
                'due_offset' => 5,
                'discount' => 0,
                'additional' => 150000,
                'dp' => 1725000,
                'notes' => 'Field shirt proyek, reflective tape + logo bordir lengan. Mendekati tenggat.',
                'items' => [
                    ['sku' => 'FS-L-BDR', 'qty' => 20],
                    ['sku' => 'FS-XL-BDR', 'qty' => 10],
                ],
            ],
            [
                'number' => $numbers[2],
                'customer' => 'Komunitas Lari Sagara Runners',
                'status' => 'confirmed',
                'order_offset' => -45,
                'due_offset' => -20,
                'discount' => 300000,
                'additional' => 0,
                'dp' => 1710000,
                'notes' => 'Kaus event tahunan, dua warna. Sudah selesai, lunas, dan terkirim penuh.',
                'items' => [
                    ['sku' => 'KC30-M-PTH', 'qty' => 50],
                    ['sku' => 'KC30-M-HTM', 'qty' => 30],
                ],
            ],
            [
                'number' => $numbers[3],
                'customer' => 'PAUD Tunas Bangsa',
                'status' => 'offered',
                'order_offset' => -3,
                'due_offset' => 20,
                'discount' => 0,
                'additional' => 0,
                'dp' => 0,
                'notes' => 'Penawaran batik anak; menunggu konfirmasi pelanggan.',
                'items' => [
                    ['sku' => 'BTS-S-NV', 'qty' => 30],
                ],
            ],
            [
                'number' => $numbers[4],
                'customer' => 'Siti Nurhaliza',
                'status' => 'draft',
                'order_offset' => -1,
                'due_offset' => 30,
                'discount' => 0,
                'additional' => 0,
                'dp' => 0,
                'notes' => 'Draft kaus couple; ukuran & warna belum final.',
                'items' => [
                    ['sku' => 'KC30-L-NVY', 'qty' => 20],
                ],
            ],
        ];

        foreach ($blueprints as $bp) {
            $customerId = $this->scalar('SELECT id FROM customers WHERE name = ? LIMIT 1', [$bp['customer']]);
            if ($customerId === 0) {
                continue; // pelanggan belum ada → lewati agar FK tetap utuh
            }

            [$subtotal, $itemRows] = $this->resolveItems($bp['items']);
            if ($itemRows === []) {
                continue;
            }

            $discount = (int) $bp['discount'];
            $additional = (int) $bp['additional'];
            $grand = $subtotal - $discount + $additional;
            $dp = min((int) $bp['dp'], $grand);

            $orderId = $this->insert('sales_orders', [
                'document_number' => $bp['number'],
                'customer_id' => $customerId,
                'order_date' => date('Y-m-d', strtotime($bp['order_offset'] . ' days')),
                'due_date' => date('Y-m-d', strtotime($bp['due_offset'] . ' days')),
                'status' => $bp['status'],
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'discount_amount' => number_format($discount, 2, '.', ''),
                'additional_cost' => number_format($additional, 2, '.', ''),
                'grand_total' => number_format($grand, 2, '.', ''),
                'dp_amount' => number_format($dp, 2, '.', ''),
                'design_notes' => $bp['notes'],
                'created_by' => $adminId ?: null,
            ]);

            foreach ($itemRows as $row) {
                $this->insert('sales_order_items', [
                    'sales_order_id' => $orderId,
                    'product_variant_id' => $row['variant_id'],
                    'quantity' => $row['quantity'],
                    'unit_price' => number_format($row['unit_price'], 2, '.', ''),
                    'line_total' => number_format($row['line_total'], 2, '.', ''),
                ]);
            }
        }

        $this->bumpSequence($key, 6);
    }

    /**
     * @param list<array{sku:string, qty:int}> $items
     * @return array{0: int, 1: list<array<string, mixed>>}
     */
    private function resolveItems(array $items): array
    {
        $subtotal = 0;
        $rows = [];

        foreach ($items as $item) {
            $variantId = $this->scalar('SELECT id FROM product_variants WHERE sku = ? LIMIT 1', [$item['sku']]);
            if ($variantId === 0) {
                continue;
            }
            $unitPrice = (int) $this->scalar('SELECT unit_price FROM product_variants WHERE id = ? LIMIT 1', [$variantId]);
            $line = $unitPrice * $item['qty'];
            $subtotal += $line;

            $rows[] = [
                'variant_id' => $variantId,
                'quantity' => $item['qty'],
                'unit_price' => $unitPrice,
                'line_total' => $line,
            ];
        }

        return [$subtotal, $rows];
    }

    private function bumpSequence(string $key, int $floor): void
    {
        // MySQL 8.4 menghapus VALUES() di ON DUPLICATE KEY UPDATE, jadi
        // pakai read + branch eksplisit agar portabel & aman.
        $current = $this->pdo->prepare('SELECT next_value FROM sequences WHERE seq_key = ? LIMIT 1');
        $current->execute([$key]);

        if ($current->fetchColumn() === false) {
            $this->pdo->prepare('INSERT INTO sequences (seq_key, next_value) VALUES (?, ?)')
                ->execute([$key, $floor]);
            return;
        }

        $this->pdo->prepare('UPDATE sequences SET next_value = ? WHERE seq_key = ? AND next_value < ?')
            ->execute([$floor, $key, $floor]);
    }

    /** @param list<mixed> $bind */
    private function scalar(string $sql, array $bind): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);

        return (int) $stmt->fetchColumn();
    }
}
