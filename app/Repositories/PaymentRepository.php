<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\Payment;
use PDO;

/**
 * Akses data pembayaran (§8.H). Transaksi, penomoran & aturan tetap di service;
 * repository hanya SQL. Baca memakai JOIN agar tanpa N+1 (§7).
 */
final class PaymentRepository
{
    private const SELECT =
        'SELECT pm.*,
                so.document_number AS sales_order_number,
                c.name AS customer_name,
                u.name AS created_by_name
         FROM payments pm
         JOIN sales_orders so ON so.id = pm.sales_order_id
         JOIN customers c ON c.id = so.customer_id
         LEFT JOIN `users` u ON u.id = pm.created_by';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array{list: list<Payment>, total: int}
     */
    public function paginate(string $search = '', string $method = 'semua', string $phase = 'semua', int $salesOrderId = 0, int $offset = 0, int $limit = 15): array
    {
        [$where, $bind] = $this->buildFilter($search, $method, $phase, $salesOrderId);
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $countStmt = $this->db->pdo()->prepare(
            'SELECT COUNT(*)
             FROM payments pm
             JOIN sales_orders so ON so.id = pm.sales_order_id
             JOIN customers c ON c.id = so.customer_id ' . $where
        );
        $countStmt->execute($bind);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->pdo()->prepare(
            self::SELECT . $where . ' ORDER BY pm.payment_date DESC, pm.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute($bind);

        return [
            'list' => array_map(static fn(array $r): Payment => Payment::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC)),
            'total' => $total,
        ];
    }

    public function find(int $id): ?Payment
    {
        $stmt = $this->db->pdo()->prepare(self::SELECT . ' WHERE pm.id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : Payment::fromRow($row);
    }

    /**
     * Riwayat pembayaran sebuah pesanan (terbaru lebih dulu).
     *
     * @return list<Payment>
     */
    public function forSalesOrder(int $salesOrderId, int $limit = 30): array
    {
        $stmt = $this->db->pdo()->prepare(
            self::SELECT . ' WHERE pm.sales_order_id = ? ORDER BY pm.payment_date DESC, pm.id DESC LIMIT ' . max(1, $limit)
        );
        $stmt->execute([$salesOrderId]);

        return array_map(static fn(array $r): Payment => Payment::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Total sudah diterima untuk sebuah pesanan (SUM) — satu-satunya sumber
     * kebenaran "sudah dibayar" (§9). Dikembalikan sebagai string desimal agar
     * tetap presisi saat dipakai PaymentCalculator.
     */
    public function sumForSalesOrder(int $salesOrderId): string
    {
        $stmt = $this->db->pdo()->prepare('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE sales_order_id = ?');
        $stmt->execute([$salesOrderId]);

        return (string) $stmt->fetchColumn();
    }

    public function countForSalesOrder(int $salesOrderId): int
    {
        $stmt = $this->db->pdo()->prepare('SELECT COUNT(*) FROM payments WHERE sales_order_id = ?');
        $stmt->execute([$salesOrderId]);

        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO payments
                (document_number, sales_order_id, amount, phase, method,
                 reference, payment_date, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (string) $data['document_number'],
            (int) $data['sales_order_id'],
            (string) $data['amount'],
            (string) $data['phase'],
            (string) $data['method'],
            $this->nullable($data, 'reference'),
            (string) $data['payment_date'],
            $this->nullable($data, 'note'),
            isset($data['created_by']) && (int) $data['created_by'] > 0 ? (int) $data['created_by'] : null,
        ]);

        return (int) $this->db->pdo()->lastInsertId();
    }

    // ------------------------------------------------------------------

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildFilter(string $search, string $method, string $phase, int $salesOrderId): array
    {
        $conditions = [];
        $bind = [];

        if ($salesOrderId > 0) {
            $conditions[] = 'pm.sales_order_id = ?';
            $bind[] = $salesOrderId;
        }

        if ($method !== 'semua' && $method !== '') {
            $conditions[] = 'pm.method = ?';
            $bind[] = $method;
        }

        if ($phase !== 'semua' && $phase !== '') {
            $conditions[] = 'pm.phase = ?';
            $bind[] = $phase;
        }

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $conditions[] = '(pm.document_number LIKE ? OR so.document_number LIKE ? OR c.name LIKE ?)';
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
