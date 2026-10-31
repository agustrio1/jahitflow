<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validators\StockMovementValidator;
use PHPUnit\Framework\TestCase;

/**
 * Validasi bentuk input pergerakan stok (§8.F). Murni — aturan aritmetika
 * (arah ↔ tanda, saldo) diputus StockLedger; di sini hanya cek penahanan input cacat.
 */
final class StockMovementValidatorTest extends TestCase
{
    private StockMovementValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new StockMovementValidator();
    }

    /** @param array<string, mixed> $overrides */
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'direction' => 'in',
            'quantity' => '10.000',
            'reason' => 'Penerimaan pembelian',
            'reference_type' => 'purchase',
            'reference_id' => '1',
        ], $overrides);
    }

    public function testValidMovementProducesNoErrors(): void
    {
        $this->assertSame([], $this->validator->validate($this->valid()));
    }

    public function testUnknownDirectionIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['direction' => 'burn']));
        $this->assertArrayHasKey('direction', $errors);
    }

    public function testEmptyQuantityIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['quantity' => '']));
        $this->assertArrayHasKey('quantity', $errors);
    }

    public function testNonNumericQuantityIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['quantity' => '12,5']));
        $this->assertArrayHasKey('quantity', $errors);
    }

    public function testZeroRejectedForInAndOut(): void
    {
        foreach (['in', 'out'] as $dir) {
            $errors = $this->validator->validate($this->valid(['direction' => $dir, 'quantity' => '0']));
            $this->assertArrayHasKey('quantity', $errors, "Arah {$dir} harus menolak nol.");
        }
    }

    public function testNegativeRejectedForInAndOut(): void
    {
        $errors = $this->validator->validate($this->valid(['direction' => 'out', 'quantity' => '-5']));
        $this->assertArrayHasKey('quantity', $errors);
    }

    public function testAdjustmentRequiresNonZeroSignedValue(): void
    {
        // Nol ditolak; ± diterima.
        $this->assertArrayHasKey('quantity', $this->validator->validate($this->valid(['direction' => 'adjustment', 'quantity' => '0'])));
        $this->assertSame([], $this->validator->validate($this->valid(['direction' => 'adjustment', 'quantity' => '-2.500'])));
    }

    public function testReasonIsRequired(): void
    {
        $errors = $this->validator->validate($this->valid(['reason' => '   ']));
        $this->assertArrayHasKey('reason', $errors);
    }

    public function testUnknownReferenceTypeIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['reference_type' => 'invoice']));
        $this->assertArrayHasKey('reference_type', $errors);
    }

    public function testEmptyReferenceTypeIsAllowed(): void
    {
        $this->assertSame([], $this->validator->validate($this->valid(['reference_type' => '', 'reference_id' => ''])));
    }

    public function testNonPositiveReferenceIdIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['reference_id' => '0']));
        $this->assertArrayHasKey('reference_id', $errors);
    }
}
