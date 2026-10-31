<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Models\ProductionOrder;
use App\Models\QcInspection;
use App\Repositories\AuditRepository;
use App\Repositories\ProductionOrderRepository;
use App\Repositories\QcInspectionRepository;
use App\Validators\QcInspectionValidator;

/**
 * Logika bisnis Quality Control (§8.G). Memastikan pemeriksaan hanya menempel
 * pada perintah produksi yang layak (berjalan/selesai), menjaga aturan jumlah
 * via validator, dan mencatat audit trail. Pemeriksaan bersifat log — tanpa edit.
 */
final class QcService
{
    public function __construct(
        private readonly Database $db,
        private readonly QcInspectionRepository $inspections,
        private readonly ProductionOrderRepository $orders,
        private readonly AuditRepository $audit,
        private readonly QcInspectionValidator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id: int|null, errors: array<string, list<string>>}
     */
    public function create(array $data, ?int $userId): array
    {
        $errors = $this->validator->validate($data);

        // Sumber barang harus perintah produksi yang ada & sedang/selesai dikerjakan.
        $order = $this->orders->find((int) ($data['production_order_id'] ?? 0));
        if ($order === null) {
            if (!isset($errors['production_order_id'])) {
                $errors['production_order_id'][] = 'Perintah produksi tidak ditemukan.';
            }
        } elseif (!in_array($order->status, [ProductionOrder::STATUS_RUNNING, ProductionOrder::STATUS_COMPLETED], true)) {
            $errors['production_order_id'][] = 'Hanya perintah produksi Berjalan/Selesai yang dapat diperiksa QC.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'id' => null, 'errors' => $errors];
        }

        $payload = $data;
        $payload['inspected_by'] = $userId;
        $payload['inspected_at'] = date('Y-m-d H:i:s');

        $id = $this->db->transaction(function () use ($payload, $userId, $order): int {
            $newId = $this->inspections->create($payload);
            $this->audit->log(
                $userId,
                'qc.inspected',
                'qc_inspection',
                $newId,
                sprintf(
                    'QC %s: %s — diperiksa %d, lolos %d, gagal %d',
                    QcInspection::labelFor((string) $payload['result']),
                    (string) $order?->documentNumber,
                    (int) $payload['inspected_qty'],
                    (int) $payload['passed_qty'],
                    (int) $payload['failed_qty'],
                ),
                [
                    'production_order_id' => (int) $payload['production_order_id'],
                    'inspected_qty' => (int) $payload['inspected_qty'],
                    'passed_qty' => (int) $payload['passed_qty'],
                    'failed_qty' => (int) $payload['failed_qty'],
                    'result' => (string) $payload['result'],
                ],
            );

            return $newId;
        });

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    /** Riwayat pemeriksaan untuk panel detail perintah produksi. */
    public function historyFor(int $productionOrderId, int $limit = 20): array
    {
        return $this->inspections->forProductionOrder($productionOrderId, $limit);
    }

    public function passedTotal(int $productionOrderId): int
    {
        return $this->inspections->passedTotal($productionOrderId);
    }

    public function findOrFail(int $id): QcInspection
    {
        $inspection = $this->inspections->find($id);
        if ($inspection === null) {
            throw new HttpException(404, 'Pemeriksaan QC tidak ditemukan.');
        }

        return $inspection;
    }
}
