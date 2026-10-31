<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderStage;
use PDO;

/**
 * Akses data perintah produksi + tahapnya. Daftar tidak memuat tahap per
 * baris (hindari N+1, §7). Transaksi, penomoran, dan aturan transisi tahap
 * berada di service.
 */
final class ProductionOrderRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array{list: list<ProductionOrder>, total: int}
     */
    public function paginate(string $search = '', string $status = 'semua', int $assignedTo = 0, int $offset = 0, int $limit = 15): array
    {
        [$where, $bind] = $this->buildFilter($search, $status, $assignedTo);
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $countStmt = $this->db->pdo()->prepare(
            'SELECT COUNT(*) FROM production_orders p
             JOIN sales_orders so ON so.id = p.sales_order_id
             JOIN customers c ON c.id = so.customer_id ' . $where
        );
        $countStmt->execute($bind);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->pdo()->prepare(
            'SELECT p.*, so.document_number AS sales_order_number, c.name AS customer_name, u.name AS assigned_user_name
             FROM production_orders p
             JOIN sales_orders so ON so.id = p.sales_order_id
             JOIN customers c ON c.id = so.customer_id
             LEFT JOIN `users` u ON u.id = p.assigned_to '
            . $where . ' ORDER BY p.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute($bind);
        $list = array_map(static fn(array $r): ProductionOrder => ProductionOrder::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC));

        return ['list' => $list, 'total' => $total];
    }

    public function find(int $id): ?ProductionOrder
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT p.*, so.document_number AS sales_order_number, c.name AS customer_name, u.name AS assigned_user_name
             FROM production_orders p
             JOIN sales_orders so ON so.id = p.sales_order_id
             JOIN customers c ON c.id = so.customer_id
             LEFT JOIN `users` u ON u.id = p.assigned_to
             WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return ProductionOrder::fromRow($row)->withStages($this->stagesFor((int) $row['id']));
    }

    public function findBySalesOrder(int $salesOrderId): ?ProductionOrder
    {
        $stmt = $this->db->pdo()->prepare('SELECT id FROM production_orders WHERE sales_order_id = ? LIMIT 1');
        $stmt->execute([$salesOrderId]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : $this->find((int) $id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO production_orders
                (document_number, sales_order_id, status, target_quantity, target_date,
                 assigned_to, description, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['document_number'],
            (int) $data['sales_order_id'],
            $data['status'] ?? ProductionOrder::STATUS_QUEUED,
            (int) ($data['target_quantity'] ?? 0),
            $this->nullable($data, 'target_date'),
            isset($data['assigned_to']) && (int) $data['assigned_to'] > 0 ? (int) $data['assigned_to'] : null,
            $this->nullable($data, 'description'),
            $this->nullable($data, 'notes'),
            $data['created_by'] ?? null,
        ]);

        return (int) $this->db->pdo()->lastInsertId();
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE production_orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    // ------------------------------------------------------------------
    // Tahap

    /** @return list<ProductionOrderStage> */
    public function stagesFor(int $productionOrderId): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT s.*, u.name AS assigned_user_name
             FROM production_order_stages s
             LEFT JOIN `users` u ON u.id = s.assigned_to
             WHERE s.production_order_id = ? ORDER BY s.position ASC'
        );
        $stmt->execute([$productionOrderId]);

        return array_map(static fn(array $r): ProductionOrderStage => ProductionOrderStage::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findStage(int $stageRowId): ?ProductionOrderStage
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT s.*, u.name AS assigned_user_name
             FROM production_order_stages s
             LEFT JOIN `users` u ON u.id = s.assigned_to
             WHERE s.id = ? LIMIT 1'
        );
        $stmt->execute([$stageRowId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : ProductionOrderStage::fromRow($row);
    }

    /**
     * Snapshot satu tahap ke perintah produksi.
     *
     * @param array{stage_id:int, stage_name:string, position:int, target_qty:int} $stage
     */
    public function insertStage(int $productionOrderId, array $stage): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO production_order_stages
                (production_order_id, stage_id, stage_name, position, status, target_qty, realized_qty)
             VALUES (?, ?, ?, ?, \'pending\', ?, 0)'
        );
        $stmt->execute([
            $productionOrderId,
            (int) $stage['stage_id'],
            (string) $stage['stage_name'],
            (int) $stage['position'],
            (int) $stage['target_qty'],
        ]);

        return (int) $this->db->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $fields hanya kolom dikenal yang di-update
     */
    public function updateStage(int $id, array $fields): void
    {
        $allowed = ['status', 'target_qty', 'realized_qty', 'assigned_to', 'started_at', 'finished_at', 'notes'];
        $sets = [];
        $bind = [];

        foreach ($allowed as $column) {
            if (array_key_exists($column, $fields)) {
                $sets[] = "`{$column}` = ?";
                $bind[] = $fields[$column];
            }
        }

        if ($sets === []) {
            return;
        }

        $bind[] = $id;
        $stmt = $this->db->pdo()->prepare('UPDATE production_order_stages SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($bind);
    }

    // ------------------------------------------------------------------

    /**
     * Pengguna yang dapat ditugaskan sebagai penanggung jawab produksi.
     *
     * @return list<array{id:int, name:string, role:string}>
     */
    public function assignableUsers(): array
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT id, name, role FROM users
             WHERE is_active = 1 AND role IN ('admin','manager','operator')
             ORDER BY role, name"
        );
        $stmt->execute();

        return array_map(
            static fn(array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'role' => (string) $r['role']],
            $stmt->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /**
     * Perintah produksi yang dapat diperiksa QC (sedang berjalan / selesai).
     * Draft-queued & cancelled tidak ikut — belum ada hasil untuk diperiksa.
     *
     * @return list<array{id:int, document_number:string, customer_name:string, target_quantity:int}>
     */
    public function selectableForQc(): array
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT p.id, p.document_number, c.name AS customer_name, p.target_quantity
             FROM production_orders p
             JOIN sales_orders so ON so.id = p.sales_order_id
             JOIN customers c ON c.id = so.customer_id
             WHERE p.status IN ('running','completed')
             ORDER BY p.id DESC"
        );
        $stmt->execute();

        return array_map(
            static fn(array $r): array => [
                'id' => (int) $r['id'],
                'document_number' => (string) $r['document_number'],
                'customer_name' => (string) $r['customer_name'],
                'target_quantity' => (int) $r['target_quantity'],
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    // ------------------------------------------------------------------

    private function nullable(array $data, string $key): ?string
    {
        return isset($data[$key]) && $data[$key] !== '' ? (string) $data[$key] : null;
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildFilter(string $search, string $status, int $assignedTo): array
    {
        $conditions = [];
        $bind = [];

        if ($status !== 'semua' && $status !== '') {
            $conditions[] = 'p.status = ?';
            $bind[] = $status;
        }

        if ($assignedTo > 0) {
            $conditions[] = 'p.assigned_to = ?';
            $bind[] = $assignedTo;
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
}
