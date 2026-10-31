<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Item pesanan (§8.D). unit_price & line_total adalah snapshot server-side;
 * nama produk/ varian ikut tersimpan saat query (untuk tampilan) namun boleh
 * kosong bila objek dipakai tanpa join.
 */
final class SalesOrderItem
{
    public function __construct(
        public readonly int $id,
        public readonly int $salesOrderId,
        public readonly int $productVariantId,
        public readonly int $quantity,
        public readonly float $unitPrice,
        public readonly float $lineTotal,
        public readonly string $productName = '',
        public readonly string $variantColor = '',
        public readonly string $variantSize = '',
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            salesOrderId: (int) $row['sales_order_id'],
            productVariantId: (int) $row['product_variant_id'],
            quantity: (int) $row['quantity'],
            unitPrice: (float) $row['unit_price'],
            lineTotal: (float) $row['line_total'],
            productName: (string) ($row['product_name'] ?? ''),
            variantColor: (string) ($row['variant_color'] ?? ''),
            variantSize: (string) ($row['variant_size'] ?? ''),
        );
    }

    public function variantLabel(): string
    {
        return trim("{$this->variantColor} / {$this->variantSize}", ' /');
    }
}
