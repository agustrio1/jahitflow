<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Satu pemeriksaan Quality Control (§8.G). Data-holder murni; angka sudah
 * dibulatkan & dibatasi di lapisan layanan. Kolom join (productionOrderNumber/
 * customerName/inspectorName) diisi repository saat membaca.
 */
final class QcInspection
{
    public const RESULT_LOLOS = 'lolos';
    public const RESULT_PERBAIKAN = 'perbaikan';
    public const RESULT_GAGAL = 'gagal';

    /** Daftar hasil valid untuk validasi input. */
    public const RESULTS = [self::RESULT_LOLOS, self::RESULT_PERBAIKAN, self::RESULT_GAGAL];

    public function __construct(
        public readonly int $id,
        public readonly int $productionOrderId,
        public readonly int $inspectedQty,
        public readonly int $passedQty,
        public readonly int $failedQty,
        public readonly string $result,
        public readonly ?string $defectNote,
        public readonly ?string $reworkAction,
        public readonly ?int $inspectedBy,
        public readonly string $inspectedAt = '',
        public readonly string $createdAt = '',
        public readonly ?string $productionOrderNumber = null,
        public readonly ?string $salesOrderNumber = null,
        public readonly ?string $customerName = null,
        public readonly ?string $inspectorName = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            productionOrderId: (int) $row['production_order_id'],
            inspectedQty: (int) $row['inspected_qty'],
            passedQty: (int) $row['passed_qty'],
            failedQty: (int) $row['failed_qty'],
            result: (string) $row['result'],
            defectNote: $row['defect_note'] ?? null,
            reworkAction: $row['rework_action'] ?? null,
            inspectedBy: isset($row['inspected_by']) ? (int) $row['inspected_by'] : null,
            inspectedAt: (string) ($row['inspected_at'] ?? ''),
            createdAt: (string) ($row['created_at'] ?? ''),
            productionOrderNumber: $row['production_order_number'] ?? null,
            salesOrderNumber: $row['sales_order_number'] ?? null,
            customerName: $row['customer_name'] ?? null,
            inspectorName: $row['inspector_name'] ?? null,
        );
    }

    public function resultLabel(): string
    {
        return self::RESULT_LABELS[$this->result] ?? $this->result;
    }

    public function resultBadge(): string
    {
        return self::RESULT_BADGE[$this->result] ?? 'jf-badge--info';
    }

    public static function labelFor(string $result): string
    {
        return self::RESULT_LABELS[$result] ?? $result;
    }

    public function isPassed(): bool
    {
        return $this->result === self::RESULT_LOLOS;
    }

    /** Persentase lolos terhadap jumlah diperiksa (0–100). */
    public function passRate(): float
    {
        return $this->inspectedQty > 0
            ? round($this->passedQty / $this->inspectedQty * 100, 1)
            : 0.0;
    }

    /** Sisa barang diperiksa yang belum diputuskan (diperiksa − lolos − gagal). */
    public function undecidedQty(): int
    {
        return max(0, $this->inspectedQty - $this->passedQty - $this->failedQty);
    }

    private const RESULT_LABELS = [
        self::RESULT_LOLOS => 'Lolos',
        self::RESULT_PERBAIKAN => 'Perlu Perbaikan',
        self::RESULT_GAGAL => 'Gagal',
    ];

    private const RESULT_BADGE = [
        self::RESULT_LOLOS => 'jf-badge--success',
        self::RESULT_PERBAIKAN => 'jf-badge--warning',
        self::RESULT_GAGAL => 'jf-badge--danger',
    ];
}
