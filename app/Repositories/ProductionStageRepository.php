<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\ProductionStage;
use PDO;

/**
 * Definisi tahap produksi (§8.E). Hanya baca untuk runtime; penyesuaian
 * tahapan dilakukan lewat data (migration/seeder/admin), bukan kode.
 */
final class ProductionStageRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<ProductionStage> */
    public function allActive(): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT * FROM production_stages WHERE is_active = 1 ORDER BY position ASC'
        );
        $stmt->execute();

        return array_map(static fn(array $r): ProductionStage => ProductionStage::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function countActive(): int
    {
        $stmt = $this->db->pdo()->prepare('SELECT COUNT(*) FROM production_stages WHERE is_active = 1');
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}
