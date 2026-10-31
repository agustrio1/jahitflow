<?php

declare(strict_types=1);

namespace App\Validators;

use DateTime;

/**
 * Validasi input perintah produksi (§8.E). Pembuatan hanya menerima pesanan
 * sumber + penanggung jawab + catatan; kelayakan (status pesanan, gerbang DP)
 * dan snapshot tahap diputus service. Aturan transisi tahap dijaga
 * ProductionStageMachine, bukan di sini.
 */
final class ProductionOrderValidator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, list<string>>
     */
    public function validateCreate(array $data): array
    {
        $errors = [];

        $salesOrderId = $data['sales_order_id'] ?? null;
        if ($salesOrderId === null || $salesOrderId === '' || (int) $salesOrderId <= 0) {
            $errors['sales_order_id'][] = 'Pesanan sumber tidak valid.';
        }

        $assignedTo = $data['assigned_to'] ?? null;
        if ($assignedTo !== null && $assignedTo !== '' && (int) $assignedTo <= 0) {
            $errors['assigned_to'][] = 'Penanggung jawab tidak valid.';
        }

        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($description) > 255) {
            $errors['description'][] = 'Deskripsi maksimal 255 karakter.';
        }

        $targetDate = trim((string) ($data['target_date'] ?? ''));
        if ($targetDate !== '' && !$this->isDate($targetDate)) {
            $errors['target_date'][] = 'Tanggal target tidak valid.';
        }

        return $errors;
    }

    /**
     * Validasi bentuk input progres tahap. Kelayakan transisi (urutan tahap,
     * status saat ini) diperiksa service lewat ProductionStageMachine.
     *
     * @param array<string, mixed> $data
     * @return array<string, list<string>>
     */
    public function validateStageInput(array $data): array
    {
        $errors = [];

        $targetQty = $data['target_qty'] ?? null;
        if ($targetQty !== null && $targetQty !== '' && (!is_numeric($targetQty) || (int) $targetQty < 0)) {
            $errors['target_qty'][] = 'Target harus angka bulat non-negatif.';
        }

        $realizedQty = $data['realized_qty'] ?? null;
        if ($realizedQty !== null && $realizedQty !== '' && (!is_numeric($realizedQty) || (int) $realizedQty < 0)) {
            $errors['realized_qty'][] = 'Realisasi harus angka bulat non-negatif.';
        }

        $notes = trim((string) ($data['notes'] ?? ''));
        if (mb_strlen($notes) > 255) {
            $errors['notes'][] = 'Catatan kendala maksimal 255 karakter.';
        }

        return $errors;
    }

    private function isDate(string $value): bool
    {
        $parsed = DateTime::createFromFormat('Y-m-d', $value);

        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    }
}
