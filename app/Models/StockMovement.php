<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\StockLedger;

/**
 * Satu baris ledger stok (§8.F). Menyimpan saldo sebelum/sesudah sehingga
 * riwayat dapat direkonstruksi & diaudit. Kolom join (materialName/materialUnit/
 * createdByName) diisi repository saat membaca.
 */
final class StockMovement
{
    public function __construct(
        public readonly int $id,
        public readonly int $rawMaterialId,
        public readonly string $direction,
        public readonly string $quantity,
        public readonly string $balanceBefore,
        public readonly string $balanceAfter,
        public readonly string $reason,
        public readonly ?string $referenceType,
        public readonly ?int $referenceId,
        public readonly ?int $createdBy,
        public readonly string $createdAt = '',
        public readonly ?string $materialName = null,
        public readonly ?string $materialUnit = null,
        public readonly ?string $createdByName = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            rawMaterialId: (int) $row['raw_material_id'],
            direction: (string) $row['direction'],
            quantity: (string) $row['quantity'],
            balanceBefore: (string) $row['balance_before'],
            balanceAfter: (string) $row['balance_after'],
            reason: (string) $row['reason'],
            referenceType: $row['reference_type'] ?? null,
            referenceId: isset($row['reference_id']) ? (int) $row['reference_id'] : null,
            createdBy: isset($row['created_by']) ? (int) $row['created_by'] : null,
            createdAt: (string) ($row['created_at'] ?? ''),
            materialName: $row['material_name'] ?? null,
            materialUnit: $row['material_unit'] ?? null,
            createdByName: $row['created_by_name'] ?? null,
        );
    }

    public function directionLabel(): string
    {
        return match ($this->direction) {
            StockLedger::IN => 'Penerimaan',
            StockLedger::OUT => 'Pemakaian',
            StockLedger::ADJUSTMENT => 'Penyesuaian',
            default => $this->direction,
        };
    }

    public function directionBadge(): string
    {
        return match ($this->direction) {
            StockLedger::IN => 'jf-badge--success',
            StockLedger::OUT => 'jf-badge--warning',
            StockLedger::ADJUSTMENT => 'jf-badge--info',
            default => 'jf-badge--info',
        };
    }

    /** Delta bertanda untuk tampilan: +12,5 / −8 / −2,5 (penyesuaian negatif). */
    public function signedDelta(): string
    {
        $magnitude = RawMaterial::formatQty($this->signedRaw());

        return str_starts_with($magnitude, '-') ? '−' . substr($magnitude, 1) : '+' . $magnitude;
    }

    private function signedRaw(): string
    {
        return match ($this->direction) {
            StockLedger::IN => $this->quantity,
            StockLedger::OUT => '-' . ltrim($this->quantity, '+'),
            default => $this->quantity, // adjustment sudah tersimpan bertanda
        };
    }
}
