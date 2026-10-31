<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Entity varian produk: warna + ukuran + harga satuan (§8.C).
 * Harga SATU-SATUNYA sumber kebenaran untuk perhitungan sales order.
 */
final class ProductVariant
{
    public function __construct(
        public readonly int $id,
        public readonly int $productId,
        public readonly string $color,
        public readonly string $size,
        public readonly float $unitPrice,
        public readonly ?string $sku,
        public readonly bool $isActive,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            productId: (int) $row['product_id'],
            color: (string) $row['color'],
            size: (string) $row['size'],
            unitPrice: (float) $row['unit_price'],
            sku: $row['sku'] ?? null,
            isActive: (bool) ($row['is_active'] ?? true),
        );
    }

    public function label(): string
    {
        return trim("{$this->color} / {$this->size}");
    }
}
