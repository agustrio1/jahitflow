<?php

declare(strict_types=1);

namespace App\Validators;

use App\Support\StockLedger;

/**
 * Validasi bentuk input pergerakan stok (§8.F). Aturan aritmetika (arah ↔ tanda,
 * saldo tak boleh negatif) diputus StockLedger saat aplikasi; validator ini
 * hanya memastikan input cacat tertahan lebih awal dengan pesan yang ramah.
 */
final class StockMovementValidator
{
    /** Jenis rujukan yang dikenal untuk penautan manual. */
    public const REFERENCE_TYPES = ['production_order', 'purchase', 'manual', 'other'];

    /**
     * @param array<string, mixed> $data
     * @return array<string, list<string>>
     */
    public function validate(array $data): array
    {
        $errors = [];

        $direction = (string) ($data['direction'] ?? '');
        if (!in_array($direction, [StockLedger::IN, StockLedger::OUT, StockLedger::ADJUSTMENT], true)) {
            $errors['direction'][] = 'Arah pergerakan tidak valid.';
        }

        $quantity = trim((string) ($data['quantity'] ?? ''));
        if ($quantity === '') {
            $errors['quantity'][] = 'Jumlah wajib diisi.';
        } elseif (preg_match('/^-?\d+(\.\d{1,3})?$/', $quantity) !== 1) {
            $errors['quantity'][] = 'Jumlah harus angka valid (maks 3 desimal).';
        } elseif ($direction !== StockLedger::ADJUSTMENT && (float) $quantity <= 0) {
            $errors['quantity'][] = 'Penerimaan/pemakaian harus lebih besar dari nol.';
        } elseif ($direction === StockLedger::ADJUSTMENT && (float) $quantity === 0.0) {
            $errors['quantity'][] = 'Penyesuaian tidak boleh nol (gunakan tanda − untuk pengurangan).';
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            $errors['reason'][] = 'Alasan wajib diisi (terutama untuk penyesuaian — audit trail §8.F).';
        } elseif (mb_strlen($reason) > 190) {
            $errors['reason'][] = 'Alasan maksimal 190 karakter.';
        }

        $referenceType = trim((string) ($data['reference_type'] ?? ''));
        if ($referenceType !== '' && !in_array($referenceType, self::REFERENCE_TYPES, true)) {
            $errors['reference_type'][] = 'Jenis rujukan tidak dikenal.';
        }

        $referenceId = $data['reference_id'] ?? '';
        if ($referenceId !== '' && (int) $referenceId <= 0) {
            $errors['reference_id'][] = 'Rujukan tidak valid.';
        }

        return $errors;
    }
}
