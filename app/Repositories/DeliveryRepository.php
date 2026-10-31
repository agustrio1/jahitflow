<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\Delivery;
use PDO;

/**
 * Akses data pengiriman (§8.I). Transaksi, penomoran, dan aturan (batas jumlah,
 * transisi status) berada di service; repository hanya SQL. Baca memakai JOIN
 * agar tanpa N+1 (§7).
 */
final class DeliveryRepository
{
    private const SELECT =
        'SELECT d.*,
                so.document_number AS sales_order_number,
                c.name AS customer_name,
                u.name AS created_by_name
         FROM deliveries d
         JOIN sales_orders so ON so.id = d.sales_order_id
         JOIN customers c ON c.id = so.customer_id
         LEFT JOIN `users` u ON u.id = d.created_by';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array{list: list<Delivery>, total: int}
     */
    public function paginate(string $search = '', string $status = 'semua', string $method = 'semua', int $salesOrderId = 0, int $offset = 0, int $limit = 15): array
    {
        [$where, $bind] = $this->buildFilter($search, $status, $method, $salesOrderId);
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $countStmt = $this->db->pdo()->prepare(
            'SELECT COUNT(*)
             FROM deliveries d
             JOIN sales_orders so ON so.id = d.sales_order_id
             JOIN customers c ON c.id = so.customer_id ' . $where
        );
        $countStmt->execute($bind);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->pdo()->prepare(
            self::SELECT . $where . ' ORDER BY d.delivery_date DESC, d.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute($bind);

        return [
            'list' => array_map(static fn(array $r): Delivery => Delivery::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC)),
            'total' => $total,
        ];
    }

    public function find(int $id): ?Delivery
    {
        $stmt = $this->db->pdo()->prepare(self::SELECT . ' WHERE d.id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : Delivery::fromRow($row);
    }

    /** Riwayat pengiriman sebuah pesanan (terbaru lebih dulu). @return list<Delivery> */
    public function forSalesOrder(int $salesOrderId, int $limit = 30): array
    {
        $stmt = $this->db->pdo()->prepare(
            self::SELECT . ' WHERE d.sales_order_id = ? ORDER BY d.delivery_date DESC, d.id DESC LIMIT ' . max(1, $limit)
        );
        $stmt->execute([$salesOrderId]);

        return array_map(static fn(array $r): Delivery => Delivery::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Total jumlah yang sudah "terpakai" untuk gating: SUM(quantity) KECUALI yang
     * dibatalkan (batal membebalkan jatah, §9). Dipakai DeliveryService.
     */
    public function shippedQuantityForSalesOrder(int $salesOrderId): int
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM deliveries WHERE sales_order_id = ? AND status <> 'batal'"
        );
        $stmt->execute([$salesOrderId]);

        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO deliveries
                (document_number, sales_order_id, quantity, delivery_date, method,
                 courier, tracking_number, recipient, handover_ref, note, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (string) $data['document_number'],
            (int) $data['sales_order_id'],
            (int) $data['quantity'],
            (string) $data['delivery_date'],
            (string) $data['method'],
            $this->nullable($data, 'courier'),
            $this->nullable($data, 'tracking_number'),
            $this->nullable($data, 'recipient'),
            $this->nullable($data, 'handover_ref'),
            $this->nullable($data, 'note'),
            (string) ($data['status'] ?? Delivery::STATUS_DISIAPKAN),
            isset($data['created_by']) && (int) $data['created_by'] > 0 ? (int) $data['created_by'] : null,
        ]);

        return (int) $this->db->pdo()->lastInsertId();
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE deliveries SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    // ------------------------------------------------------------------

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildFilter(string $search, string $status, string $method, int $salesOrderId): array
    {
        $conditions = [];
        $bind = [];

        if ($salesOrderId > 0) {
            $conditions[] = 'd.sales_order_id = ?';
            $bind[] = $salesOrderId;
        }

        if ($status !== 'semua' && $status !== '') {
            $conditions[] = 'd.status = ?';
            $bind[] = $status;
        }

        if ($method !== 'semua' && $method !== '') {
            $conditions[] = 'd.method = ?';
            $bind[] = $method;
        }

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $conditions[] = '(d.document_number LIKE ? OR so.document_number LIKE ? OR c.name LIKE ? OR d.courier LIKE ? OR d.tracking_number LIKE ?)';
            $bind[] = $like;
            $bind[] = $like;
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
