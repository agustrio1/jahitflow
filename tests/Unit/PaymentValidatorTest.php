<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Payment;
use App\Validators\PaymentValidator;
use PHPUnit\Framework\TestCase;

/**
 * Validasi bentuk transaksi pembayaran (§13 DOC: validasi teruji). Murni —
 * tanpa DB. Batas "melebihi sisa tagihan" bukan ranah validator (butuh agregat),
 * itu diuji di PaymentCalculatorTest.
 */
final class PaymentValidatorTest extends TestCase
{
    private PaymentValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PaymentValidator();
    }

    /** @param array<string, mixed> $overrides */
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'sales_order_id' => '1',
            'amount' => '100000.00',
            'phase' => Payment::PHASE_CICILAN,
            'method' => Payment::METHOD_TRANSFER,
            'payment_date' => '2026-10-29',
            'reference' => '',
            'note' => '',
        ], $overrides);
    }

    public function testValidInputProducesNoErrors(): void
    {
        $this->assertSame([], $this->validator->validate($this->valid()));
    }

    public function testMissingOrderRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['sales_order_id' => '0']));
        $this->assertArrayHasKey('sales_order_id', $errors);
    }

    public function testNonNumericAmountRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['amount' => 'abc']));
        $this->assertArrayHasKey('amount', $errors);
    }

    public function testTooManyDecimalsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['amount' => '10.123']));
        $this->assertArrayHasKey('amount', $errors);
    }

    public function testZeroAmountRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['amount' => '0']));
        $this->assertArrayHasKey('amount', $errors);
    }

    public function testNegativeAmountRejectedByFormat(): void
    {
        // Tanda minus tidak lolos pola desimal → dianggap format salah.
        $errors = $this->validator->validate($this->valid(['amount' => '-5000']));
        $this->assertArrayHasKey('amount', $errors);
    }

    public function testInvalidPhaseRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['phase' => 'cicir']));
        $this->assertArrayHasKey('phase', $errors);
    }

    public function testInvalidMethodRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['method' => 'bitcoin']));
        $this->assertArrayHasKey('method', $errors);
    }

    public function testImpossibleDateRejected(): void
    {
        // Format benar tapi tanggal tidak ada (bulan 13) → checkdate gagal.
        $errors = $this->validator->validate($this->valid(['payment_date' => '2026-13-01']));
        $this->assertArrayHasKey('payment_date', $errors);
    }

    public function testMalformedDateRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['payment_date' => '29-10-2026']));
        $this->assertArrayHasKey('payment_date', $errors);
    }

    public function testOverlongReferenceRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['reference' => str_repeat('R', 101)]));
        $this->assertArrayHasKey('reference', $errors);
    }

    public function testOverlongNoteRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['note' => str_repeat('N', 256)]));
        $this->assertArrayHasKey('note', $errors);
    }
}
