<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Pencatatan audit trail (§9 & §12 DOC). Tulis-saja: tidak ada update/delete.
 * Dipakai service layer setiap perubahan data bisnis penting.
 */
final class AuditRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed>|null $meta */
    public function log(
        ?int $userId,
        string $action,
        string $entity,
        ?int $entityId,
        string $description,
        ?array $meta = null,
    ): void {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO audit_logs (user_id, action, entity, entity_id, description, meta)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $userId,
            $action,
            $entity,
            $entityId,
            mb_substr($description, 0, 255),
            $meta === null ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** Riwayat sebuah entity, terbaru lebih dulu — untuk panel "Riwayat". */
    public function forEntity(string $entity, int $entityId, int $limit = 25): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT a.*, u.name AS user_name
             FROM audit_logs a
             LEFT JOIN `users` u ON u.id = a.user_id
             WHERE a.entity = ? AND a.entity_id = ?
             ORDER BY a.id DESC
             LIMIT ' . max(1, min(100, $limit))
        );
        $stmt->execute([$entity, $entityId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
