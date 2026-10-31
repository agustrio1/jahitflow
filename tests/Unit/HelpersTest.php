<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Test helper tampilan — fungsi yang dipakai di semua report/invoice.
 * Dijalankan dengan: php bin/console test  (atau vendor/bin/phpunit)
 */
final class HelpersTest extends TestCase
{
    public function testMoneyFormatsRupiahWithoutDecimals(): void
    {
        $this->assertSame('Rp0', money(0));
        $this->assertSame('Rp1.250.000', money(1250000));
        $this->assertSame('Rp999.999', money('999999'));
    }

    public function testMoneyRoundsToWholeRupiah(): void
    {
        // Nilai desimal dari DECIMAL(14,2) harga satuan dibulatkan ke rupiah.
        $this->assertSame('Rp333', money(333.33));
        $this->assertSame('Rp334', money(333.50));
    }

    public function testEscapeHandlesQuotesAndTags(): void
    {
        $this->assertSame('&lt;script&gt;', e('<script>'));
        $this->assertSame("&#039;kutip&#039;", e("'kutip'"));
        $this->assertSame('&quot;d&quot;', e('"d"'));
    }

    public function testTanggalFormatsIndonesianMonthAbbreviation(): void
    {
        $this->assertSame('17 Agu 2026', tanggal('2026-08-17'));
        $this->assertSame('28 Okt 2026, 14:30', tanggal('2026-10-28 14:30:00', denganWaktu: true));
        $this->assertSame('-', tanggal(null));
    }

    public function testTanggalRejectsGarbageGracefully(): void
    {
        // String tak terbaca dikembalikan apa adanya, bukan error/throw.
        $this->assertSame('bukan-tanggal', tanggal('bukan-tanggal'));
    }
}
