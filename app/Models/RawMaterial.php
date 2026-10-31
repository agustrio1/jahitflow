<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\StockLedger;

/**
 * Bahan baku / item inventaris (§8.F). Data holder; current_stock adalah cache
 * saldo yang dikelola StockService lewat ledger (bukan diubah langsung).
 */
final class RawMaterial
{
    public const TYPE_UTAMA = 'bahan_utama';
    public const TYPE_PENOLONG = 'bahan_penolong';
    public const TYPE_PACKAGING = 'packaging';

    public const TYPES = [self::TYPE_UTAMA, self::TYPE_PENOLONG, self::TYPE_PACKAGING];

    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
        public readonly string $type,
        public readonly string $unit,
        public readonly string $currentStock,
        public readonly string $minStock,
        public readonly bool $isActive,
        public readonly ?string $notes,
        public readonly string $createdAt = '',
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            code: (string) $row['code'],
            name: (string) $row['name'],
            type: (string) $row['type'],
            unit: (string) $row['unit'],
            currentStock: (string) ($row['current_stock'] ?? '0'),
            minStock: (string) ($row['min_stock'] ?? '0'),
            isActive: (bool) ($row['is_active'] ?? true),
            notes: $row['notes'] ?? null,
            createdAt: (string) ($row['created_at'] ?? ''),
        );
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_PENOLONG => 'Bahan Penolong',
            self::TYPE_PACKAGING => 'Kemasan',
            default => 'Bahan Utama',
        };
    }

    public function isLowStock(): bool
    {
        return StockLedger::isLowStock($this->currentStock, $this->minStock);
    }

    /** Stok + satuan, dipangkas desimal berlebih untuk tampilan. */
    public function stockWithUnit(): string
    {
        return self::formatQty($this->currentStock) . ' ' . $this->unit;
    }

    /** 12.500 → "12,5"; 8.000 → "8". Tampilan saja (pisahkan desimal koma). */
    public static function formatQty(string $value): string
    {
        $rounded = round((float) $value, 3);
        $formatted = number_format($rounded, 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}
