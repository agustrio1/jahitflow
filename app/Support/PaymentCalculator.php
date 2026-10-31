<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Kalkulator pembayaran — MURNI (tanpa DB, tanpa side-effect), memakai bcmath
 * agar uang desimal eksak (§7). Memisahkan aritmetika "sudah diterima vs sisa
 * tagihan" dari lapisan data supaya dapat diuji tanpa database (§13).
 *
 * Sumber kebenaran "sudah diterima" = SUM(payments) sebuah pesanan (bukan
 * dp_amount — itu hanya gerbang produksi, §9). Semua argumen diterima sebagai
 * string/int/float desimal dan dinormalisasi ke 2 angka di belakang koma.
 */
final class PaymentCalculator
{
    private const SCALE = 2;

    public const STATUS_UNPAID = 'belum';
    public const STATUS_PARTIAL = 'sebagian';
    public const STATUS_PAID = 'lunas';

    /** Normalisasi nilai uang ke desimal 2 digit; bukan-angka dianggap 0. */
    public static function normalize(int|string|float $value): string
    {
        if (!is_numeric($value)) {
            return '0.00';
        }

        return bcadd((string) $value, '0', self::SCALE);
    }

    /**
     * Sisa tagihan = total − sudah dibayar, tidak pernah negatif.
     */
    public static function remaining(int|string|float $grandTotal, int|string|float $paidTotal): string
    {
        $sisa = bcsub(self::normalize($grandTotal), self::normalize($paidTotal), self::SCALE);

        return bccomp($sisa, '0', self::SCALE) < 0 ? '0.00' : $sisa;
    }

    /**
     * Apakah jumlah ini akan MELAMPAUI sisa tagihan? (§9: pembayaran tidak boleh
     * melebihi sisa). Dipakai Service sebagai pagar server-side.
     */
    public static function exceeds(int|string|float $amount, int|string|float $remaining): bool
    {
        return bccomp(self::normalize($amount), self::normalize($remaining), self::SCALE) > 0;
    }

    /** Lunas tercapai bila sudah dibayar ≥ total (dan total > 0). */
    public static function isLunas(int|string|float $grandTotal, int|string|float $paidTotal): bool
    {
        $grand = self::normalize($grandTotal);
        if (bccomp($grand, '0', self::SCALE) === 0) {
            return false;
        }

        return bccomp(self::normalize($paidTotal), $grand, self::SCALE) >= 0;
    }

    /** Status agregat untuk laporan/UI. */
    public static function status(int|string|float $grandTotal, int|string|float $paidTotal): string
    {
        $paid = self::normalize($paidTotal);

        if (bccomp($paid, '0', self::SCALE) <= 0) {
            return self::STATUS_UNPAID;
        }

        return self::isLunas($grandTotal, $paid) ? self::STATUS_PAID : self::STATUS_PARTIAL;
    }

    /** Persentase sudah dibayar terhadap total (0–100), aman untuk total nol. */
    public static function paidPercent(int|string|float $grandTotal, int|string|float $paidTotal): float
    {
        $grand = self::normalize($grandTotal);
        if (bccomp($grand, '0', self::SCALE) === 0) {
            return 0.0;
        }

        $ratio = bcdiv(self::normalize($paidTotal), $grand, 6);
        $capped = bccomp($ratio, '1', 6) > 0 ? '1' : $ratio;

        return (float) bcmul($capped, '100', 2);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PAID => 'Lunas',
            self::STATUS_PARTIAL => 'Dibayar Sebagian',
            default => 'Belum Dibayar',
        };
    }

    public static function statusBadge(string $status): string
    {
        return match ($status) {
            self::STATUS_PAID => 'jf-badge--success',
            self::STATUS_PARTIAL => 'jf-badge--warning',
            default => 'jf-badge--info',
        };
    }
}
