<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Models\ProductionOrder;
use App\Repositories\AuditRepository;
use App\Repositories\ProductionOrderRepository;
use App\Repositories\ProductionStageRepository;
use App\Repositories\SalesOrderRepository;
use App\Repositories\SequenceRepository;
use App\Support\ProductionStageMachine;
use App\Validators\ProductionOrderValidator;
use Throwable;

/**
 * Logika bisnis perintah produksi (§8.E + §9).
 *  - Dibuat HANYA dari pesanan yang sudah dikonfirmasi DAN memenuhi gerbang
 *    DP (produksi tidak mulai sebelum persetujuan terpenuhi — §9).
 *  - Nomor via sequensi 'PP' (FOR UPDATE) dalam transaksi.
 *  - Definisi tahap di-SNAPSHOT ke baris pesanan saat pembuatan.
 *  - Transisi tahap dijaga ProductionStageMachine (pipeline berurutan).
 *  - Status perintah diturunkan dari gabungan status tahap; tiap perubahan
 *    penting diaudit.
 */
final class ProductionService
{
    public function __construct(
        private readonly Database $db,
        private readonly ProductionOrderRepository $orders,
        private readonly ProductionStageRepository $stages,
        private readonly SalesOrderRepository $salesOrders,
        private readonly SequenceRepository $sequences,
        private readonly AuditRepository $audit,
        private readonly ProductionOrderValidator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id: int|null, errors: array<string, list<string>>}
     */
    public function createFromSalesOrder(int $salesOrderId, array $data, ?int $userId): array
    {
        $data['sales_order_id'] = $salesOrderId;
        $errors = $this->validator->validateCreate($data);

        $so = $this->salesOrders->find($salesOrderId);
        if ($so === null) {
            throw new HttpException(404, 'Pesanan sumber tidak ditemukan.');
        }

        if ($so->status !== \App\Models\SalesOrder::STATUS_CONFIRMED) {
            $errors['sales_order'][] = 'Hanya pesanan yang sudah dikonfirmasi dapat dibuatkan perintah produksi.';
        } elseif (!$so->meetsDpGate()) {
            $errors['dp'][] = 'Uang muka belum memenuhi 30% total — produksi belum boleh dibuka.';
        }

        if ($this->orders->findBySalesOrder($salesOrderId) !== null) {
            $errors['existing'][] = 'Perintah produksi untuk pesanan ini sudah dibuat.';
        }

        $stageDefs = $this->stages->allActive();
        if ($stageDefs === []) {
            $errors['stages'][] = 'Belum ada tahap produksi terdefinisi. Konfigurasikan tahap lebih dulu.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'id' => null, 'errors' => $errors];
        }

        $targetQuantity = array_sum(array_map(static fn($i): int => $i->quantity, $so->items));
        $targetDate = trim((string) ($data['target_date'] ?? '')) !== '' ? trim((string) $data['target_date']) : $so->dueDate;

        $id = $this->db->transaction(function () use ($salesOrderId, $data, $userId, $stageDefs, $targetQuantity, $targetDate): int {
            $number = $this->sequences->nextDocumentNumber('PP');
            $orderId = $this->orders->create([
                'document_number' => $number,
                'sales_order_id' => $salesOrderId,
                'status' => ProductionOrder::STATUS_QUEUED,
                'target_quantity' => $targetQuantity,
                'target_date' => $targetDate,
                'assigned_to' => (int) ($data['assigned_to'] ?? 0),
                'description' => trim((string) ($data['description'] ?? '')),
                'created_by' => $userId,
            ]);

            foreach ($stageDefs as $stage) {
                $this->orders->insertStage($orderId, [
                    'stage_id' => $stage->id,
                    'stage_name' => $stage->name,
                    'position' => $stage->position,
                    'target_qty' => $targetQuantity,
                ]);
            }

            $this->audit->log(
                $userId,
                'production.created',
                'production_order',
                $orderId,
                'Perintah produksi dibuat: ' . $number . ' dari pesanan ' . $salesOrderId,
                ['sales_order_id' => $salesOrderId, 'target_quantity' => $targetQuantity, 'stage_count' => count($stageDefs)],
            );

            return $orderId;
        });

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    /**
     * @param 'start'|'complete'|'reopen'|'update' $action
     * @param array<string, mixed> $data
     * @return array{ok: bool, errors: array<string, list<string>>}
     */
    public function updateStage(int $stageRowId, string $action, array $data, ?int $userId): array
    {
        $stage = $this->orders->findStage($stageRowId);
        if ($stage === null) {
            throw new HttpException(404, 'Tahap produksi tidak ditemukan.');
        }

        $order = $this->orders->find($stage->productionOrderId);
        if ($order === null) {
            throw new HttpException(404, 'Perintah produksi tidak ditemukan.');
        }

        if ($order->status === ProductionOrder::STATUS_CANCELLED) {
            return ['ok' => false, 'errors' => ['status' => ['Perintah produksi dibatalkan — progres tidak dapat diubah.']]];
        }

        $errors = $this->validator->validateStageInput($data);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        // Cari indeks tahap ini dalam urutan posisi (dipakai mesin transisi).
        $statuses = [];
        $index = null;
        foreach ($order->stages as $i => $row) {
            $statuses[] = $row->status;
            if ($row->id === $stage->id) {
                $index = $i;
            }
        }

        if ($index === null) {
            throw new HttpException(404, 'Tahap tidak cocok dengan perintah produksinya.');
        }

        // Batas realisasi terhadap target (target bisa ikut diubah pada request).
        $target = (int) ($data['target_qty'] ?? $stage->targetQty);
        $realized = (int) ($data['realized_qty'] ?? $stage->realizedQty);
        if ($realized > $target) {
            $errors['realized_qty'][] = 'Realisasi tidak boleh melebihi target tahap.';
            return ['ok' => false, 'errors' => $errors];
        }

        $fields = $this->stageFieldsFor($action, $stage, $statuses, $index, $data, $errors);
        if ($fields === null) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->db->transaction(function () use ($stage, $order, $fields, $action, $userId): void {
            $this->orders->updateStage($stage->id, $fields);

            // Turunkan status perintah produksi dari gabungan tahap terbaru.
            $this->syncOrderStatus($order, $stage->id, $fields['status'] ?? $stage->status, $userId);

            $this->audit->log(
                $userId,
                'production.stage.' . $action,
                'production_order',
                $order->id,
                "Tahap \"{$stage->stageName}\" ({$order->documentNumber}) → " . $this->actionLabel($action, $fields),
                [
                    'stage_id' => $stage->id,
                    'old_status' => $stage->status,
                    'new_status' => $fields['status'] ?? $stage->status,
                    'realized_qty' => $fields['realized_qty'] ?? $stage->realizedQty,
                ],
            );
        });

        return ['ok' => true, 'errors' => []];
    }

    public function cancel(int $id, ?int $userId): void
    {
        $order = $this->findOrFail($id);
        if (in_array($order->status, [ProductionOrder::STATUS_COMPLETED, ProductionOrder::STATUS_CANCELLED], true)) {
            throw new HttpException(422, 'Perintah produksi ' . $order->statusLabel() . ' tidak dapat dibatalkan.');
        }

        $this->db->transaction(function () use ($id, $order, $userId): void {
            $this->orders->updateStatus($id, ProductionOrder::STATUS_CANCELLED);
            $this->audit->log(
                $userId,
                'production.cancelled',
                'production_order',
                $id,
                'Perintah produksi dibatalkan: ' . $order->documentNumber,
                ['old_status' => $order->status, 'new_status' => ProductionOrder::STATUS_CANCELLED],
            );
        });
    }

    public function findOrFail(int $id): ProductionOrder
    {
        $order = $this->orders->find($id);
        if ($order === null) {
            throw new HttpException(404, 'Perintah produksi tidak ditemukan.');
        }

        return $order;
    }

    // ------------------------------------------------------------------

    /**
     * Bangun kolom tahap hasil aksi, atau null + isi $errors bila transisi
     * ditolak mesin. Mengembalikan array fields siap updateStage.
     *
     * @param list<string> $statuses
     * @param array<string, mixed> $data
     * @param array<string, list<string>> $errors diisi by-ref saat ditolak
     * @return array<string, mixed>|null
     */
    private function stageFieldsFor(string $action, \App\Models\ProductionOrderStage $stage, array $statuses, int $index, array $data, array &$errors): ?array
    {
        $now = date('Y-m-d H:i:s');
        $fields = [];

        // Kolom opsional yang selalu boleh disimpan bersama aksi apa pun.
        foreach (['target_qty', 'realized_qty', 'notes'] as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== '') {
                $fields[$col] = $col === 'notes' ? trim((string) $data[$col]) : (int) $data[$col];
            }
        }
        if (array_key_exists('assigned_to', $data) && (int) $data['assigned_to'] > 0) {
            $fields['assigned_to'] = (int) $data['assigned_to'];
        }

        $reason = match ($action) {
            'start' => ProductionStageMachine::startReason($statuses, $index),
            'complete' => ProductionStageMachine::completeReason($statuses, $index),
            'reopen' => ProductionStageMachine::reopenReason($statuses, $index),
            'update' => null,
            default => 'Aksi tahap tidak dikenal.',
        };

        if ($reason !== null) {
            $errors['status'][] = $reason;
            return null;
        }

        if ($action === 'start') {
            $fields['status'] = ProductionStageMachine::IN_PROGRESS;
            $fields['started_at'] = $now;
        } elseif ($action === 'complete') {
            $fields['status'] = ProductionStageMachine::DONE;
            $fields['finished_at'] = $now;
        } elseif ($action === 'reopen') {
            $fields['status'] = ProductionStageMachine::PENDING;
            $fields['started_at'] = null;
            $fields['finished_at'] = null;
        }

        return $fields;
    }

    /**
     * Update status perintah produksi dari gabungan tahap terkini.
     */
    private function syncOrderStatus(ProductionOrder $order, int $changedStageId, string $newStageStatus, ?int $userId): void
    {
        $statuses = [];
        foreach ($order->stages as $row) {
            $statuses[] = $row->id === $changedStageId ? $newStageStatus : $row->status;
        }

        $derived = ProductionStageMachine::deriveOrderStatus($statuses);
        if ($derived !== $order->status) {
            $this->orders->updateStatus($order->id, $derived);
            $this->audit->log(
                $userId,
                'production.status.' . $derived,
                'production_order',
                $order->id,
                "Status produksi {$order->documentNumber}: {$order->statusLabel()} → " . $this->orderStatusLabel($derived),
                ['old_status' => $order->status, 'new_status' => $derived],
            );
        }
    }

    private function actionLabel(string $action, array $fields): string
    {
        if ($action === 'update') {
            return 'progres diperbarui';
        }

        return match ($fields['status'] ?? '') {
            ProductionStageMachine::IN_PROGRESS => 'dimulai',
            ProductionStageMachine::DONE => 'selesai',
            ProductionStageMachine::PENDING => 'dibuka kembali',
            default => $action,
        };
    }

    private function orderStatusLabel(string $status): string
    {
        return match ($status) {
            ProductionOrder::STATUS_RUNNING => 'Berjalan',
            ProductionOrder::STATUS_COMPLETED => 'Selesai',
            ProductionOrder::STATUS_QUEUED => 'Menunggu Produksi',
            default => $status,
        };
    }
}
