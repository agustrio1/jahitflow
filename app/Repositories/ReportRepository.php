<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\Delivery;
use App\Models\ProductionOrder;
use App\Models\SalesOrder;
use App\Support\ReportPeriod;
use PDO;

/**
 * Query agregasi lintas modul untuk Laporan (§8.J). READ-ONLY — hanya SELECT.
 * Konsisten dengan aturan bisnis: "nilai pesanan" (grand_total) TIDAK pernah
 * dicampur sebagai omzet yang sudah diterima; pendapatan diterima = SUM(payments).
 * Pesanan dibatalkan dikecualikan dari nilai/pendapatan. Semua tanggal lewat
 * bind parameter (tak ada string interpolation dari input).
 */
final class ReportRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Pesanan berdasarkan periode (order_date).
     *
     * @return array{byStatus: list<array<string, mixed>>, byMonth: list<array<string, mixed>>}
     */
    public function orders(ReportPeriod $p): array
    {
        return [
            'byStatus' => $this->all(
                'SELECT status, COUNT(*) AS total_orders, COALESCE(SUM(grand_total), 0) AS nilai
                 FROM sales_orders
                 WHERE order_date BETWEEN ? AND ?
                 GROUP BY status
                 ORDER BY total_orders DESC',
                [$p->fromDate(), $p->toDate()],
            ),
            'byMonth' => $this->all(
                "SELECT DATE_FORMAT(order_date, '%Y-%m') AS bulan, COUNT(*) AS total_orders,
                        COALESCE(SUM(grand_total), 0) AS nilai
                 FROM sales_orders
                 WHERE order_date BETWEEN ? AND ? AND status <> ?
                 GROUP BY bulan
                 ORDER BY bulan",
                [$p->fromDate(), $p->toDate(), SalesOrder::STATUS_CANCELLED],
            ),
        ];
    }

    /**
     * Progres produksi (perintah produksi yang dibuat dalam periode, created_at).
     *
     * @return array{byStatus: list<array<string, mixed>>, overdue: array{total: int, pcs: int}}
     */
    public function production(ReportPeriod $p): array
    {
        $byStatus = $this->all(
            'SELECT status, COUNT(*) AS total, COALESCE(SUM(target_quantity), 0) AS pcs
             FROM production_orders
             WHERE created_at BETWEEN ? AND ?
             GROUP BY status
             ORDER BY total DESC',
            [$p->fromDateTime(), $p->toDateTime()],
        );

        $over = $this->first(
            'SELECT COUNT(*) AS total, COALESCE(SUM(target_quantity), 0) AS pcs
             FROM production_orders
             WHERE created_at BETWEEN ? AND ?
               AND status NOT IN (?, ?)
               AND target_date IS NOT NULL AND target_date < CURDATE()',
            [$p->fromDateTime(), $p->toDateTime(), ProductionOrder::STATUS_COMPLETED, ProductionOrder::STATUS_CANCELLED],
        );

        return [
            'byStatus' => $byStatus,
            'overdue' => ['total' => (int) ($over['total'] ?? 0), 'pcs' => (int) ($over['pcs'] ?? 0)],
        ];
    }

    /**
     * Pemakaian bahan: mutasi keluar (direction='out') dalam periode (created_at).
     *
     * @return array{byMaterial: list<array<string, mixed>>, movementCount: int}
     */
    public function materialUsage(ReportPeriod $p): array
    {
        $byMaterial = $this->all(
            "SELECT rm.code, rm.name, rm.unit, COALESCE(SUM(sm.quantity), 0) AS used
             FROM stock_movements sm
             JOIN raw_materials rm ON rm.id = sm.raw_material_id
             WHERE sm.direction = 'out' AND sm.created_at BETWEEN ? AND ?
             GROUP BY rm.id, rm.code, rm.name, rm.unit
             ORDER BY used DESC",
            [$p->fromDateTime(), $p->toDateTime()],
        );

        $count = $this->first(
            'SELECT COUNT(*) AS n FROM stock_movements WHERE direction = ? AND created_at BETWEEN ? AND ?',
            ['out', $p->fromDateTime(), $p->toDateTime()],
        );

        return ['byMaterial' => $byMaterial, 'movementCount' => (int) ($count['n'] ?? 0)];
    }

    /**
     * Hasil QC dalam periode (inspected_at): agregat lulus/gagal + rincian hasil.
     *
     * @return array{overall: array{inspected: int, passed: int, failed: int, checks: int}, byResult: list<array<string, mixed>>}
     */
    public function qc(ReportPeriod $p): array
    {
        $overall = $this->first(
            'SELECT COALESCE(SUM(inspected_qty), 0) AS inspected,
                    COALESCE(SUM(passed_qty), 0) AS passed,
                    COALESCE(SUM(failed_qty), 0) AS failed,
                    COUNT(*) AS checks
             FROM qc_inspections
             WHERE inspected_at BETWEEN ? AND ?',
            [$p->fromDateTime(), $p->toDateTime()],
        );

        $byResult = $this->all(
            'SELECT result, COUNT(*) AS checks, COALESCE(SUM(inspected_qty), 0) AS inspected
             FROM qc_inspections
             WHERE inspected_at BETWEEN ? AND ?
             GROUP BY result
             ORDER BY checks DESC',
            [$p->fromDateTime(), $p->toDateTime()],
        );

        return [
            'overall' => [
                'inspected' => (int) ($overall['inspected'] ?? 0),
                'passed' => (int) ($overall['passed'] ?? 0),
                'failed' => (int) ($overall['failed'] ?? 0),
                'checks' => (int) ($overall['checks'] ?? 0),
            ],
            'byResult' => $byResult,
        ];
    }

    /**
     * Pembayaran & tunggakan.
     * - received: SUM(amount) uang MASUK dalam periode (payment_date) = pendapatan diterima.
     * - byMethod: rincian per metode dalam periode.
     * - receivables: tagihan BERJALAN (pesanan confirmed, outstanding = grand_total − paid > 0).
     *   Tunggakan adalah kondisi kini (bukan per periode) — ditandai di UI.
     *
     * @return array{received: string, byMethod: list<array<string, mixed>>, transactionCount: int, receivables: list<array<string, mixed>>, receivableCount: int, receivableTotal: string}
     */
    public function payments(ReportPeriod $p): array
    {
        $agg = $this->first(
            'SELECT COALESCE(SUM(amount), 0) AS received, COUNT(*) AS n
             FROM payments
             WHERE payment_date BETWEEN ? AND ?',
            [$p->fromDate(), $p->toDate()],
        );

        $byMethod = $this->all(
            'SELECT method, COUNT(*) AS n, COALESCE(SUM(amount), 0) AS jumlah
             FROM payments
             WHERE payment_date BETWEEN ? AND ?
             GROUP BY method
             ORDER BY jumlah DESC',
            [$p->fromDate(), $p->toDate()],
        );

        $receivables = $this->all(
            'SELECT so.id, so.document_number, so.customer_name, so.grand_total, so.paid, so.outstanding
             FROM (
                SELECT s.id, s.document_number, c.name AS customer_name, s.grand_total,
                       COALESCE(SUM(pm.amount), 0) AS paid,
                       (s.grand_total - COALESCE(SUM(pm.amount), 0)) AS outstanding
                FROM sales_orders s
                JOIN customers c ON c.id = s.customer_id
                LEFT JOIN payments pm ON pm.sales_order_id = s.id
                WHERE s.status = ?
                GROUP BY s.id, s.document_number, c.name, s.grand_total
             ) so
             WHERE so.outstanding > 0.005
             ORDER BY so.outstanding DESC
             LIMIT 200',
            [SalesOrder::STATUS_CONFIRMED],
        );

        // Total piutang akurat (daftar di atas dibatasi 200 baris untuk UI).
        $recv = $this->first(
            'SELECT COUNT(*) AS n, COALESCE(SUM(t.outstanding), 0) AS total
             FROM (
                SELECT (s.grand_total - COALESCE(SUM(pm.amount), 0)) AS outstanding
                FROM sales_orders s
                LEFT JOIN payments pm ON pm.sales_order_id = s.id
                WHERE s.status = ?
                GROUP BY s.id, s.grand_total
             ) t
             WHERE t.outstanding > 0.005',
            [SalesOrder::STATUS_CONFIRMED],
        );
        $recvCount = (int) ($recv['n'] ?? 0);
        $recvTotal = (string) ($recv['total'] ?? '0');

        return [
            'received' => (string) ($agg['received'] ?? '0'),
            'transactionCount' => (int) ($agg['n'] ?? '0'),
            'byMethod' => $byMethod,
            'receivables' => $receivables,
            'receivableCount' => $recvCount,
            'receivableTotal' => $recvTotal,
        ];
    }

    /**
     * Pengiriman dalam periode (delivery_date).
     *
     * @return array{byStatus: list<array<string, mixed>>}
     */
    public function deliveries(ReportPeriod $p): array
    {
        return [
            'byStatus' => $this->all(
                'SELECT status, COUNT(*) AS shipments, COALESCE(SUM(quantity), 0) AS pcs
                 FROM deliveries
                 WHERE delivery_date BETWEEN ? AND ?
                 GROUP BY status
                 ORDER BY FIELD(status, ?, ?, ?, ?)',
                [
                    $p->fromDate(),
                    $p->toDate(),
                    Delivery::STATUS_DISIAPKAN,
                    Delivery::STATUS_DIKIRIM,
                    Delivery::STATUS_DITERIMA,
                    Delivery::STATUS_BATAL,
                ],
            ),
        ];
    }

    // ------------------------------------------------------------------

    /**
     * @param list<mixed> $bind
     * @return list<array<string, mixed>>
     */
    private function all(string $sql, array $bind): array
    {
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($bind);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param list<mixed> $bind
     * @return array<string, mixed>
     */
    private function first(string $sql, array $bind): array
    {
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($bind);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? [] : $row;
    }
}
