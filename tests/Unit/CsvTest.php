<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Csv;
use PHPUnit\Framework\TestCase;

/**
 * Pembentuk CSV laporan (§13: ekspor teruji). Murni — tanpa DB. Menutup:
 * pemisah ';', penulisan nilai MENTAH (bukan tampilan), label kolom, dan
 * penanda bagian kosong.
 */
final class CsvTest extends TestCase
{
    /** @param list<array<string, mixed>> $sections */
    private function make(string $title, array $sections): string
    {
        return Csv::build($title, ['from' => '2026-01-01', 'to' => '2026-01-31'], $sections);
    }

    public function testHeaderAndPeriodRow(): void
    {
        $csv = $this->make('Penjualan', []);

        self::assertStringContainsString('Laporan;Penjualan', $csv);
        self::assertStringContainsString('Periode;2026-01-01;2026-01-31', $csv);
    }

    public function testWritesRawValueNotFormatted(): void
    {
        $sections = [[
            'caption' => 'Rekap',
            'columns' => [
                ['key' => 'nama', 'label' => 'Nama'],
                ['key' => 'nilai', 'label' => 'Nilai'],
            ],
            'rows' => [['nama' => 'PT ABC', 'nilai' => '1500000.00']],
        ]];

        $csv = $this->make('Laporan', $sections);

        // Nilai uang ditulis mentah, tanpa 'Rp' atau pemisah ribuan.
        self::assertStringContainsString('PT ABC;1500000.00', $csv);
        self::assertStringNotContainsString('Rp', $csv);
    }

    public function testEmptySectionMarksNoData(): void
    {
        $sections = [[
            'caption' => 'Rekap',
            'columns' => [['key' => 'nama', 'label' => 'Nama']],
            'rows' => [],
        ]];

        $csv = $this->make('Laporan', $sections);

        self::assertStringContainsString('(tidak ada data)', $csv);
    }

    public function testValueContainingDelimiterIsQuoted(): void
    {
        $sections = [[
            'caption' => 'Rekap',
            'columns' => [['key' => 'catatan', 'label' => 'Catatan']],
            'rows' => [['catatan' => 'a;b']],
        ]];

        $csv = $this->make('Laporan', $sections);

        // fputcsv membungkus nilai berisi pemisah dengan tanda kutip.
        self::assertStringContainsString('"a;b"', $csv);
    }

    public function testNullValueBecomesEmptyCell(): void
    {
        $sections = [[
            'caption' => 'Rekap',
            'columns' => [['key' => 'a', 'label' => 'A'], ['key' => 'b', 'label' => 'B']],
            'rows' => [['a' => 'isi']], // 'b' tidak ada → sel kosong
        ]];

        $csv = $this->make('Laporan', $sections);

        // Sel untuk kolom 'b' yang kosong ditulis sebagai field kosong (diikuti EOL).
        self::assertStringContainsString("isi;\n", $csv);
    }
}
