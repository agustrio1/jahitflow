<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ReportPeriod;
use PHPUnit\Framework\TestCase;

/**
 * Normalisasi periode laporan (§13: aturan bisnis teruji). Murni — tanpa DB.
 */
final class ReportPeriodTest extends TestCase
{
    public function testDefaultsToLastThirtyDays(): void
    {
        $period = ReportPeriod::resolve(null, null);

        self::assertSame(date('Y-m-d'), $period->toDate());
        self::assertSame(date('Y-m-d', strtotime('-29 day')), $period->fromDate());
        self::assertSame(30, $period->days());
        self::assertTrue($period->isDefault());
    }

    public function testValidInputIsRespected(): void
    {
        $period = ReportPeriod::resolve('2026-01-01', '2026-01-31');

        self::assertSame('2026-01-01', $period->fromDate());
        self::assertSame('2026-01-31', $period->toDate());
        self::assertSame(31, $period->days());
        self::assertFalse($period->isDefault());
    }

    public function testInvalidValuesFallBackToDefaults(): void
    {
        // Format salah + bulan mustahil → keduanya dibuang, pakai default.
        $period = ReportPeriod::resolve('abc', '2026-13-01');

        self::assertTrue($period->isDefault());
        self::assertSame(30, $period->days());
    }

    public function testReversedRangeIsSwapped(): void
    {
        $period = ReportPeriod::resolve('2026-02-10', '2026-02-01');

        self::assertSame('2026-02-01', $period->fromDate());
        self::assertSame('2026-02-10', $period->toDate());
    }

    public function testSingleDayRange(): void
    {
        $period = ReportPeriod::resolve('2026-05-05', '2026-05-05');

        self::assertSame(1, $period->days());
    }

    public function testDateTimeBounds(): void
    {
        $period = ReportPeriod::resolve('2026-03-01', '2026-03-31');

        self::assertSame('2026-03-01 00:00:00', $period->fromDateTime());
        self::assertSame('2026-03-31 23:59:59', $period->toDateTime());
    }
}
