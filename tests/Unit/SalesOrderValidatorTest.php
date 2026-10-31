<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validators\SalesOrderValidator;
use PHPUnit\Framework\TestCase;

/**
 * Validasi input pesanan — bentuk & rentang dasar (§8.D). Batas uang
 * terhadap total ditegakkan OrderTotals (lihat OrderTotalsTest), jadi di
 * sini cukup memastikan struktur item & tanggal ditolak saat cacat.
 */
final class SalesOrderValidatorTest extends TestCase
{
    private SalesOrderValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SalesOrderValidator();
    }

    /** @return array<string, mixed> */
    private function valid(): array
    {
        return [
            'customer_id' => 1,
            'order_date' => '2026-10-01',
            'due_date' => '2026-10-20',
            'discount_amount' => 0,
            'additional_cost' => 0,
            'dp_amount' => 500000,
            'items' => [
                ['variant_id' => 5, 'quantity' => 10],
                ['variant_id' => 6, 'quantity' => 4],
            ],
        ];
    }

    public function testValidInputHasNoErrors(): void
    {
        self::assertSame([], $this->validator->validate($this->valid()));
    }

    public function testMissingCustomerRejected(): void
    {
        $data = $this->valid();
        $data['customer_id'] = 0;

        self::assertArrayHasKey('customer_id', $this->validator->validate($data));
    }

    public function testInvalidDateFormatRejected(): void
    {
        $data = $this->valid();
        $data['order_date'] = '01/10/2026';

        self::assertArrayHasKey('order_date', $this->validator->validate($data));
    }

    public function testDueDateBeforeOrderDateRejected(): void
    {
        $data = $this->valid();
        $data['due_date'] = '2026-09-30';

        $errors = $this->validator->validate($data);
        self::assertArrayHasKey('due_date', $errors);
    }

    public function testEmptyItemsRejected(): void
    {
        $data = $this->valid();
        $data['items'] = [];

        $errors = $this->validator->validate($data);
        self::assertArrayHasKey('items', $errors);
    }

    public function testDuplicateVariantRejected(): void
    {
        $data = $this->valid();
        $data['items'] = [
            ['variant_id' => 5, 'quantity' => 10],
            ['variant_id' => 5, 'quantity' => 4],
        ];

        $errors = $this->validator->validate($data);
        self::assertNotEmptyItemError($errors);
    }

    public function testNonPositiveQuantityRejected(): void
    {
        $data = $this->valid();
        $data['items'] = [['variant_id' => 5, 'quantity' => 0]];

        $errors = $this->validator->validate($data);
        self::assertArrayHasKey('items', $errors);
    }

    public function testNegativeMoneyRejected(): void
    {
        $data = $this->valid();
        $data['dp_amount'] = -1;

        $errors = $this->validator->validate($data);
        self::assertArrayHasKey('dp_amount', $errors);
    }

    /**
     * Penolong kecil agar assertion error item tetap informatif.
     *
     * @param array<string, list<string>> $errors
     */
    private function assertNotEmptyItemError(array $errors): void
    {
        self::assertArrayHasKey('items', $errors);
        self::assertNotEmpty($errors['items']);
    }
}
