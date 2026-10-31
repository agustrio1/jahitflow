<?php

declare(strict_types=1);

namespace App\Validators;

use App\Models\QcInspection;

/**
 * Validasi input pemeriksaan QC (§8.G). Murni — tanpa DB. Menjaga aturan
 * jumlah agar lolos + gagal tidak melebihi yang diperiksa, dan konsistensi
 * hasil terhadap jumlah cacat (kursor terakhir juga ditegakkan CHECK di DB).
 */
final class QcInspectionValidator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, list<string>>
     */
    public function validate(array $data): array
    {
        $errors = [];

        $productionOrderId = (int) ($data['production_order_id'] ?? 0);
        if ($productionOrderId <= 0) {
            $errors['production_order_id'][] = 'Pilih perintah produksi yang diperiksa.';
        }

        // Jumlah: bilangan bulat non-negatif (diperiksa wajib > 0).
        $inspected = $this->nonNegativeInt($data['inspected_qty'] ?? null);
        $passed = $this->nonNegativeInt($data['passed_qty'] ?? null);
        $failed = $this->nonNegativeInt($data['failed_qty'] ?? null);

        if ($inspected === null) {
            $errors['inspected_qty'][] = 'Jumlah diperiksa harus angka bulat positif.';
        } elseif ($inspected <= 0) {
            $errors['inspected_qty'][] = 'Jumlah diperiksa harus lebih besar dari nol.';
        }
        if ($passed === null) {
            $errors['passed_qty'][] = 'Jumlah lolos harus angka bulat (≥ 0).';
        }
        if ($failed === null) {
            $errors['failed_qty'][] = 'Jumlah gagal harus angka bulat (≥ 0).';
        }

        // Aturan inti: lolos + gagal tidak melebihi diperiksa (§8.G).
        if ($inspected !== null && $passed !== null && $failed !== null) {
            if ($passed + $failed > $inspected) {
                $errors['failed_qty'][] = 'Jumlah lolos + gagal melebihi jumlah diperiksa.';
            }
        }

        $result = (string) ($data['result'] ?? '');
        if (!in_array($result, QcInspection::RESULTS, true)) {
            $errors['result'][] = 'Pilih hasil pemeriksaan yang valid.';
        } elseif ($failed !== null) {
            // Konsistensi hasil ↔ jumlah cacat.
            if ($result === QcInspection::RESULT_LOLOS && $failed > 0) {
                $errors['result'][] = 'Hasil "Lolos" tidak boleh memiliki barang gagal.';
            }
            if ($result !== QcInspection::RESULT_LOLOS && $failed === 0) {
                $errors['result'][] = 'Hasil perbaikan/gagal memerlukan barang gagal (> 0).';
            }
        }

        // Catatan cacat wajib bila ada barang gagal (terutama penyesuaian alur).
        $defect = trim((string) ($data['defect_note'] ?? ''));
        if (($failed ?? 0) > 0 && $defect === '') {
            $errors['defect_note'][] = 'Catatan cacat wajib diisi bila ada barang gagal.';
        }
        if (mb_strlen($defect) > 500) {
            $errors['defect_note'][] = 'Catatan cacat maksimal 500 karakter.';
        }

        // Tindakan perbaikan wajib bila hasil "perbaikan".
        $rework = trim((string) ($data['rework_action'] ?? ''));
        if ($result === QcInspection::RESULT_PERBAIKAN && $rework === '') {
            $errors['rework_action'][] = 'Tindakan perbaikan wajib diisi untuk hasil "Perlu Perbaikan".';
        }
        if (mb_strlen($rework) > 500) {
            $errors['rework_action'][] = 'Tindakan perbaikan maksimal 500 karakter.';
        }

        return $errors;
    }

    /**
     * Baca bilangan bulat non-negatif dari input string/number.
     * Mengembalikan null bila format tidak sah.
     */
    private function nonNegativeInt(mixed $value): ?int
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '' || preg_match('/^\d+$/', $trimmed) !== 1) {
            return null;
        }

        return (int) $trimmed;
    }
}
