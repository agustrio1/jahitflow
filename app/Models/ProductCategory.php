<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Entity kategori produk (§8.C).
 */
final class ProductCategory
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $productCount = 0,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: (string) $row['name'],
            description: $row['description'] ?? null,
            productCount: (int) ($row['product_count'] ?? 0),
        );
    }
}
