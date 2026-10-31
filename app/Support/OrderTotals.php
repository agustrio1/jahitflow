<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Kalkulator total pesanan — MURNI (tanpa DB, tanpa side-effect), memakai
 * bcmath agar uang desimal eksak (§7 DOC). Inilah "satu-satunya sumber
 * kebenaran" angka: service selalu menghitung ulang lewat kelas ini dari
 * harga varian di database, tidak pernah memakai total kiriman browser
 * (§8.D & §13 DOC: unit test kalkulasi pesanan).
 */
final class OrderTotals
{
    private const SCALE = 2;

    private function __construct(
        public readonly string $subtotal,
        public readonly string $discount,
        public readonly string $additionalCost,
        public readonly string $grandTotal,
        public readonly string $dpAmount,
        public readonly string $remaining,
    ) {
    }

    /**
     * @param list<array{quantity: int|string, unit_price: int|string|float}> $lines
     *        tiap baris = kuantitas x harga satuan varian.
     * @param int|string|float $discount      diskon (nominal, bukan persen)
     * @param int|string|float $additionalCost biaya tambahan
     * @param int|string|float $dpAmount       uang muka
     * @throws OrderTotalsException saat salah satu nilai di luar batas bisnis
     */
    public static function compute(
        array $lines,
        int|string|float $discount = 0,
        int|string|float $additionalCost = 0,
        int|string|float $dpAmount = 0,
    ): self {
        $subtotal = '0.00';

        foreach ($lines as $index => $line) {
            $qty = self::positiveInt($line['quantity'] ?? 0, $index);
            $unit = self::nonNegativeMoney($line['unit_price'] ?? 0, 'unit_price', $index);
            $lineTotal = bcmul($qty, $unit, self::SCALE);
            $subtotal = bcadd($subtotal, $lineTotal, self::SCALE);
        }

        $discountStr = self::nonNegativeMoney($discount, 'discount_amount');
        if (bccomp($discountStr, $subtotal, self::SCALE) > 0) {
            throw new OrderTotalsException('discount_amount', 'Diskon tidak boleh melebihi subtotal pesanan.');
        }

        $additionalStr = self::nonNegativeMoney($additionalCost, 'additional_cost');
        $grandTotal = bcadd(bcsub($subtotal, $discountStr, self::SCALE), $additionalStr, self::SCALE);

        $dpStr = self::nonNegativeMoney($dpAmount, 'dp_amount');
        if (bccomp($dpStr, $grandTotal, self::SCALE) > 0) {
            throw new OrderTotalsException('dp_amount', 'Uang muka tidak boleh melebihi total pesanan.');
        }

        $remaining = bcsub($grandTotal, $dpStr, self::SCALE);

        return new self($subtotal, $discountStr, $additionalStr, $grandTotal, $dpStr, $remaining);
    }

    /** Persentase uang muka terhadap total (0 bila total nol). */
    public function dpPercent(): float
    {
        if (bccomp($this->grandTotal, '0', self::SCALE) === 0) {
            return 0.0;
        }

        return (float) bcmul(bcdiv($this->dpAmount, $this->grandTotal, 6), '100', 2);
    }

    /**
     * Gerbang DP untuk memulai produksi (§9): uang muka minimal `minRatio`
     * dari total. Dipakai modul Production Order (Batch berikutnya).
     */
    public function meetsDpGate(float $minRatio = 0.30): bool
    {
        if (bccomp($this->grandTotal, '0', self::SCALE) === 0) {
            return false;
        }

        $threshold = bcmul($this->grandTotal, (string) $minRatio, self::SCALE);

        return bccomp($this->dpAmount, $threshold, self::SCALE) >= 0;
    }

    // ------------------------------------------------------------------

    private static function positiveInt(mixed $value, int $lineIndex): string
    {
        $int = (int) $value;
        if ($int <= 0) {
            throw new OrderTotalsException(
                'items',
                'Kuantitas item ke-' . ($lineIndex + 1) . ' harus angka bulat lebih dari nol.',
            );
        }

        return (string) $int;
    }

    private static function nonNegativeMoney(mixed $value, string $field, ?int $lineIndex = null): string
    {
        if (!is_numeric($value)) {
            throw new OrderTotalsException($field, self::moneyLabel($field, $lineIndex, 'harus angka.'));
        }

        $normalized = bcadd((string) $value, '0', self::SCALE);
        if (bccomp($normalized, '0', self::SCALE) < 0) {
            throw new OrderTotalsException($field, self::moneyLabel($field, $lineIndex, 'tidak boleh negatif.'));
        }

        return $normalized;
    }

    private static function moneyLabel(string $field, ?int $lineIndex, string $problem): string
    {
        $names = [
            'unit_price' => 'Harga satuan',
            'discount_amount' => 'Diskon',
            'additional_cost' => 'Biaya tambahan',
            'dp_amount' => 'Uang muka',
        ];

        $label = $names[$field] ?? $field;

        return $lineIndex !== null
            ? "{$label} item ke-" . ($lineIndex + 1) . " {$problem}"
            : "{$label} {$problem}";
    }
}
