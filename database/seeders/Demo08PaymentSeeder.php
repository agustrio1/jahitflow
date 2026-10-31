<?php

declare(strict_types=1);

use App\Core\Seeder;

/**
 * Pembayaran demo FIKTIF (§8.H). Menempel pada pesanan confirmed skenario buatan
 * Demo05. Nominal diturunkan dari grand_total TER SIMPAN (bukan asumsi) memakai
 * bcmath 2 desimal; nomor dokumen BYR-YYYY-MM-NNNN (bukan MAX(id)+1) + counter
 * dinaikkan agar aplikasi tidak menabrak nomor demo.
 *
 * Variasi keadaan untuk Laporan & Dashboard (§8.J/§8.A) — jujur, tanpa melebih total:
 *   ...-0001  uang_muka 30% saja                       -> Dibayar Sebagian, ada piutang (order telat)
 *   ...-0002  uang_muka 30% + cicilan 20%              -> Dibayar Sebagian (sisa 50%)
 *   ...-0003  uang_muka 30% + cicilan 20% + pelunasan   -> LUNAS (pendapatan diterima penuh)
 *
 * Idempoten: bila pesanan itu sudah punya pembayaran → dilewati. created_by admin.
 */
class Demo08PaymentSeeder extends Seeder
{
    /**
     * suffix => [nomor awal BYR, daftar fase [phase, fraksi|'rest', metode, offset-hari]].
     * fraksi 'rest' = sisa tagihan (grand_total − yang sudah dicatat).
     */
    private const SCENARIO = [
        '0001' => ['start' => 1, 'phases' => [['uang_muka', '0.30', 'transfer', '-7']]],
        '0002' => ['start' => 2, 'phases' => [['uang_muka', '0.30', 'transfer', '-10'], ['cicilan', '0.20', 'tunai', '-5']]],
        '0003' => [
            'start' => 4,
            'phases' => [
                ['uang_muka', '0.30', 'transfer', '-30'],
                ['cicilan', '0.20', 'tunai', '-20'],
                ['pelunasan', 'rest', 'transfer', '-12'],
            ],
        ],
    ];

    public function run(): void
    {
        $label = date('Y-m');
        $key = 'BYR:' . date('Ym');
        $adminId = $this->scalar('SELECT id FROM users WHERE role = ? ORDER BY id LIMIT 1', ['admin']);

        foreach (self::SCENARIO as $suffix => $cfg) {
            $soId = $this->scalar('SELECT id FROM sales_orders WHERE document_number = ? LIMIT 1', ["SO-{$label}-{$suffix}"]);
            if ($soId === 0) {
                continue;
            }

            if ($this->scalar('SELECT COUNT(*) FROM payments WHERE sales_order_id = ?', [$soId]) > 0) {
                continue; // sudah ada pembayaran → lewati (idempoten)
            }

            $grand = $this->scalarStr('SELECT grand_total FROM sales_orders WHERE id = ? LIMIT 1', [$soId]);
            if (bccomp($grand, '0', 2) <= 0) {
                continue;
            }

            $runningIndex = (int) $cfg['start'];
            $paidSoFar = '0.00';
            foreach ($cfg['phases'] as [$phase, $fraction, $method, $offset]) {
                if ($fraction === 'rest') {
                    $amount = bcsub($grand, $paidSoFar, 2); // sisanya → lunas
                } else {
                    $amount = bcmul($grand, $fraction, 2);
                }
                if (bccomp($amount, '0', 2) <= 0) {
                    continue;
                }
                $paidSoFar = bcadd($paidSoFar, $amount, 2);

                $this->record(
                    sprintf('BYR-%s-%04d', $label, $runningIndex),
                    $soId,
                    $amount,
                    $phase,
                    $method,
                    $phase === 'uang_muka' ? 'TRF-DEMO-DP-' . $suffix : null,
                    date('Y-m-d', strtotime($offset . ' day')),
                    $this->phaseNote($phase),
                    $adminId ?: null,
                );
                $runningIndex++;
            }
        }

        // Enam nomor demo terpakai (0001-0006) → penerus mulai 0007.
        $this->bumpSequence($key, 7);
    }

    private function phaseNote(string $phase): string
    {
        return match ($phase) {
            'uang_muka' => 'Uang muka sesuai kesepakatan awal.',
            'cicilan' => 'Cicilan pertama di kasir.',
            'pelunasan' => 'Pelunasan — pesanan selesai & lunas.',
            default => 'Pembayaran demo.',
        };
    }

    private function record(
        string $documentNumber,
        int $salesOrderId,
        string $amount,
        string $phase,
        string $method,
        ?string $reference,
        string $paymentDate,
        ?string $note,
        ?int $createdBy,
    ): void {
        $this->insert('payments', [
            'document_number' => $documentNumber,
            'sales_order_id' => $salesOrderId,
            'amount' => $amount,
            'phase' => $phase,
            'method' => $method,
            'reference' => $reference,
            'payment_date' => $paymentDate,
            'note' => $note,
            'created_by' => $createdBy,
        ]);
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
        return (int) $this->scalarRaw($sql, $bind);
    }

    /** @param list<mixed> $bind */
    private function scalarStr(string $sql, array $bind = []): string
    {
        return (string) $this->scalarRaw($sql, $bind);
    }

    /** @param list<mixed> $bind */
    private function scalarRaw(string $sql, array $bind): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);

        return $stmt->fetchColumn();
    }
}
