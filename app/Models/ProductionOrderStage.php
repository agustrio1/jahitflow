<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\ProductionStageMachine;

/**
 * Tahap pada sebuah perintah produksi — snapshot definisi + progres nyata
 * (§8.E): status, target vs realisasi, penanggung jawab, waktu mulai/selesai.
 */
final class ProductionOrderStage
{
    public function __construct(
        public readonly int $id,
        public readonly int $productionOrderId,
        public readonly int $stageId,
        public readonly string $stageName,
        public readonly int $position,
        public readonly string $status,
        public readonly int $targetQty,
        public readonly int $realizedQty,
        public readonly ?int $assignedTo,
        public readonly ?string $startedAt,
        public readonly ?string $finishedAt,
        public readonly ?string $notes,
        public readonly ?string $assignedUserName = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            productionOrderId: (int) $row['production_order_id'],
            stageId: (int) $row['stage_id'],
            stageName: (string) $row['stage_name'],
            position: (int) $row['position'],
            status: (string) $row['status'],
            targetQty: (int) ($row['target_qty'] ?? 0),
            realizedQty: (int) ($row['realized_qty'] ?? 0),
            assignedTo: isset($row['assigned_to']) ? (int) $row['assigned_to'] : null,
            startedAt: $row['started_at'] ?? null,
            finishedAt: $row['finished_at'] ?? null,
            notes: $row['notes'] ?? null,
            assignedUserName: $row['assigned_user_name'] ?? null,
        );
    }

    public function isDone(): bool
    {
        return $this->status === ProductionStageMachine::DONE;
    }

    public function statusLabel(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::BADGE[$this->status] ?? 'jf-badge--info';
    }

    /** Realisasi terhadap target (0–100), dibatasi agar tidak > 100. */
    public function progressPercent(): float
    {
        if ($this->targetQty <= 0) {
            return 0.0;
        }

        return min(100.0, round($this->realizedQty / $this->targetQty * 100, 1));
    }

    private const LABELS = [
        ProductionStageMachine::PENDING => 'Menunggu',
        ProductionStageMachine::IN_PROGRESS => 'Dikerjakan',
        ProductionStageMachine::DONE => 'Selesai',
    ];

    private const BADGE = [
        ProductionStageMachine::PENDING => 'jf-badge--info',
        ProductionStageMachine::IN_PROGRESS => 'jf-badge--warning',
        ProductionStageMachine::DONE => 'jf-badge--success',
    ];
}
