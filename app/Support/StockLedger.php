<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Kalkulator ledger stok — MURNI (tanpa DB, tanpa side-effect), bcmath SCALE 3
 * (§7 DOC; satuan bisa meter/kg). Satu-satunya tempat aturan "saldo bergerak"
 * hidup, sehingga mudah diuji (§13: test pemakaian stok).
 *
 * Arah:
 *  - in         : penerimaan; quantity > 0 → saldo bertambah.
 *  - out        : pemakaian; quantity > 0 → saldo berkurang (tak boleh negatif).
 *  - adjustment : penyesuaian beralasan; quantity ± → saldo bergeser (tak boleh negatif).
 *
 * Service memanggil ini di DALAM transaksi (setelah SELECT ... FOR UPDATE),
 * lalu memakai balance_after untuk menulis raw_materials.current_stock + baris
 * stock_movements. Kelas ini TIDAK menyentuh database.
 */
final class StockLedger
{
    private const SCALE = 3;

    public const IN = 'in';
    public const OUT = 'out';
    public const ADJUSTMENT = 'adjustment';

    private function __construct(
        public readonly string $direction,
        public readonly string $quantity,
        public readonly string $balanceBefore,
        public readonly string $balanceAfter,
        public readonly string $signedDelta,
    ) {
    }

    /**
     * Hitung dampak satu pergerakan terhadap saldo.
     *
     * @throws StockLedgerException saat arah/jumlah tidak sah atau hasil negatif
     */
    public static function apply(string $direction, string $balanceBefore, string $quantity): self
    {
        if (!in_array($direction, [self::IN, self::OUT, self::ADJUSTMENT], true)) {
            throw new StockLedgerException('direction', 'Arah pergerakan stok tidak dikenal.');
        }

        $before = self::decimal($balanceBefore, 'balance_before');

        // Penerimaan & pemakaian memakai magnitude positif; penyesuaian boleh bertanda.
        if ($direction === self::ADJUSTMENT) {
            $amount = self::decimal($quantity, 'quantity');
            if (bccomp($amount, '0', self::SCALE) === 0) {
                throw new StockLedgerException('quantity', 'Penyesuaian tidak boleh nol.');
            }
            $delta = $amount;
        } else {
            $amount = self::positive($quantity, 'quantity');
            $delta = $direction === self::IN ? $amount : bcsub('0', $amount, self::SCALE);
        }

        $after = bcadd($before, $delta, self::SCALE);
        if (bccomp($after, '0', self::SCALE) < 0) {
            throw new StockLedgerException('quantity', 'Stok tidak mencukupi — saldo tidak boleh negatif.');
        }

        return new self($direction, $amount, $before, $after, $delta);
    }

    /** Peringatan stok minimum: saldo saat ini ≤ ambang minimum. */
    public static function isLowStock(string $currentStock, string $minStock): bool
    {
        $current = self::decimal($currentStock, 'current_stock');
        $min = self::decimal($minStock, 'min_stock');

        return bccomp($current, $min, self::SCALE) <= 0;
    }

    /**
     * Validasi konversi ke desimal normal (menerima tanda −), menolak
     * pemisah ribu/koma dan notasi ilmiah agar aman ke bcmath.
     */
    private static function decimal(string $value, string $field): string
    {
        $trimmed = trim($value);
        if (preg_match('/^-?\d+(\.\d{1,3})?$/', $trimmed) !== 1) {
            throw new StockLedgerException($field, 'Jumlah harus angka valid (maks 3 desimal).');
        }

        return bcadd($trimmed, '0', self::SCALE);
    }

    /** Desimal yang WAJIB > 0 (magnitude penerimaan/pemakaian). */
    private static function positive(string $value, string $field): string
    {
        $decimal = self::decimal($value, $field);
        if (bccomp($decimal, '0', self::SCALE) <= 0) {
            throw new StockLedgerException($field, 'Jumlah harus lebih besar dari nol.');
        }

        return $decimal;
    }
}
