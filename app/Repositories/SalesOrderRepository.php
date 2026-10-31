<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use PDO;

/**
 * Akses data sales order + item. Item daftar tidak dimuat per baris
 * (hindari N+1, §7 DOC) — hanya detail yang memuat item. Penomoran
 * & transaksi berada di service, bukan di sini.
 */
final class SalesOrderRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array{list: list<SalesOrder>, total: int}
     */
    public function paginate(string $search = '', string $status = 'semua', int $customerId = 0, int $offset = 0, int $limit = 15): array
    {
        [$where, $bind] = $this->buildFilter($search, $status, $customerId);
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $countStmt = $this->db->pdo()->prepare(
            "SELECT COUNT(*) FROM sales_orders o JOIN customers c ON c.id = o.customer_id {$where}"
        );
        $countStmt->execute($bind);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->pdo()->prepare(
            'SELECT o.*, c.name AS customer_name FROM sales_orders o
             JOIN customers c ON c.id = o.customer_id '
            . $where . ' ORDER BY o.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute($bind);
        $list = array_map(static fn(array $r): SalesOrder => SalesOrder::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC));

        return ['list' => $list, 'total' => $total];
    }

    public function find(int $id): ?SalesOrder
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT o.*, c.name AS customer_name FROM sales_orders o
             JOIN customers c ON c.id = o.customer_id WHERE o.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return SalesOrder::fromRow($row)->withItems($this->itemsFor($id));
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO sales_orders
                (document_number, customer_id, order_date, due_date, status,
                 subtotal, discount_amount, additional_cost, grand_total, dp_amount,
                 design_notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['document_number'],
            (int) $data['customer_id'],
            $data['order_date'],
            $data['due_date'],
            $data['status'] ?? SalesOrder::STATUS_DRAFT,
            $data['subtotal'],
            $data['discount_amount'],
            $data['additional_cost'],
            $data['grand_total'],
            $data['dp_amount'],
            ($data['design_notes'] ?? '') !== '' ? (string) $data['design_notes'] : null,
            $data['created_by'] ?? null,
        ]);

        return (int) $this->db->pdo()->lastInsertId();
    }

    /**
     * Perubah fields isi (bukan nomor/status). Total overwritten service.
     *
     * @param array<string, mixed> $data
     */
    public function updateContent(int $id, array $data): void
    {
        $stmt = $this->db->pdo()->prepare(
            'UPDATE sales_orders SET
                customer_id = ?, order_date = ?, due_date = ?,
                subtotal = ?, discount_amount = ?, additional_cost = ?, grand_total = ?, dp_amount = ?,
                design_notes = ?
             WHERE id = ?'
        );
        $stmt->execute([
            (int) $data['customer_id'],
            $data['order_date'],
            $data['due_date'],
            $data['subtotal'],
            $data['discount_amount'],
            $data['additional_cost'],
            $data['grand_total'],
            $data['dp_amount'],
            ($data['design_notes'] ?? '') !== '' ? (string) $data['design_notes'] : null,
            $id,
        ]);
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE sales_orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    /**
     * Kunci baris pesanan (SELECT ... FOR UPDATE) untuk serialisasi penulisan
     * turunannya — pembayaran & pengiriman — dalam satu transaksi (§9).
     * WAJIB dipanggil di dalam Database::transaction(); permintaan serentak kedua
     * akan menunggu commit yang pertama lalu membaca ulang saldo otoritatif,
     * menutup celah double-submit. Sama pola dengan RawMaterialRepository::lockStock.
     */
    public function lock(int $id): void
    {
        $stmt = $this->db->pdo()->prepare('SELECT id FROM sales_orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->pdo()->prepare('DELETE FROM sales_orders WHERE id = ?');
        $stmt->execute([$id]);
    }

    // ------------------------------------------------------------------
    // Item
    // ------------------------------------------------------------------

    public function deleteItems(int $orderId): void
    {
        $stmt = $this->db->pdo()->prepare('DELETE FROM sales_order_items WHERE sales_order_id = ?');
        $stmt->execute([$orderId]);
    }

    /**
     * @param array{product_variant_id:int, quantity:int, unit_price:string|float, line_total:string|float} $item
     */
    public function insertItem(int $orderId, array $item): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO sales_order_items (sales_order_id, product_variant_id, quantity, unit_price, line_total)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $orderId,
            (int) $item['product_variant_id'],
            (int) $item['quantity'],
            $item['unit_price'],
            $item['line_total'],
        ]);

        return (int) $this->db->pdo()->lastInsertId();
    }

    /** @return list<SalesOrderItem> */
    public function itemsFor(int $orderId): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT i.*, p.name AS product_name, v.color AS variant_color, v.size AS variant_size
             FROM sales_order_items i
             JOIN product_variants v ON v.id = i.product_variant_id
             JOIN products p ON p.id = v.product_id
             WHERE i.sales_order_id = ? ORDER BY i.id ASC'
        );
        $stmt->execute([$orderId]);

        return array_map(static fn(array $r): SalesOrderItem => SalesOrderItem::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // ------------------------------------------------------------------
    // Referensi untuk form & perhitungan ulang server

    /**
     * Opsi varian aktif untuk picker item (label + harga dari DB).
     *
     * @return list<array{id:int, label:string, unit_price:string, product_name:string}>
     */
    public function activeVariantOptions(): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT v.id, v.unit_price, p.name AS product_name, v.color, v.size
             FROM product_variants v
             JOIN products p ON p.id = v.product_id
             WHERE v.is_active = 1 AND p.is_active = 1
             ORDER BY p.name, v.color, v.size'
        );

        $rows = [];
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[] = [
                'id' => (int) $r['id'],
                'label' => trim($r['product_name'] . ' — ' . $r['color'] . ' / ' . $r['size'], ' —/'),
                'unit_price' => (string) $r['unit_price'],
                'product_name' => (string) $r['product_name'],
            ];
        }

        return $rows;
    }

    /**
     * Harga satuan + status varian dari DATABASE. Dipakai service untuk
     * menghitung ulang total — TIDAK pernah mempercayai harga dari browser.
     *
     * @return array{unit_price: string, is_active: bool}|null
     */
    public function variantPrice(int $variantId): ?array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT v.unit_price, v.is_active, p.is_active AS product_active
             FROM product_variants v
             JOIN products p ON p.id = v.product_id
             WHERE v.id = ? LIMIT 1'
        );
        $stmt->execute([$variantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return [
            'unit_price' => (string) $row['unit_price'],
            'is_active' => (bool) $row['is_active'] && (bool) $row['product_active'],
        ];
    }

    /**
     * Opsi pesanan yang dapat menerima pembayaran (semua status KECUALI
     * dibatalkan) untuk picker form. Sertakan grand_total & sudah-dibayar (SUM
     * payments) supaya form dapat menampilkan sisa tagihan; angka final tetap
     * dihitung ulang otoritatif di PaymentService.
     *
     * @return list<array{id:int, document_number:string, customer_name:string, status:string, grand_total:string, paid_total:string}>
     */
    public function selectableForPayment(): array
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT o.id, o.document_number, c.name AS customer_name, o.status, o.grand_total,
                    (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.sales_order_id = o.id) AS paid_total
             FROM sales_orders o
             JOIN customers c ON c.id = o.customer_id
             WHERE o.status <> ?
             ORDER BY o.id DESC"
        );

        $rows = [];
        $stmt->execute([SalesOrder::STATUS_CANCELLED]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[] = [
                'id' => (int) $r['id'],
                'document_number' => (string) $r['document_number'],
                'customer_name' => (string) $r['customer_name'],
                'status' => (string) $r['status'],
                'grand_total' => (string) $r['grand_total'],
                'paid_total' => (string) $r['paid_total'],
            ];
        }

        return $rows;
    }

    /**
     * Opsi pesanan yang dapat dikirim (semua status KECUALI dibatalkan) untuk
     * picker form. Menyertakan total kuantitas pesanan, total lolos QC, dan sudah
     * dikirim agar form dapat menunjukkan sisa siap-kirim. Angka final dihitung
     * ulang otoritatif di DeliveryService.
     *
     * @return list<array{id:int, document_number:string, customer_name:string, status:string, ordered_qty:int, qc_ready:int, shipped:int}>
     */
    public function selectableForDelivery(): array
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT o.id, o.document_number, c.name AS customer_name, o.status,
                    (SELECT COALESCE(SUM(i.quantity), 0) FROM sales_order_items i WHERE i.sales_order_id = o.id) AS ordered_qty,
                    (SELECT COALESCE(SUM(q.passed_qty), 0) FROM qc_inspections q
                        JOIN production_orders po ON po.id = q.production_order_id
                        WHERE po.sales_order_id = o.id) AS qc_ready,
                    (SELECT COALESCE(SUM(dv.quantity), 0) FROM deliveries dv
                        WHERE dv.sales_order_id = o.id AND dv.status <> 'batal') AS shipped
             FROM sales_orders o
             JOIN customers c ON c.id = o.customer_id
             WHERE o.status <> ?
             ORDER BY o.id DESC"
        );

        $rows = [];
        $stmt->execute([SalesOrder::STATUS_CANCELLED]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[] = [
                'id' => (int) $r['id'],
                'document_number' => (string) $r['document_number'],
                'customer_name' => (string) $r['customer_name'],
                'status' => (string) $r['status'],
                'ordered_qty' => (int) $r['ordered_qty'],
                'qc_ready' => (int) $r['qc_ready'],
                'shipped' => (int) $r['shipped'],
            ];
        }

        return $rows;
    }

    // ------------------------------------------------------------------

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildFilter(string $search, string $status, int $customerId): array
    {
        $conditions = [];
        $bind = [];

        if ($status !== 'semua' && $status !== '') {
            $conditions[] = 'o.status = ?';
            $bind[] = $status;
        }

        if ($customerId > 0) {
            $conditions[] = 'o.customer_id = ?';
            $bind[] = $customerId;
        }

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $conditions[] = '(o.document_number LIKE ? OR c.name LIKE ?)';
            $bind[] = $like;
            $bind[] = $like;
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $bind];
    }
}
