<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\StockLedger;
use App\Support\StockLedgerException;
use PHPUnit\Framework\TestCase;

/**
 * Ledger stok murni — aturan saldo & anti-negatif (§13 DOC: test pemakaian stok).
 * Tanpa DB. Membuktikan arah in/out/adjustment, penolakan stok kurang, dan
 * peringatan minimum berjalan sesuai spesifikasi §8.F/§9.
 */
final class StockLedgerTest extends TestCase
{
    public function testInIncreasesBalance(): void
    {
        $ledger = StockLedger::apply(StockLedger::IN, '10.000', '5.500');

        self::assertSame('10.000', $ledger->balanceBefore);
        self::assertSame('15.500', $ledger->balanceAfter);
        self::assertSame('5.500', $ledger->signedDelta);
    }

    public function testOutDecreasesBalance(): void
    {
        $ledger = StockLedger::apply(StockLedger::OUT, '10.000', '4.000');

        self::assertSame('6.000', $ledger->balanceAfter);
        self::assertSame('-4.000', $ledger->signedDelta);
    }

    public function testOutRejectsInsufficientStock(): void
    {
        $this->expectException(StockLedgerException::class);
        $this->expectExceptionMessage('Stok tidak mencukupi — saldo tidak boleh negatif.');

        StockLedger::apply(StockLedger::OUT, '3.000', '5.000');
    }

    public function testOutRequiresPositiveQuantity(): void
    {
        $this->expectException(StockLedgerException::class);
        StockLedger::apply(StockLedger::OUT, '10.000', '0');
    }

    public function testInRejectsNegativeQuantity(): void
    {
        $this->expectException(StockLedgerException::class);
        StockLedger::apply(StockLedger::IN, '10.000', '-5');
    }

    public function testAdjustmentCanAdd(): void
    {
        $ledger = StockLedger::apply(StockLedger::ADJUSTMENT, '10.000', '2.500');

        self::assertSame('12.500', $ledger->balanceAfter);
        self::assertSame('2.500', $ledger->signedDelta);
    }

    public function testAdjustmentCanSubtractWithSign(): void
    {
        $ledger = StockLedger::apply(StockLedger::ADJUSTMENT, '10.000', '-3.000');

        self::assertSame('7.000', $ledger->balanceAfter);
        self::assertSame('-3.000', $ledger->signedDelta);
    }

    public function testAdjustmentCannotGoBelowZero(): void
    {
        $this->expectException(StockLedgerException::class);
        StockLedger::apply(StockLedger::ADJUSTMENT, '2.000', '-5.000');
    }

    public function testAdjustmentRejectsZero(): void
    {
        $this->expectException(StockLedgerException::class);
        $this->expectExceptionMessage('Penyesuaian tidak boleh nol.');

        StockLedger::apply(StockLedger::ADJUSTMENT, '10.000', '0');
    }

    public function testUnknownDirectionIsRejected(): void
    {
        $this->expectException(StockLedgerException::class);
        $this->expectExceptionMessage('Arah pergerakan stok tidak dikenal.');

        StockLedger::apply('burn', '10.000', '1.000');
    }

    public function testMalformedQuantityIsRejected(): void
    {
        // Pemisah ribu / koma desimal / notasi ilmiah tidak boleh lolos ke bcmath.
        foreach (['1.000,5', '12,5', '1e3', '5.', 'abc'] as $bad) {
            try {
                StockLedger::apply(StockLedger::IN, '0', $bad);
                self::fail("Kuantitas cacat '{$bad}' seharusnya ditolak.");
            } catch (StockLedgerException $e) {
                self::assertSame('quantity', $e->field);
            }
        }
    }

    public function testQuantityKeepsMaximumThreeDecimals(): void
    {
        // 3 desimal diterima dan dinormalkan.
        $ledger = StockLedger::apply(StockLedger::IN, '0', '1.234');
        self::assertSame('1.234', $ledger->balanceAfter);
    }

    public function testExceptionCarriesFieldForFormMapping(): void
    {
        try {
            StockLedger::apply(StockLedger::OUT, '1.000', '9.000');
            self::fail('Seharusnya melempar StockLedgerException.');
        } catch (StockLedgerException $e) {
            self::assertSame('quantity', $e->field);
        }
    }

    public function testIsLowStockAtOrBelowThreshold(): void
    {
        self::assertTrue(StockLedger::isLowStock('40.000', '40.000')); // tepat di ambang → menipis
        self::assertTrue(StockLedger::isLowStock('10.000', '40.000')); // di bawah → menipis
        self::assertFalse(StockLedger::isLowStock('40.001', '40.000')); // sedikit di atas → aman
    }
}
