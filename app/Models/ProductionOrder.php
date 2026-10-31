<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Perintah produksi (§8.E). Status diturunkan dari gabungan status tahap
 * (lihat ProductionStageMachine), kecuali 'cancelled' yang eksplisit.
 */
final class ProductionOrder
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /** @param list<ProductionOrderStage> $stages */
    public function __construct(
        public readonly int $id,
        public readonly string $documentNumber,
        public readonly int $salesOrderId,
        public readonly string $status,
        public readonly int $targetQuantity,
        public readonly ?string $targetDate,
        public readonly ?int $assignedTo,
        public readonly ?string $description,
        public readonly ?string $notes,
        public readonly ?int $createdBy,
        public readonly string $createdAt = '',
        public readonly ?string $salesOrderNumber = null,
        public readonly ?string $customerName = null,
        public readonly ?string $assignedUserName = null,
        public readonly array $stages = [],
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            documentNumber: (string) $row['document_number'],
            salesOrderId: (int) $row['sales_order_id'],
            status: (string) $row['status'],
            targetQuantity: (int) ($row['target_quantity'] ?? 0),
            targetDate: $row['target_date'] ?? null,
            assignedTo: isset($row['assigned_to']) ? (int) $row['assigned_to'] : null,
            description: $row['description'] ?? null,
            notes: $row['notes'] ?? null,
            createdBy: isset($row['created_by']) ? (int) $row['created_by'] : null,
            createdAt: (string) ($row['created_at'] ?? ''),
            salesOrderNumber: $row['sales_order_number'] ?? null,
            customerName: $row['customer_name'] ?? null,
            assignedUserName: $row['assigned_user_name'] ?? null,
        );
    }

    /** @param list<ProductionOrderStage> $stages */
    public function withStages(array $stages): self
    {
        return new self(
            $this->id, $this->documentNumber, $this->salesOrderId, $this->status,
            $this->targetQuantity, $this->targetDate, $this->assignedTo, $this->description,
            $this->notes, $this->createdBy, $this->createdAt, $this->salesOrderNumber,
            $this->customerName, $this->assignedUserName, $stages,
        );
    }

    /** Progres = jumlah tahap selesai / total tahap (0–100). */
    public function progressPercent(): float
    {
        if ($this->stages === []) {
            return 0.0;
        }

        $done = count(array_filter($this->stages, static fn(ProductionOrderStage $s): bool => $s->isDone()));

        return round($done / count($this->stages) * 100, 1);
    }

    public function isOverdue(): bool
    {
        return $this->targetDate !== null
            && $this->status !== self::STATUS_COMPLETED
            && $this->status !== self::STATUS_CANCELLED
            && $this->targetDate < date('Y-m-d');
    }

    public function statusLabel(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    /** Label statis untuk agregat laporan (baris hasil GROUP BY status). */
    public static function labelFor(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }

    public function statusBadge(): string
    {
        return self::BADGE[$this->status] ?? 'jf-badge--info';
    }

    private const LABELS = [
        self::STATUS_QUEUED => 'Menunggu Produksi',
        self::STATUS_RUNNING => 'Berjalan',
        self::STATUS_COMPLETED => 'Selesai',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    private const BADGE = [
        self::STATUS_QUEUED => 'jf-badge--info',
        self::STATUS_RUNNING => 'jf-badge--warning',
        self::STATUS_COMPLETED => 'jf-badge--success',
        self::STATUS_CANCELLED => 'jf-badge--danger',
    ];
}
