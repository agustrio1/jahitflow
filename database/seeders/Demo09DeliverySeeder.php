<?php

declare(strict_types=1);

use App\Core\Seeder;

/**
 * Pengiriman demo FIKTIF (§8.I). Menempel pada pesanan yang hasil QC-nya lolos
 * (dari Demo06 & Demo07). Kuantitas setiap batch dibatasi aturan siap-kirim
 * aplikasi — min(total dipesan, total lolos QC) − sudah dikirim — supaya data demo
 * jujur & tidak pernah melebihi jatah.
 *
 * Variasi status untuk Dashboard & Laporan (§8.A/§8.J):
 *   ...-0002  disiapkan 1 batch  -> "pengiriman belum selesai" (terbuka), order masih dekat tenggat
 *   ...-0003  dikirim + diterima -> siklus penuh: sebagian dalam jalan, sebagian diterima pelanggan
 *
 * Nomor dokumen KRM-YYYY-MM-NNNN (bukan MAX(id)+1) + counter dinaikkan.
 * Idempoten: sudah ada pengiriman untuk pesanan itu → dilewati.
 */
class Demo09DeliverySeeder extends Seeder
{
    /** suffix pesanan -> batch [kuantitas rencana, status, metode, kurir, offset-hari]. */
    private const SCENARIO = [
        '0002' => [
            [20, 'disiapkan', 'ekspedisi', 'JNE Demo', '+1 day'],
        ],
        '0003' => [
            [40, 'dikirim', 'ekspedisi', 'TIKI Demo', '-2 day'],
            [40, 'diterima', 'ambil_sendiri', null, '-8 day'],
        ],
    ];

    public function run(): void
    {
        $label = date('Y-m');
        $key = 'KRM:' . date('Ym');
        $adminId = $this->scalar('SELECT id FROM users WHERE role = ? ORDER BY id LIMIT 1', ['admin']);

        $issued = 0;
        foreach (self::SCENARIO as $suffix => $batches) {
            $soId = $this->scalar('SELECT id FROM sales_orders WHERE document_number = ? LIMIT 1', ["SO-{$label}-{$suffix}"]);
            if ($soId === 0) {
                continue;
            }

            if ($this->scalar('SELECT COUNT(*) FROM deliveries WHERE sales_order_id = ?', [$soId]) > 0) {
                continue; // sudah ada pengiriman → lewati (idempoten)
            }

            $ordered = $this->scalar('SELECT COALESCE(SUM(quantity), 0) FROM sales_order_items WHERE sales_order_id = ?', [$soId]);
            $qcReady = $this->scalar(
                'SELECT COALESCE(SUM(q.passed_qty), 0)
                 FROM qc_inspections q
                 JOIN production_orders po ON po.id = q.production_order_id
                 WHERE po.sales_order_id = ?',
                [$soId],
            );
            $available = max(0, min($ordered, $qcReady));
            if ($available <= 0) {
                continue; // belum ada barang lolos QC → tidak ada yang bisa dikirim
            }

            $allocated = 0;
            foreach ($batches as [$planned, $status, $method, $courier, $when]) {
                $quantity = min((int) $planned, $available - $allocated);
                if ($quantity <= 0) {
                    continue;
                }
                $allocated += $quantity;

                $issued++;
                $this->insert('deliveries', [
                    'document_number' => sprintf('KRM-%s-%04d', $label, $issued),
                    'sales_order_id' => $soId,
                    'quantity' => $quantity,
                    'delivery_date' => date('Y-m-d', strtotime($when)),
                    'method' => $method,
                    'courier' => $courier,
                    'tracking_number' => $courier !== null ? 'DEMO-' . strtoupper(substr(md5($key . $issued), 0, 8)) : null,
                    'recipient' => 'Bagian Penerima Pelanggan',
                    'handover_ref' => 'DO-DEMO-' . sprintf('%04d', $issued),
                    'note' => 'Batch pengiriman demo.',
                    'status' => $status,
                    'created_by' => $adminId ?: null,
                ]);
            }
        }

        if ($issued > 0) {
            $this->bumpSequence($key, $issued + 1);
        }
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

    /** @param list<mixed> $bind */
    private function scalar(string $sql, array $bind = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);

        return (int) $stmt->fetchColumn();
    }
}
