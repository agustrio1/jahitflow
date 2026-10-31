<?php

declare(strict_types=1);

use App\Core\Seeder;

/**
 * Perintah produksi demo FIKTIF (§8.E). Bergantung pada DefaultProductionStagesSeeder
 * (tahap) & Demo05SalesOrderSeeder (pesanan terkonfirmasi) yang semuanya berjalan
 * lebih dulu (angka urutan lebih kecil).
 *
 * Membuat SATU perintah produksi untuk setiap pesanan confirmed dalam skenario,
 * dengan progres tahap yang BERBEDA supaya papan tahap & dashboard kaya keadaan:
 *   ...-0001  running    (tahap 1 selesai, tahap 2 berjalan)   -> nanti TANPA QC (menunggu QC)
 *   ...-0002  running    (tahap 1-2 selesai, tahap 3 berjalan)
 *   ...-0003  completed  (semua tahap selesai)
 *
 * Status perintah konsisten dengan gabungan tahap (lihat StageMachine). Snapshot
 * tahap aktif disalin persis seperti service runtime. Idempoten: satu perintah per
 * pesanan (UNIQUE sales_order_id) — dilewati bila sudah ada. created_by admin (Demo01).
 */
class Demo06ProductionOrderSeeder extends Seeder
{
    /** Nomor pesanan confirmed yang dibuat PO-nya + pola progres tahapnya. */
    private const SCENARIO = [
        ['suffix' => '0001', 'number' => '0001', 'status' => 'running', 'done_stages' => 1, 'running' => true],
        ['suffix' => '0002', 'number' => '0002', 'status' => 'running', 'done_stages' => 2, 'running' => true],
        // completed: seluruh tahap selesai — done_stages dihitung dari jumlah tahap nyata.
        ['suffix' => '0003', 'number' => '0003', 'status' => 'completed', 'done_stages' => 0, 'running' => false],
    ];

    public function run(): void
    {
        $label = date('Y-m');
        $key = 'PP:' . date('Ym');

        $adminId = $this->scalar('SELECT id FROM users WHERE role = ? ORDER BY id LIMIT 1', ['admin']);
        $stages = $this->rows('SELECT id, name, position FROM production_stages WHERE is_active = 1 ORDER BY position ASC');
        if ($stages === []) {
            return; // belum ada definisi tahap → jangan buat perintah kosong
        }

        $created = 0;
        foreach (self::SCENARIO as $tpl) {
            $soNumber = "SO-{$label}-{$tpl['suffix']}";
            $soId = $this->scalar('SELECT id FROM sales_orders WHERE document_number = ? LIMIT 1', [$soNumber]);
            if ($soId === 0) {
                continue; // pesanan sumber belum ada → lewati
            }

            // Satu perintah per pesanan (UNIQUE sales_order_id) — lewati bila ada.
            if ($this->scalar('SELECT id FROM production_orders WHERE sales_order_id = ? LIMIT 1', [$soId]) !== 0) {
                $created++;
                continue;
            }

            $targetQty = $this->scalar('SELECT COALESCE(SUM(quantity), 0) FROM sales_order_items WHERE sales_order_id = ?', [$soId]);
            if ($targetQty < 1) {
                continue;
            }
            $dueDate = $this->scalarStr('SELECT due_date FROM sales_orders WHERE id = ? LIMIT 1', [$soId]);

            $orderId = $this->insert('production_orders', [
                'document_number' => "PP-{$label}-{$tpl['number']}",
                'sales_order_id' => $soId,
                'status' => $tpl['status'],
                'target_quantity' => $targetQty,
                'target_date' => $dueDate !== '' ? $dueDate : date('Y-m-d'),
                'assigned_to' => null,
                'description' => 'Produksi demo: progres tahap ilustratif.',
                'created_by' => $adminId ?: null,
            ]);

            $doneStages = $tpl['status'] === 'completed' ? count($stages) : (int) $tpl['done_stages'];
            $this->seedStages($orderId, $stages, $targetQty, $doneStages, (bool) $tpl['running']);
            $created++;
        }

        if ($created > 0) {
            $this->bumpSequence($key, $created + 1);
        }
    }

    /**
     * Salin snapshot tahap dengan status berjenjang sesuai pola progres.
     *
     * @param list<array<string, mixed>> $stages
     */
    private function seedStages(int $orderId, array $stages, int $targetQty, int $doneStages, bool $running): void
    {
        foreach ($stages as $i => $stage) {
            $position = $i + 1;

            if ($position <= $doneStages) {
                $status = 'done';
                $realized = $targetQty;
                $started = date('Y-m-d H:i:s', strtotime('-' . (2 * $position) . ' days'));
                $finished = date('Y-m-d H:i:s', strtotime('-' . max(0, 2 * ($doneStages - $position)) . ' days'));
            } elseif ($running && $position === $doneStages + 1) {
                $status = 'in_progress';
                $realized = intdiv($targetQty, 2);
                $started = date('Y-m-d H:i:s', strtotime('-1 day'));
                $finished = null;
            } else {
                $status = 'pending';
                $realized = 0;
                $started = null;
                $finished = null;
            }

            $this->insert('production_order_stages', [
                'production_order_id' => $orderId,
                'stage_id' => (int) $stage['id'],
                'stage_name' => (string) $stage['name'],
                'position' => (int) $stage['position'],
                'status' => $status,
                'target_qty' => $targetQty,
                'realized_qty' => $realized,
                'started_at' => $started,
                'finished_at' => $finished,
            ]);
        }
    }

    /** @param list<mixed> $bind */
    private function scalar(string $sql, array $bind = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Ambil satu nilai sebagai STRING tanpa cast ke int — wajib untuk kolom
     * date/string (mis. due_date) karena (int)"2026-10-24" = 2026.
     *
     * @param list<mixed> $bind
     */
    private function scalarStr(string $sql, array $bind = []): string
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);
        $value = $stmt->fetchColumn();

        return $value === false ? '' : (string) $value;
    }

    /**
     * @param list<mixed> $bind
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $bind = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function bumpSequence(string $key, int $floor): void
    {
        // MySQL 8.4 menghapus VALUES() di ON DUPLICATE KEY UPDATE → read + branch.
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
}
