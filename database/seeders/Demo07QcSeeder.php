<?php

declare(strict_types=1);

use App\Core\Seeder;

/**
 * Pemeriksaan QC demo FIKTIF (§8.G). Menempel pada perintah produksi demo yang
 * dibuat Demo06 (berjalan lebih dulu karena angka urutan lebih kecil). Jumlah
 * diperiksa diturunkan dari target produksi supaya masuk akal.
 *
 * PENTING untuk dashboard (§8.A): PO pesanan ...-0001 SENGAJA tidak diberi QC,
 * sehingga ia tetap berstatus "menunggu quality control" (running/completed tanpa
 * pemeriksaan). Yang diberi QC:
 *   ...-0002  split  -> batch lolos penuh + batch sebagian cacat (perbaikan)
 *   ...-0003  clean  -> satu batch lolos penuh (order selesai & siap kirim penuh)
 *
 * Idempoten: bila perintah produksi itu sudah punya pemeriksaan → dilewati.
 * inspected_by diisi admin bila ada (kolom nullable).
 */
class Demo07QcSeeder extends Seeder
{
    /** suffix pesanan -> mode pemeriksaan. (0001 sengaja tidak ada QC.) */
    private const SCENARIO = [
        '0002' => 'split',
        '0003' => 'clean',
    ];

    public function run(): void
    {
        $label = date('Y-m');
        $adminId = $this->scalar('SELECT id FROM users WHERE role = ? ORDER BY id LIMIT 1', ['admin']);

        foreach (self::SCENARIO as $suffix => $mode) {
            $poId = $this->scalar(
                'SELECT po.id
                 FROM production_orders po
                 JOIN sales_orders so ON so.id = po.sales_order_id
                 WHERE so.document_number = ?
                 ORDER BY po.id LIMIT 1',
                ["SO-{$label}-{$suffix}"],
            );
            if ($poId === 0) {
                continue; // belum ada perintah produksi untuk pesanan ini
            }

            if ($this->scalar('SELECT COUNT(*) FROM qc_inspections WHERE production_order_id = ?', [$poId]) > 0) {
                continue; // sudah ada riwayat → lewati (idempoten)
            }

            $target = $this->scalar('SELECT target_quantity FROM production_orders WHERE id = ? LIMIT 1', [$poId]);
            if ($target < 1) {
                continue;
            }

            if ($mode === 'clean' || $target < 3) {
                $this->record($poId, $target, $target, 0, 'lolos', null, null, $adminId ?: null, '-3 day');
                continue;
            }

            // split: separuh lolos penuh, separuh berikutnya sebagian cacat (perbaikan).
            $firstInt = intdiv($target, 2);
            $second = $target - $firstInt;
            $this->record($poId, $firstInt, $firstInt, 0, 'lolos', null, null, $adminId ?: null, '-2 day');

            $failed = max(1, intdiv($second, 5));
            $passed = $second - $failed;
            $this->record(
                $poId,
                $second,
                $passed,
                $failed,
                'perbaikan',
                'Dinding jahitan lepas dan benang keluar pada sebagian produk.',
                'Perjahit ulang bagian yang lepas, lalu masuk pemeriksaan susulan.',
                $adminId ?: null,
                '-1 day',
            );
        }
    }

    private function record(
        int $productionOrderId,
        int $inspected,
        int $passed,
        int $failed,
        string $result,
        ?string $defect,
        ?string $rework,
        ?int $inspectorId,
        string $when,
    ): void {
        $this->insert('qc_inspections', [
            'production_order_id' => $productionOrderId,
            'inspected_qty' => $inspected,
            'passed_qty' => $passed,
            'failed_qty' => $failed,
            'result' => $result,
            'defect_note' => $defect,
            'rework_action' => $rework,
            'inspected_by' => $inspectorId,
            'inspected_at' => date('Y-m-d H:i:s', strtotime($when)),
        ]);
    }

    /** @param list<mixed> $bind */
    private function scalar(string $sql, array $bind = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);

        return (int) $stmt->fetchColumn();
    }
}
