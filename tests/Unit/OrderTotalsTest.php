<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\OrderTotals;
use App\Support\OrderTotalsException;
use PHPUnit\Framework\TestCase;

/**
 * Kalkulasi pesanan (§13 DOC: unit test kalkulasi). Murni — tanpa DB.
 * Memastikan eksak desimal (bcmath) dan penegakan batas bisnis:
 * diskon ≤ subtotal, DP ≤ total, kuantitas & harga valid.
 */
final class OrderTotalsTest extends TestCase
{
    public function testComputesSubtotalDiscountAdditionalAndRemaining(): void
    {
        $totals = OrderTotals::compute(
            [
                ['quantity' => 2, 'unit_price' => 150000],
                ['quantity' => 3, 'unit_price' => 100000],
            ],
            50000,   // diskon
            20000,   // biaya tambahan
            200000,  // uang muka
        );

        self::assertSame('600000.00', $totals->subtotal);
        self::assertSame('570000.00', $totals->grandTotal);
        self::assertSame('370000.00', $totals->remaining);
        self::assertSame('200000.00', $totals->dpAmount);
    }

    public function testEmptyLinesYieldZeroTotals(): void
    {
        $totals = OrderTotals::compute([]);

        self::assertSame('0.00', $totals->subtotal);
        self::assertSame('0.00', $totals->grandTotal);
        self::assertSame(0.0, $totals->dpPercent());
        self::assertFalse($totals->meetsDpGate());
    }

    public function testDpPercentMatchesRatio(): void
    {
        $totals = OrderTotals::compute(
            [['quantity' => 1, 'unit_price' => 100000]],
            0,
            0,
            25000,
        );

        self::assertSame(25.0, $totals->dpPercent());
    }

    public function testDpGateBoundary(): void
    {
        $passing = OrderTotals::compute([['quantity' => 1, 'unit_price' => 100000]], 0, 0, 30000);
        $failing = OrderTotals::compute([['quantity' => 1, 'unit_price' => 100000]], 0, 0, 29900);

        self::assertTrue($passing->meetsDpGate(), 'DP tepat 30% harus memenuhi gerbang.');
        self::assertFalse($failing->meetsDpGate(), 'DP di bawah 30% belum memenuhi gerbang.');
    }

    public function testDiscountOverSubtotalRejected(): void
    {
        $this->expectException(OrderTotalsException::class);
        $this->expectExceptionMessage('Diskon tidak boleh melebihi subtotal');

        OrderTotals::compute([['quantity' => 1, 'unit_price' => 100000]], 150000);
    }

    public function testDpOverGrandTotalRejected(): void
    {
        $this->expectException(OrderTotalsException::class);
        $this->expectExceptionMessage('Uang muka tidak boleh melebihi total');

        OrderTotals::compute([['quantity' => 1, 'unit_price' => 100000]], 0, 0, 100001);
    }

    public function testZeroQuantityRejected(): void
    {
        $this->expectException(OrderTotalsException::class);

        OrderTotals::compute([['quantity' => 0, 'unit_price' => 100000]]);
    }

    public function testNegativeUnitPriceRejected(): void
    {
        $this->expectException(OrderTotalsException::class);

        OrderTotals::compute([['quantity' => 2, 'unit_price' => -1000]]);
    }

    public function testExceptionCarriesFieldForFormMapping(): void
    {
        try {
            OrderTotals::compute([['quantity' => 1, 'unit_price' => 100000]], 0, 0, 500000);
            self::fail('Harusnya melempar OrderTotalsException.');
        } catch (OrderTotalsException $e) {
            self::assertSame('dp_amount', $e->field);
        }
    }
}
