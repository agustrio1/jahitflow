<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Delivery;
use App\Validators\DeliveryValidator;
use PHPUnit\Framework\TestCase;

/**
 * Validasi bentuk catatan pengiriman (§13 DOC: validasi teruji). Murni — tanpa DB.
 * Batas "melebihi jumlah siap kirim" bukan ranah validator (butuh agregat QC/pesanan/
 * terkirim) — itu dijaga DeliveryService dan tidak diuji di sini.
 */
final class DeliveryValidatorTest extends TestCase
{
    private DeliveryValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DeliveryValidator();
    }

    /** @param array<string, mixed> $overrides */
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'sales_order_id' => '1',
            'quantity' => '10',
            'delivery_date' => '2026-10-29',
            'method' => Delivery::METHOD_KURIR,
            'courier' => '',
            'tracking_number' => '',
            'recipient' => '',
            'handover_ref' => '',
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

    public function testNonIntegerQuantityRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['quantity' => 'abc']));
        $this->assertArrayHasKey('quantity', $errors);
    }

    public function testDecimalQuantityRejected(): void
    {
        // Jumlah pengiriman harus bulat (pcs) — '5.5' ditolak pola digit.
        $errors = $this->validator->validate($this->valid(['quantity' => '5.5']));
        $this->assertArrayHasKey('quantity', $errors);
    }

    public function testZeroQuantityRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['quantity' => '0']));
        $this->assertArrayHasKey('quantity', $errors);
    }

    public function testEmptyQuantityRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['quantity' => '']));
        $this->assertArrayHasKey('quantity', $errors);
    }

    public function testMalformedDateRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['delivery_date' => '29-10-2026']));
        $this->assertArrayHasKey('delivery_date', $errors);
    }

    public function testImpossibleDateRejected(): void
    {
        // Format benar tapi bulan 13 tidak ada → checkdate gagal.
        $errors = $this->validator->validate($this->valid(['delivery_date' => '2026-13-01']));
        $this->assertArrayHasKey('delivery_date', $errors);
    }

    public function testInvalidMethodRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['method' => 'helikopter']));
        $this->assertArrayHasKey('method', $errors);
    }

    public function testOverlongCourierRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['courier' => str_repeat('K', 121)]));
        $this->assertArrayHasKey('courier', $errors);
    }

    public function testOverlongTrackingRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['tracking_number' => str_repeat('T', 121)]));
        $this->assertArrayHasKey('tracking_number', $errors);
    }

    public function testOverlongNoteRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['note' => str_repeat('N', 256)]));
        $this->assertArrayHasKey('note', $errors);
    }
}
