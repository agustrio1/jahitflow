<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\Delivery;
use App\Models\ProductionOrder;
use App\Models\SalesOrder;
use PDO;

/**
 * Query dashboard operasional (§8.A). READ-ONLY — mengutamakan PEKERJAAN YANG
 * PERLU DITINDAKLANJUTI (mendekati tenggat, terlambat, menunggu QC, pengiriman
 * belum selesai), bukan sekadar angka besar. Definisi uang jujur: nilai pesanan
 * (kontrak) dipisah dari pendapatan diterima (SUM pembayaran). Semua bind via
 * parameter.
 */
final class DashboardRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Angka untuk strip KPI.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $today = date('Y-m-d');
        $soon = date('Y-m-d', strtotime('+7 day'));

        return [
            'orders_new' => $this->count(
                'SELECT COUNT(*) FROM sales_orders WHERE status IN (?, ?)',
                [SalesOrder::STATUS_DRAFT, SalesOrder::STATUS_OFFERED],
            ),
            'in_production' => $this->count(
                'SELECT COUNT(*) FROM production_orders WHERE status = ?',
                [ProductionOrder::STATUS_RUNNING],
            ),
            'due_soon' => $this->count(
                'SELECT COUNT(*) FROM sales_orders o
                 WHERE o.status = ? AND o.due_date BETWEEN ? AND ? AND ' . $this->pendingDeliverySql('o'),
                [SalesOrder::STATUS_CONFIRMED, $today, $soon],
            ),
            'overdue' => $this->count(
                'SELECT COUNT(*) FROM sales_orders o
                 WHERE o.status = ? AND o.due_date < ? AND ' . $this->pendingDeliverySql('o'),
                [SalesOrder::STATUS_CONFIRMED, $today],
            ),
            'awaiting_qc' => $this->count(
                'SELECT COUNT(*) FROM production_orders p
                 WHERE p.status IN (?, ?)
                   AND NOT EXISTS (SELECT 1 FROM qc_inspections q WHERE q.production_order_id = p.id)',
                [ProductionOrder::STATUS_RUNNING, ProductionOrder::STATUS_COMPLETED],
            ),
            'open_deliveries' => $this->count(
                'SELECT COUNT(*) FROM deliveries WHERE status IN (?, ?)',
                [Delivery::STATUS_DISIAPKAN, Delivery::STATUS_DIKIRIM],
            ),
        ];
    }

    /**
     * Ringkasan uang dengan definisi terpisah (§8.A). Nilai dalam string desimal.
     *
     * @return array{order_value: string, received: string, receivable: string}
     */
    public function money(): array
    {
        $orderValue = $this->scalarString(
            'SELECT COALESCE(SUM(grand_total), 0) FROM sales_orders WHERE status = ?',
            [SalesOrder::STATUS_CONFIRMED],
        );

        $received = $this->scalarString('SELECT COALESCE(SUM(amount), 0) FROM payments');

        $receivable = $this->scalarString(
            'SELECT COALESCE(SUM(t.outstanding), 0) FROM (
                SELECT (s.grand_total - COALESCE(SUM(pm.amount), 0)) AS outstanding
                FROM sales_orders s
                LEFT JOIN payments pm ON pm.sales_order_id = s.id
                WHERE s.status = ?
                GROUP BY s.id, s.grand_total
             ) t WHERE t.outstanding > 0.005',
            [SalesOrder::STATUS_CONFIRMED],
        );

        return ['order_value' => $orderValue, 'received' => $received, 'receivable' => $receivable];
    }

    /**
     * Pesanan dikonfirmasi yang mendekati tenggat (7 hari ke depan), terdekat dulu.
     *
     * @return list<array<string, mixed>>
     */
    public function dueSoonOrders(int $limit = 5): array
    {
        $today = date('Y-m-d');
        $soon = date('Y-m-d', strtotime('+7 day'));

        return $this->all(
            'SELECT o.id, o.document_number, c.name AS customer_name, o.due_date, o.status
             FROM sales_orders o
             JOIN customers c ON c.id = o.customer_id
             WHERE o.status = ? AND o.due_date BETWEEN ? AND ? AND ' . $this->pendingDeliverySql('o') . '
             ORDER BY o.due_date ASC, o.id ASC
             LIMIT ' . max(1, $limit),
            [SalesOrder::STATUS_CONFIRMED, $today, $soon],
        );
    }

    /**
     * Pesanan dikonfirmasi yang lewat tenggat — paling lama tertunda dulu.
     *
     * @return list<array<string, mixed>>
     */
    public function overdueOrders(int $limit = 5): array
    {
        return $this->all(
            'SELECT o.id, o.document_number, c.name AS customer_name, o.due_date, o.status
             FROM sales_orders o
             JOIN customers c ON c.id = o.customer_id
             WHERE o.status = ? AND o.due_date < ? AND ' . $this->pendingDeliverySql('o') . '
             ORDER BY o.due_date ASC, o.id ASC
             LIMIT ' . max(1, $limit),
            [SalesOrder::STATUS_CONFIRMED, date('Y-m-d')],
        );
    }

    /**
     * Perintah produksi berjalan/selesai yang belum punya pemeriksaan QC sama sekali.
     *
     * @return list<array<string, mixed>>
     */
    public function awaitingQc(int $limit = 5): array
    {
        return $this->all(
            'SELECT p.id, p.document_number, so.document_number AS sales_order_number,
                    p.target_quantity, p.status, p.target_date
             FROM production_orders p
             JOIN sales_orders so ON so.id = p.sales_order_id
             WHERE p.status IN (?, ?)
               AND NOT EXISTS (SELECT 1 FROM qc_inspections q WHERE q.production_order_id = p.id)
             ORDER BY p.target_date ASC, p.id ASC
             LIMIT ' . max(1, $limit),
            [ProductionOrder::STATUS_RUNNING, ProductionOrder::STATUS_COMPLETED],
        );
    }

    /**
     * Pengiriman belum selesai (disiapkan/dikirim), tanggal kirim terdekat dulu.
     *
     * @return list<array<string, mixed>>
     */
    public function openDeliveries(int $limit = 5): array
    {
        return $this->all(
            'SELECT d.id, d.document_number, d.quantity, d.status, d.delivery_date,
                    so.document_number AS sales_order_number, c.name AS customer_name
             FROM deliveries d
             JOIN sales_orders so ON so.id = d.sales_order_id
             JOIN customers c ON c.id = so.customer_id
             WHERE d.status IN (?, ?)
             ORDER BY d.delivery_date ASC, d.id ASC
             LIMIT ' . max(1, $limit),
            [Delivery::STATUS_DISIAPKAN, Delivery::STATUS_DIKIRIM],
        );
    }

    // ------------------------------------------------------------------

    /**
     * Ekspresi SQL: pesanan masih punya sisa belum terkirim (kuantitas dipesan >
     * terkirim: status dikirim/diterima). Memakai alias table yang diberikan.
     * Menjaga "terlambat/mendekati tenggat" tetap actionable — pesanan yang sudah
     * dikirim penuh tidak lagi dianggap perlu ditindaklanjuti.
     */
    private function pendingDeliverySql(string $alias): string
    {
        return '(SELECT COALESCE(SUM(i.quantity), 0) FROM sales_order_items i WHERE i.sales_order_id = ' . $alias . '.id)'
            . ' > (SELECT COALESCE(SUM(dv.quantity), 0) FROM deliveries dv'
            . ' WHERE dv.sales_order_id = ' . $alias . '.id AND dv.status IN (\'dikirim\', \'diterima\'))';
    }

    /** @param list<mixed> $bind */
    private function count(string $sql, array $bind): int
    {
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($bind);

        return (int) $stmt->fetchColumn();
    }

    /** @param list<mixed> $bind */
    private function scalarString(string $sql, array $bind = []): string
    {
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($bind);

        return (string) $stmt->fetchColumn();
    }

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
}
