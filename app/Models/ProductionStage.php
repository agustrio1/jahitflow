<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Definisi tahap produksi (§8.E). Data-driven agar tahapan dapat
 * disesuaikan tanpa mengubah kode.
 */
final class ProductionStage
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $position,
        public readonly bool $isActive,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: (string) $row['name'],
            position: (int) $row['position'],
            isActive: (bool) ($row['is_active'] ?? true),
        );
    }
}
