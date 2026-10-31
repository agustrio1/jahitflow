<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Dilempar saat pagar integritas §9 (mis. "pembayaran ≤ sisa tagihan" atau
 * "pengiriman ≤ jumlah siap-kirim") masih terlanggar WALAU sudah diverifikasi di
 * luar transaksi — tanda dua permintaan serentak saling menembus (double-submit).
 *
 * Service melempar ini dari DALAM transaksi (setelah baris pesanan dikunci
 * FOR UPDATE dan saldo dibaca ulang), sehingga Database::transaction()rollback,
 * lalu service menangkapnya dan memetakan ke error form per-field — BUKAN 500.
 * Satu keluarga pemakaian dengan StockLedgerException / OrderTotalsException.
 */
final class IntegrityGateException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
