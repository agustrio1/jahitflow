<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Sales order (§8.D). Entity data-holder; total sudah dihitung server saat
 * disimpan. Helper turunan (sisa, % DP, kelayakan edit) murni membaca
 * kolom — tanpa query.
 */
final class SalesOrder
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_OFFERED = 'offered';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    /** Ambang minimum uang muka (rasio terhadap total) untuk gerbang produksi. */
    public const DP_MIN_RATIO = 0.30;

    /** Daftar status valid untuk validasi input. */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_OFFERED,
        self::STATUS_CONFIRMED,
        self::STATUS_CANCELLED,
    ];

    /** @param list<SalesOrderItem> $items */
    public function __construct(
        public readonly int $id,
        public readonly string $documentNumber,
        public readonly int $customerId,
        public readonly string $orderDate,
        public readonly string $dueDate,
        public readonly string $status,
        public readonly float $subtotal,
        public readonly float $discountAmount,
        public readonly float $additionalCost,
        public readonly float $grandTotal,
        public readonly float $dpAmount,
        public readonly ?string $designNotes,
        public readonly ?int $createdBy,
        public readonly string $createdAt = '',
        public readonly ?string $customerName = null,
        public readonly array $items = [],
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            documentNumber: (string) $row['document_number'],
            customerId: (int) $row['customer_id'],
            orderDate: (string) $row['order_date'],
            dueDate: (string) $row['due_date'],
            status: (string) $row['status'],
            subtotal: (float) $row['subtotal'],
            discountAmount: (float) $row['discount_amount'],
            additionalCost: (float) $row['additional_cost'],
            grandTotal: (float) $row['grand_total'],
            dpAmount: (float) $row['dp_amount'],
            designNotes: $row['design_notes'] ?? null,
            createdBy: isset($row['created_by']) ? (int) $row['created_by'] : null,
            createdAt: (string) ($row['created_at'] ?? ''),
            customerName: $row['customer_name'] ?? null,
        );
    }

    /** @param list<SalesOrderItem> $items */
    public function withItems(array $items): self
    {
        return new self(
            $this->id, $this->documentNumber, $this->customerId, $this->orderDate,
            $this->dueDate, $this->status, $this->subtotal, $this->discountAmount,
            $this->additionalCost, $this->grandTotal, $this->dpAmount, $this->designNotes,
            $this->createdBy, $this->createdAt, $this->customerName, $items,
        );
    }

    public function remaining(): float
    {
        return round($this->grandTotal - $this->dpAmount, 2);
    }

    public function dpPercent(): float
    {
        return $this->grandTotal > 0 ? round($this->dpAmount / $this->grandTotal * 100, 2) : 0.0;
    }

    /** Draft & penawaran masih dapat diubah isinya; confirmed/cancelled tidak. */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT || $this->status === self::STATUS_OFFERED;
    }

    public function isCancellable(): bool
    {
        return $this->isEditable();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGE[$this->status] ?? 'jf-badge--info';
    }

    /** Label Indonesia untuk sebuah kode status (dipakai service/audit). */
    public static function labelFor(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    /**
     * Gerbang DP untuk memulai produksi (§9): uang muka minimal 30% total.
     * Dipakai modul Production Order; ditampilkan di detail pesanan.
     */
    public function meetsDpGate(): bool
    {
        return $this->grandTotal > 0
            && $this->dpAmount + 0.005 >= round($this->grandTotal * self::DP_MIN_RATIO, 2);
    }

    /** Total kuantitas seluruh item. */
    public function totalQuantity(): int
    {
        return array_sum(array_map(static fn(SalesOrderItem $i): int => $i->quantity, $this->items));
    }

    private const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_OFFERED => 'Penawaran',
        self::STATUS_CONFIRMED => 'Dikonfirmasi',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    private const STATUS_BADGE = [
        self::STATUS_DRAFT => 'jf-badge--info',
        self::STATUS_OFFERED => 'jf-badge--warning',
        self::STATUS_CONFIRMED => 'jf-badge--success',
        self::STATUS_CANCELLED => 'jf-badge--danger',
    ];
}
