<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\RawMaterial;
use App\Validators\RawMaterialValidator;
use PHPUnit\Framework\TestCase;

/**
 * Validasi bentuk input master bahan baku (§8.F). Uniqueness kode ditangani
 * service lewat DB; di sini murni memeriksa isi bidang.
 */
final class RawMaterialValidatorTest extends TestCase
{
    private RawMaterialValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RawMaterialValidator();
    }

    /** @param array<string, mixed> $overrides */
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'code' => 'DRL-NVY-01',
            'name' => 'Kain Drill Navy',
            'type' => RawMaterial::TYPE_UTAMA,
            'unit' => 'meter',
            'min_stock' => '50',
            'notes' => '',
        ], $overrides);
    }

    public function testValidMaterialProducesNoErrors(): void
    {
        $this->assertSame([], $this->validator->validate($this->valid()));
    }

    public function testEmptyCodeIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['code' => '   ']));
        $this->assertArrayHasKey('code', $errors);
    }

    public function testCodeMustStartWithLetterOrDigit(): void
    {
        $errors = $this->validator->validate($this->valid(['code' => '-DRL01']));
        $this->assertArrayHasKey('code', $errors);
    }

    public function testSingleCharacterCodeIsRejected(): void
    {
        // Aturan: minimal 2 karakter (karakter awal + {1,29}).
        $errors = $this->validator->validate($this->valid(['code' => 'A']));
        $this->assertArrayHasKey('code', $errors);
    }

    public function testLowercaseCodeIsNormalizedAndAccepted(): void
    {
        // Validator mengubah ke huruf besar sebelum memeriksa, jadi input kecil sah.
        $this->assertSame([], $this->validator->validate($this->valid(['code' => 'drl-nvy-02'])));
    }

    public function testEmptyNameIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['name' => '']));
        $this->assertArrayHasKey('name', $errors);
    }

    public function testUnknownTypeIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['type' => 'cadangan']));
        $this->assertArrayHasKey('type', $errors);
    }

    public function testEmptyUnitIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['unit' => '']));
        $this->assertArrayHasKey('unit', $errors);
    }

    public function testNegativeMinStockIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['min_stock' => '-5']));
        $this->assertArrayHasKey('min_stock', $errors);
    }

    public function testEmptyMinStockIsAllowed(): void
    {
        $this->assertSame([], $this->validator->validate($this->valid(['min_stock' => ''])));
    }

    public function testTooLongNotesIsRejected(): void
    {
        $errors = $this->validator->validate($this->valid(['notes' => str_repeat('a', 2001)]));
        $this->assertArrayHasKey('notes', $errors);
    }
}
