<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Models\SalesOrder;
use App\Repositories\AuditRepository;
use App\Repositories\SalesOrderRepository;
use App\Repositories\SequenceRepository;
use App\Support\OrderTotals;
use App\Support\OrderTotalsException;
use App\Validators\SalesOrderValidator;
use Throwable;

/**
 * Logika bisnis sales order (§8.D + §9 DOC). Tanggung jawab inti:
 *  - Nomor dokumen via sequensi FOR UPDATE (BUKAN MAX(id)+1), dalam transaksi.
 *  - Total SELALU dihitung ulang dari harga varian di DATABASE lewat
 *    OrderTotals — harga/total kiriman browser dibuang (§8.D).
 *  - Transisi status eksplisit + audit trail setiap perubahan penting.
 * Semua write = satu transaksi (nomor + pesanan + item + audit).
 */
final class SalesOrderService
{
    public function __construct(
        private readonly Database $db,
        private readonly SalesOrderRepository $orders,
        private readonly SequenceRepository $sequences,
        private readonly AuditRepository $audit,
        private readonly SalesOrderValidator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id: int|null, errors: array<string, list<string>>}
     */
    public function create(array $data, ?int $userId): array
    {
        [$payload, $errors] = $this->prepare($data);
        if ($errors !== []) {
            return ['ok' => false, 'id' => null, 'errors' => $errors];
        }

        $id = $this->db->transaction(function () use ($payload, $userId): int {
            $number = $this->sequences->nextDocumentNumber('SO');
            $orderId = $this->orders->create([
                ...$payload['order'],
                'document_number' => $number,
                'status' => SalesOrder::STATUS_DRAFT,
                'created_by' => $userId,
            ]);

            foreach ($payload['items'] as $item) {
                $this->orders->insertItem($orderId, $item);
            }

            $this->audit->log(
                $userId,
                'salesorder.created',
                'sales_order',
                $orderId,
                'Pesanan dibuat: ' . $number,
                ['grand_total' => $payload['order']['grand_total'], 'item_count' => count($payload['items'])],
            );

            return $orderId;
        });

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, errors: array<string, list<string>>}
     */
    public function update(int $id, array $data, ?int $userId): array
    {
        $order = $this->findOrFail($id);

        if (!$order->isEditable()) {
            return ['ok' => false, 'errors' => ['status' => ['Pesanan berstatus ' . $order->statusLabel() . ' tidak dapat diubah.']]];
        }

        [$payload, $errors] = $this->prepare($data);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->db->transaction(function () use ($id, $payload, $userId, $order): void {
            $this->orders->updateContent($id, $payload['order']);
            $this->orders->deleteItems($id);
            foreach ($payload['items'] as $item) {
                $this->orders->insertItem($id, $item);
            }

            $this->audit->log(
                $userId,
                'salesorder.updated',
                'sales_order',
                $id,
                'Pesanan diperbarui: ' . $order->documentNumber,
                ['grand_total' => $payload['order']['grand_total'], 'item_count' => count($payload['items'])],
            );
        });

        return ['ok' => true, 'errors' => []];
    }

    /** draft → offered (penawaran dikirim ke pelanggan). */
    public function markOffered(int $id, ?int $userId): void
    {
        $order = $this->findOrFail($id);
        $this->assertTransition($order, [SalesOrder::STATUS_DRAFT], SalesOrder::STATUS_OFFERED);
        $this->transition($id, SalesOrder::STATUS_OFFERED, 'salesorder.offered', $order, $userId);
    }

    /**
     * draft/offered → confirmed. Konfirmasi = persetujuan; wajib ada item
     * dan total > 0 (§9: hanya dikonfirmasi setelah data wajib lengkap).
     *
     * @return array{ok: bool, errors: array<string, list<string>>}
     */
    public function confirm(int $id, ?int $userId): array
    {
        $order = $this->findOrFail($id);

        try {
            $this->assertTransition($order, [SalesOrder::STATUS_DRAFT, SalesOrder::STATUS_OFFERED], SalesOrder::STATUS_CONFIRMED);
        } catch (HttpException $e) {
            return ['ok' => false, 'errors' => ['status' => [$e->getMessage()]]];
        }

        if ($order->items === [] || $order->grandTotal <= 0) {
            return ['ok' => false, 'errors' => ['items' => ['Pesanan harus memiliki minimal satu item dengan total lebih dari nol sebelum dikonfirmasi.']]];
        }

        $this->transition($id, SalesOrder::STATUS_CONFIRMED, 'salesorder.confirmed', $order, $userId);

        return ['ok' => true, 'errors' => []];
    }

    /** draft/offered → cancelled. */
    public function cancel(int $id, ?int $userId): void
    {
        $order = $this->findOrFail($id);
        $this->assertTransition($order, [SalesOrder::STATUS_DRAFT, SalesOrder::STATUS_OFFERED], SalesOrder::STATUS_CANCELLED);
        $this->transition($id, SalesOrder::STATUS_CANCELLED, 'salesorder.cancelled', $order, $userId);
    }

    /** Hanya draft yang boleh dihapus (cascade ke item). */
    public function delete(int $id, ?int $userId): void
    {
        $order = $this->findOrFail($id);
        if ($order->status !== SalesOrder::STATUS_DRAFT) {
            throw new HttpException(422, 'Hanya pesanan berstatus Draft yang dapat dihapus.');
        }

        $this->db->transaction(function () use ($id, $userId, $order): void {
            $this->orders->delete($id);
            $this->audit->log($userId, 'salesorder.deleted', 'sales_order', $id, 'Pesanan draft dihapus: ' . $order->documentNumber);
        });
    }

    public function findOrFail(int $id): SalesOrder
    {
        $order = $this->orders->find($id);
        if ($order === null) {
            throw new HttpException(404, 'Pesanan tidak ditemukan.');
        }

        return $order;
    }

    // ------------------------------------------------------------------

    /**
     * Validasi + hitung ulang total dari harga DB. Menghasilkan payload siap
     * simpan ATAU error per-field (termasuk pemetaan OrderTotalsException).
     *
     * @param array<string, mixed> $data
     * @return array{0: array<string, mixed>, 1: array<string, list<string>>}
     *         [payload siap simpan (kosong bila error), error per-field]
     */
    private function prepare(array $data): array
    {
        $errors = $this->validator->validate($data);
        if ($errors !== []) {
            return [[], $errors];
        }

        // Bangun baris dari harga varian DI DATABASE — abaikan harga kiriman browser.
        $lines = [];
        $itemPayload = [];
        foreach (array_values($data['items']) as $index => $rawItem) {
            $variantId = (int) $rawItem['variant_id'];
            $variant = $this->orders->variantPrice($variantId);

            if ($variant === null) {
                $errors['items'][] = 'Item ke-' . ($index + 1) . ': varian tidak ditemukan.';
                continue;
            }
            if (!$variant['is_active']) {
                $errors['items'][] = 'Item ke-' . ($index + 1) . ': varian sedang nonaktif dan tidak dapat dipesan.';
                continue;
            }

            $quantity = (int) $rawItem['quantity'];
            $unitPrice = $variant['unit_price'];
            $lineTotal = bcmul((string) $quantity, $unitPrice, 2);

            $lines[] = ['quantity' => $quantity, 'unit_price' => $unitPrice];
            $itemPayload[] = [
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
        }

        if ($errors !== []) {
            return [[], $errors];
        }

        try {
            $totals = OrderTotals::compute(
                $lines,
                $data['discount_amount'] ?? 0,
                $data['additional_cost'] ?? 0,
                $data['dp_amount'] ?? 0,
            );
        } catch (OrderTotalsException $e) {
            return [[], [$e->field => [$e->getMessage()]]];
        }

        $order = [
            'customer_id' => (int) $data['customer_id'],
            'order_date' => trim((string) $data['order_date']),
            'due_date' => trim((string) $data['due_date']),
            'subtotal' => $totals->subtotal,
            'discount_amount' => $totals->discount,
            'additional_cost' => $totals->additionalCost,
            'grand_total' => $totals->grandTotal,
            'dp_amount' => $totals->dpAmount,
            'design_notes' => trim((string) ($data['design_notes'] ?? '')),
        ];

        return [['order' => $order, 'items' => $itemPayload], []];
    }

    /**
     * @param list<string> $froms
     */
    private function assertTransition(SalesOrder $order, array $froms, string $to): void
    {
        if (!in_array($order->status, $froms, true)) {
            throw new HttpException(
                422,
                "Status pesanan tidak dapat berubah dari {$order->statusLabel()} ke " . $this->labelFor($to) . '.',
            );
        }
    }

    private function transition(int $id, string $status, string $action, SalesOrder $order, ?int $userId): void
    {
        $this->db->transaction(function () use ($id, $status, $action, $order, $userId): void {
            $this->orders->updateStatus($id, $status);
            $this->audit->log(
                $userId,
                $action,
                'sales_order',
                $id,
                "Status pesanan {$order->documentNumber}: {$order->statusLabel()} → " . $this->labelFor($status),
                ['old_status' => $order->status, 'new_status' => $status],
            );
        });
    }

    private function labelFor(string $status): string
    {
        return SalesOrder::labelFor($status);
    }
}
