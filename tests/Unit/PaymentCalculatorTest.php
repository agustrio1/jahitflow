<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PaymentCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Kalkulasi pembayaran — sisa tagihan, batas "tidak boleh melebihi sisa",
 * status lunas, dan persentase (§9 & §13 DOC: kalkulasi pembayaran teruji).
 * Murni — tanpa DB.
 */
final class PaymentCalculatorTest extends TestCase
{
    public function testNormalizeToTwoDecimals(): void
    {
        $this->assertSame('12.50', PaymentCalculator::normalize('12.5'));
        $this->assertSame('100.00', PaymentCalculator::normalize(100));
        $this->assertSame('0.00', PaymentCalculator::normalize('abc'));
        $this->assertSame('0.00', PaymentCalculator::normalize(''));
    }

    public function testRemainingSubtractsPaidFromGrand(): void
    {
        $this->assertSame('70.00', PaymentCalculator::remaining('100.00', '30.00'));
    }

    public function testRemainingNeverNegative(): void
    {
        // Sudah bayar lebih dari total (harusnya tak terjadi) → sisa clamp ke 0.
        $this->assertSame('0.00', PaymentCalculator::remaining('100.00', '150.00'));
    }

    public function testExceedsDetectsOverpayment(): void
    {
        $this->assertTrue(PaymentCalculator::exceeds('70.01', '70.00'));
        // Pas sama dengan sisa: BOLEH (bukan melebihi).
        $this->assertFalse(PaymentCalculator::exceeds('70.00', '70.00'));
        $this->assertFalse(PaymentCalculator::exceeds('50.00', '70.00'));
    }

    public function testIsLunasBoundary(): void
    {
        $this->assertTrue(PaymentCalculator::isLunas('100.00', '100.00'));
        $this->assertTrue(PaymentCalculator::isLunas('100.00', '120.00'));
        $this->assertFalse(PaymentCalculator::isLunas('100.00', '99.99'));
        // Total nol tidak pernah dianggap lunas.
        $this->assertFalse(PaymentCalculator::isLunas('0.00', '0.00'));
    }

    public function testStatusTransitions(): void
    {
        $this->assertSame(PaymentCalculator::STATUS_UNPAID, PaymentCalculator::status('100.00', '0.00'));
        $this->assertSame(PaymentCalculator::STATUS_PARTIAL, PaymentCalculator::status('100.00', '50.00'));
        $this->assertSame(PaymentCalculator::STATUS_PAID, PaymentCalculator::status('100.00', '100.00'));
    }

    public function testPaidPercentNormalAndCappedAndZeroGrand(): void
    {
        $this->assertSame(50.0, PaymentCalculator::paidPercent('100.00', '50.00'));
        // Lebih dari total dipotong ke 100 agar bar progres tidak meluap.
        $this->assertSame(100.0, PaymentCalculator::paidPercent('100.00', '150.00'));
        $this->assertSame(0.0, PaymentCalculator::paidPercent('0.00', '0.00'));
    }
}
