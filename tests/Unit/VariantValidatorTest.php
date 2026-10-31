<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validators\VariantValidator;
use PHPUnit\Framework\TestCase;

/**
 * Validasi varian produk — harga adalah sumber kebenaran pesanan,
 * jadi aturannya dijaga ketat dan teruji (§8.C DOC).
 */
final class VariantValidatorTest extends TestCase
{
    private VariantValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new VariantValidator();
    }

    /** @param array<string, mixed> $overrides */
    private function valid(array $overrides = []): array
    {
        return array_merge(['color' => 'Hitam', 'size' => 'L', 'unit_price' => '75000', 'sku' => 'POLO-HIT-L'], $overrides);
    }

    public function testValidVariantPasses(): void
    {
        $this->assertSame([], $this->validator->validate($this->valid()));
    }

    public function testMissingColorAndSizeRejected(): void
    {
        $errors = $this->validator->validate(['color' => '', 'size' => '', 'unit_price' => '1000']);
        $this->assertArrayHasKey('color', $errors);
        $this->assertArrayHasKey('size', $errors);
    }

    public function testNegativePriceRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['unit_price' => '-5']));
        $this->assertArrayHasKey('unit_price', $errors);
    }

    public function testNonNumericPriceRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['unit_price' => 'gratis']));
        $this->assertArrayHasKey('unit_price', $errors);
    }

    public function testEmptyPriceRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['unit_price' => '']));
        $this->assertArrayHasKey('unit_price', $errors);
    }

    public function testZeroPriceIsAllowed(): void
    {
        // Harga nol valid secara bisnis (mis. sampel internal); bukan error.
        $this->assertSame([], $this->validator->validate($this->valid(['unit_price' => '0', 'sku' => ''])));
    }

    public function testIllegalSkuCharactersRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['sku' => 'SKU palsu!']));
        $this->assertArrayHasKey('sku', $errors);
    }
}
