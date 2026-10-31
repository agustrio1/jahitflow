<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Models\RawMaterial;
use App\Repositories\AuditRepository;
use App\Repositories\RawMaterialRepository;
use App\Repositories\StockMovementRepository;
use App\Support\StockLedger;
use App\Support\StockLedgerException;
use App\Validators\StockMovementValidator;

/**
 * Orkestrasi pergerakan stok (§8.F + §9). Alur aman-konkurensi:
 *   transaksi → SELECT current_stock ... FOR UPDATE (kunci baris bahan)
 *            → StockLedger::apply (hitung saldo baru; tolak bila negatif)
 *            → UPDATE current_stock + INSERT baris ledger + audit.
 *
 * Stok berkurang HANYA lewat arah 'out' (peristiwa pemakaian), bukan saat
 * pesanan/produksi dibuat (§9 DOC).
 */
final class StockService
{
    public function __construct(
        private readonly Database $db,
        private readonly RawMaterialRepository $materials,
        private readonly StockMovementRepository $movements,
        private readonly AuditRepository $audit,
        private readonly StockMovementValidator $validator,
    ) {
    }

    /**
     * @param 'in'|'out'|'adjustment' $direction
     * @param array<string, mixed> $data reason + quantity + rujukan opsional
     * @return array{ok: bool, errors: array<string, list<string>>, balance_after?: string}
     */
    public function record(int $materialId, string $direction, array $data, ?int $userId): array
    {
        $material = $this->materials->find($materialId);
        if ($material === null) {
            throw new HttpException(404, 'Bahan baku tidak ditemukan.');
        }

        $data['direction'] = $direction;
        $errors = $this->validator->validate($data);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $quantity = trim((string) $data['quantity']);
        $reason = trim((string) $data['reason']);
        $referenceType = isset($data['reference_type']) && $data['reference_type'] !== '' ? (string) $data['reference_type'] : null;
        $referenceId = isset($data['reference_id']) && (int) $data['reference_id'] > 0 ? (int) $data['reference_id'] : null;

        try {
            return $this->db->transaction(function () use ($materialId, $material, $direction, $quantity, $reason, $referenceType, $referenceId, $userId): array {
                // Kunci saldo — dua transaksi bersamaan tidak saling menimpa.
                $before = $this->materials->lockStock($materialId);
                if ($before === null) {
                    throw new HttpException(404, 'Bahan baku tidak ditemukan.');
                }

                $ledger = StockLedger::apply($direction, $before, $quantity);

                $this->materials->setStock($materialId, $ledger->balanceAfter);
                $this->movements->record([
                    'raw_material_id' => $materialId,
                    'direction' => $direction,
                    'quantity' => $ledger->quantity,
                    'balance_before' => $ledger->balanceBefore,
                    'balance_after' => $ledger->balanceAfter,
                    'reason' => $reason,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'created_by' => $userId,
                ]);

                $this->audit->log(
                    $userId,
                    'stock.' . $direction,
                    'raw_material',
                    $materialId,
                    $material->name . ': ' . $this->movementLabel($direction) . ' ' . RawMaterial::formatQty($ledger->quantity) . ' ' . $material->unit
                        . ' (saldo ' . RawMaterial::formatQty($ledger->balanceBefore) . ' → ' . RawMaterial::formatQty($ledger->balanceAfter) . ')',
                    [
                        'direction' => $direction,
                        'quantity' => $ledger->quantity,
                        'balance_before' => $ledger->balanceBefore,
                        'balance_after' => $ledger->balanceAfter,
                        'reason' => $reason,
                        'reference' => $referenceType ? $referenceType . ':' . $referenceId : null,
                    ],
                );

                return ['ok' => true, 'errors' => [], 'balance_after' => $ledger->balanceAfter];
            });
        } catch (StockLedgerException $e) {
            // Transaksi sudah di-rollback Database::transaction(); petakan ke error form.
            return ['ok' => false, 'errors' => [$e->field => [$e->getMessage()]]];
        }
    }

    private function movementLabel(string $direction): string
    {
        return match ($direction) {
            StockLedger::IN => 'penerimaan',
            StockLedger::OUT => 'pemakaian',
            StockLedger::ADJUSTMENT => 'penyesuaian',
            default => $direction,
        };
    }
}
