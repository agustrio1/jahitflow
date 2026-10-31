<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\QcInspection;
use PDO;

/**
 * Akses data pemeriksaan QC (§8.G). Transaksi & aturan tetap di service;
 * repository hanya SQL. Baca memakai JOIN agar tanpa N+1 (§7).
 */
final class QcInspectionRepository
{
    private const SELECT =
        'SELECT q.*,
                p.document_number AS production_order_number,
                so.document_number AS sales_order_number,
                c.name AS customer_name,
                u.name AS inspector_name
         FROM qc_inspections q
         JOIN production_orders p ON p.id = q.production_order_id
         JOIN sales_orders so ON so.id = p.sales_order_id
         JOIN customers c ON c.id = so.customer_id
         LEFT JOIN `users` u ON u.id = q.inspected_by';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array{list: list<QcInspection>, total: int}
     */
    public function paginate(string $search = '', string $result = 'semua', int $productionOrderId = 0, int $offset = 0, int $limit = 15): array
    {
        [$where, $bind] = $this->buildFilter($search, $result, $productionOrderId);
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $countStmt = $this->db->pdo()->prepare(
            'SELECT COUNT(*)
             FROM qc_inspections q
             JOIN production_orders p ON p.id = q.production_order_id
             JOIN sales_orders so ON so.id = p.sales_order_id
             JOIN customers c ON c.id = so.customer_id ' . $where
        );
        $countStmt->execute($bind);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->pdo()->prepare(
            self::SELECT . $where . ' ORDER BY q.inspected_at DESC, q.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute($bind);

        return [
            'list' => array_map(static fn(array $r): QcInspection => QcInspection::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC)),
            'total' => $total,
        ];
    }

    public function find(int $id): ?QcInspection
    {
        $stmt = $this->db->pdo()->prepare(self::SELECT . ' WHERE q.id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : QcInspection::fromRow($row);
    }

    /**
     * Riwayat pemeriksaan sebuah perintah produksi (terbaru lebih dulu).
     *
     * @return list<QcInspection>
     */
    public function forProductionOrder(int $productionOrderId, int $limit = 20): array
    {
        $stmt = $this->db->pdo()->prepare(
            self::SELECT . ' WHERE q.production_order_id = ? ORDER BY q.inspected_at DESC, q.id DESC LIMIT ' . max(1, $limit)
        );
        $stmt->execute([$productionOrderId]);

        return array_map(static fn(array $r): QcInspection => QcInspection::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Total barang LULUS QC untuk sebuah perintah produksi — dasar gerbang
     * "siap dikirim" (§9: barang gagal QC tidak boleh dikirim). Dipakai modul
     * Pengiriman (Batch E3).
     */
    public function passedTotal(int $productionOrderId): int
    {
        $stmt = $this->db->pdo()->prepare('SELECT COALESCE(SUM(passed_qty), 0) FROM qc_inspections WHERE production_order_id = ?');
        $stmt->execute([$productionOrderId]);

        return (int) $stmt->fetchColumn();
    }

    public function countForProductionOrder(int $productionOrderId): int
    {
        $stmt = $this->db->pdo()->prepare('SELECT COUNT(*) FROM qc_inspections WHERE production_order_id = ?');
        $stmt->execute([$productionOrderId]);

        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO qc_inspections
                (production_order_id, inspected_qty, passed_qty, failed_qty, result,
                 defect_note, rework_action, inspected_by, inspected_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $data['production_order_id'],
            (int) $data['inspected_qty'],
            (int) $data['passed_qty'],
            (int) $data['failed_qty'],
            (string) $data['result'],
            $this->nullable($data, 'defect_note'),
            $this->nullable($data, 'rework_action'),
            isset($data['inspected_by']) && (int) $data['inspected_by'] > 0 ? (int) $data['inspected_by'] : null,
            (string) ($data['inspected_at'] ?? date('Y-m-d H:i:s')),
        ]);

        return (int) $this->db->pdo()->lastInsertId();
    }

    // ------------------------------------------------------------------

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildFilter(string $search, string $result, int $productionOrderId): array
    {
        $conditions = [];
        $bind = [];

        if ($productionOrderId > 0) {
            $conditions[] = 'q.production_order_id = ?';
            $bind[] = $productionOrderId;
        }

        if ($result !== 'semua' && $result !== '') {
            $conditions[] = 'q.result = ?';
            $bind[] = $result;
        }

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $conditions[] = '(p.document_number LIKE ? OR so.document_number LIKE ? OR c.name LIKE ?)';
            $bind[] = $like;
            $bind[] = $like;
            $bind[] = $like;
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $bind];
    }

    /** @param array<string, mixed> $data */
    private function nullable(array $data, string $key): ?string
    {
        $value = trim((string) ($data[$key] ?? ''));

        return $value === '' ? null : $value;
    }
}
