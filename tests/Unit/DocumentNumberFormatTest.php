<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SequenceRepository;
use PHPUnit\Framework\TestCase;

/**
 * Format nomor dokumen — pengganti MAX(id)+1. Fungsi statis murni,
 * aman diuji tanpa DB. Yang diuji: prefiks kapital, tapak bulan,
 * dan nomor urut bernol-depan (DOC §6: penomoran tak boleh loncat/duplikat).
 */
final class DocumentNumberFormatTest extends TestCase
{
    public function testFormatsPrefixMonthAndPaddedSequence(): void
    {
        $this->assertSame('SO-2026-10-0007', SequenceRepository::format('so', '2026-10', 7));
        $this->assertSame('PO-2026-01-0001', SequenceRepository::format('PO', '2026-01', 1));
    }

    public function testPrefixIsUppercased(): void
    {
        $this->assertSame('JB-2026-12-0042', SequenceRepository::format('jb', '2026-12', 42));
    }

    public function testSequenceNotTruncatedBeyondFourDigits(): void
    {
        // %04d hanya menambahkan nol, tidak memotong — 5 digit tetap utuh.
        $this->assertSame('SO-2026-10-12345', SequenceRepository::format('so', '2026-10', 12345));
    }

    public function testSingleZeroPaddedSequence(): void
    {
        $this->assertSame('SF-2026-03-0000', SequenceRepository::format('SF', '2026-03', 0));
    }
}
