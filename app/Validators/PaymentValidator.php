<?php

declare(strict_types=1);

namespace App\Validators;

use App\Models\Payment;

/**
 * Validasi input pembayaran (§8.H). MURNI — tanpa DB. Menjaga bentuk tiap
 * transaksi: jumlah uang positif dengan maksimal 2 desimal, fase & metode dari
 * daftar valid, dan tanggal pembayaran sah. Batas bisnis "tidak boleh melebihi
 * sisa tagihan" TIDAK di sini (memerlukan agregat SUM) — itu di PaymentService.
 */
final class PaymentValidator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, list<string>>
     */
    public function validate(array $data): array
    {
        $errors = [];

        $salesOrderId = (int) ($data['sales_order_id'] ?? 0);
        if ($salesOrderId <= 0) {
            $errors['sales_order_id'][] = 'Pilih pesanan yang dibayar.';
        }

        // Jumlah: desimal positif, maksimal 2 angka di belakang koma.
        $amount = trim((string) ($data['amount'] ?? ''));
        if ($amount === '' || preg_match('/^\d+(\.\d{1,2})?$/', $amount) !== 1) {
            $errors['amount'][] = 'Jumlah harus angka (maksimal 2 desimal).';
        } elseif (bccomp($amount, '0', 2) <= 0) {
            $errors['amount'][] = 'Jumlah pembayaran harus lebih besar dari nol.';
        }

        $phase = (string) ($data['phase'] ?? '');
        if (!in_array($phase, Payment::PHASES, true)) {
            $errors['phase'][] = 'Pilih jenis pembayaran yang valid.';
        }

        $method = (string) ($data['method'] ?? '');
        if (!in_array($method, Payment::METHODS, true)) {
            $errors['method'][] = 'Pilih metode pembayaran yang valid.';
        }

        // Tanggal: wajib, format YYYY-MM-DD, dan benar-benar ada (checkdate).
        $date = trim((string) ($data['payment_date'] ?? ''));
        if ($date === '' || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) !== 1
            || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $errors['payment_date'][] = 'Tanggal pembayaran tidak valid (format YYYY-MM-DD).';
        }

        $reference = trim((string) ($data['reference'] ?? ''));
        if (mb_strlen($reference) > 100) {
            $errors['reference'][] = 'Nomor referensi maksimal 100 karakter.';
        }

        $note = trim((string) ($data['note'] ?? ''));
        if (mb_strlen($note) > 255) {
            $errors['note'][] = 'Catatan maksimal 255 karakter.';
        }

        return $errors;
    }
}
