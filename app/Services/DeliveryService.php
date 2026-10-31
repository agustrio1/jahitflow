<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Models\Delivery;
use App\Models\SalesOrder;
use App\Repositories\AuditRepository;
use App\Repositories\DeliveryRepository;
use App\Repositories\ProductionOrderRepository;
use App\Repositories\QcInspectionRepository;
use App\Repositories\SalesOrderRepository;
use App\Repositories\SequenceRepository;
use App\Support\DeliveryFlow;
use App\Support\IntegrityGateException;
use App\Validators\DeliveryValidator;

/**
 * Logika bisnis Pengiriman (§8.I). Satu batch = satu record dengan status yang
 * bertransisi eksplisit (DeliveryFlow). Penomoran lewat SequenceRepository ('KRM',
 * BUKAN MAX(id)+1) di dalam transaksi.
 *
 * Pagar §9 "tidak boleh dikirim melebihi jumlah siap & tersedia" dihitung otoritatif
 * di sini: batas = min(total kuantitas pesanan, total lolos QC) − sudah dikirim
 * (non-batal). Barang gagal QC tidak mengurangi "siap" karena hanya passed_qty QC
 * yang dihitung. Pembatalan membebalkan jatah (tidak dihitung sebagai terkirim).
 */
final class DeliveryService
{
    public const DOC_PREFIX = 'KRM';

    public function __construct(
        private readonly Database $db,
        private readonly DeliveryRepository $deliveries,
        private readonly SalesOrderRepository $orders,
        private readonly ProductionOrderRepository $productionOrders,
        private readonly QcInspectionRepository $inspections,
        private readonly AuditRepository $audit,
        private readonly SequenceRepository $sequences,
        private readonly DeliveryValidator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id: int|null, errors: array<string, list<string>>}
     */
    public function create(array $data, ?int $userId): array
    {
        $errors = $this->validator->validate($data);

        $salesOrderId = (int) ($data['sales_order_id'] ?? 0);
        $order = $salesOrderId > 0 ? $this->orders->find($salesOrderId) : null;

        if ($order === null) {
            if (!isset($errors['sales_order_id'])) {
                $errors['sales_order_id'][] = 'Pesanan tidak ditemukan.';
            }
        } elseif ($order->status === SalesOrder::STATUS_CANCELLED) {
            $errors['sales_order_id'][] = 'Pesanan dibatalkan — tidak dapat dikirim.';
        }

        // Batas siap-kirim (§9): min(pesanan, lolos QC) − sudah dikirim non-batal.
        if ($order !== null) {
            $summary = $this->summaryFor($salesOrderId);
            if (!isset($errors['quantity'])) {
                $qty = (int) ($data['quantity'] ?? 0);
                if ($qty > $summary['available']) {
                    $errors['quantity'][] = $summary['available'] <= 0
                        ? 'Belum ada barang siap kirim (butuh lolos QC & belum melebihi jumlah pesanan).'
                        : "Melebihi jumlah siap kirim (tersisa {$summary['available']}).";
                }
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'id' => null, 'errors' => $errors];
        }

        $payload = $data;
        $payload['created_by'] = $userId;
        $payload['status'] = Delivery::STATUS_DISIAPKAN;

        $qty = (int) ($data['quantity'] ?? 0);

        try {
            $id = $this->db->transaction(function () use ($payload, $userId, $order, $salesOrderId, $qty): int {
                // Kunci baris pesanan: dua pengiriman serentak diserialisasi sehingga
                // batas siap-kirim (§9) dibaca ulang setelah commit yang pertama —
                // tidak bisa ditembus double-submit (pola sama dgn Stock/Pembayaran).
                $this->orders->lock($salesOrderId);

                $summary = $this->summaryFor($salesOrderId);
                if ($qty > $summary['available']) {
                    throw new IntegrityGateException('quantity', $summary['available'] <= 0
                        ? 'Belum ada barang siap kirim (butuh lolos QC & belum melebihi jumlah pesanan).'
                        : "Melebihi jumlah siap kirim (tersisa {$summary['available']}).");
                }

                $payload['document_number'] = $this->sequences->nextDocumentNumber(self::DOC_PREFIX);
                $newId = $this->deliveries->create($payload);

                $this->audit->log(
                    $userId,
                    'delivery.created',
                    'delivery',
                    $newId,
                    sprintf(
                        'Pengiriman disiapkan — pesanan %s: %d pcs via %s',
                        (string) $order->documentNumber,
                        (int) $payload['quantity'],
                        Delivery::methodLabelFor((string) $payload['method']),
                    ),
                    [
                        'sales_order_id' => (int) $payload['sales_order_id'],
                        'quantity' => (int) $payload['quantity'],
                        'method' => (string) $payload['method'],
                        'delivery_date' => (string) $payload['delivery_date'],
                    ],
                );

                return $newId;
            });
        } catch (IntegrityGateException $e) {
            // Transaksi sudah di-rollback Database::transaction(); petakan ke error form.
            return ['ok' => false, 'id' => null, 'errors' => [$e->field => [$e->getMessage()]]];
        }

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    /**
     * Transisi status sebuah pengiriman. Validasi dijaga DeliveryFlow; ditolak
     * secara server-side (§9), bukan hanya menyembunyikan tombol di UI.
     *
     * @return array{ok: bool, id: int|null, errors: array<string, list<string>>}
     */
    public function transition(int $id, string $to, ?int $userId): array
    {
        $delivery = $this->findOrFail($id);

        if (!in_array($to, Delivery::STATUSES, true)) {
            return ['ok' => false, 'id' => $id, 'errors' => ['status' => ['Status tujuan tidak valid.']]];
        }

        if (!DeliveryFlow::canTransition($delivery->status, $to)) {
            return ['ok' => false, 'id' => $id, 'errors' => ['status' => [DeliveryFlow::rejectionMessage($delivery->status, $to)]]];
        }

        $from = $delivery->status;

        $this->db->transaction(function () use ($id, $to, $from, $delivery, $userId): void {
            $this->deliveries->updateStatus($id, $to);
            $this->audit->log(
                $userId,
                'delivery.status',
                'delivery',
                $id,
                sprintf(
                    'Pengiriman %s: %s → %s',
                    $delivery->documentNumber,
                    Delivery::statusLabelFor($from),
                    Delivery::statusLabelFor($to),
                ),
                ['from' => $from, 'to' => $to, 'sales_order_id' => $delivery->salesOrderId],
            );
        });

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    /**
     * Ringkasan saldo kirim sebuah pesanan — dasar pagar & UI progres.
     *
     * @return array{ordered: int, qc_ready: int, shipped: int, available: int}
     */
    public function summaryFor(int $salesOrderId): array
    {
        $order = $this->orders->find($salesOrderId);
        $ordered = $order !== null ? $order->totalQuantity() : 0;

        $po = $this->productionOrders->findBySalesOrder($salesOrderId);
        $qcReady = $po !== null ? $this->inspections->passedTotal($po->id) : 0;

        $shipped = $this->deliveries->shippedQuantityForSalesOrder($salesOrderId);

        $available = max(0, min($ordered, $qcReady) - $shipped);

        return ['ordered' => $ordered, 'qc_ready' => $qcReady, 'shipped' => $shipped, 'available' => $available];
    }

    /** Riwayat pengiriman sebuah pesanan. @return list<Delivery> */
    public function historyFor(int $salesOrderId, int $limit = 30): array
    {
        return $this->deliveries->forSalesOrder($salesOrderId, $limit);
    }

    public function findOrFail(int $id): Delivery
    {
        $delivery = $this->deliveries->find($id);
        if ($delivery === null) {
            throw new HttpException(404, 'Data pengiriman tidak ditemukan.');
        }

        return $delivery;
    }
}
