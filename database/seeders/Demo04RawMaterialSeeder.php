<?php

declare(strict_types=1);

use App\Core\Seeder;

/**
 * Bahan baku demo FIKTIF (§8.F). Master bahan terpisah dari katalog produk,
 * diisi lewat ledger: setiap bahan menerima riwayat pergerakan (penerimaan,
 * pemakaian, satu contoh penyesuaian) sehingga current_stock = hasil rangkaian
 * saldo — konsisten dengan cara StockService menulis di aplikasi.
 *
 * Sengaja menyisakan beberapa bahan berstatus "stok menipis" (saldo <= minimum)
 * agar filter peringatan & halaman detail punya contoh nyata saat pertama dibuka.
 *
 * Urutan (Batch H): Demo04 — mandiri (tidak bergantung pesanan); created_by
 * diisi admin karena pengguna sudah ditanam Demo01.
 *
 * Idempoten: bahan dengan kode yang sama dilewati (UNIQUE code).
 */
class Demo04RawMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = $this->scalar('SELECT id FROM users WHERE role = ? ORDER BY id LIMIT 1', ['admin']);

        foreach ($this->catalog() as $item) {
            if ($this->scalar('SELECT id FROM raw_materials WHERE code = ? LIMIT 1', [$item['code']]) !== 0) {
                continue; // sudah ada → lewati (idempoten)
            }

            $materialId = $this->seedMaterial($item, $adminId ?: null);
            $this->seedMovements($materialId, $item['movements'], $adminId ?: null);
        }
    }

    /**
     * Tanam master dengan saldo akhir = hasil rangkaian pergerakan.
     *
     * @param array<string, mixed>  $item
     * @param int|null              $adminId
     */
    private function seedMaterial(array $item, ?int $adminId): int
    {
        $balance = $this->closingBalance($item['movements']);

        return $this->insert('raw_materials', [
            'code' => $item['code'],
            'name' => $item['name'],
            'type' => $item['type'],
            'unit' => $item['unit'],
            'current_stock' => $balance,
            'min_stock' => $item['min_stock'],
            'is_active' => 1,
            'notes' => $item['notes'] ?? null,
        ]);
    }

    /**
     * Tulis baris ledger dengan saldo sebelum/sesudah yang berantai.
     *
     * @param list<array<string, mixed>> $movements
     */
    private function seedMovements(int $materialId, array $movements, ?int $adminId): void
    {
        $running = '0.000';
        foreach ($movements as $mv) {
            $direction = (string) $mv['direction'];
            $quantity = (string) $mv['quantity'];
            $delta = $this->deltaFor($direction, $quantity);

            $before = $running;
            $after = $this->dec(bcadd($before, $delta, 3));
            $running = $after;

            $this->insert('stock_movements', [
                'raw_material_id' => $materialId,
                'direction' => $direction,
                'quantity' => $quantity,
                'balance_before' => $before,
                'balance_after' => $after,
                'reason' => (string) $mv['reason'],
                'reference_type' => $mv['reference_type'] ?? null,
                'reference_id' => $mv['reference_id'] ?? null,
                'created_by' => $adminId,
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function catalog(): array
    {
        return [
            [
                'code' => 'DRL-NVY-01', 'name' => 'Kain Drill Navy', 'type' => 'bahan_utama',
                'unit' => 'meter', 'min_stock' => '50.000', 'notes' => 'Drill katun 240gr, warna navy konsisten.',
                'movements' => [
                    ['direction' => 'in', 'quantity' => '200.000', 'reason' => 'Penerimaan pembelian awal', 'reference_type' => 'purchase', 'reference_id' => 1],
                    ['direction' => 'out', 'quantity' => '60.000', 'reason' => 'Pemakaian produksi seragam', 'reference_type' => 'production_order', 'reference_id' => 1],
                ],
            ],
            [
                // Sengaja dibuat menipis: saldo (30) <= minimum (40).
                'code' => 'DRL-KHK-01', 'name' => 'Kain Drill Khaki', 'type' => 'bahan_utama',
                'unit' => 'meter', 'min_stock' => '40.000', 'notes' => null,
                'movements' => [
                    ['direction' => 'in', 'quantity' => '80.000', 'reason' => 'Penerimaan pembelian awal', 'reference_type' => 'purchase', 'reference_id' => 1],
                    ['direction' => 'out', 'quantity' => '50.000', 'reason' => 'Pemakaian produksi pesanan batch kecil', 'reference_type' => 'production_order', 'reference_id' => 1],
                ],
            ],
            [
                'code' => 'THR-PP-01', 'name' => 'Benang Polyster', 'type' => 'bahan_penolong',
                'unit' => 'roll', 'min_stock' => '20.000', 'notes' => 'Benang jahit serbaguna.',
                'movements' => [
                    ['direction' => 'in', 'quantity' => '100.000', 'reason' => 'Penerimaan pembelian awal', 'reference_type' => 'purchase', 'reference_id' => 1],
                    ['direction' => 'out', 'quantity' => '30.000', 'reason' => 'Pemakaian jahit harian'],
                    ['direction' => 'adjustment', 'quantity' => '-2.000', 'reason' => 'Stock opname: susut/rusak', 'reference_type' => 'manual'],
                ],
            ],
            [
                // Sengaja dibuat menipis: saldo (20) <= minimum (25).
                'code' => 'ZIP-STD-01', 'name' => 'Resleting Standar 20cm', 'type' => 'bahan_penolong',
                'unit' => 'pcs', 'min_stock' => '25.000', 'notes' => null,
                'movements' => [
                    ['direction' => 'in', 'quantity' => '20.000', 'reason' => 'Penerimaan pembelian awal', 'reference_type' => 'purchase', 'reference_id' => 1],
                ],
            ],
            [
                'code' => 'PKG-PLA-01', 'name' => 'Plastik Kemasan Zip', 'type' => 'packaging',
                'unit' => 'lembar', 'min_stock' => '100.000', 'notes' => 'Kemasan jadi per pcs produk.',
                'movements' => [
                    ['direction' => 'in', 'quantity' => '500.000', 'reason' => 'Penerimaan pembelian awal', 'reference_type' => 'purchase', 'reference_id' => 1],
                    ['direction' => 'out', 'quantity' => '120.000', 'reason' => 'Pengemasan pesanan jalan', 'reference_type' => 'production_order', 'reference_id' => 1],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------------

    /** @param list<array<string, mixed>> $movements */
    private function closingBalance(array $movements): string
    {
        $running = '0.000';
        foreach ($movements as $mv) {
            $running = $this->dec(bcadd($running, $this->deltaFor((string) $mv['direction'], (string) $mv['quantity']), 3));
        }

        return $running;
    }

    /** Delta bertanda dari arah + quantity (quantity in/out selalu magnitude positif). */
    private function deltaFor(string $direction, string $quantity): string
    {
        return match ($direction) {
            'in' => bcadd($quantity, '0', 3),
            'out' => bcsub('0', $quantity, 3),
            default => bcadd($quantity, '0', 3), // adjustment sudah tersimpan bertanda
        };
    }

    private function dec(string $value): string
    {
        return bcadd($value, '0', 3);
    }

    /** @param list<mixed> $bind */
    private function scalar(string $sql, array $bind = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);

        return (int) $stmt->fetchColumn();
    }
}
