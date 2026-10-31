<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\StockMovement;
use PDO;

/**
 * Ledger pergerakan stok (§8.F). Baris hanya DIINSERT (riwayat imutabel —
 * koreksi lewat penyesuaian baru, bukan edit/delete). Pembacaan menyertakan
 * nama pengguna pembuat untuk jejak audit.
 */
final class StockMovementRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Riwayat satu bahan, terbaru dulu.
     *
     * @return array{list: list<StockMovement>, total: int}
     */
    public function paginateForMaterial(int $materialId, int $offset = 0, int $limit = 20): array
    {
        $countStmt = $this->db->pdo()->prepare('SELECT COUNT(*) FROM stock_movements WHERE raw_material_id = ?');
        $countStmt->execute([$materialId]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->pdo()->prepare(
            'SELECT m.*, u.name AS created_by_name
             FROM stock_movements m
             LEFT JOIN `users` u ON u.id = m.created_by
             WHERE m.raw_material_id = ?
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute([$materialId]);

        return [
            'list' => array_map(static fn(array $r): StockMovement => StockMovement::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC)),
            'total' => $total,
        ];
    }

    /**
     * @param array<string, mixed> $m data hasil ledger (sudah tervalidasi service)
     */
    public function record(array $m): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO stock_movements
                (raw_material_id, direction, quantity, balance_before, balance_after,
                 reason, reference_type, reference_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $m['raw_material_id'],
            (string) $m['direction'],
            (string) $m['quantity'],
            (string) $m['balance_before'],
            (string) $m['balance_after'],
            (string) $m['reason'],
            isset($m['reference_type']) && $m['reference_type'] !== '' ? (string) $m['reference_type'] : null,
            isset($m['reference_id']) && (int) $m['reference_id'] > 0 ? (int) $m['reference_id'] : null,
            $m['created_by'] ?? null,
        ]);

        return (int) $this->db->pdo()->lastInsertId();
    }
}
